<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\PostgreSQLReplayBarrier;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class PostgreSQLReplicaFailureContractTest extends TestCase
{
    use EnvTestBehaviour;

    private Connection $primary;

    private Connection $replicaControl;

    private PrimaryReadReplicaConnection $router;

    private PostgreSQLReplayBarrier $barrier;

    private DatabaseEndpoint $primaryEndpoint;

    private DatabaseEndpoint $replicaEndpoint;

    protected function setUp(): void
    {
        $this->primary = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->replicaControl = ContractConnectionFactory::create('DATABASE_CONTRACT_CONTROL_URL');
        $replica = ContractConnectionFactory::create('DATABASE_CONTRACT_REPLICA_URL');
        try {
            $this->primaryEndpoint = DatabaseEndpoint::observe($this->primary);
            $this->replicaEndpoint = DatabaseEndpoint::observe($replica);
        } finally {
            $replica->close();
        }
        static::assertStringStartsWith('18.6 ', $this->primaryEndpoint->version);
        static::assertStringStartsWith('18.6 ', $this->replicaEndpoint->version);
        static::assertFalse($this->primaryEndpoint->standby);
        static::assertTrue($this->replicaEndpoint->standby);
        static::assertNotSame($this->primaryEndpoint->serverId, $this->replicaEndpoint->serverId);
        static::assertSame($this->replicaEndpoint->serverId, DatabaseEndpoint::observe($this->replicaControl)->serverId);

        $this->barrier = new PostgreSQLReplayBarrier($this->primary, $this->replicaControl);
        $this->primary->executeStatement('DROP TABLE IF EXISTS sw_replica_failure_contract');
        $this->primary->executeStatement('CREATE TABLE sw_replica_failure_contract (id VARCHAR(80) NOT NULL PRIMARY KEY, value INTEGER NOT NULL)');
        $this->primary->executeStatement('INSERT INTO sw_replica_failure_contract (id, value) VALUES (\'initial\', 1)');
        $this->barrier->wait();

        $primaryUrl = getenv('DATABASE_CONTRACT_URL');
        $replicaUrl = getenv('DATABASE_CONTRACT_REPLICA_URL');
        static::assertIsString($primaryUrl);
        static::assertIsString($replicaUrl);
        $this->setEnvVars([
            'DATABASE_URL' => $primaryUrl . '&keepReplica=1',
            'DATABASE_REPLICA_0_URL' => $replicaUrl,
            'DATABASE_REPLICA_1_URL' => null,
        ]);
        $router = DatabaseConnectionFactory::create();
        static::assertInstanceOf(PrimaryReadReplicaConnection::class, $router);
        $this->router = $router;
    }

    protected function tearDown(): void
    {
        $cleanupErrors = [];

        if (isset($this->replicaControl)) {
            try {
                $this->replicaControl->executeStatement('SELECT pg_wal_replay_resume()');
            } catch (\Throwable $exception) {
                $cleanupErrors[] = $exception;
            }
        }
        if (isset($this->router)) {
            try {
                $this->router->close();
            } catch (\Throwable $exception) {
                $cleanupErrors[] = $exception;
            }
        }
        if (isset($this->primary)) {
            try {
                $this->primary->executeStatement('DROP TABLE IF EXISTS sw_replica_failure_contract');
            } catch (\Throwable $exception) {
                $cleanupErrors[] = $exception;
            } finally {
                $this->primary->close();
            }
        }
        if (isset($this->replicaControl)) {
            $this->replicaControl->close();
        }

        if ($cleanupErrors !== []) {
            if ($this->status()->isSuccess()) {
                throw $cleanupErrors[0];
            }

            foreach ($cleanupErrors as $exception) {
                fwrite(\STDERR, '[replica-cleanup] ' . $exception::class . "\n");
            }
        }
    }

    public function testPausedReplayMakesReplicaStaleButExplicitPrimaryCurrent(): void
    {
        // R04: the control connection pauses replay independently of the application router.
        $this->pauseReplay();
        try {
            $this->primary->executeStatement('UPDATE sw_replica_failure_contract SET value = 2 WHERE id = \'initial\'');
            $lsn = $this->assertReplayBehindPrimary();

            static::assertSame(1, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            $this->assertRoute($this->replicaEndpoint);

            $this->router->ensureConnectedToPrimary();
            static::assertSame(2, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            $this->assertRoute($this->primaryEndpoint);

            $this->router->executeStatement('UPDATE sw_replica_failure_contract SET value = 3 WHERE id = \'initial\'');
            static::assertSame(3, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            $this->assertRoute($this->primaryEndpoint);
            $pid = getmypid();
            static::assertIsInt($pid);
            fwrite(\STDERR, \sprintf("[R04] pid=%d primary=%s standby=%s lag-barrier=%s\n", $pid, $this->primaryEndpoint->serverId, $this->replicaEndpoint->serverId, $lsn));
        } finally {
            $this->resumeReplay();
        }
        $this->barrier->wait();
    }

    public function testResetDoesNotGuaranteeFreshnessAcrossUnitsDuringLag(): void
    {
        // R05: a dependent unit explicitly selects primary after reset.
        $this->pauseReplay();
        try {
            $this->router->executeStatement('UPDATE sw_replica_failure_contract SET value = 2 WHERE id = \'initial\'');
            $this->assertReplayBehindPrimary();
            (new ReplicaConnectionResetter($this->router))->reset();

            static::assertSame(1, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            $this->assertRoute($this->replicaEndpoint);
            $this->router->ensureConnectedToPrimary();
            static::assertSame(2, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            $this->assertRoute($this->primaryEndpoint);
        } finally {
            $this->resumeReplay();
        }
        $this->barrier->wait();
    }

    public function testTerminatedReplicaBackendNeedsExplicitReconnectWithoutFallback(): void
    {
        // R07: DBAL reports a broken physical connection and does not silently fail over.
        $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\'');
        $replicaBackend = DatabaseEndpoint::observe($this->router);
        $this->assertRoute($this->replicaEndpoint);
        static::assertTrue(filter_var($this->replicaControl->fetchOne('SELECT pg_terminate_backend(?)', [$replicaBackend->backendId]), \FILTER_VALIDATE_BOOL));

        try {
            $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\'');
            static::fail('A terminated replica backend must surface an error');
        } catch (DriverException $exception) {
            fwrite(\STDERR, \sprintf("[R07] role=standby host=%s backend=%d sqlstate=%s\n", $replicaBackend->serverId, $replicaBackend->backendId, $exception->getSQLState() ?? 'none'));
        }

        $this->router->close();
        static::assertSame(1, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
        $this->assertRoute($this->replicaEndpoint);
    }

    public function testTerminatedPrimaryBackendNeedsExplicitReconnect(): void
    {
        // R07: committed work remains visible; reconnect restores a new primary session.
        $this->router->executeStatement('UPDATE sw_replica_failure_contract SET value = 2 WHERE id = \'initial\'');
        $primaryBackend = DatabaseEndpoint::observe($this->router);
        $this->assertRoute($this->primaryEndpoint);
        static::assertTrue(filter_var($this->primary->fetchOne('SELECT pg_terminate_backend(?)', [$primaryBackend->backendId]), \FILTER_VALIDATE_BOOL));

        try {
            $this->router->executeStatement('UPDATE sw_replica_failure_contract SET value = 3 WHERE id = \'initial\'');
            static::fail('A terminated primary backend must surface an error');
        } catch (DriverException $exception) {
            fwrite(\STDERR, \sprintf("[R07] role=primary host=%s backend=%d sqlstate=%s\n", $primaryBackend->serverId, $primaryBackend->backendId, $exception->getSQLState() ?? 'none'));
        }

        $this->router->close();
        static::assertSame(2, (int) $this->primary->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
        $this->router->executeStatement('UPDATE sw_replica_failure_contract SET value = 4 WHERE id = \'initial\'');
        static::assertSame(4, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
        $this->assertRoute($this->primaryEndpoint);
        static::assertSame('UTC', $this->router->fetchOne('SHOW TimeZone'));
    }

    public function testSerializationFailureIsRolledBackBeforeControlledRetry(): void
    {
        // R06: concurrent serializable writers conflict; retry starts a new transaction.
        $writer = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $contender = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        try {
            $writer->beginTransaction();
            $contender->beginTransaction();
            $writer->executeStatement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $contender->executeStatement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            static::assertSame(1, (int) $writer->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            static::assertSame(1, (int) $contender->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
            $writer->executeStatement('UPDATE sw_replica_failure_contract SET value = 2 WHERE id = \'initial\'');
            $writer->commit();

            try {
                $contender->executeStatement('UPDATE sw_replica_failure_contract SET value = 3 WHERE id = \'initial\'');
                static::fail('A stale serializable writer must fail');
            } catch (DriverException $exception) {
                static::assertSame('40001', $exception->getSQLState());
            } finally {
                $contender->rollBack();
            }

            $contender->beginTransaction();
            $contender->executeStatement('UPDATE sw_replica_failure_contract SET value = 3 WHERE id = \'initial\'');
            $contender->commit();
            static::assertSame(3, (int) $this->primary->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\''));
        } finally {
            if ($writer->getTransactionNestingLevel() > 0) {
                $writer->rollBack();
            }
            if ($contender->getTransactionNestingLevel() > 0) {
                $contender->rollBack();
            }
            $writer->close();
            $contender->close();
        }
    }

    public function testCommittedBusinessIdResolvesOutcomeAfterConnectionLoss(): void
    {
        // R08: a business key resolves the persisted result; no automatic write retry is assumed.
        $this->router->beginTransaction();
        $this->router->executeStatement('INSERT INTO sw_replica_failure_contract (id, value) VALUES (\'operation-1\', 7)');
        $this->router->commit();
        $backend = DatabaseEndpoint::observe($this->router);
        static::assertTrue(filter_var($this->primary->fetchOne('SELECT pg_terminate_backend(?)', [$backend->backendId]), \FILTER_VALIDATE_BOOL));

        try {
            $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'operation-1\'');
            static::fail('A dead connection must surface an error');
        } catch (DriverException $exception) {
            fwrite(\STDERR, \sprintf("[R08] operation=operation-1 primary=%s backend=%d sqlstate=%s\n", $backend->serverId, $backend->backendId, $exception->getSQLState() ?? 'none'));
        }

        static::assertSame(1, (int) $this->primary->fetchOne('SELECT COUNT(*) FROM sw_replica_failure_contract WHERE id = \'operation-1\''));
        $this->router->close();
        $this->router->ensureConnectedToPrimary();
        static::assertSame(7, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'operation-1\''));
    }

    public function testConnectionLossDuringWriteIsResolvedBeforeRetry(): void
    {
        // R08: terminate the actual primary backend while INSERT executes in a trigger.
        $this->primary->executeStatement(<<<'SQL'
            CREATE FUNCTION sw_replica_delay_insert() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM pg_sleep(2);
                RETURN NEW;
            END
            $$
            SQL);
        $this->primary->executeStatement('CREATE TRIGGER sw_replica_delay_insert BEFORE INSERT ON sw_replica_failure_contract FOR EACH ROW EXECUTE FUNCTION sw_replica_delay_insert()');
        $this->router->ensureConnectedToPrimary();
        $backend = DatabaseEndpoint::observe($this->router);

        $terminator = new Process([
            \PHP_BINARY,
            '-r',
            <<<'PHP'
                $url = parse_url(getenv('DATABASE_CONTRACT_URL'));
                $pdo = new PDO('pgsql:host=' . $url['host'] . ';port=' . ($url['port'] ?? 5432) . ';dbname=' . ltrim($url['path'], '/'), urldecode($url['user']), urldecode($url['pass']));
                usleep(300000);
                $statement = $pdo->prepare('SELECT pg_terminate_backend(?)');
                $statement->execute([(int) $argv[1]]);
                if (!$statement->fetchColumn()) { throw new RuntimeException('Backend was not terminated'); }
                PHP,
            (string) $backend->backendId,
        ]);
        $terminator->setTimeout(15);
        $terminator->start();

        try {
            $start = microtime(true);
            try {
                $this->router->executeStatement('INSERT INTO sw_replica_failure_contract (id, value) VALUES (\'operation-during-write\', 7)');
                static::fail('Terminating the backend during INSERT must surface an error');
            } catch (DriverException $exception) {
                static::assertLessThanOrEqual(15.0, microtime(true) - $start);
                fwrite(\STDERR, \sprintf("[R08-write] primary=%s backend=%d sqlstate=%s elapsed=%.3Fs\n", $backend->serverId, $backend->backendId, $exception->getSQLState() ?? 'none', microtime(true) - $start));
            }
            $terminator->wait();
            static::assertTrue($terminator->isSuccessful(), $terminator->getErrorOutput());
            static::assertSame(0, (int) $this->primary->fetchOne('SELECT COUNT(*) FROM sw_replica_failure_contract WHERE id = \'operation-during-write\''));
        } finally {
            if ($terminator->isRunning()) {
                $terminator->stop(1);
            }
            $this->primary->executeStatement('DROP TRIGGER IF EXISTS sw_replica_delay_insert ON sw_replica_failure_contract');
            $this->primary->executeStatement('DROP FUNCTION IF EXISTS sw_replica_delay_insert()');
        }

        $this->router->close();
        $this->router->executeStatement('INSERT INTO sw_replica_failure_contract (id, value) VALUES (\'operation-during-write\', 7)');
        static::assertSame(1, (int) $this->primary->fetchOne('SELECT COUNT(*) FROM sw_replica_failure_contract WHERE id = \'operation-during-write\''));
    }

    public function testSequenceAndLockReadsAreForcedToPrimary(): void
    {
        // R10: read-looking SQL can mutate sequence state or require a writable transaction.
        $this->primary->executeStatement('CREATE SEQUENCE sw_replica_contract_sequence');
        try {
            $this->router->ensureConnectedToPrimary();
            static::assertSame(1, (int) $this->router->fetchOne('SELECT nextval(\'sw_replica_contract_sequence\')'));
            $this->assertRoute($this->primaryEndpoint);

            (new ReplicaConnectionResetter($this->router))->reset();
            $this->assertRoute($this->replicaEndpoint);
            $this->router->beginTransaction();
            static::assertSame(1, (int) $this->router->fetchOne('SELECT value FROM sw_replica_failure_contract WHERE id = \'initial\' FOR UPDATE'));
            $this->assertRoute($this->primaryEndpoint);
            $this->router->commit();
        } finally {
            if ($this->router->getTransactionNestingLevel() > 0) {
                $this->router->rollBack();
            }
            $this->primary->executeStatement('DROP SEQUENCE IF EXISTS sw_replica_contract_sequence');
        }
    }

    private function pauseReplay(): void
    {
        $this->replicaControl->executeStatement('SELECT pg_wal_replay_pause()');
        $deadline = microtime(true) + 15;
        do {
            if ($this->replicaControl->fetchOne('SELECT pg_get_wal_replay_pause_state()') === 'paused') {
                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException('Standby did not enter paused replay state within 15 seconds');
    }

    private function resumeReplay(): void
    {
        $this->replicaControl->executeStatement('SELECT pg_wal_replay_resume()');
    }

    private function assertReplayBehindPrimary(): string
    {
        $lsn = $this->primary->fetchOne('SELECT pg_current_wal_lsn()::text');
        static::assertIsString($lsn);
        static::assertTrue(filter_var($this->replicaControl->fetchOne('SELECT pg_last_wal_replay_lsn() < ?::pg_lsn', [$lsn]), \FILTER_VALIDATE_BOOL));

        return $lsn;
    }

    private function assertRoute(DatabaseEndpoint $expected): void
    {
        $observed = DatabaseEndpoint::observe($this->router);
        static::assertSame($expected->serverId, $observed->serverId);
        static::assertNotSame($expected->backendId, $observed->backendId);
    }
}

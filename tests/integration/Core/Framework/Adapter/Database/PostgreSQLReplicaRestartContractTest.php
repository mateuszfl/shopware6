<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\PostgreSQLReplayBarrier;
use Symfony\Component\Process\Process;

/**
 * Runs on the Docker host because the test controls independent Compose services.
 *
 * @internal
 */
#[Package('framework')]
class PostgreSQLReplicaRestartContractTest extends TestCase
{
    use EnvTestBehaviour;

    public function testSeparateReplicaAndPrimaryRestartsReconnectInOneClientProcess(): void
    {
        // R07: restart each physical server while retaining the PHP process and DBAL router.
        $primary = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $control = ContractConnectionFactory::create('DATABASE_CONTRACT_CONTROL_URL');
        $primaryEndpoint = DatabaseEndpoint::observe($primary);
        $replicaEndpoint = DatabaseEndpoint::observe($control);
        static::assertStringStartsWith('18.6 ', $primaryEndpoint->version);
        static::assertStringStartsWith('18.6 ', $replicaEndpoint->version);
        static::assertFalse($primaryEndpoint->standby);
        static::assertTrue($replicaEndpoint->standby);
        static::assertNotSame($primaryEndpoint->serverId, $replicaEndpoint->serverId);

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

        try {
            $primary->executeStatement('DROP TABLE IF EXISTS sw_replica_restart_contract');
            $primary->executeStatement('CREATE TABLE sw_replica_restart_contract (id VARCHAR(80) PRIMARY KEY, value INTEGER NOT NULL)');
            $primary->executeStatement('INSERT INTO sw_replica_restart_contract (id, value) VALUES (\'initial\', 1)');
            (new PostgreSQLReplayBarrier($primary, $control))->wait();

            static::assertSame(1, (int) $router->fetchOne('SELECT value FROM sw_replica_restart_contract WHERE id = \'initial\''));
            $oldReplica = DatabaseEndpoint::observe($router);
            static::assertSame($replicaEndpoint->serverId, $oldReplica->serverId);
            $this->restart('pg-replica-0');
            $this->assertOldConnectionFailsWithin15Seconds($router, 'SELECT value FROM sw_replica_restart_contract WHERE id = \'initial\'');
            $router->close();
            static::assertSame(1, (int) $router->fetchOne('SELECT value FROM sw_replica_restart_contract WHERE id = \'initial\''));
            $newReplica = DatabaseEndpoint::observe($router);
            static::assertSame($replicaEndpoint->serverId, $newReplica->serverId);
            static::assertNotSame($oldReplica->backendId, $newReplica->backendId);

            $router->executeStatement('UPDATE sw_replica_restart_contract SET value = 2 WHERE id = \'initial\'');
            $oldPrimary = DatabaseEndpoint::observe($router);
            static::assertSame($primaryEndpoint->serverId, $oldPrimary->serverId);
            $this->restart('pg-primary');
            $this->assertOldConnectionFailsWithin15Seconds($router, 'SELECT value FROM sw_replica_restart_contract WHERE id = \'initial\'');
            $router->close();
            $router->executeStatement('UPDATE sw_replica_restart_contract SET value = 3 WHERE id = \'initial\'');
            static::assertSame(3, (int) $router->fetchOne('SELECT value FROM sw_replica_restart_contract WHERE id = \'initial\''));
            $newPrimary = DatabaseEndpoint::observe($router);
            static::assertSame($primaryEndpoint->serverId, $newPrimary->serverId);
            static::assertNotSame($oldPrimary->backendId, $newPrimary->backendId);
            static::assertSame('UTC', $router->fetchOne('SHOW TimeZone'));
            static::assertSame('"$user", public', $router->fetchOne('SHOW search_path'));
            static::assertSame('read committed', $router->fetchOne('SHOW transaction_isolation'));
            $pid = getmypid();
            static::assertIsInt($pid);
            fwrite(\STDERR, \sprintf("[R07-restart] pid=%d replica=%s backend=%d->%d primary=%s backend=%d->%d\n", $pid, $replicaEndpoint->serverId, $oldReplica->backendId, $newReplica->backendId, $primaryEndpoint->serverId, $oldPrimary->backendId, $newPrimary->backendId));
        } finally {
            $router->close();
            $primary->close();
            $control->close();
            // Leave the test cluster healthy even when an assertion or restart fails.
            $this->waitForBothServers();
            $cleanup = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
            try {
                $cleanup->executeStatement('DROP TABLE IF EXISTS sw_replica_restart_contract');
            } finally {
                $cleanup->close();
            }
        }
    }

    private function restart(string $service): void
    {
        $process = new Process(['docker', 'compose', '-f', 'compose.pg-replicated.yaml', 'restart', $service], \dirname(__DIR__, 6));
        $process->setTimeout(120);
        $process->mustRun();
        $this->waitForBothServers();
    }

    private function waitForBothServers(): void
    {
        $process = new Process(['docker', 'compose', '-f', 'compose.pg-replicated.yaml', 'up', '-d', '--wait', '--wait-timeout', '120', 'pg-primary', 'pg-replica-0'], \dirname(__DIR__, 6));
        $process->setTimeout(130);
        $process->mustRun();
    }

    private function assertOldConnectionFailsWithin15Seconds(PrimaryReadReplicaConnection $router, string $sql): void
    {
        $start = microtime(true);
        try {
            $router->fetchOne($sql);
            static::fail('A connection opened before restart must report a broken backend');
        } catch (DriverException $exception) {
            static::assertLessThanOrEqual(15.0, microtime(true) - $start);
            fwrite(\STDERR, \sprintf("[R07-restart] sqlstate=%s elapsed=%.3Fs\n", $exception->getSQLState() ?? 'none', microtime(true) - $start));
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\PostgreSQLReplayBarrier;

/**
 * @internal
 */
#[Package('framework')]
class PostgreSQLTwoReplicaContractTest extends TestCase
{
    use EnvTestBehaviour;

    public function testBothConfiguredPhysicalStandbysServeReads(): void
    {
        // R11: DBAL chooses one replica per connection, without a round-robin promise.
        $primary = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $replica0 = ContractConnectionFactory::create('DATABASE_CONTRACT_REPLICA_URL');
        $replica1 = ContractConnectionFactory::create('DATABASE_CONTRACT_REPLICA_1_URL');
        $control0 = ContractConnectionFactory::create('DATABASE_CONTRACT_CONTROL_URL');
        $control1 = ContractConnectionFactory::create('DATABASE_CONTRACT_CONTROL_1_URL');

        try {
            $primaryEndpoint = DatabaseEndpoint::observe($primary);
            $endpoint0 = DatabaseEndpoint::observe($replica0);
            $endpoint1 = DatabaseEndpoint::observe($replica1);
            static::assertStringStartsWith('18.6 ', $primaryEndpoint->version);
            static::assertStringStartsWith('18.6 ', $endpoint0->version);
            static::assertStringStartsWith('18.6 ', $endpoint1->version);
            static::assertFalse($primaryEndpoint->standby);
            static::assertTrue($endpoint0->standby);
            static::assertTrue($endpoint1->standby);
            static::assertCount(3, array_unique([$primaryEndpoint->serverId, $endpoint0->serverId, $endpoint1->serverId]));
            static::assertSame($endpoint0->serverId, DatabaseEndpoint::observe($control0)->serverId);
            static::assertSame($endpoint1->serverId, DatabaseEndpoint::observe($control1)->serverId);

            $primary->executeStatement('CREATE TABLE IF NOT EXISTS sw_two_replica_contract (id VARCHAR(80) PRIMARY KEY)');
            $primary->executeStatement('INSERT INTO sw_two_replica_contract (id) VALUES (\'both\') ON CONFLICT (id) DO NOTHING');
            $lsn0 = (new PostgreSQLReplayBarrier($primary, $control0))->wait();
            $lsn1 = (new PostgreSQLReplayBarrier($primary, $control1))->wait();
            static::assertSame($lsn0, $lsn1);

            $primaryUrl = getenv('DATABASE_CONTRACT_URL');
            $replicaUrl0 = getenv('DATABASE_CONTRACT_REPLICA_URL');
            $replicaUrl1 = getenv('DATABASE_CONTRACT_REPLICA_1_URL');
            static::assertIsString($primaryUrl);
            static::assertIsString($replicaUrl0);
            static::assertIsString($replicaUrl1);
            $this->setEnvVars([
                'DATABASE_URL' => $primaryUrl . '&keepReplica=1',
                'DATABASE_REPLICA_0_URL' => $replicaUrl0,
                'DATABASE_REPLICA_1_URL' => $replicaUrl1,
            ]);

            $seen = [];
            for ($attempt = 0; $attempt < 100 && \count($seen) < 2; ++$attempt) {
                $router = DatabaseConnectionFactory::create();
                static::assertInstanceOf(PrimaryReadReplicaConnection::class, $router);
                try {
                    static::assertSame('both', $router->fetchOne('SELECT id FROM sw_two_replica_contract'));
                    $endpoint = DatabaseEndpoint::observe($router);
                    static::assertContains($endpoint->serverId, [$endpoint0->serverId, $endpoint1->serverId]);
                    $seen[$endpoint->serverId] = true;
                } finally {
                    $router->close();
                }
            }
            static::assertCount(2, $seen, 'Both physical replicas must be selected by a fresh router');
            fwrite(\STDERR, \sprintf("[R11] primary=%s replica-0=%s replica-1=%s replay=%s attempts=%d\n", $primaryEndpoint->serverId, $endpoint0->serverId, $endpoint1->serverId, $lsn0, $attempt));
        } finally {
            $primary->executeStatement('DROP TABLE IF EXISTS sw_two_replica_contract');
            foreach ([$control1, $control0, $replica1, $replica0, $primary] as $connection) {
                $connection->close();
            }
        }
    }
}

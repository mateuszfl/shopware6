<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractFixture;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractGateway;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractScenario;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\PostgreSQLReplayBarrier;

/**
 * @internal
 */
#[Package('framework')]
class PostgreSQLReplicationFixtureTest extends TestCase
{
    private static bool $platformLogged = false;

    private Connection $primary;

    private Connection $replica;

    private Connection $control;

    private DatabaseContractFixture $fixture;

    private PostgreSQLReplayBarrier $barrier;

    protected function setUp(): void
    {
        $this->primary = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->replica = ContractConnectionFactory::create('DATABASE_CONTRACT_REPLICA_URL');
        $this->control = ContractConnectionFactory::create('DATABASE_CONTRACT_CONTROL_URL');

        static::assertInstanceOf(PostgreSQLPlatform::class, $this->primary->getDatabasePlatform());
        static::assertInstanceOf(PostgreSQLPlatform::class, $this->replica->getDatabasePlatform());
        static::assertInstanceOf(PostgreSQLPlatform::class, $this->control->getDatabasePlatform());

        $primaryEndpoint = DatabaseEndpoint::observe($this->primary);
        $replicaEndpoint = DatabaseEndpoint::observe($this->replica);
        $controlEndpoint = DatabaseEndpoint::observe($this->control);
        static::assertStringStartsWith('18.6 ', $primaryEndpoint->version);
        static::assertStringStartsWith('18.6 ', $replicaEndpoint->version);
        static::assertFalse($primaryEndpoint->standby);
        static::assertTrue($replicaEndpoint->standby);
        static::assertTrue($controlEndpoint->standby);
        static::assertNotSame($primaryEndpoint->serverId, $replicaEndpoint->serverId, 'Primary and replica DSNs must target different servers');
        static::assertSame($replicaEndpoint->serverId, $controlEndpoint->serverId, 'The control connection must observe the tested standby');
        static::assertNotSame($replicaEndpoint->backendId, $controlEndpoint->backendId, 'Control and application access need separate physical connections');

        if (!self::$platformLogged) {
            fwrite(\STDERR, \sprintf(
                "[database-contract-replication] platform=postgresql version=%s primary=%s replica=%s planned-tests=2\n",
                $primaryEndpoint->version,
                $primaryEndpoint->serverId,
                $replicaEndpoint->serverId,
            ));
            self::$platformLogged = true;
        }

        $this->fixture = new DatabaseContractFixture($this->primary);
        $this->barrier = new PostgreSQLReplayBarrier($this->primary, $this->control);
        $this->fixture->setUp();
        $this->barrier->wait();
    }

    protected function tearDown(): void
    {
        if (isset($this->fixture)) {
            $this->fixture->tearDown();
            $this->barrier->wait();
        }
        foreach (['control', 'replica', 'primary'] as $property) {
            if (isset($this->{$property})) {
                $this->{$property}->close();
            }
        }
    }

    public function testPrimaryFixtureAndDataBecomeVisibleAfterReplayBarrier(): void
    {
        $primaryGateway = new DatabaseContractGateway($this->primary);
        $primaryGateway->write(DatabaseContractScenario::data());
        $lsn = $this->barrier->wait();

        static::assertMatchesRegularExpression('/^[0-9A-F]+\/[0-9A-F]+$/', $lsn);
        DatabaseContractScenario::assertState(
            (new DatabaseContractGateway($this->replica))->read(DatabaseContractScenario::ENGLISH_LANGUAGE),
        );
    }

    public function testPrimaryTeardownIsReplayedWithoutWritingToStandby(): void
    {
        $this->fixture->tearDown();
        $this->barrier->wait();

        $tables = array_map(
            static fn ($table): string => $table->getUnqualifiedName()->getValue(),
            $this->replica->createSchemaManager()->introspectTableNames(),
        );
        static::assertNotContains('sw_contract_product', $tables);
        static::assertNotContains('sw_contract_product_translation', $tables);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Service;

use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 */
#[Package('framework')]
class SetupDatabaseAdapterTest extends TestCase
{
    public function testInitialize(): void
    {
        $connectionInfo = DatabaseConnectionInformation::fromEnv();

        $testDbName = 'test_schema';
        $connection = DatabaseConnectionFactory::createConnection($connectionInfo, true);
        $setupDatabaseAdapter = new SetupDatabaseAdapter();

        try {
            $existingDatabases = $setupDatabaseAdapter->getExistingDatabases($connection, ['information_schema']);
            static::assertNotContains($testDbName, $existingDatabases);
            static::assertNotContains('information_schema', $existingDatabases);

            $setupDatabaseAdapter->createDatabase($connection, $testDbName);

            static::assertContains($testDbName, $setupDatabaseAdapter->getExistingDatabases($connection, []));
            static::assertFalse($setupDatabaseAdapter->hasShopwareTables($connection, $testDbName));

            $setupDatabaseAdapter->initializeShopwareDb($connection, $testDbName);

            static::assertTrue($setupDatabaseAdapter->hasShopwareTables($connection, $testDbName));
            static::assertSame('00ff80', $connection->fetchOne('SELECT sw_uuid_hex(?)', ["\x00\xff\x80"], [ParameterType::BINARY]));
            static::assertFalse($setupDatabaseAdapter->initializeShopwareDb($connection, $testDbName));
            static::assertSame('00ff80', $connection->fetchOne('SELECT sw_uuid_hex(?)', ["\x00\xff\x80"], [ParameterType::BINARY]));
        } finally {
            $setupDatabaseAdapter->dropDatabase($connection, $testDbName);

            static::assertNotContains($testDbName, $setupDatabaseAdapter->getExistingDatabases($connection, []));
        }
    }
}

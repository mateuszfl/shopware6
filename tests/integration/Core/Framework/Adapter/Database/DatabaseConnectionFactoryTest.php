<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory as MaintenanceDatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseConnectionFactoryTest extends TestCase
{
    use EnvTestBehaviour;

    #[DataProvider('databaseProvider')]
    public function testConnectAndReconnectRestoresSession(string $environmentName, string $driver, string $timezoneQuery, string $timezone): void
    {
        $url = getenv($environmentName);
        if (!\is_string($url) || $url === '') {
            static::markTestSkipped($environmentName . ' is not configured');
        }

        $this->setEnvVars([
            'DATABASE_URL' => $url,
            'DATABASE_REPLICA_0_URL' => '',
            'PGTZ' => $driver === 'pdo_pgsql' ? 'Europe/Warsaw' : null,
        ]);

        $connection = DatabaseConnectionFactory::create();
        static::assertSame($driver, $connection->getParams()['driver'] ?? null);
        static::assertSame($timezone, (string) $connection->fetchOne($timezoneQuery));

        $connection->close();
        static::assertFalse($connection->isConnected());
        static::assertSame($timezone, (string) $connection->fetchOne($timezoneQuery));
    }

    public function testReplicaRoutingAndTransactionResetOnPostgreSQL(): void
    {
        $url = getenv('DATABASE_CONNECTION_TEST_POSTGRESQL_URL');
        if (!\is_string($url) || $url === '') {
            static::markTestSkipped('DATABASE_CONNECTION_TEST_POSTGRESQL_URL is not configured');
        }

        // This checks DBAL routing and lifecycle against two physical connections.
        // A real standby and lag are covered by task 006a.
        $this->setEnvVars([
            'DATABASE_URL' => $url,
            'DATABASE_REPLICA_0_URL' => $url,
        ]);

        $connection = DatabaseConnectionFactory::create();
        static::assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        $connection->fetchOne('SELECT 1');
        static::assertFalse($connection->isConnectedToPrimary());

        $connection->beginTransaction();
        static::assertTrue($connection->isConnectedToPrimary());
        $connection->beginTransaction();
        (new ReplicaConnectionResetter($connection))->reset();
        static::assertTrue($connection->isConnectedToPrimary());
        $connection->rollBack();
        $connection->rollBack();

        (new ReplicaConnectionResetter($connection))->reset();
        static::assertFalse($connection->isConnectedToPrimary());
    }

    #[DataProvider('databaseUrls')]
    public function testPluginBootstrapQueryOnBothDrivers(string $environmentName): void
    {
        $url = getenv($environmentName);
        if (!\is_string($url) || $url === '') {
            static::markTestSkipped($environmentName . ' is not configured');
        }

        $this->setEnvVars(['DATABASE_URL' => $url, 'DATABASE_REPLICA_0_URL' => '']);
        $connection = DatabaseConnectionFactory::create();
        $connection->executeStatement('CREATE TEMPORARY TABLE plugin (name varchar(255), base_class varchar(255), active boolean, installed_at timestamp NULL, path varchar(255), version varchar(255), autoload text, managed_by_composer boolean, composer_name varchar(255))');

        $loader = new DbalKernelPluginLoader(new ClassLoader(), null, $connection);
        $loader->initializePlugins(\dirname(__DIR__, 6));

        static::assertSame([], $loader->getPluginInfos());
        $connection->close();
    }

    #[DataProvider('databaseUrls')]
    public function testPluginBootstrapMapsColumnsOnBothDrivers(string $environmentName): void
    {
        $url = getenv($environmentName);
        if (!\is_string($url) || $url === '') {
            static::markTestSkipped($environmentName . ' is not configured');
        }

        $this->setEnvVars(['DATABASE_URL' => $url, 'DATABASE_REPLICA_0_URL' => '']);
        $connection = DatabaseConnectionFactory::create();
        $connection->executeStatement('CREATE TEMPORARY TABLE plugin (name varchar(255), base_class varchar(255), active boolean, installed_at timestamp NULL, path varchar(255), version varchar(255), autoload text, managed_by_composer boolean, composer_name varchar(255))');
        $connection->insert('plugin', [
            'name' => 'Example',
            'base_class' => 'ExamplePlugin',
            'active' => true,
            'installed_at' => null,
            'path' => 'custom/plugins/Example',
            'version' => '1.0.0',
            'autoload' => '{}',
            'managed_by_composer' => true,
            'composer_name' => 'example/plugin',
        ], [
            'active' => ParameterType::BOOLEAN,
            'managed_by_composer' => ParameterType::BOOLEAN,
        ]);

        $loader = new DbalKernelPluginLoader(new ClassLoader(), null, $connection);
        $loader->initializePlugins(\dirname(__DIR__, 6));

        static::assertSame([[
            'baseClass' => 'ExamplePlugin',
            'name' => 'Example',
            'active' => false,
            'path' => 'custom/plugins/Example',
            'version' => '1.0.0',
            'autoload' => [],
            'managedByComposer' => true,
            'composerName' => 'example/plugin',
        ]], $loader->getPluginInfos());
        $connection->close();
    }

    #[DataProvider('databaseUrls')]
    public function testMaintenanceConnectionOnBothDrivers(string $environmentName): void
    {
        $url = getenv($environmentName);
        if (!\is_string($url) || $url === '') {
            static::markTestSkipped($environmentName . ' is not configured');
        }

        $this->setEnvVars(['DATABASE_URL' => $url]);
        $info = DatabaseConnectionInformation::fromEnv();
        $connection = MaintenanceDatabaseConnectionFactory::createConnection($info);

        static::assertSame($info->getDriver(), $connection->getParams()['driver'] ?? null);
        static::assertSame(1, (int) $connection->fetchOne('SELECT 1'));

        if ($info->getDriver() === 'pdo_pgsql') {
            static::assertSame('UTC', $connection->fetchOne('SHOW TimeZone'));
            $withoutDatabase = MaintenanceDatabaseConnectionFactory::createConnection($info, true);
            static::assertSame('postgres', $withoutDatabase->fetchOne('SELECT current_database()'));
        }
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function databaseProvider(): iterable
    {
        yield 'PostgreSQL' => ['DATABASE_CONNECTION_TEST_POSTGRESQL_URL', 'pdo_pgsql', 'SHOW TimeZone', 'UTC'];
        yield 'MySQL' => ['DATABASE_CONNECTION_TEST_MYSQL_URL', 'pdo_mysql', 'SELECT @@session.time_zone', '+00:00'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function databaseUrls(): iterable
    {
        yield 'PostgreSQL' => ['DATABASE_CONNECTION_TEST_POSTGRESQL_URL'];
        yield 'MySQL' => ['DATABASE_CONNECTION_TEST_MYSQL_URL'];
    }
}

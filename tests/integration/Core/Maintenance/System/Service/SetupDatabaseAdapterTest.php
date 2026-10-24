<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Installer\Controller\DatabaseConfigurationController;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Shopware\Tests\Integration\Core\Installer\_fixtures\InstallerDatabaseTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
class SetupDatabaseAdapterTest extends InstallerDatabaseTestCase
{
    public function testCreateInitializeIntrospectAndRepeatWithoutSwitchingAdminConnection(): void
    {
        $adminDatabase = $this->adminConnection->getDatabase();
        $this->adapter->createDatabase($this->adminConnection, $this->database);
        static::assertContains($this->database, $this->adapter->getExistingDatabases($this->adminConnection, []));
        static::assertNotContains($this->database, $this->adapter->getExistingDatabases($this->adminConnection, [$this->database]));
        static::assertFalse($this->adapter->hasShopwareTables($this->adminConnection, $this->database));
        static::assertSame(0, $this->adapter->getTableCount($this->adminConnection, $this->database));
        static::assertTrue($this->adapter->initializeShopwareDb($this->adminConnection, $this->database));
        static::assertTrue($this->adapter->hasShopwareTables($this->adminConnection, $this->database));
        static::assertSame(2, $this->adapter->getTableCount($this->adminConnection, $this->database));
        static::assertFalse($this->adapter->initializeShopwareDb($this->connection));
        static::assertSame($adminDatabase, $this->adminConnection->getDatabase());
        static::assertSame('00ff80', $this->connection->fetchOne('SELECT sw_uuid_hex(?)', ["\x00\xff\x80"], [ParameterType::BINARY]));
    }

    public function testQuotedDatabaseIdentifierAndRepeatedDrop(): void
    {
        $name = $this->database . '-quote"`';
        try {
            $this->adapter->createDatabase($this->adminConnection, $name);
            static::assertSame(0, $this->adapter->getTableCount($this->adminConnection, $name));
        } finally {
            $this->adapter->dropDatabase($this->adminConnection, $name);
            $this->adapter->dropDatabase($this->adminConnection, $name);
        }
        static::assertNotContains($name, $this->adapter->getExistingDatabases($this->adminConnection, []));
    }

    public function testWebInstallerCreatesMissingDatabaseUsingAuthenticatedMaintenanceConnection(): void
    {
        $information = DatabaseConnectionInformation::fromEnv();
        $name = $this->database . '_web';
        $information->setDatabaseName($name);
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/installer/database-import');
        $container = new ContainerBuilder();
        $container->set('router', $router);
        $controller = new DatabaseConfigurationController(static::createStub(TranslatorInterface::class), new BlueGreenDeploymentService(), $this->adapter, new DatabaseConnectionFactory());
        $controller->setContainer($container);
        $request = Request::create('/installer/database-configuration', 'POST', $information->getVars());
        $request->setSession(new Session(new MockArraySessionStorage()));
        try {
            $response = $controller->databaseConfiguration($request);
            static::assertSame(302, $response->getStatusCode());
            static::assertSame('/installer/database-import', $response->headers->get('Location'));
            static::assertContains($name, $this->adapter->getExistingDatabases($this->adminConnection, []));
            static::assertSame(0, $this->adapter->getTableCount($this->adminConnection, $name));
        } finally {
            // The controller's request connection may still be retained by a driver exception trace.
            if ($this->adminConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->adminConnection->executeStatement('DROP DATABASE IF EXISTS ' . $this->adminConnection->quoteSingleIdentifier($name) . ' WITH (FORCE)');
            } else {
                $this->adapter->dropDatabase($this->adminConnection, $name);
            }
        }
    }

    public function testPluginBootstrapStoresBinaryJsonAndPrecision(): void
    {
        $this->adapter->initializeShopwareDb($this->connection);
        $id = "\x00\xff\x80" . str_repeat("\x12", 13);
        $this->connection->insert('plugin', ['id' => $id, 'name' => 'Plugin', 'base_class' => 'Vendor\\Plugin', 'autoload' => '{"psr-4":{"Vendor\\\\":"src/"}}', 'version' => '1.0', 'created_at' => '2026-10-24 12:34:56.789'], ['id' => ParameterType::BINARY]);
        static::assertSame(bin2hex($id), $this->connection->fetchOne('SELECT sw_uuid_hex(id) FROM plugin'));
        $plugin = $this->connection->fetchAssociative('SELECT autoload, active, created_at FROM plugin');
        static::assertIsArray($plugin);
        static::assertSame(['psr-4' => ['Vendor\\' => 'src/']], json_decode($plugin['autoload'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(0, (int) $plugin['active']);
        static::assertStringContainsString('.789', $plugin['created_at']);
    }

    #[DataProvider('pluginUniqueFields')]
    public function testPluginNamesRetainCaseAndAccentInsensitiveUniqueness(string $field): void
    {
        $this->adapter->initializeShopwareDb($this->connection);
        $this->connection->insert('plugin', ['id' => Uuid::randomBytes(), 'name' => 'Café', 'base_class' => 'Vendor\\Café', 'autoload' => '{}', 'version' => '1', 'created_at' => '2026-10-24'], ['id' => ParameterType::BINARY]);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('plugin', ['id' => Uuid::randomBytes(), 'name' => $field === 'name' ? 'CAFE' : 'Other', 'base_class' => $field === 'base_class' ? 'Vendor\\CAFE' : 'Vendor\\Two', 'autoload' => '{}', 'version' => '1', 'created_at' => '2026-10-24'], ['id' => ParameterType::BINARY]);
    }

    public static function pluginUniqueFields(): \Generator
    {
        yield 'plugin name' => ['name'];
        yield 'plugin class' => ['base_class'];
    }

    public function testMigrationIdentityKeepsCaseInsensitiveMatching(): void
    {
        $this->adapter->initializeShopwareDb($this->connection);
        $this->connection->insert('migration', ['class' => 'Vendor\\Migration123Example', 'creation_timestamp' => 123]);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration WHERE class = ?', ['vendor\\migration123example']));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('migration', ['class' => 'vendor\\migration123example', 'creation_timestamp' => 123]);
    }

    public function testInstallerFormRetainsPostgreSqlPortAndTlsOptions(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 6) . '/src/Core/Installer/Resources/views/installer/database-configuration.html.twig');
        static::assertIsString($source);
        $twig = new Environment(new ArrayLoader([
            'form' => $source,
            '@Installer/installer/base.html.twig' => '{% block base_content %}{% endblock %}',
        ]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('url', static fn (string $route): string => '/' . $route));
        $information = DatabaseConnectionInformation::fromDsn('postgresql://user@localhost/shop?sslmode=verify-full');
        $form = $twig->render('form', ['connectionInfo' => $information, 'error' => null]);
        static::assertStringContainsString('value="pdo_pgsql" selected', $form);
        static::assertStringContainsString('value="5432" name="port"', $form);
        static::assertStringContainsString('name="dsnParameters[sslmode]" value="verify-full"', $form);
    }

    public function testCoreHistoryStopsExplicitlyBeforeRecordingMissingPostgreSQLVariants(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            static::markTestSkipped('PostgreSQL migration variants are intentionally not yet supplied.');
        }
        $this->adapter->initializeShopwareDb($this->connection);
        $loader = (new MigrationCollectionFactory(\dirname(__DIR__, 6)))->getMigrationCollectionLoader($this->connection);
        $collection = $loader->collectAllForVersion('6.7.0.0');
        try {
            $collection->sync();
            $collection->migrateInPlace();
            static::fail('Missing migration variant must prevent installation.');
        } catch (MigrationException $exception) {
            static::assertStringContainsString('PostgreSQL', $exception->getMessage());
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration WHERE "update" IS NOT NULL OR update_destructive IS NOT NULL'));
            static::assertTrue($this->connection->createSchemaManager()->tablesExist(['migration', 'plugin']));
            static::assertSame(2, $this->adapter->getTableCount($this->adminConnection, $this->database));
        }
    }
}

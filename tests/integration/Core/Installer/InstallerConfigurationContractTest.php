<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Installer;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Core\Installer\Configuration\ShopConfigurationService;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Installer\InstallerException;
use Shopware\Core\Maintenance\MaintenanceException;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Tests\Integration\Core\Installer\_fixtures\InstallerDatabaseTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Exercises installer SQL on a minimal synthetic schema, independently of historical migrations.
 *
 * @internal
 */
#[Package('framework')]
class InstallerConfigurationContractTest extends InstallerDatabaseTestCase
{
    private MockClock $clock;

    private CollectingEventDispatcher $events;

    private ShopConfigurator $configurator;

    /**
     * @var array<string, string>
     */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter->initializeShopwareDb($this->connection);
        $this->clock = new MockClock('2026-10-24 12:34:56.789 UTC');
        $this->events = new CollectingEventDispatcher();
        $this->configurator = new ShopConfigurator($this->connection, $this->events, $this->clock);
        $this->createConfigurationSchema();
        $this->seed();
    }

    public function testBasicConfigurationUpsertsWithoutDuplicateRows(): void
    {
        $this->configurator->updateBasicInformation('Café', 'first@example.com');
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE updated_at IS NOT NULL'));
        $this->configurator->updateBasicInformation('Updated', 'second@example.com');
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM system_config'));
        $actual = $this->connection->fetchAllKeyValue('SELECT configuration_key, configuration_value FROM system_config');
        static::assertSame(['_value' => 'Updated'], json_decode($actual['core.basicInformation.shopName'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(['_value' => 'second@example.com'], json_decode($actual['core.basicInformation.email'], true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testChangingExistingAndNewCurrencyPreservesRatesAndTranslations(): void
    {
        $this->configurator->setDefaultCurrency('GBP');
        static::assertSame('GBP', $this->connection->fetchOne('SELECT iso_code FROM currency WHERE id = ?', [Uuid::fromHexToBytes(Defaults::CURRENCY)], [ParameterType::BINARY]));
        static::assertEquals(['EUR' => 0.5, 'GBP' => 1.0], array_map('floatval', $this->connection->fetchAllKeyValue('SELECT iso_code, factor FROM currency')));
        $this->configurator->setDefaultCurrency('USD');
        $this->configurator->setDefaultCurrency('USD');
        static::assertSame('USD', $this->connection->fetchOne('SELECT iso_code FROM currency WHERE id = ?', [Uuid::fromHexToBytes(Defaults::CURRENCY)], [ParameterType::BINARY]));
        static::assertSame('USD', $this->connection->fetchOne('SELECT short_name FROM currency_translation WHERE currency_id = ?', [Uuid::fromHexToBytes(Defaults::CURRENCY)], [ParameterType::BINARY]));
        static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM currency'));
    }

    public function testGermanLanguageSwapAddsMissingCountryStateTranslation(): void
    {
        $this->configurator->setDefaultLanguage('de-DE');
        $this->configurator->setDefaultLanguage('de-DE');
        static::assertSame('de-DE', $this->connection->fetchOne('SELECT locale.code FROM language JOIN locale ON locale.id = language.translation_code_id WHERE language.id = ?', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::BINARY]));
        static::assertSame('Baden-Württemberg', $this->connection->fetchOne('SELECT name FROM country_state_translation WHERE language_id = ?', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::BINARY]));
    }

    public function testNewLanguageSwapsLocaleDataAndKeepsTranslations(): void
    {
        $this->configurator->setDefaultLanguage('es-ES');
        $this->configurator->setDefaultLanguage('es-ES');
        static::assertSame('es-ES', $this->connection->fetchOne('SELECT locale.code FROM language JOIN locale ON locale.id = language.translation_code_id WHERE language.id = ?', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::BINARY]));
        static::assertSame('Spanish', $this->connection->fetchOne('SELECT name FROM language WHERE id = ?', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::BINARY]));
        static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM language'));
    }

    public function testShopConfigurationCreatesStorefrontAndUpdatesHeadlessCurrencies(): void
    {
        $service = new ShopConfigurationService($this->events, $this->clock);
        $service->updateShop([
            'name' => 'Shop', 'locale' => 'en-GB', 'currency' => 'EUR', 'additionalCurrencies' => ['GBP'],
            'country' => 'DEU', 'email' => 'shop@example.com', 'host' => 'example.com', 'basePath' => '/store', 'schema' => 'https', 'blueGreenDeployment' => true,
        ], $this->connection);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel'));
        static::assertSame(['http://example.com/store', 'https://example.com/store'], $this->connection->fetchFirstColumn('SELECT url FROM sales_channel_domain ORDER BY url'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_currency WHERE sales_channel_id = ?', [$this->ids['headless']], [ParameterType::BINARY]));
        static::assertSame(bin2hex($this->ids['eur']), $this->connection->fetchOne('SELECT sw_uuid_hex(currency_id) FROM sales_channel WHERE id = ?', [$this->ids['headless']], [ParameterType::BINARY]));
    }

    public function testShopConfigurationWithoutAdditionalCurrenciesRemovesUnselectedCurrencies(): void
    {
        $service = new ShopConfigurationService($this->events, $this->clock);
        $service->updateShop([
            'name' => 'Shop', 'locale' => 'en-GB', 'currency' => 'EUR', 'additionalCurrencies' => null,
            'country' => 'DEU', 'email' => 'shop@example.com', 'host' => 'example.com', 'basePath' => '', 'schema' => 'https', 'blueGreenDeployment' => false,
        ], $this->connection);
        static::assertSame(['EUR'], $this->connection->fetchFirstColumn('SELECT iso_code FROM currency'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_currency WHERE sales_channel_id = ?', [$this->ids['headless']], [ParameterType::BINARY]));
    }

    public function testFailedShopConfigurationRollsBackConfigCurrencyAndChannels(): void
    {
        $before = $this->connection->fetchAllKeyValue('SELECT sw_uuid_hex(id), iso_code FROM currency');
        $service = new ShopConfigurationService($this->events, $this->clock);
        try {
            $service->updateShop([
                'name' => 'Rollback', 'locale' => 'en-GB', 'currency' => 'USD', 'additionalCurrencies' => null,
                'country' => 'UNKNOWN', 'email' => 'rollback@example.com', 'host' => 'example.com', 'basePath' => '', 'schema' => 'https', 'blueGreenDeployment' => false,
            ], $this->connection);
            static::fail('Unknown country must reject configuration.');
        } catch (InstallerException $exception) {
            static::assertSame(InstallerException::countryNotFound('UNKNOWN')->getMessage(), $exception->getMessage());
        }
        static::assertSame($before, $this->connection->fetchAllKeyValue('SELECT sw_uuid_hex(id), iso_code FROM currency'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM system_config'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_domain'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_currency'));
    }

    public function testAdminProvisioningUsesQuotedUserTableAndBinaryLocale(): void
    {
        $service = new AdminConfigurationService($this->clock);
        $service->createAdmin(['username' => 'admin', 'password' => 'shopware', 'email' => 'admin@example.com', 'admin' => false], $this->connection);
        $table = $this->connection->quoteSingleIdentifier('user');
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . $table);
        static::assertIsArray($row);
        static::assertTrue(password_verify('shopware', $row['password']));
        static::assertSame(0, (int) $row['admin']);
        static::assertSame(1, (int) $row['active']);
        static::assertSame($this->ids['en'], Uuid::fromHexToBytes(Uuid::fromBytesToHex($row['locale_id'])));
        $this->expectExceptionObject(MaintenanceException::userAlreadyExists('admin'));
        $service->createAdmin(['username' => 'admin', 'password' => 'shopware'], $this->connection);
    }

    public function testTriggerProbeCleansUpAndCanBeRepeated(): void
    {
        $service = new BlueGreenDeploymentService();
        $session = new Session(new MockArraySessionStorage());
        $service->setEnvironmentVariable($this->connection, $session);
        static::assertTrue($session->get(BlueGreenDeploymentService::ENV_NAME));
        $service->setEnvironmentVariable($this->connection, $session);
        static::assertTrue($session->get(BlueGreenDeploymentService::ENV_NAME));
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['sw_blue_green_probe']));
    }

    private function createConfigurationSchema(): void
    {
        // Only the columns exercised by installer configuration, never a replacement for historical migrations.
        $definitions = [
            'locale' => [['id'], ['code']],
            'language' => [['id', 'locale_id', 'translation_code_id'], ['name']],
            'locale_translation' => [['locale_id', 'language_id'], ['name', 'territory']],
            'currency' => [['id'], ['iso_code', 'symbol', 'item_rounding', 'total_rounding']],
            'currency_translation' => [['currency_id', 'language_id'], ['short_name', 'name']],
            'system_config' => [['id', 'sales_channel_id'], ['configuration_key', 'configuration_value']],
            'user' => [['id', 'locale_id'], ['first_name', 'last_name', 'email', 'username', 'password']],
            'category' => [['id', 'parent_id'], []],
            'country' => [['id'], ['iso3']],
            'country_state' => [['id'], ['short_code']],
            'country_state_translation' => [['country_state_id', 'language_id'], ['name']],
            'payment_method' => [['id'], []],
            'shipping_method' => [['id'], []],
            'snippet_set' => [['id'], ['iso']],
            'sales_channel' => [['id', 'type_id', 'navigation_category_id', 'navigation_category_version_id', 'language_id', 'currency_id', 'payment_method_id', 'shipping_method_id', 'country_id', 'customer_group_id'], ['access_key']],
            'sales_channel_translation' => [['sales_channel_id', 'language_id'], ['name']],
            'sales_channel_domain' => [['id', 'sales_channel_id', 'language_id', 'currency_id', 'snippet_set_id'], ['url', 'custom_fields']],
            'sales_channel_language' => [['sales_channel_id', 'language_id'], []],
            'sales_channel_currency' => [['sales_channel_id', 'currency_id'], []],
            'sales_channel_payment_method' => [['sales_channel_id', 'payment_method_id'], []],
            'sales_channel_shipping_method' => [['sales_channel_id', 'shipping_method_id'], []],
            'sales_channel_country' => [['sales_channel_id', 'country_id'], []],
        ];
        $schema = new DatabaseSchema($this->connection);
        foreach ($definitions as $name => [$binary, $strings]) {
            $table = new Table($name);
            foreach ($binary as $column) {
                $table->addColumn($column, Types::BINARY, ['length' => 16, 'fixed' => true, 'notnull' => $column === 'id']);
            }
            foreach ($strings as $column) {
                $json = \in_array($column, ['configuration_value', 'item_rounding', 'total_rounding', 'custom_fields'], true);
                $table->addColumn($column, $json ? Types::JSON : Types::STRING, ['length' => 255, 'notnull' => false]);
            }
            $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
            $table->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
            if (\in_array($name, ['user', 'category', 'payment_method', 'shipping_method'], true)) {
                $table->addColumn('active', Types::BOOLEAN, ['default' => 1]);
            }
            if ($name === 'user') {
                $table->addColumn('admin', Types::BOOLEAN, ['default' => 1]);
            }
            if (\in_array($name, ['currency', 'payment_method', 'shipping_method'], true)) {
                $table->addColumn('position', Types::INTEGER, ['default' => 1]);
            }
            if ($name === 'currency') {
                $table->addColumn('factor', Types::DECIMAL, ['precision' => 20, 'scale' => 12, 'default' => '1']);
            }
            if ($name === 'locale') {
                $table->addUniqueIndex(['code'], 'unique_locale_code');
            }
            $primaryColumns = \in_array('id', $binary, true) ? ['id'] : array_values($binary);
            $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primaryColumns)->create());
            foreach ($binary as $column) {
                $target = match ($column) {
                    'language_id' => 'language', 'locale_id', 'translation_code_id' => 'locale',
                    'currency_id' => 'currency', 'country_state_id' => 'country_state', 'sales_channel_id' => $name === 'system_config' ? null : 'sales_channel', default => null,
                };
                if ($target !== null) {
                    $table->addForeignKeyConstraint($target, [$column], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
                }
            }
            foreach ($schema->createTableQueries($schema->prepareTable($table)) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }
    }

    private function seed(): void
    {
        foreach (['en', 'de', 'es', 'german', 'gbp', 'category', 'country', 'state', 'payment', 'shipping', 'snippet', 'headless', 'group'] as $name) {
            $this->ids[$name] = Uuid::randomBytes();
        }
        $this->ids['english'] = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->ids['eur'] = Uuid::fromHexToBytes(Defaults::CURRENCY);
        foreach (['en' => 'en-GB', 'de' => 'de-DE', 'es' => 'es-ES'] as $name => $code) {
            $this->insert('locale', ['id' => $this->ids[$name], 'code' => $code]);
        }
        foreach (['english' => ['en', 'English'], 'german' => ['de', 'Deutsch']] as $language => [$locale, $name]) {
            $this->insert('language', ['id' => $this->ids[$language], 'locale_id' => $this->ids[$locale], 'translation_code_id' => $this->ids[$locale], 'name' => $name]);
        }
        foreach (['en' => 'English', 'de' => 'German', 'es' => 'Spanish'] as $locale => $name) {
            $this->insert('locale_translation', ['locale_id' => $this->ids[$locale], 'language_id' => $this->ids['english'], 'name' => $name, 'territory' => $name]);
        }
        $this->insert('currency', ['id' => $this->ids['eur'], 'iso_code' => 'EUR', 'factor' => '1']);
        $this->insert('currency', ['id' => $this->ids['gbp'], 'iso_code' => 'GBP', 'factor' => '2']);
        $this->insert('currency_translation', ['currency_id' => $this->ids['eur'], 'language_id' => $this->ids['english'], 'short_name' => 'EUR', 'name' => 'Euro']);
        $this->insert('country_state', ['id' => $this->ids['state'], 'short_code' => 'DE-BW']);
        $this->insert('country_state_translation', ['country_state_id' => $this->ids['state'], 'language_id' => $this->ids['english'], 'name' => 'Baden-Wuerttemberg']);
        foreach (['category', 'payment_method' => 'payment', 'shipping_method' => 'shipping'] as $table => $id) {
            $this->insert(\is_int($table) ? $id : $table, ['id' => $this->ids[$id]]);
        }
        $this->insert('country', ['id' => $this->ids['country'], 'iso3' => 'DEU']);
        $this->insert('snippet_set', ['id' => $this->ids['snippet'], 'iso' => 'en-GB']);
        $this->insert('sales_channel', ['id' => $this->ids['headless'], 'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API), 'currency_id' => $this->ids['eur'], 'customer_group_id' => $this->ids['group']]);
        $this->insert('sales_channel_currency', ['sales_channel_id' => $this->ids['headless'], 'currency_id' => $this->ids['gbp']]);
    }

    /**
     * @param array<string, string> $values
     */
    private function insert(string $table, array $values): void
    {
        $types = [];
        foreach ($values as $column => $value) {
            if ($column === 'id' || str_ends_with($column, '_id')) {
                $types[$column] = ParameterType::BINARY;
            }
        }
        $this->connection->insert($this->connection->quoteSingleIdentifier($table), $values, $types);
    }
}

<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

const DEMO_DATABASE = 'shopware_024_dal_demo';

$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
$database = parse_url($databaseUrl, PHP_URL_PATH);
if ($database !== '/' . DEMO_DATABASE) {
    throw new RuntimeException('Set DATABASE_URL to the isolated ' . DEMO_DATABASE . ' database.');
}

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$kernel = KernelFactory::create(
    'dev',
    false,
    $loader,
    new StaticKernelPluginLoader($loader),
);
if (!$kernel instanceof Kernel) {
    throw new RuntimeException('Could not create the Shopware Kernel.');
}

$kernel->boot();
try {
    $container = $kernel->getContainer();
    $connection = $container->get(Connection::class);
    $existingTables = $connection->createSchemaManager()->introspectTableNames();
    if ($existingTables !== []) {
        throw new RuntimeException('Refusing to alter a non-empty demo database. Use a new isolated database.');
    }

    $registry = $container->get(DefinitionInstanceRegistry::class);
    $schemaBuilder = new SchemaBuilder();
    $schema = new DatabaseSchema($connection);

    foreach ($schema->prerequisites() as $query) {
        $connection->executeStatement($query);
    }

    $availableDefinitions = [];
    foreach ($registry->getDefinitions() as $definition) {
        $availableDefinitions[$definition->getEntityName()] = $definition;
    }

    $entityNames = [
        'app', 'script', 'category', 'category_translation', 'country', 'country_translation', 'currency', 'currency_translation',
        'customer_group', 'customer_group_translation', 'delivery_time', 'delivery_time_translation', 'integration',
        'language', 'locale', 'locale_translation', 'media', 'payment_method',
        'acl_role', 'acl_user_role', 'integration_role', 'app_payment_method', 'app_shipping_method', 'app_translation', 'payment_method_translation', 'plugin', 'plugin_translation', 'product', 'product_manufacturer', 'product_manufacturer_translation', 'product_media', 'product_price',
        'product_translation', 'product_visibility', 'sales_channel', 'sales_channel_currency', 'sales_channel_language',
        'sales_channel_country', 'sales_channel_payment_method', 'sales_channel_shipping_method', 'sales_channel_domain', 'sales_channel_type',
        'sales_channel_type_translation', 'sales_channel_translation', 'shipping_method',
        'shipping_method_translation', 'snippet_set', 'system_config', 'tax', 'unit', 'unit_translation', 'user', 'user_access_key',
    ];
    $definitions = [];
    $missingDefinitions = [];
    foreach ($entityNames as $entityName) {
        if (!isset($availableDefinitions[$entityName])) {
            $missingDefinitions[] = $entityName;

            continue;
        }

        $definitions[$entityName] = $availableDefinitions[$entityName];
    }

    $schemaExceptions = [];
    $createdTables = [];
    foreach ($definitions as $definition) {
        if (!$definition instanceof EntityDefinition) {
            throw new RuntimeException('Missing DAL definition for demo table.');
        }

        try {
        $table = $schemaBuilder->buildSchemaOfDefinition($definition);
        foreach ($table->getColumns() as $column) {
            if ($column->getType() instanceof JsonType && is_array($column->getDefault())) {
                $column->setDefault(json_encode($column->getDefault(), JSON_THROW_ON_ERROR));
            } elseif ($column->getDefault() instanceof DateTimeInterface) {
                $column->setDefault($column->getDefault()->format('Y-m-d H:i:s.v'));
            }
        }
        $table = $schema->prepareTable($table);
        foreach ($table->getForeignKeys() as $foreignKey) {
            $constraintName = $foreignKey->getObjectName();
            if ($constraintName === null) {
                continue;
            }

            $table = $table->edit()->dropForeignKeyConstraint($constraintName)->create();
        }

        foreach ($schema->createTableQueries($table) as $query) {
            $connection->executeStatement($query);
        }
            $createdTables[] = $definition->getEntityName();
        } catch (Throwable $exception) {
            $schemaExceptions[$definition->getEntityName()] = $exception->getMessage();
        }
    }

    (new DatabaseFunctionInstaller())->install($connection);
    // Product manufacturer cache invalidation reads this denormalized table before the first full indexer run.
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE product_category_tree (
            product_id BYTEA NOT NULL,
            product_version_id BYTEA NOT NULL,
            category_id BYTEA NOT NULL,
            PRIMARY KEY (product_id, product_version_id, category_id)
        )
        SQL);
    // The password-grant token store is migration-created rather than DAL-defined.
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE oauth_user (
            id BYTEA NOT NULL UNIQUE,
            user_id BYTEA NOT NULL UNIQUE,
            user_sub VARCHAR(255) NOT NULL UNIQUE,
            token JSONB,
            expiry TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(3),
            PRIMARY KEY (id)
        )
        SQL);
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE refresh_token (
            id BYTEA NOT NULL,
            user_id BYTEA NOT NULL,
            token_id VARCHAR(80) NOT NULL UNIQUE,
            issued_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
            family_id BYTEA,
            revoked_at TIMESTAMP(3) WITHOUT TIME ZONE,
            PRIMARY KEY (id)
        )
        SQL);
    // Product cache invalidation reads this migration-created mapping even for an empty stream.
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE product_stream_mapping (
            product_id BYTEA NOT NULL,
            product_version_id BYTEA NOT NULL,
            product_stream_id BYTEA NOT NULL,
            PRIMARY KEY (product_id, product_version_id, product_stream_id)
        )
        SQL);

    $context = Context::createDefaultContext();
    $create = static function (string $entityName, array $payload) use ($container, $context): void {
        $container->get($entityName . '.repository')->create([$payload], $context);
    };
    $insert = static function (string $table, array $data, array $binaryColumns = []) use ($connection): void {
        $types = array_fill_keys($binaryColumns, \Doctrine\DBAL\ParameterType::BINARY);
        $connection->insert($table, $data, $types);
    };
    $localeId = Uuid::randomHex();
    $languageId = Defaults::LANGUAGE_SYSTEM;
    $currencyId = Defaults::CURRENCY;
    $countryId = Uuid::randomHex();
    $groupId = Uuid::randomHex();
    $taxId = Uuid::randomHex();
    $deliveryTimeId = Uuid::randomHex();
    $manufacturerId = Uuid::randomHex();
    $unitId = Uuid::randomHex();
    $paymentMethodId = Uuid::randomHex();
    $shippingMethodId = Uuid::randomHex();
    $channelTypeId = Uuid::randomHex();
    $snippetSetId = Uuid::randomHex();
    $categoryId = Uuid::randomHex();
    $salesChannelId = Uuid::randomHex();
    $productId = Uuid::randomHex();
    $adminPassword = bin2hex(random_bytes(16));
    $now = (new DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    $binary = static fn (string $id): string => Uuid::fromHexToBytes($id);

    // The current read SQL compares boolean flags to integer 1; keep Shopware's smallint schema here.
    $insert('locale', ['id' => $binary($localeId), 'code' => 'en-GB', 'created_at' => $now], ['id']);
    $insert('language', [
        'id' => $binary($languageId), 'locale_id' => $binary($localeId), 'translation_code_id' => $binary($localeId),
        'name' => 'English', 'active' => 1, 'created_at' => $now,
    ], ['id', 'locale_id', 'translation_code_id']);
    $insert('locale_translation', [
        'locale_id' => $binary($localeId), 'language_id' => $binary($languageId), 'name' => 'English',
        'territory' => 'United Kingdom', 'created_at' => $now,
    ], ['locale_id', 'language_id']);

    $create('currency', [
        'id' => $currencyId, 'isoCode' => 'EUR', 'factor' => 1, 'symbol' => '€',
        'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        'translations' => [$languageId => ['name' => 'Euro', 'shortName' => 'EUR']],
    ]);
    $insert('country', ['id' => $binary($countryId), 'iso' => 'GB', 'iso3' => 'GBR', 'position' => 1, 'is_eu' => 0, 'created_at' => $now], ['id']);
    $insert('country_translation', ['country_id' => $binary($countryId), 'language_id' => $binary($languageId), 'name' => 'United Kingdom', 'created_at' => $now], ['country_id', 'language_id']);
    $insert('customer_group', ['id' => $binary($groupId), 'display_gross' => 1, 'created_at' => $now], ['id']);
    $insert('customer_group_translation', ['customer_group_id' => $binary($groupId), 'language_id' => $binary($languageId), 'name' => 'Demo customers', 'created_at' => $now], ['customer_group_id', 'language_id']);

    $create('tax', ['id' => $taxId, 'name' => 'Standard rate', 'taxRate' => 20, 'position' => 1]);
    $create('delivery_time', ['id' => $deliveryTimeId, 'min' => 1, 'max' => 3, 'unit' => 'day', 'translations' => [$languageId => ['name' => '1–3 days']]]);
    $create('product_manufacturer', ['id' => $manufacturerId, 'translations' => [$languageId => ['name' => 'Demo maker']]]);
    $create('unit', ['id' => $unitId, 'translations' => [$languageId => ['name' => 'Piece', 'shortCode' => 'pc']]]);

    $insert('payment_method', [
        'id' => $binary($paymentMethodId), 'handler_identifier' => Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment::class,
        'technical_name' => 'demo_cash', 'active' => 1, 'created_at' => $now,
    ], ['id']);
    $insert('payment_method_translation', ['payment_method_id' => $binary($paymentMethodId), 'language_id' => $binary($languageId), 'name' => 'Demo payment', 'created_at' => $now], ['payment_method_id', 'language_id']);
    $insert('shipping_method', [
        'id' => $binary($shippingMethodId), 'technical_name' => 'demo_shipping', 'active' => 1,
        'delivery_time_id' => $binary($deliveryTimeId), 'tax_type' => 'auto', 'created_at' => $now,
    ], ['id', 'delivery_time_id']);
    $insert('shipping_method_translation', ['shipping_method_id' => $binary($shippingMethodId), 'language_id' => $binary($languageId), 'name' => 'Demo delivery', 'created_at' => $now], ['shipping_method_id', 'language_id']);
    $create('sales_channel_type', ['id' => $channelTypeId, 'translations' => [$languageId => ['name' => 'Demo storefront']]]);
    $create('snippet_set', ['id' => $snippetSetId, 'name' => 'Demo English', 'baseFile' => 'messages.en-GB', 'iso' => 'en-GB']);

    $insert('category', [
        'id' => $binary($categoryId), 'version_id' => $binary(Defaults::LIVE_VERSION),
        'parent_version_id' => $binary(Defaults::LIVE_VERSION), 'after_category_version_id' => $binary(Defaults::LIVE_VERSION),
        'display_nested_products' => 1, 'type' => 'page', 'product_assignment_type' => 'product',
        'cms_page_version_id' => $binary(Defaults::LIVE_VERSION), 'created_at' => $now,
    ], ['id', 'version_id', 'parent_version_id', 'after_category_version_id', 'cms_page_version_id']);
    $insert('category_translation', [
        'category_id' => $binary($categoryId), 'category_version_id' => $binary(Defaults::LIVE_VERSION),
        'language_id' => $binary($languageId), 'name' => 'Home', 'created_at' => $now,
    ], ['category_id', 'category_version_id', 'language_id']);

    $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');
    $insert('sales_channel', [
        'id' => $binary($salesChannelId), 'type_id' => $binary($channelTypeId), 'language_id' => $binary($languageId),
        'customer_group_id' => $binary($groupId), 'currency_id' => $binary($currencyId),
        'payment_method_id' => $binary($paymentMethodId), 'shipping_method_id' => $binary($shippingMethodId),
        'country_id' => $binary($countryId), 'navigation_category_id' => $binary($categoryId),
        'navigation_category_version_id' => $binary(Defaults::LIVE_VERSION), 'footer_category_version_id' => $binary(Defaults::LIVE_VERSION),
        'service_category_version_id' => $binary(Defaults::LIVE_VERSION), 'home_cms_page_version_id' => $binary(Defaults::LIVE_VERSION),
        'access_key' => $accessKey, 'active' => 1, 'created_at' => $now,
    ], ['id', 'type_id', 'language_id', 'customer_group_id', 'currency_id', 'payment_method_id', 'shipping_method_id', 'country_id', 'navigation_category_id', 'navigation_category_version_id', 'footer_category_version_id', 'service_category_version_id', 'home_cms_page_version_id']);
    $insert('sales_channel_translation', [
        'sales_channel_id' => $binary($salesChannelId), 'language_id' => $binary($languageId), 'name' => 'Demo storefront',
        'home_enabled' => 1, 'home_name' => 'Home', 'created_at' => $now,
    ], ['sales_channel_id', 'language_id']);
    $insert('sales_channel_domain', [
        'id' => $binary(Uuid::randomHex()), 'url' => 'http://localhost:8000', 'sales_channel_id' => $binary($salesChannelId),
        'language_id' => $binary($languageId), 'currency_id' => $binary($currencyId), 'snippet_set_id' => $binary($snippetSetId), 'created_at' => $now,
    ], ['id', 'sales_channel_id', 'language_id', 'currency_id', 'snippet_set_id']);
    $insert('"user"', [
        'id' => $binary(Uuid::randomHex()), 'locale_id' => $binary($localeId), 'username' => 'demo-admin',
        'password' => password_hash($adminPassword, PASSWORD_DEFAULT), 'first_name' => 'Demo', 'last_name' => 'Administrator',
        'email' => 'demo-admin@example.test', 'time_zone' => 'UTC', 'admin' => 1, 'active' => 1, 'created_at' => $now,
    ], ['id', 'locale_id']);

    $insert('product', [
        'id' => $binary($productId), 'version_id' => $binary(Defaults::LIVE_VERSION), 'parent_version_id' => $binary(Defaults::LIVE_VERSION),
        'product_manufacturer_id' => $binary($manufacturerId), 'product_manufacturer_version_id' => $binary(Defaults::LIVE_VERSION),
        'unit_id' => $binary($unitId), 'tax_id' => $binary($taxId), 'delivery_time_id' => $binary($deliveryTimeId),
        'product_media_version_id' => $binary(Defaults::LIVE_VERSION), 'canonical_product_version_id' => $binary(Defaults::LIVE_VERSION),
        'cms_page_version_id' => $binary(Defaults::LIVE_VERSION),
        'price' => json_encode([$currencyId => ['gross' => 12.99, 'net' => 10.825, 'linked' => true]], JSON_THROW_ON_ERROR),
        'product_number' => 'DEMO-001', 'stock' => 10, 'active' => 1, 'created_at' => $now,
    ], ['id', 'version_id', 'parent_version_id', 'product_manufacturer_id', 'product_manufacturer_version_id', 'unit_id', 'tax_id', 'delivery_time_id', 'product_media_version_id', 'canonical_product_version_id', 'cms_page_version_id']);
    $insert('product_translation', [
        'product_id' => $binary($productId), 'product_version_id' => $binary(Defaults::LIVE_VERSION), 'language_id' => $binary($languageId),
        'name' => 'Demo product', 'created_at' => $now,
    ], ['product_id', 'product_version_id', 'language_id']);
    $insert('product_visibility', [
        'id' => $binary(Uuid::randomHex()), 'product_id' => $binary($productId), 'product_version_id' => $binary(Defaults::LIVE_VERSION),
        'sales_channel_id' => $binary($salesChannelId), 'visibility' => 30, 'created_at' => $now,
    ], ['id', 'product_id', 'product_version_id', 'sales_channel_id']);
    fwrite(STDOUT, 'Created ' . count($createdTables) . ' DAL tables in ' . DEMO_DATABASE . ".\n");
    if ($missingDefinitions !== []) {
        fwrite(STDOUT, 'No registered definition for: ' . implode(', ', $missingDefinitions) . "\n");
    }
    foreach ($schemaExceptions as $entityName => $message) {
        fwrite(STDOUT, 'Skipped ' . $entityName . ': ' . $message . "\n");
    }
    fwrite(STDOUT, "No historical migrations were loaded or marked as executed.\n");
    fwrite(STDOUT, 'Store API access key: ' . $accessKey . "\n");
    fwrite(STDOUT, 'Demo Admin API username: demo-admin; password: ' . $adminPassword . "\n");
} finally {
    $kernel->shutdown();
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Migration\V6_3\Migration1536232600Language;
use Shopware\Core\Migration\V6_3\Migration1536232850Media;
use Shopware\Core\Migration\V6_3\Migration1536232880Category;
use Shopware\Core\Migration\V6_3\Migration1536232920PaymentMethod;
use Shopware\Core\Migration\V6_3\Migration1536232990Order;
use Shopware\Core\Migration\V6_3\Migration1536233030OrderLineItem;
use Shopware\Core\Migration\V6_3\Migration1536233040OrderDeliveryPosition;
use Shopware\Core\Migration\V6_3\Migration1536233130ProductMedia;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('framework')]
class InitialSchemaPostgreSQLTest extends TestCase
{
    private const LAST_TIMESTAMP = 1536233340;

    private Connection $postgres;

    private Connection $mysql;

    /**
     * @var list<Connection>
     */
    private array $ownedDatabases = [];

    protected function setUp(): void
    {
        if (!getenv('DATABASE_CONTRACT_URL') || !getenv('DATABASE_CONTRACT_URL_MYSQL')) {
            static::markTestSkipped('Set DATABASE_CONTRACT_URL and DATABASE_CONTRACT_URL_MYSQL to dedicated empty contract databases.');
        }

        $this->postgres = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->mysql = ContractConnectionFactory::create('DATABASE_CONTRACT_URL_MYSQL');
        static::assertInstanceOf(PostgreSQLPlatform::class, $this->postgres->getDatabasePlatform());
        static::assertNotInstanceOf(PostgreSQLPlatform::class, $this->mysql->getDatabasePlatform());

        foreach ([$this->postgres, $this->mysql] as $connection) {
            static::assertSame([], $this->tableNames($connection), 'Use a dedicated empty database.');
            $schema = $connection === $this->postgres ? 'current_schema()' : 'DATABASE()';
            static::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM information_schema.views WHERE table_schema = $schema"), 'Use a dedicated empty database.');
        }

        foreach ([$this->postgres, $this->mysql] as $connection) {
            $this->ownedDatabases[] = $connection;
            static::assertTrue((new SetupDatabaseAdapter())->initializeShopwareDb($connection));
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->ownedDatabases as $connection) {
            if ($connection === $this->mysql) {
                $connection->executeStatement('SET SESSION FOREIGN_KEY_CHECKS = 0');
            }
            try {
                foreach ($this->tableNames($connection) as $table) {
                    $connection->executeStatement('DROP TABLE ' . $connection->quoteSingleIdentifier($table) . ($connection === $this->postgres ? ' CASCADE' : ''));
                }
            } finally {
                try {
                    if ($connection === $this->mysql) {
                        $connection->executeStatement('SET SESSION FOREIGN_KEY_CHECKS = 1');
                    }
                } finally {
                    $connection->close();
                }
            }
        }
    }

    public function testInitialMigrationsMatchMySqlAtEachStepAndCanBeReapplied(): void
    {
        $postgres = $this->collection($this->postgres);
        $mysql = $this->collection($this->mysql);
        $registry = new MigrationVariantRegistry();
        $mysql->sync();
        $postgres->sync();
        static::assertCount(335, $postgres->getMigrationSteps());

        $pending = $mysql->getExecutableMigrations(self::LAST_TIMESTAMP);
        static::assertCount(74, $pending);
        static::assertSame($pending, $postgres->getExecutableMigrations(self::LAST_TIMESTAMP));

        foreach ($pending as $class) {
            $timestamp = (new $class())->getCreationTimestamp();
            static::assertSame([$class], $mysql->migrateInPlace($timestamp));
            static::assertSame([$class], $postgres->migrateInPlace($timestamp));

            $mysqlSchema = $this->schema($this->mysql);
            $postgresSchema = $this->schema($this->postgres);
            static::assertSame($mysqlSchema, $postgresSchema, $class);

            $registry->resolve($class, $timestamp)->update($this->postgres);
            static::assertSame($postgresSchema, $this->schema($this->postgres), $class . ' changed the schema on retry');
        }

        $postgres->sync();
        static::assertSame([], $postgres->migrateInPlace(self::LAST_TIMESTAMP));
        static::assertSame($pending, $this->postgres->fetchFirstColumn('SELECT class FROM migration WHERE "update" IS NOT NULL ORDER BY creation_timestamp'));
        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM migration WHERE message IS NOT NULL'));
    }

    public function testVersionIdentityAndBinaryJsonAndCollationSemanticsSurviveRetry(): void
    {
        $postgres = $this->collection($this->postgres);
        $postgres->sync();
        $postgres->migrateInPlace(self::LAST_TIMESTAMP);

        $locale = str_repeat('L', 16);
        $language = str_repeat('N', 16);
        $this->postgres->executeStatement('INSERT INTO locale (id, code, created_at) VALUES (?, ?, ?)', [$locale, 'café', '2020-01-01 00:00:00.123'], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
        $this->postgres->executeStatement('INSERT INTO language (id, locale_id, name, custom_fields, created_at) VALUES (?, ?, ?, ?, ?)', [$language, $locale, 'Customized', '{"enabled": true}', '2020-01-01 00:00:00.123'], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

        $registry = new MigrationVariantRegistry();
        foreach (array_keys($postgres->getMigrationSteps()) as $class) {
            $timestamp = (new $class())->getCreationTimestamp();
            if ($timestamp <= self::LAST_TIMESTAMP) {
                $registry->resolve($class, $timestamp)->update($this->postgres);
            }
        }

        static::assertSame('Customized', $this->postgres->fetchOne('SELECT name FROM language WHERE id = ?', [$language], [ParameterType::BINARY]));
        static::assertSame('true', $this->postgres->fetchOne('SELECT custom_fields ->> \'enabled\' FROM language'));
        static::assertSame('2020-01-01 00:00:00.123', $this->postgres->fetchOne('SELECT created_at FROM locale'));

        $movedLocale = str_repeat('M', 16);
        $this->postgres->update('locale', ['id' => $movedLocale], ['id' => $locale], [ParameterType::BINARY, ParameterType::BINARY]);
        static::assertSame(bin2hex($movedLocale), $this->postgres->fetchOne('SELECT sw_uuid_hex(locale_id) FROM language'));

        $this->postgres->executeStatement('INSERT INTO version_commit (id, version_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP), (?, ?, CURRENT_TIMESTAMP)', [str_repeat('1', 16), $language, str_repeat('2', 16), $language], array_fill(0, 4, ParameterType::BINARY));
        static::assertSame([1, 2], array_map('intval', $this->postgres->fetchFirstColumn('SELECT auto_increment FROM version_commit ORDER BY auto_increment')));

        try {
            $this->postgres->executeStatement('INSERT INTO locale (id, code, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)', ['too short', 'en-GB'], [ParameterType::BINARY, ParameterType::STRING]);
            static::fail('Non-UUID byte lengths must be rejected.');
        } catch (DriverException $exception) {
            static::assertSame('23514', $exception->getSQLState());
        }

        try {
            $this->postgres->executeStatement('UPDATE language SET custom_fields = ?', ['{invalid}']);
            static::fail('Invalid JSON must be rejected.');
        } catch (DriverException $exception) {
            static::assertSame('22P02', $exception->getSQLState());
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->postgres->executeStatement('INSERT INTO locale (id, code, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)', [str_repeat('C', 16), 'CAFE'], [ParameterType::BINARY, ParameterType::STRING]);
    }

    public function testStateMachineRestrictsDeletingUsedStatesAndClearsTheInitialState(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(1536232760);

        $machine = str_repeat('M', 16);
        $initial = str_repeat('I', 16);
        $next = str_repeat('N', 16);
        $transition = str_repeat('T', 16);
        $this->postgres->executeStatement('INSERT INTO state_machine (id, technical_name, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)', [$machine, 'checkout'], [ParameterType::BINARY, ParameterType::STRING]);
        $this->postgres->executeStatement(<<<'SQL'
            INSERT INTO state_machine_state (id, state_machine_id, technical_name, created_at)
            VALUES (?, ?, 'initial', CURRENT_TIMESTAMP), (?, ?, 'next', CURRENT_TIMESTAMP)
            SQL, [$initial, $machine, $next, $machine], array_fill(0, 4, ParameterType::BINARY));
        $this->postgres->update('state_machine', ['initial_state_id' => $initial], ['id' => $machine], [ParameterType::BINARY, ParameterType::BINARY]);
        $this->postgres->executeStatement(<<<'SQL'
            INSERT INTO state_machine_transition (id, state_machine_id, from_state_id, to_state_id, action_name, created_at)
            VALUES (?, ?, ?, ?, 'move', CURRENT_TIMESTAMP)
            SQL, [$transition, $machine, $initial, $next], array_fill(0, 4, ParameterType::BINARY));

        try {
            $this->postgres->delete('state_machine_state', ['id' => $initial], [ParameterType::BINARY]);
            static::fail('A transition must prevent removal of one of its states.');
        } catch (DriverException $exception) {
            static::assertSame('23001', $exception->getSQLState());
            static::assertSame(bin2hex($initial), $this->postgres->fetchOne('SELECT sw_uuid_hex(initial_state_id) FROM state_machine'));
        }

        $this->postgres->delete('state_machine_transition', ['id' => $transition], [ParameterType::BINARY]);
        $this->postgres->delete('state_machine_state', ['id' => $initial], [ParameterType::BINARY]);
        static::assertNull($this->postgres->fetchOne('SELECT initial_state_id FROM state_machine'));
    }

    public function testCategoryParentCascadeUsesBothIdAndVersion(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(1536232880);

        $parent = str_repeat('P', 16);
        $child = str_repeat('C', 16);
        $draft = str_repeat('D', 16);
        $published = str_repeat('L', 16);
        $this->postgres->executeStatement(<<<'SQL'
            INSERT INTO category (id, version_id, parent_id, parent_version_id, type, created_at)
            VALUES
                (?, ?, NULL, NULL, 'page', CURRENT_TIMESTAMP),
                (?, ?, NULL, NULL, 'page', CURRENT_TIMESTAMP),
                (?, ?, ?, ?, 'page', CURRENT_TIMESTAMP)
            SQL, [$parent, $draft, $parent, $published, $child, $draft, $parent, $published], array_fill(0, 8, ParameterType::BINARY));

        $this->postgres->delete('category', ['id' => $parent, 'version_id' => $draft], [ParameterType::BINARY, ParameterType::BINARY]);
        static::assertSame(bin2hex($published), $this->postgres->fetchOne('SELECT sw_uuid_hex(parent_version_id) FROM category WHERE id = ?', [$child], [ParameterType::BINARY]));

        $this->postgres->delete('category', ['id' => $parent, 'version_id' => $published], [ParameterType::BINARY, ParameterType::BINARY]);
        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM category'));
    }

    public function testCategorySequencePreservesTheFullUnsignedRangeAndOwnership(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(1536232880);

        $first = str_repeat('A', 16);
        $imported = str_repeat('B', 16);
        $last = str_repeat('C', 16);
        $version = str_repeat('V', 16);
        $insert = 'INSERT INTO category (id, version_id, type, created_at) VALUES (?, ?, \'page\', CURRENT_TIMESTAMP) RETURNING auto_increment';
        static::assertSame('1', (string) $this->postgres->fetchOne($insert, [$first, $version], [ParameterType::BINARY, ParameterType::BINARY]));

        $this->postgres->executeStatement(
            'INSERT INTO category (id, version_id, type, created_at, auto_increment) VALUES (?, ?, \'page\', CURRENT_TIMESTAMP, ?)',
            [$imported, $version, '9223372036854775808'],
            [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING]
        );
        $this->postgres->executeStatement('SELECT setval(\'category_auto_increment_seq\', 9223372036854775805, true)');

        (new MigrationVariantRegistry())->resolve(Migration1536232880Category::class, 1536232880)->update($this->postgres);

        static::assertSame('9223372036854775808', $this->postgres->fetchOne('SELECT auto_increment FROM category WHERE id = ?', [$imported], [ParameterType::BINARY]));
        static::assertSame('18446744073709551615', (string) $this->postgres->fetchOne($insert, [$last, $version], [ParameterType::BINARY, ParameterType::BINARY]));
        $this->postgres->executeStatement('TRUNCATE TABLE category, category_translation RESTART IDENTITY');
        static::assertSame('1', (string) $this->postgres->fetchOne($insert, [$first, $version], [ParameterType::BINARY, ParameterType::BINARY]));
    }

    public function testMediaFileSizeAndPaymentHandlerDefaultsSurviveRetry(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(1536232920);

        $media = str_repeat('M', 16);
        $method = str_repeat('P', 16);
        $this->postgres->executeStatement('INSERT INTO media (id, file_size, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)', [$media, 4294967295], [ParameterType::BINARY, ParameterType::INTEGER]);
        $this->postgres->executeStatement('INSERT INTO payment_method (id, media_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)', [$method, $media], [ParameterType::BINARY, ParameterType::BINARY]);
        static::assertSame(DefaultPayment::class, $this->postgres->fetchOne('SELECT handler_identifier FROM payment_method'));

        $this->postgres->update('payment_method', ['handler_identifier' => 'CustomPayment'], ['id' => $method], [ParameterType::STRING, ParameterType::BINARY]);
        $registry = new MigrationVariantRegistry();
        $registry->resolve(Migration1536232850Media::class, 1536232850)->update($this->postgres);
        $registry->resolve(Migration1536232920PaymentMethod::class, 1536232920)->update($this->postgres);

        static::assertSame(4294967295, (int) $this->postgres->fetchOne('SELECT file_size FROM media'));
        static::assertSame('CustomPayment', $this->postgres->fetchOne('SELECT handler_identifier FROM payment_method'));
        try {
            $this->postgres->executeStatement('UPDATE media SET file_size = 4294967296');
            static::fail('The unsigned integer bound must be enforced.');
        } catch (DriverException $exception) {
            static::assertSame('23514', $exception->getSQLState());
        }

        $this->postgres->delete('media', ['id' => $media], [ParameterType::BINARY]);
        static::assertNull($this->postgres->fetchOne('SELECT media_id FROM payment_method'));
    }

    public function testGeneratedOrderAndDeliveryPricesUseTheHistoricalJsonSemantics(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1536233050);

            $reference = str_repeat('R', 16);
            $version = str_repeat('V', 16);
            $created = '2020-01-01 00:00:00.123';
            $this->insertOrderReferences($connection, $reference, $version, $created);

            $order = $connection->quoteSingleIdentifier('order');
            $connection->insert($order, [
                'id' => $reference,
                'version_id' => $version,
                'state_id' => $reference,
                'currency_id' => $reference,
                'sales_channel_id' => $reference,
                'billing_address_id' => $reference,
                'billing_address_version_id' => $version,
                'price' => '{"totalPrice":12.5,"netPrice":"10.25","positionPrice":7.5,"taxStatus":"gross"}',
                'order_date' => $created,
                'shipping_costs' => '{"totalPrice":5.25}',
                'deep_link_code' => str_repeat('L', 32),
                'created_at' => $created,
            ], array_fill(0, 7, ParameterType::BINARY));
            $connection->insert('order_line_item', [
                'id' => $reference,
                'version_id' => $version,
                'order_id' => $reference,
                'order_version_id' => $version,
                'identifier' => 'custom-item',
                'label' => 'Customized order item',
                'quantity' => 2,
                'price' => '{"unitPrice":3.125,"totalPrice":6.25}',
                'created_at' => $created,
            ], array_fill(0, 4, ParameterType::BINARY));
            $connection->insert('order_delivery', [
                'id' => $reference,
                'version_id' => $version,
                'order_id' => $reference,
                'order_version_id' => $version,
                'state_id' => $reference,
                'shipping_method_id' => $reference,
                'shipping_date_earliest' => '2020-01-02',
                'shipping_date_latest' => '2020-01-03',
                'shipping_costs' => '{}',
                'created_at' => $created,
            ], array_fill(0, 6, ParameterType::BINARY));
            $connection->insert('order_delivery_position', [
                'id' => $reference,
                'version_id' => $version,
                'order_delivery_id' => $reference,
                'order_delivery_version_id' => $version,
                'order_line_item_id' => $reference,
                'order_line_item_version_id' => $version,
                'price' => '{"totalPrice":2.5,"unitPrice":-1.5,"quantity":"1.5e1"}',
                'created_at' => $created,
            ], array_fill(0, 6, ParameterType::BINARY));

            if ($connection === $this->postgres) {
                $registry = new MigrationVariantRegistry();
                $registry->resolve(Migration1536232990Order::class, 1536232990)->update($connection);
                $registry->resolve(Migration1536233030OrderLineItem::class, 1536233030)->update($connection);
                $registry->resolve(Migration1536233040OrderDeliveryPosition::class, 1536233040)->update($connection);
            }

            static::assertSame([12.5, 10.25, 7.5, 5.25, 'gross'], $connection->fetchNumeric("SELECT amount_total, amount_net, position_price, shipping_total, tax_status FROM $order"));
            static::assertSame([3.125, 6.25, 'Customized order item'], $connection->fetchNumeric('SELECT unit_price, total_price, label FROM order_line_item'));
            static::assertSame([3, -2, 15], $connection->fetchNumeric('SELECT total_price, unit_price, quantity FROM order_delivery_position'));
            static::assertSame(1, (int) $connection->fetchOne("SELECT auto_increment FROM $order"));

            $connection->executeStatement("UPDATE $order SET price = ?, shipping_costs = ?", ['{"taxStatus":null}', '{"totalPrice":22.5}']);
            static::assertSame([null, null, null, 22.5, 'null'], $connection->fetchNumeric("SELECT amount_total, amount_net, position_price, shipping_total, tax_status FROM $order"));
            $connection->executeStatement("UPDATE $order SET price = ?", ['{}']);
            static::assertNull($connection->fetchOne("SELECT tax_status FROM $order"));
            $connection->executeStatement('UPDATE order_line_item SET price = ?', ['{"unitPrice":-1.25}']);
            static::assertSame([-1.25, null], $connection->fetchNumeric('SELECT unit_price, total_price FROM order_line_item'));
            $connection->executeStatement('UPDATE order_delivery_position SET price = ?', ['{"quantity":1.25}']);
            static::assertSame([null, null, 1], $connection->fetchNumeric('SELECT total_price, unit_price, quantity FROM order_delivery_position'));

            foreach (['{"totalPrice":null}', '{"totalPrice":"NaN"}'] as $invalidPrice) {
                try {
                    $connection->executeStatement("UPDATE $order SET price = ?", [$invalidPrice]);
                    static::fail('An invalid numeric price must be rejected.');
                } catch (DriverException) {
                    static::assertNull($connection->fetchOne("SELECT amount_total FROM $order"));
                }
            }

            try {
                $connection->executeStatement("UPDATE $order SET deep_link_code = ?", ['short']);
                static::fail('The deep-link code must contain 32 characters.');
            } catch (DriverException) {
                static::assertSame(str_repeat('L', 32), $connection->fetchOne("SELECT deep_link_code FROM $order"));
            }

            $connection->delete($order, ['id' => $reference, 'version_id' => $version], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM order_delivery_position'));
        }
    }

    public function testProductDecimalAndVersionedMediaLinkSurviveRetry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1536233120);

            $product = str_repeat('P', 16);
            $cover = str_repeat('C', 16);
            $media = str_repeat('M', 16);
            $version = str_repeat('V', 16);
            $created = '2020-01-01 00:00:00.123';
            $searchKeywords = $connection->quoteSingleIdentifier('searchKeywords');
            $connection->insert('product', [
                'id' => $product,
                'version_id' => $version,
                $searchKeywords => $product,
                'product_number' => 'custom-sku',
                'purchase_unit' => '1234567.9876',
                'created_at' => $created,
            ], array_fill(0, 3, ParameterType::BINARY));
            $connection->insert('media', ['id' => $media, 'created_at' => $created], [ParameterType::BINARY]);

            static::assertSame([Migration1536233130ProductMedia::class], $collection->migrateInPlace(1536233130));
            $connection->insert('product_media', [
                'id' => $cover,
                'version_id' => $version,
                'product_id' => $product,
                'product_version_id' => $version,
                'media_id' => $media,
                'created_at' => $created,
            ], array_fill(0, 5, ParameterType::BINARY));
            $connection->update('product', [
                'product_media_id' => $cover,
                'product_media_version_id' => $version,
            ], ['id' => $product, 'version_id' => $version], array_fill(0, 4, ParameterType::BINARY));

            if ($connection === $this->postgres) {
                (new MigrationVariantRegistry())->resolve(Migration1536233130ProductMedia::class, 1536233130)->update($connection);
            }

            static::assertSame(
                ['custom-sku', '1234567.9876', bin2hex($product), bin2hex($cover)],
                $connection->fetchNumeric("SELECT product_number, purchase_unit, sw_uuid_hex($searchKeywords), sw_uuid_hex(product_media_id) FROM product")
            );
            try {
                $connection->update('product', ['product_media_version_id' => $media], ['id' => $product], [ParameterType::BINARY, ParameterType::BINARY]);
                static::fail('A cover of a different version cannot satisfy the foreign key.');
            } catch (DriverException) {
                static::assertSame(bin2hex($version), $connection->fetchOne('SELECT sw_uuid_hex(product_media_version_id) FROM product'));
            }
            try {
                $connection->executeStatement('UPDATE product SET purchase_unit = -0.01');
                static::fail('A negative purchase unit must be rejected.');
            } catch (DriverException) {
                static::assertSame('1234567.9876', $connection->fetchOne('SELECT purchase_unit FROM product'));
            }
            $connection->delete('product_media', ['id' => $cover, 'version_id' => $version], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM product'));
        }
    }

    public function testConfigAndNumberRangePreserveHistoricalPersistenceBoundaries(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1536233340);

            $created = '2020-01-01 00:00:00.123';
            $first = str_repeat('F', 16);
            $second = str_repeat('S', 16);
            $connection->executeStatement(<<<'SQL'
                INSERT INTO system_config (id, configuration_key, configuration_value, created_at)
                VALUES (?, 'core.example', '{"value":"first"}', ?), (?, 'core.example', '{"value":"second"}', ?)
                SQL, [$first, $created, $second, $created], [ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING]);
            static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.example\' AND sales_channel_id IS NULL'));

            $lastValue = $connection->quoteSingleIdentifier('last_value');
            $connection->insert('number_range_state', [
                'id' => $second,
                'number_range_id' => $first,
                $lastValue => 37,
                'created_at' => $created,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER]);
            static::assertSame(37, $connection->fetchOne("SELECT $lastValue FROM number_range_state"));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM number_range'));

            try {
                $connection->insert('message_queue_stats', ['id' => $first, 'name' => 'queue', 'size' => -4, 'created_at' => $created], [ParameterType::BINARY, ParameterType::STRING, ParameterType::INTEGER]);
                static::fail('A queue count must not be negative.');
            } catch (DriverException) {
                static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM message_queue_stats'));
            }
        }
    }

    public function testDestructiveHistoryRunsSeparatelyWithoutChangingTheInitialSchema(): void
    {
        $postgres = $this->collection($this->postgres);
        $postgres->sync();
        static::assertSame([], $postgres->migrateDestructiveInPlace(self::LAST_TIMESTAMP));
        $updated = $postgres->migrateInPlace(self::LAST_TIMESTAMP);
        static::assertCount(74, $updated);
        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM migration WHERE update_destructive IS NOT NULL'));

        $schema = $this->schema($this->postgres);
        static::assertSame($updated, $postgres->migrateDestructiveInPlace(self::LAST_TIMESTAMP));
        static::assertSame($schema, $this->schema($this->postgres));
        static::assertSame([], $postgres->migrateDestructiveInPlace(self::LAST_TIMESTAMP));
    }

    private function insertOrderReferences(Connection $connection, string $reference, string $version, string $created): void
    {
        foreach (['country', 'customer_group', 'payment_method', 'sales_channel_type'] as $table) {
            $connection->insert($table, ['id' => $reference, 'created_at' => $created], [ParameterType::BINARY]);
        }

        $connection->insert('locale', ['id' => $reference, 'code' => 'en-GB', 'created_at' => $created], [ParameterType::BINARY]);
        $connection->insert('language', ['id' => $reference, 'locale_id' => $reference, 'name' => 'English', 'created_at' => $created], [ParameterType::BINARY, ParameterType::BINARY]);
        $connection->insert('currency', ['id' => $reference, 'iso_code' => 'EUR', 'factor' => 1, 'symbol' => '€', 'decimal_precision' => 2, 'created_at' => $created], [ParameterType::BINARY]);
        $connection->insert('rule', ['id' => $reference, 'name' => 'Shipping availability', 'priority' => 1, 'created_at' => $created], [ParameterType::BINARY]);
        $connection->insert('delivery_time', ['id' => $reference, 'min' => 1, 'max' => 2, 'unit' => 'day', 'created_at' => $created], [ParameterType::BINARY]);
        $connection->insert('shipping_method', ['id' => $reference, 'availability_rule_id' => $reference, 'delivery_time_id' => $reference, 'created_at' => $created], array_fill(0, 3, ParameterType::BINARY));
        $connection->insert('category', ['id' => $reference, 'version_id' => $version, 'type' => 'page', 'created_at' => $created], [ParameterType::BINARY, ParameterType::BINARY]);
        $connection->insert('sales_channel', [
            'id' => $reference,
            'type_id' => $reference,
            'language_id' => $reference,
            'currency_id' => $reference,
            'payment_method_id' => $reference,
            'shipping_method_id' => $reference,
            'country_id' => $reference,
            'navigation_category_id' => $reference,
            'navigation_category_version_id' => $version,
            'customer_group_id' => $reference,
            'access_key' => 'migration-contract',
            'created_at' => $created,
        ], array_fill(0, 10, ParameterType::BINARY));
    }

    private function collection(Connection $connection): MigrationCollection
    {
        $file = (new \ReflectionClass(Migration1536232600Language::class))->getFileName();
        static::assertIsString($file);
        $source = new MigrationSource('core.V6_3', [\dirname($file) => 'Shopware\\Core\\Migration\\V6_3']);
        $logger = new NullLogger();

        return new MigrationCollection($source, new MigrationRuntime($connection, $logger), $connection, $logger);
    }

    /**
     * @return array<string, array{columns: list<array<string, mixed>>, primaryKey: list<string>|null, foreignKeys: list<array<mixed>>, indexes: list<array<mixed>>}>
     */
    private function schema(Connection $connection): array
    {
        $manager = $connection->createSchemaManager();
        $tableNames = array_values(array_diff($this->tableNames($connection), ['migration', 'plugin']));
        sort($tableNames);

        $tables = [];
        foreach ($tableNames as $tableName) {
            $name = OptionallyQualifiedName::unquoted($tableName);
            $primaryKey = $manager->introspectTablePrimaryKeyConstraint($name);
            $tables[$tableName] = [
                'columns' => $this->columns($connection, $tableName),
                'primaryKey' => $primaryKey === null ? null : array_map(
                    static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
                    $primaryKey->getColumnNames()
                ),
                'foreignKeys' => array_map(
                    $this->foreignKeySignature(...),
                    $manager->introspectTableForeignKeyConstraints($name)
                ),
                'indexes' => array_map(
                    static fn (Index $index): array => [
                        $index->getType()->name,
                        array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()),
                    ],
                    $manager->introspectTableIndexes($name)
                ),
            ];
            sort($tables[$tableName]['foreignKeys']);
            sort($tables[$tableName]['indexes']);
        }

        return $tables;
    }

    /**
     * @return array{?string, array<string, string>, string, string, string}
     */
    private function foreignKeySignature(ForeignKeyConstraint $key): array
    {
        $column = static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue();
        $pairs = array_combine(
            array_map($column, $key->getReferencingColumnNames()),
            array_map($column, $key->getReferencedColumnNames())
        );
        ksort($pairs);

        return [
            $key->getObjectName()?->getIdentifier()->getValue(),
            $pairs,
            $key->getReferencedTableName()->getUnqualifiedName()->getValue(),
            $key->getOnDeleteAction()->name,
            $key->getOnUpdateAction()->name,
        ];
    }

    /**
     * @return list<non-empty-string>
     */
    private function tableNames(Connection $connection): array
    {
        return array_map(
            static fn (OptionallyQualifiedName $table): string => $table->getUnqualifiedName()->getValue(),
            (new DatabaseSchema($connection))->introspectCurrentSchemaTableNames()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(Connection $connection, string $table): array
    {
        $postgres = $connection === $this->postgres;
        $schema = $postgres ? 'current_schema()' : 'DATABASE()';
        $nativeType = $postgres ? 'data_type' : 'column_type';
        $identity = $postgres
            ? 'pg_get_serial_sequence(quote_ident(table_name), column_name) IS NOT NULL'
            : 'extra = \'auto_increment\'';
        $generated = $postgres ? 'is_generated = \'ALWAYS\'' : 'extra = \'VIRTUAL GENERATED\'';
        $columns = $connection->fetchAllAssociative(
            "SELECT column_name AS name, data_type AS type, $nativeType AS native_type, character_maximum_length AS length,
                    numeric_precision AS number_precision, numeric_scale AS scale, datetime_precision AS time_precision,
                    collation_name AS collation, is_nullable AS nullable, column_default AS default_value,
                    CASE WHEN $identity THEN 1 ELSE 0 END AS identity_column,
                    CASE WHEN $generated THEN 1 ELSE 0 END AS generated_column
             FROM information_schema.columns
             WHERE table_schema = $schema AND table_name = ?
             ORDER BY ordinal_position",
            [$table],
        );

        foreach ($columns as &$column) {
            $type = (string) $column['type'];
            $nativeType = (string) $column['native_type'];
            unset($column['native_type']);
            $unsignedSequence = (int) $column['identity_column'] === 1 && $nativeType === 'bigint unsigned';
            $column['type'] = match ($type) {
                'bigint' => $unsignedSequence ? 'numeric' : 'bigint',
                'binary', 'longblob' => 'bytea',
                'character' => 'char',
                'character varying' => 'varchar',
                'datetime' => 'timestamp without time zone',
                'decimal' => 'numeric',
                'double' => 'double precision',
                'float' => 'real',
                'int' => str_ends_with($nativeType, ' unsigned') ? 'bigint' : 'integer',
                'json' => 'jsonb',
                'longtext', 'mediumtext' => 'text',
                'tinyint' => 'smallint',
                default => $type,
            };
            $column['length'] = \in_array($type, ['char', 'character', 'character varying', 'varchar'], true) ? (int) $column['length'] : null;
            $column['number_precision'] = $unsignedSequence ? 20 : (\in_array($type, ['decimal', 'numeric'], true) ? (int) $column['number_precision'] : null);
            $column['scale'] = $column['type'] === 'numeric' ? (int) $column['scale'] : null;
            $column['time_precision'] = $column['time_precision'] === null || $type === 'date' ? null : (int) $column['time_precision'];
            $column['collation'] = match ($column['collation']) {
                'sw_type_ci', 'utf8mb4_unicode_ci' => 'shopware_ci',
                default => $column['collation'],
            };
            $column['identity_column'] = (int) $column['identity_column'];
            $column['generated_column'] = (int) $column['generated_column'];
            if ($column['identity_column'] === 1) {
                $column['default_value'] = null;
            } elseif ($postgres && \is_string($column['default_value'])) {
                $literal = explode('::', $column['default_value'], 2)[0];
                $column['default_value'] = $literal === 'NULL' ? null : trim($literal, '\'');
            } elseif ($column['default_value'] !== null) {
                $column['default_value'] = (string) $column['default_value'];
            }
        }

        return $columns;
    }
}

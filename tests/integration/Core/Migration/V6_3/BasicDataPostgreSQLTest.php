<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Migration\V6_3\Migration1536233560BasicData;

/**
 * @internal
 */
#[Package('framework')]
class BasicDataPostgreSQLTest extends MigrationHistoryDatabaseTestCase
{
    private const TIMESTAMP = 1536233560;

    public function testInitialDataMatchesMySqlAndDoesNotChangeOnRetry(): void
    {
        $postgres = $this->collection($this->postgres);
        $mysql = $this->collection($this->mysql);
        foreach ([$postgres, $mysql] as $collection) {
            $collection->sync();
            static::assertCount(94, $collection->migrateInPlace(self::TIMESTAMP - 1));
            static::assertSame([Migration1536233560BasicData::class], $collection->migrateInPlace(self::TIMESTAMP));
        }
        static::assertSame($this->seedSnapshot($this->mysql, true), $this->seedSnapshot($this->postgres, true));

        foreach ([$this->mysql, $this->postgres] as $connection) {
            static::assertSame(
                [['de-DE', 'Deutsch'], ['en-GB', 'English']],
                $connection->fetchAllNumeric('SELECT locale.code, language.name FROM language JOIN locale ON language.locale_id = locale.id ORDER BY locale.code')
            );
            static::assertSame(Defaults::LANGUAGE_SYSTEM, $connection->fetchOne('SELECT sw_uuid_hex(language.id) FROM language JOIN locale ON language.locale_id = locale.id WHERE locale.code = \'en-GB\''));
            static::assertSame(
                [['EUR', 'DE', 'Catalogue #1', Defaults::CURRENCY, Defaults::LIVE_VERSION]],
                $connection->fetchAllNumeric(<<<'SQL'
                    SELECT currency.iso_code, country.iso, category_translation.name,
                           sw_uuid_hex(currency.id), sw_uuid_hex(category_translation.category_version_id)
                    FROM sales_channel
                    JOIN currency ON currency.id = sales_channel.currency_id
                    JOIN country ON country.id = sales_channel.country_id
                    JOIN category_translation ON category_translation.category_id = sales_channel.navigation_category_id
                        AND category_translation.category_version_id = sales_channel.navigation_category_version_id
                        AND category_translation.language_id = sales_channel.language_id
                    SQL)
            );
            $states = $connection->fetchAllNumeric(<<<'SQL'
                SELECT state_machine.technical_name, state_machine_state.technical_name
                FROM state_machine
                JOIN state_machine_state ON state_machine_state.id = state_machine.initial_state_id
                SQL);
            sort($states);
            static::assertSame([['order.state', 'open'], ['order_delivery.state', 'open'], ['order_transaction.state', 'open']], $states);
            static::assertSame(
                [
                    ['customer', '{n}', 10000],
                    ['document_credit_note', '{n}', 1000],
                    ['document_delivery_note', '{n}', 1000],
                    ['document_invoice', '{n}', 1000],
                    ['document_storno', '{n}', 1000],
                    ['order', '{n}', 10000],
                    ['product', 'SW{n}', 10000],
                ],
                $connection->fetchAllNumeric(<<<'SQL'
                    SELECT number_range_type.technical_name, number_range.pattern, number_range.start
                    FROM number_range
                    JOIN number_range_type ON number_range.type_id = number_range_type.id
                    ORDER BY number_range_type.technical_name
                    SQL)
            );
            static::assertSame(
                14,
                (int) $connection->fetchOne(<<<'SQL'
                    SELECT COUNT(*) FROM mail_template_translation
                    JOIN mail_template ON mail_template.id = mail_template_translation.mail_template_id
                    JOIN language ON language.id = mail_template_translation.language_id
                    SQL)
            );
        }

        $beforeRetry = $this->seedSnapshot($this->postgres, false);
        (new MigrationVariantRegistry())->resolve(Migration1536233560BasicData::class, self::TIMESTAMP)->update($this->postgres);
        $postgres->sync();
        static::assertSame([], $postgres->migrateInPlace(self::TIMESTAMP));
        static::assertSame($beforeRetry, $this->seedSnapshot($this->postgres, false));
    }

    public function testCustomizedLanguageAndConfigurationPreventSeeding(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(self::TIMESTAMP - 1);

        $id = str_repeat('U', 16);
        $created = '2020-01-01 00:00:00.123';
        $this->postgres->insert('locale', ['id' => $id, 'code' => 'pl-PL', 'created_at' => $created], [ParameterType::BINARY]);
        $this->postgres->insert('language', ['id' => $id, 'locale_id' => $id, 'name' => 'Polski sklepu', 'created_at' => $created], array_fill(0, 2, ParameterType::BINARY));
        $this->postgres->insert('system_config', ['id' => $id, 'configuration_key' => 'custom.setting', 'configuration_value' => '{"value":"Custom"}', 'created_at' => $created], [ParameterType::BINARY]);
        $priorData = $this->seedSnapshot($this->postgres, false);

        static::assertSame([Migration1536233560BasicData::class], $collection->migrateInPlace(self::TIMESTAMP));
        static::assertSame($priorData, $this->seedSnapshot($this->postgres, false));
    }

    public function testSeedFailureRollsBackAndRetryPreservesExistingCategory(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(self::TIMESTAMP - 1);

        $custom = str_repeat('C', 16);
        $version = str_repeat('V', 16);
        $created = '2020-01-01 00:00:00.123';
        $this->postgres->insert('cms_page', ['id' => $custom, 'type' => 'custom', 'created_at' => $created], [ParameterType::BINARY]);
        $this->postgres->insert('category', [
            'id' => $custom,
            'version_id' => $version,
            'cms_page_id' => $custom,
            'type' => 'page',
            'created_at' => $created,
        ], array_fill(0, 3, ParameterType::BINARY));
        $this->postgres->executeStatement('ALTER TABLE country ADD CONSTRAINT test_block_initial_country CHECK (false)');
        $priorData = $this->seedSnapshot($this->postgres, false);

        try {
            $collection->migrateInPlace(self::TIMESTAMP);
            static::fail('A failed seed must leave the logical migration pending.');
        } catch (DriverException $exception) {
            static::assertSame('23514', $exception->getSQLState());
        }
        static::assertSame([Migration1536233560BasicData::class], $collection->getExecutableMigrations(self::TIMESTAMP));
        static::assertSame($priorData, $this->seedSnapshot($this->postgres, false));

        $this->postgres->executeStatement('ALTER TABLE country DROP CONSTRAINT test_block_initial_country');
        static::assertSame([Migration1536233560BasicData::class], $collection->migrateInPlace(self::TIMESTAMP));
        static::assertSame(bin2hex($custom), $this->postgres->fetchOne('SELECT sw_uuid_hex(cms_page_id) FROM category WHERE id = ?', [$custom], [ParameterType::BINARY]));
        static::assertSame(
            1,
            (int) $this->postgres->fetchOne(<<<'SQL'
                SELECT COUNT(*) FROM category
                JOIN cms_page ON cms_page.id = category.cms_page_id
                JOIN sales_channel ON sales_channel.navigation_category_id = category.id
                    AND sales_channel.navigation_category_version_id = category.version_id
                WHERE cms_page.type <> 'custom'
                SQL)
        );
    }

    /**
     * @return array<string, array{count: int, hash: string}>
     */
    private function seedSnapshot(Connection $connection, bool $crossPlatform): array
    {
        $schema = $connection === $this->postgres ? 'current_schema()' : 'DATABASE()';
        $types = [];
        foreach ($connection->fetchAllAssociative("SELECT table_name AS tbl, column_name AS col, data_type AS type FROM information_schema.columns WHERE table_schema = $schema") as $column) {
            $types[(string) $column['tbl']][(string) $column['col']] = (string) $column['type'];
        }

        $mailTypes = $crossPlatform ? $connection->fetchAllKeyValue('SELECT sw_uuid_hex(id), technical_name FROM mail_template_type') : [];
        $snapshot = [];
        foreach (array_diff($this->tableNames($connection), ['migration', 'plugin']) as $table) {
            $rows = [];
            foreach ($connection->fetchAllAssociative('SELECT * FROM ' . $connection->quoteSingleIdentifier($table)) as $row) {
                if ($crossPlatform) {
                    unset($row['created_at'], $row['updated_at']);
                    if ($table === 'event_action') {
                        static::assertIsString($row['config']);
                        $config = json_decode($row['config'], flags: \JSON_THROW_ON_ERROR);
                        static::assertInstanceOf(\stdClass::class, $config);
                        static::assertIsString($config->mail_template_type_id);
                        static::assertArrayHasKey($config->mail_template_type_id, $mailTypes);
                        $config->mail_template_type_id = $mailTypes[$config->mail_template_type_id];
                        $row['config'] = json_encode($config, \JSON_THROW_ON_ERROR);
                    }
                }
                foreach ($row as $column => $value) {
                    $row[$column] = $this->normalizeSeedValue($value, $column, $types[$table][$column], $crossPlatform);
                }
                ksort($row);
                $rows[] = json_encode($row, \JSON_THROW_ON_ERROR);
            }
            sort($rows);
            $snapshot[$table] = ['count' => \count($rows), 'hash' => hash('sha256', implode("\n", $rows))];
        }
        ksort($snapshot);

        return $snapshot;
    }

    private function normalizeSeedValue(mixed $value, string $column, string $type, bool $crossPlatform): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($column === 'access_key' || \in_array($type, ['binary', 'varbinary', 'bytea', 'longblob'], true)) {
            if ($crossPlatform) {
                return 'present';
            }

            $content = \is_resource($value) ? stream_get_contents($value) : $value;
            static::assertIsString($content);

            return hash('sha256', $content);
        }
        if (\in_array($type, ['json', 'jsonb'], true)) {
            static::assertIsString($value);

            return $this->normalizeJson(json_decode($value, flags: \JSON_THROW_ON_ERROR));
        }

        return \is_numeric($value) ? (string) $value : $value;
    }

    private function normalizeJson(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);

            return (object) array_map($this->normalizeJson(...), $properties);
        }

        return \is_array($value) ? array_map($this->normalizeJson(...), $value) : $value;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Migration\V6_3\Migration1552360944MediaFolderConfigurationNoAssoc;
use Shopware\Core\Migration\V6_3\Migration1554199340AddImportExportProfile;
use Shopware\Core\Migration\V6_3\Migration1554900301AddReviewTable;
use Shopware\Core\Migration\V6_3\Migration1556809270AddAverageRatingToProduct;
use Shopware\Core\Migration\V6_3\Migration1558082916AddBreadcrumb;
use Shopware\Core\Migration\V6_3\Migration1558105657CurrencyPrices;

/**
 * @internal
 */
#[Package('framework')]
class PostInitialSchemaPostgreSQLTest extends MigrationHistoryDatabaseTestCase
{
    private const BASIC_DATA_TIMESTAMP = 1536233560;
    private const LAST_TIMESTAMP = 1558105657;

    public function testSchemaMatchesMySqlAfterEachUpgradeStepAndRetry(): void
    {
        $postgres = $this->collection($this->postgres);
        $mysql = $this->collection($this->mysql);
        foreach ([$postgres, $mysql] as $collection) {
            $collection->sync();
            static::assertCount(95, $collection->migrateInPlace(self::BASIC_DATA_TIMESTAMP));
        }

        $registry = new MigrationVariantRegistry();
        $pending = $mysql->getExecutableMigrations(self::LAST_TIMESTAMP);
        static::assertCount(8, $pending);
        static::assertSame($pending, $postgres->getExecutableMigrations(self::LAST_TIMESTAMP));
        foreach ($pending as $class) {
            $timestamp = (new $class())->getCreationTimestamp();
            static::assertSame([$class], $mysql->migrateInPlace($timestamp));
            static::assertSame([$class], $postgres->migrateInPlace($timestamp));
            $schema = $this->schema($this->postgres);
            static::assertSame($this->schema($this->mysql), $schema, $class);
            $registry->resolve($class, $timestamp)->update($this->postgres);
            static::assertSame($schema, $this->schema($this->postgres), $class);
        }

        $postgres->sync();
        static::assertSame([], $postgres->migrateInPlace(self::LAST_TIMESTAMP));
    }

    public function testCurrencyPriceColumnIsRemovedWithoutLosingTheRemainingSchema(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(1558082916);
        $before = $this->schema($this->postgres)['product_price'];
        static::assertContains('currency_id', array_column($before['columns'], 'name'));

        $variant = (new MigrationVariantRegistry())->resolve(Migration1558105657CurrencyPrices::class, 1558105657);
        $variant->update($this->postgres);
        $after = $this->schema($this->postgres)['product_price'];
        static::assertNotContains('currency_id', array_column($after['columns'], 'name'));
        static::assertContains('price', array_column($after['columns'], 'name'));
        static::assertNotContains('fk.product_price.currency_id', array_column($after['foreignKeys'], 0));

        $variant->update($this->postgres);
        static::assertSame($after, $this->schema($this->postgres)['product_price']);
    }

    public function testImportProfileRetainsValidUuidSubstitutionsAndCustomizations(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1554199340);
            $profile = $connection->fetchAssociative('SELECT name, source_entity, file_type, delimiter, mapping FROM import_export_profile WHERE system_default = 1');
            static::assertIsArray($profile);
            static::assertSame(['Default customer', 'customer', 'text/csv', ';'], [$profile['name'], $profile['source_entity'], $profile['file_type'], $profile['delimiter']]);
            static::assertIsString($profile['mapping']);
            $mapping = json_decode($profile['mapping'], true, flags: \JSON_THROW_ON_ERROR);
            static::assertIsArray($mapping);
            static::assertCount(25, $mapping);
            $fields = array_column($mapping, 'valueSubstitutions', 'entityField');
            static::assertSame([false, true], $fields['guest']);
            static::assertSame('cfbd5018d38d41d8adca10d94fc8bdd6', $fields['groupId']['default']);
            static::assertSame('98432def39fc4624b33213a56b8c944d', $fields['salesChannelId']['default']);
            static::assertSame(
                $connection->fetchOne('SELECT sw_uuid_hex(id) FROM country WHERE iso = \'DE\''),
                $fields['defaultBillingAddress.countryId']['DE']
            );
            static::assertSame(
                $connection->fetchOne('SELECT sw_uuid_hex(id) FROM salutation WHERE salutation_key = \'mr\''),
                $fields['defaultShippingAddress.salutationId']['mr']
            );
            static::assertSame(
                $connection->fetchOne('SELECT sw_uuid_hex(id) FROM payment_method WHERE handler_identifier LIKE \'%CashPayment\''),
                $fields['defaultPaymentMethodId']['cash']
            );
        }

        $this->postgres->executeStatement('UPDATE import_export_profile SET name = ?, mapping = ?', ['My customer profile', '[]']);
        (new MigrationVariantRegistry())->resolve(Migration1554199340AddImportExportProfile::class, 1554199340)->update($this->postgres);
        static::assertSame([['My customer profile', '[]']], $this->postgres->fetchAllNumeric('SELECT name, mapping FROM import_export_profile'));
    }

    public function testFileDeletionPreservesTheHistoryEntry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1554203706);

            $id = str_repeat('F', 16);
            $loggedAt = '2020-01-01 00:00:00.123';
            $connection->insert('import_export_file', [
                'id' => $id,
                'original_name' => 'customers.csv',
                'path' => 'customers.csv',
                'expire_date' => '2020-01-02 00:00:00.123',
                'created_at' => $loggedAt,
                'access_token' => 'test-token',
            ], [ParameterType::BINARY]);
            $connection->insert('import_export_log', [
                'id' => $id,
                'file_id' => $id,
                'activity' => 'import',
                'state' => 'complete',
                'records' => 12,
                'created_at' => $loggedAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);
            $connection->delete('import_export_file', ['id' => $id], [ParameterType::BINARY]);
            static::assertSame([['complete', 12, null]], $connection->fetchAllNumeric('SELECT state, records, file_id FROM import_export_log'));
        }
    }

    public function testHistoricalReviewResetAndIdempotentColumnChangesMatchSourceSemantics(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(self::LAST_TIMESTAMP);
        $id = str_repeat('R', 16);
        $version = str_repeat('V', 16);
        $created = '2020-01-01 00:00:00.123';
        $this->postgres->insert('product', ['id' => $id, 'version_id' => $version, 'rating_average' => 4.5, 'created_at' => $created], [ParameterType::BINARY, ParameterType::BINARY]);
        $this->postgres->insert('product_review', ['id' => $id, 'product_id' => $id, 'product_version_id' => $version, 'title' => 'Historical customer review', 'created_at' => $created], array_fill(0, 3, ParameterType::BINARY));
        $this->postgres->executeStatement('UPDATE category_translation SET breadcrumb = ?', ['{"current":"Catalogue"}']);
        $this->postgres->executeStatement('UPDATE media_folder_configuration SET no_association = 1');

        $registry = new MigrationVariantRegistry();
        $registry->resolve(Migration1552360944MediaFolderConfigurationNoAssoc::class, 1552360944)->update($this->postgres);
        $registry->resolve(Migration1554900301AddReviewTable::class, 1554900301)->update($this->postgres);
        $registry->resolve(Migration1556809270AddAverageRatingToProduct::class, 1556809270)->update($this->postgres);
        $registry->resolve(Migration1558082916AddBreadcrumb::class, 1558082916)->update($this->postgres);

        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM product_review'), 'The original update drops product_review before recreating it.');
        static::assertSame(4.5, $this->postgres->fetchOne('SELECT rating_average FROM product'));
        static::assertSame(['Catalogue', 'Catalogue'], $this->postgres->fetchFirstColumn('SELECT breadcrumb ->> \'current\' FROM category_translation'));
        static::assertSame([1], $this->postgres->fetchFirstColumn('SELECT DISTINCT no_association FROM media_folder_configuration'));
    }

    /**
     * @return array<string, array{columns: list<array<string, mixed>>, primaryKey: list<string>|null, foreignKeys: list<array<mixed>>, indexes: list<array<mixed>>}>
     */
    private function schema(Connection $connection): array
    {
        $columns = $this->columns($connection);
        $indexes = $this->indexes($connection);
        $tables = [];
        foreach ($connection->createSchemaManager()->introspectTables() as $table) {
            $name = $table->getObjectName();
            $tableName = $name->getUnqualifiedName()->getValue();
            if ($name->getQualifier() !== null || !isset($columns[$tableName])) {
                continue;
            }

            $primaryKey = $table->getPrimaryKeyConstraint();
            $tables[$tableName] = [
                'columns' => $columns[$tableName],
                'primaryKey' => $primaryKey === null ? null : array_map(
                    static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
                    $primaryKey->getColumnNames()
                ),
                'foreignKeys' => array_values(array_map($this->foreignKeySignature(...), $table->getForeignKeys())),
                'indexes' => array_values($indexes[$tableName] ?? []),
            ];
            sort($tables[$tableName]['foreignKeys']);
            sort($tables[$tableName]['indexes']);
        }
        ksort($tables);

        return $tables;
    }

    /**
     * @return array<string, array<string, array{string, list<string>}>>
     */
    private function indexes(Connection $connection): array
    {
        // DBAL can synthesize an index from a foreign key while building a Table.
        // Read the physical indexes so additional indexes and their column order are observable.
        $sql = $connection === $this->postgres
            ? <<<'SQL'
                SELECT t.relname AS table_name, idx.relname AS index_name, a.attname AS column_name,
                       CASE WHEN i.indisunique THEN 'UNIQUE' ELSE 'REGULAR' END AS index_type
                FROM pg_catalog.pg_index i
                JOIN pg_catalog.pg_class t ON t.oid = i.indrelid
                JOIN pg_catalog.pg_namespace n ON n.oid = t.relnamespace
                JOIN pg_catalog.pg_class idx ON idx.oid = i.indexrelid
                CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS part(attribute_number, index_position)
                JOIN pg_catalog.pg_attribute a ON a.attrelid = t.oid AND a.attnum = part.attribute_number
                WHERE n.nspname = current_schema() AND NOT i.indisprimary
                ORDER BY t.relname, idx.relname, part.index_position
                SQL
            : <<<'SQL'
                SELECT table_name AS table_name, index_name AS index_name, column_name AS column_name,
                       CASE WHEN non_unique = 0 THEN 'UNIQUE' ELSE 'REGULAR' END AS index_type
                FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND index_name <> 'PRIMARY'
                ORDER BY table_name, index_name, seq_in_index
                SQL;
        $tables = [];
        foreach ($connection->fetchAllAssociative($sql) as $row) {
            $tableName = (string) $row['table_name'];
            $indexName = (string) $row['index_name'];
            $tables[$tableName][$indexName] ??= [(string) $row['index_type'], []];
            $tables[$tableName][$indexName][1][] = (string) $row['column_name'];
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
     * @return array<string, list<array<string, mixed>>>
     */
    private function columns(Connection $connection): array
    {
        $postgres = $connection === $this->postgres;
        $schema = $postgres ? 'current_schema()' : 'DATABASE()';
        $nativeType = $postgres ? 'data_type' : 'column_type';
        $identity = $postgres
            ? 'pg_get_serial_sequence(quote_ident(table_name), column_name) IS NOT NULL'
            : 'extra = \'auto_increment\'';
        $generated = $postgres
            ? 'CASE WHEN is_generated = \'ALWAYS\' THEN (
                    SELECT attr.attgenerated::text
                    FROM pg_catalog.pg_attribute AS attr
                    WHERE attr.attrelid = to_regclass(quote_ident(column_info.table_name))
                      AND attr.attname = column_info.column_name AND attr.attnum > 0
                ) ELSE \'\' END'
            : 'CASE extra WHEN \'VIRTUAL GENERATED\' THEN \'v\' WHEN \'STORED GENERATED\' THEN \'s\' ELSE \'\' END';
        $columns = $connection->fetchAllAssociative(
            "SELECT table_name AS table_name, column_name AS name, data_type AS type, $nativeType AS native_type, character_maximum_length AS length,
                    numeric_precision AS number_precision, numeric_scale AS scale, datetime_precision AS time_precision,
                    collation_name AS collation, is_nullable AS nullable, column_default AS default_value,
                    CASE WHEN $identity THEN 1 ELSE 0 END AS identity_column,
                    $generated AS generated_column
             FROM information_schema.columns AS column_info
             WHERE table_schema = $schema AND table_name NOT IN ('migration', 'plugin')",
        );

        $tables = [];
        foreach ($columns as $column) {
            $table = (string) $column['table_name'];
            unset($column['table_name']);
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
                'sw_type_ci', 'utf8mb4_unicode_ci', 'utf8mb4_0900_ai_ci' => 'shopware_ci',
                default => $column['collation'],
            };
            $column['identity_column'] = (int) $column['identity_column'];
            if ($column['identity_column'] === 1) {
                $column['default_value'] = null;
            } elseif ($postgres && \is_string($column['default_value'])) {
                $literal = explode('::', $column['default_value'], 2)[0];
                $column['default_value'] = $literal === 'NULL' ? null : trim($literal, '\'');
            } elseif ($column['default_value'] !== null) {
                $column['default_value'] = (string) $column['default_value'];
            }
            $tables[$table][] = $column;
        }
        foreach ($tables as &$tableColumns) {
            usort($tableColumns, static fn (array $first, array $second): int => strcmp((string) $first['name'], (string) $second['name']));
        }

        return $tables;
    }
}

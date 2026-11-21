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
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Migration\V6_3\Migration1536232600Language;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('framework')]
class InitialSchemaPostgreSQLTest extends TestCase
{
    private const LAST_TIMESTAMP = 1536232750;

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
        static::assertCount(16, $pending);
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

    public function testDestructiveHistoryRunsSeparatelyWithoutChangingTheInitialSchema(): void
    {
        $postgres = $this->collection($this->postgres);
        $postgres->sync();
        static::assertSame([], $postgres->migrateDestructiveInPlace(self::LAST_TIMESTAMP));
        $updated = $postgres->migrateInPlace(self::LAST_TIMESTAMP);
        static::assertCount(16, $updated);
        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM migration WHERE update_destructive IS NOT NULL'));

        $schema = $this->schema($this->postgres);
        static::assertSame($updated, $postgres->migrateDestructiveInPlace(self::LAST_TIMESTAMP));
        static::assertSame($schema, $this->schema($this->postgres));
        static::assertSame([], $postgres->migrateDestructiveInPlace(self::LAST_TIMESTAMP));
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
                    static fn (ForeignKeyConstraint $key): array => [
                        $key->getObjectName()?->getIdentifier()->getValue(),
                        array_map(static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(), $key->getReferencingColumnNames()),
                        $key->getReferencedTableName()->getUnqualifiedName()->getValue(),
                        array_map(static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(), $key->getReferencedColumnNames()),
                        $key->getOnDeleteAction()->name,
                        $key->getOnUpdateAction()->name,
                    ],
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
        $identity = $postgres ? 'is_identity = \'YES\'' : 'extra = \'auto_increment\'';
        $columns = $connection->fetchAllAssociative(
            "SELECT LOWER(column_name) AS name, data_type AS type, character_maximum_length AS length,
                    numeric_precision AS number_precision, numeric_scale AS scale, datetime_precision AS time_precision,
                    collation_name AS collation, is_nullable AS nullable, column_default AS default_value,
                    CASE WHEN $identity THEN 1 ELSE 0 END AS identity_column
             FROM information_schema.columns
             WHERE table_schema = $schema AND table_name = ?
             ORDER BY ordinal_position",
            [$table],
        );

        foreach ($columns as &$column) {
            $type = (string) $column['type'];
            $column['type'] = match ($type) {
                'binary', 'longblob' => 'bytea',
                'character' => 'char',
                'character varying' => 'varchar',
                'datetime' => 'timestamp without time zone',
                'decimal' => 'numeric',
                'double' => 'double precision',
                'int' => 'integer',
                'json' => 'jsonb',
                'longtext' => 'text',
                'tinyint' => 'smallint',
                default => $type,
            };
            $column['length'] = \in_array($type, ['char', 'character', 'character varying', 'varchar'], true) ? (int) $column['length'] : null;
            $column['number_precision'] = \in_array($type, ['decimal', 'numeric'], true) ? (int) $column['number_precision'] : null;
            $column['scale'] = $column['scale'] !== null && $column['type'] === 'numeric' ? (int) $column['scale'] : null;
            $column['time_precision'] = $column['time_precision'] === null ? null : (int) $column['time_precision'];
            $column['collation'] = match ($column['collation']) {
                'sw_type_ci', 'utf8mb4_unicode_ci' => 'shopware_ci',
                default => $column['collation'],
            };
            $column['identity_column'] = (int) $column['identity_column'];
            if ($postgres && \is_string($column['default_value'])) {
                $column['default_value'] = trim(explode('::', $column['default_value'], 2)[0], '\'');
            } elseif ($column['default_value'] !== null) {
                $column['default_value'] = (string) $column['default_value'];
            }
        }

        return $columns;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialectException;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseSchemaTest extends TestCase
{
    private Connection $connection;

    private DatabaseSchema $schema;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->schema = new DatabaseSchema($this->connection);
        if ($this->schema->isPostgreSQL()) {
            static::assertStringStartsWith('18.6 ', (string) $this->connection->fetchOne('SHOW server_version'));
            $this->connection->executeStatement('CREATE SCHEMA sw_schema_contract');
            $this->connection->executeStatement('SET search_path TO sw_schema_contract');
        } else {
            static::assertSame('shopware_json_test', $this->connection->getDatabase());
            static::assertStringStartsWith('8.4.', (string) $this->connection->fetchOne('SELECT VERSION()'));
        }
        foreach ($this->schema->prerequisites() as $query) {
            $this->connection->executeStatement($query);
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        if ($this->schema->isPostgreSQL()) {
            $this->connection->executeStatement('DROP SCHEMA sw_schema_contract CASCADE');
        } else {
            foreach (['sw_schema_child', 'sw_schema_other', 'sw_schema_scalar'] as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
            }
        }
        $this->connection->close();
    }

    public function testTypesDefaultsIndexesAndDataSurviveRoundTrip(): void
    {
        $table = $this->scalarTable();
        $table->addUniqueIndex(['name'], 'unique_name');
        $table->addIndex(['enabled'], 'enabled_index');
        $table = $this->schema->prepareTable($table);
        foreach ($this->schema->createTableQueries($table) as $query) {
            $this->connection->executeStatement($query);
        }
        $id = Uuid::randomBytes();
        $payload = "\x00\xff\x80bytes";
        $this->connection->insert('sw_schema_scalar', ['id' => $id, 'name' => 'café', 'payload' => $payload, 'created_at' => '2026-10-05 12:34:56.123', 'data' => '{"number":1,"null":null}', 'amount' => '12345.678901'], ['id' => ParameterType::BINARY, 'payload' => ParameterType::BINARY]);

        $actual = $this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar');
        static::assertSame([], $this->schema->alterTableQueries($actual, $table));
        static::assertSame([], $this->schema->prerequisites());
        static::assertTrue($actual->hasIndex($this->schema->isPostgreSQL() ? 'sw_schema_scalar__unique_name' : 'unique_name'));
        static::assertTrue($actual->hasIndex($this->schema->isPostgreSQL() ? 'sw_schema_scalar__enabled_index' : 'enabled_index'));
        $row = $this->connection->fetchAssociative('SELECT * FROM sw_schema_scalar');
        static::assertIsArray($row);
        static::assertSame($id, self::bytes($row['id']));
        static::assertSame($payload, self::bytes($row['payload']));
        static::assertSame(Types::BINARY, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'id')->type);
        static::assertSame(16, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'id')->length);
        static::assertSame(Types::BLOB, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'payload')->type);
        static::assertSame(Types::BOOLEAN, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'enabled')->type);
        static::assertSame(Types::INTEGER, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'counter')->type);
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'counter')->unsigned);
        static::assertSame(Types::DATETIME_MUTABLE, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'created_at')->type);
        static::assertSame($this->schema->isPostgreSQL() ? Types::JSONB : Types::JSON, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'data')->type);
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $this->schema->prepareTable($this->schema->introspectTable('sw_schema_scalar'))));
        static::assertSame('12345.678901', $row['amount']);
        static::assertSame(0, (int) $row['enabled']);
        static::assertSame(7, (int) $row['counter']);
        static::assertSame(1, (int) $row['sequence']);
        static::assertEquals(['number' => 1, 'null' => null], json_decode($row['data'], true, 512, \JSON_THROW_ON_ERROR));
        if ($this->schema->isPostgreSQL()) {
            $metadata = $this->schema->columnMetadata($actual);
            static::assertSame('bytea', $metadata['id']['physical_type']);
            static::assertSame('bytea', $metadata['payload']['physical_type']);
            static::assertSame('smallint', $metadata['enabled']['physical_type']);
            static::assertSame('timestamp(3) without time zone', $metadata['created_at']['physical_type']);
            static::assertSame('jsonb', $metadata['data']['physical_type']);
            static::assertSame('bigint', $metadata['counter']['physical_type']);
            static::assertSame('numeric(20,6)', $metadata['amount']['physical_type']);
            static::assertSame('2026-10-05 12:34:56.123', $row['created_at']);
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM pg_catalog.pg_collation WHERE collname = \'sw_type_ci\' AND collnamespace = current_schema()::regnamespace AND collversion IS NOT NULL'));
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('sw_schema_scalar', ['id' => Uuid::randomBytes(), 'name' => 'CAFE'], ['id' => ParameterType::BINARY]);
    }

    public function testDefaultsNullabilityNumericPrecisionAndAddedColumnsReallyChange(): void
    {
        $table = $this->schema->prepareTable($this->scalarTable());
        foreach ($this->schema->createTableQueries($table) as $query) {
            $this->connection->executeStatement($query);
        }
        $id = Uuid::randomBytes();
        $this->connection->insert('sw_schema_scalar', ['id' => $id, 'name' => 'before', 'amount' => '12345.123456'], ['id' => ParameterType::BINARY]);
        $updated = $this->scalarTable();
        $updated->getColumn('counter')->setDefault(9);
        $updated->getColumn('name')->setNotnull(false);
        $updated->getColumn('amount')->setPrecision(24)->setScale(8);
        $updated->addColumn('note', Types::STRING, ['length' => 255, 'default' => 'added']);
        $updated = $this->schema->prepareTable($updated);
        $queries = $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated);
        static::assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
        $row = $this->connection->fetchAssociative('SELECT counter, note, amount FROM sw_schema_scalar');
        static::assertIsArray($row);
        static::assertSame(7, (int) $row['counter']);
        static::assertSame('added', $row['note']);
        static::assertSame('12345.12345600', $row['amount']);
        $this->connection->insert('sw_schema_scalar', ['id' => Uuid::randomBytes(), 'name' => null], ['id' => ParameterType::BINARY]);
        static::assertSame(9, (int) $this->connection->fetchOne('SELECT counter FROM sw_schema_scalar WHERE name IS NULL'));
    }

    public function testManagedChecksRejectInvalidValuesAndAreRemovedOnTypeChange(): void
    {
        $table = $this->schema->prepareTable($this->scalarTable());
        foreach ($this->schema->createTableQueries($table) as $query) {
            $this->connection->executeStatement($query);
        }
        if ($this->schema->isPostgreSQL()) {
            foreach ([['id' => 'short'], ['enabled' => 2], ['counter' => '-1'], ['counter' => '4294967296']] as $invalid) {
                try {
                    $this->connection->insert('sw_schema_scalar', $invalid + ['id' => Uuid::randomBytes(), 'name' => 'invalid'], ['id' => ParameterType::BINARY]);
                    static::fail('A physical type check must reject the value');
                } catch (DriverException) {
                    static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_scalar'));
                }
            }
        }
        $updated = $this->scalarTable();
        $updated->getColumn('enabled')->setType(Type::getType(Types::INTEGER));
        $updated = $this->schema->prepareTable($updated);
        foreach ($this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['id' => Uuid::randomBytes(), 'name' => 'accepted', 'enabled' => 2], ['id' => ParameterType::BINARY]);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT enabled FROM sw_schema_scalar'));
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
    }

    public function testGeneratedExpressionAndTimestampPrecisionReallyChange(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('value', Types::INTEGER);
        $table->addColumn('calculated', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('occurred_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        if ($this->schema->isPostgreSQL()) {
            $table->addOption('shopware_generated', ['calculated' => ['expression' => 'value + value * 2', 'stored' => true]]);
        } else {
            $table->getColumn('calculated')->setColumnDefinition('INT GENERATED ALWAYS AS (value + value * 2) STORED');
        }
        $table = $this->schema->prepareTable($table);
        foreach ($this->schema->createTableQueries($table) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['value' => 3, 'occurred_at' => '2026-10-05 12:34:56.123']);
        static::assertSame(9, (int) $this->connection->fetchOne('SELECT calculated FROM sw_schema_scalar'));
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $table));
        if ($this->schema->isPostgreSQL()) {
            $updated = new Table('sw_schema_scalar');
            $updated->addColumn('value', Types::INTEGER);
            $updated->addColumn('calculated', Types::INTEGER, ['notnull' => false]);
            $updated->addColumn('occurred_at', Types::DATETIME_MUTABLE, ['notnull' => false, 'precision' => 6]);
            $updated->addOption('shopware_generated', ['calculated' => ['expression' => '(value + value) * 3', 'stored' => true]]);
            $updated = $this->schema->prepareTable($updated);
            $queries = $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated);
            static::assertCount(2, $queries);
            foreach ($queries as $query) {
                $this->connection->executeStatement($query);
            }
            static::assertSame(18, (int) $this->connection->fetchOne('SELECT calculated FROM sw_schema_scalar'));
            static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
            $this->connection->executeStatement('UPDATE sw_schema_scalar SET occurred_at = \'2026-10-05 12:34:56.123456\'');
            static::assertSame('2026-10-05 12:34:56.123456', $this->connection->fetchOne('SELECT occurred_at FROM sw_schema_scalar'));
        }
    }

    public function testManagedCheckExpressionReallyChangesAndUuidCanBecomeBlob(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('id', Types::BINARY, ['length' => 16, 'fixed' => true]);
        $table->addColumn('value', Types::INTEGER);
        $table->addOption('shopware_checks', ['chk_sw_positive' => 'value > 0']);
        $prepared = $this->schema->prepareTable($table);
        foreach ($this->schema->createTableQueries($prepared) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['id' => Uuid::randomBytes(), 'value' => 1], ['id' => ParameterType::BINARY]);
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $prepared));
        $table->getColumn('id')->setType(Type::getType(Types::BLOB))->setLength(null)->setFixed(false);
        $table->addOption('shopware_checks', ['chk_sw_positive' => 'value >= 0']);
        $updated = $this->schema->prepareTable($table);
        foreach ($this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['id' => 'short', 'value' => 0], ['id' => ParameterType::BINARY]);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_scalar'));
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
        if ($this->schema->isPostgreSQL()) {
            static::assertSame(Types::BLOB, TableHelper::getColumnOfTable($this->connection, 'sw_schema_scalar', 'id')->type);
            $this->expectException(DriverException::class);
            $this->connection->executeStatement('UPDATE sw_schema_scalar SET value = -1');
        }
    }

    public function testLongForeignKeyNamesAndRepeatedIndexNamesDoNotCollide(): void
    {
        $parent = new Table('sw_schema_scalar');
        $parent->addColumn('id', Types::INTEGER);
        $parent->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        foreach ($this->schema->createTableQueries($this->schema->prepareTable($parent)) as $query) {
            $this->connection->executeStatement($query);
        }
        $fkNames = [];
        foreach (['sw_schema_child', 'sw_schema_other'] as $tableName) {
            $table = new Table($tableName);
            $table->addColumn('parent_id', Types::INTEGER);
            $table->addColumn('other_id', Types::INTEGER, ['notnull' => false]);
            $prefix = $this->schema->isPostgreSQL() ? 'fk_ce_' . str_repeat('long_', 14) : 'fk_ce_' . $tableName;
            $table->addForeignKeyConstraint('sw_schema_scalar', ['parent_id'], ['id'], ['onDelete' => 'CASCADE'], $prefix . '_first');
            $table->addForeignKeyConstraint('sw_schema_scalar', ['other_id'], ['id'], ['onDelete' => 'SET NULL'], $prefix . '_second');
            $table->addIndex(['other_id'], 'shared_index');
            $prepared = $this->schema->prepareTable($table);
            foreach ($this->schema->createTableQueries($prepared) as $query) {
                $this->connection->executeStatement($query);
            }
            $actual = $this->connection->createSchemaManager()->introspectTableByUnquotedName($tableName);
            static::assertSame([], $this->schema->alterTableQueries($actual, $prepared));
            foreach ($actual->getForeignKeys() as $fk) {
                $name = $fk->getObjectName()?->getIdentifier()->getValue();
                static::assertNotNull($name);
                static::assertLessThanOrEqual($this->schema->isPostgreSQL() ? 63 : 64, \strlen($name));
                static::assertStringStartsWith('fk_ce_', $name);
                $fkNames[] = $name;
            }
        }
        static::assertCount($this->schema->isPostgreSQL() ? 2 : 4, array_unique($fkNames));
        $this->connection->insert('sw_schema_scalar', ['id' => 1]);
        $this->connection->insert('sw_schema_child', ['parent_id' => 1, 'other_id' => 1]);
        $this->connection->executeStatement('DELETE FROM sw_schema_scalar');
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_child'));
    }

    public function testPostgreSqlQuotedNamesRetainTheirSpelling(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('`Mixed`', Types::INTEGER);
        $table->addColumn('`select`', Types::STRING, ['length' => 255]);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setQuotedColumnNames('Mixed')->create());
        $table->addUniqueIndex(['`select`'], 'unique_select');
        $prepared = $this->schema->prepareTable($table);
        foreach ($this->schema->createTableQueries($prepared) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert($prepared->getObjectName()->toSQL($this->connection->getDatabasePlatform()), [$this->connection->getDatabasePlatform()->quoteSingleIdentifier('Mixed') => 1, $this->connection->getDatabasePlatform()->quoteSingleIdentifier('select') => 'retained']);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('Mixed') . ' FROM sw_schema_scalar'));
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $prepared));
    }

    public function testIdentifierLengthKeepsThePlatformBoundary(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn(str_repeat('x', 64), Types::INTEGER);
        if ($this->schema->isPostgreSQL()) {
            $this->expectExceptionObject(DatabaseDialectException::invalidOperation('PostgreSQL schema identifiers must contain 1 to 63 bytes: ' . str_repeat('x', 64)));
            $this->schema->prepareTable($table);
        } else {
            foreach ($this->schema->createTableQueries($this->schema->prepareTable($table)) as $query) {
                $this->connection->executeStatement($query);
            }
            static::assertTrue(TableHelper::columnExists($this->connection, 'sw_schema_scalar', str_repeat('x', 64)));
        }
    }

    public function testBooleanDefaultsKeepTheExistingContractAndRejectInvalidPostgreSqlValues(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('enabled', Types::BOOLEAN, ['default' => 2]);
        if ($this->schema->isPostgreSQL()) {
            $this->expectExceptionObject(DatabaseDialectException::invalidOperation('Boolean schema defaults must be 0 or 1'));
            $this->schema->prepareTable($table);
        } else {
            foreach ($this->schema->createTableQueries($this->schema->prepareTable($table)) as $query) {
                $this->connection->executeStatement($query);
            }
            $this->connection->executeStatement('INSERT INTO sw_schema_scalar () VALUES ()');
            static::assertSame(2, (int) $this->connection->fetchOne('SELECT enabled FROM sw_schema_scalar'));
        }
    }

    public function testPostgreSqlDoesNotAcceptAnIncompatibleExistingCollation(): void
    {
        if (!$this->schema->isPostgreSQL()) {
            static::assertSame([], $this->schema->prerequisites());

            return;
        }
        $this->connection->executeStatement('DROP COLLATION sw_type_ci');
        $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = true)');
        $this->expectExceptionObject(DatabaseDialectException::invalidOperation('sw_type_ci must use nondeterministic ICU und-u-ks-level1 collation'));
        $this->schema->prerequisites();
    }

    public function testTimestampDefaultAndNullabilityReallyChange(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('occurred_at', Types::DATETIME_MUTABLE, ['default' => '2026-10-05 12:00:00']);
        $prepared = $this->schema->prepareTable($table);
        foreach ($this->schema->createTableQueries($prepared) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['occurred_at' => '2026-10-04 10:00:00']);
        $table->getColumn('occurred_at')->setDefault('2026-10-06 12:00:00')->setNotnull(false);
        $updated = $this->schema->prepareTable($table);
        $queries = $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated);
        static::assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
        $this->connection->executeStatement($this->schema->isPostgreSQL() ? 'INSERT INTO sw_schema_scalar DEFAULT VALUES' : 'INSERT INTO sw_schema_scalar () VALUES ()');
        $this->connection->insert('sw_schema_scalar', ['occurred_at' => null]);
        static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_scalar'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_scalar WHERE occurred_at = \'2026-10-06 12:00:00\''));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_scalar WHERE occurred_at = \'2026-10-04 10:00:00\''));
    }

    public function testGeneratedTypeChangesRetainTheExpressionAndData(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('value', Types::INTEGER);
        $table->addColumn('calculated', Types::INTEGER, ['notnull' => false]);
        if ($this->schema->isPostgreSQL()) {
            $table->addOption('shopware_generated', ['calculated' => ['expression' => 'value * 2', 'stored' => true]]);
        } else {
            $table->getColumn('calculated')->setColumnDefinition('INT GENERATED ALWAYS AS (value * 2) STORED');
        }
        foreach ($this->schema->createTableQueries($this->schema->prepareTable($table)) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['value' => 7]);
        $table->getColumn('calculated')->setType(Type::getType(Types::BIGINT));
        if (!$this->schema->isPostgreSQL()) {
            $table->getColumn('calculated')->setColumnDefinition('BIGINT GENERATED ALWAYS AS (value * 2) STORED');
        }
        $updated = $this->schema->prepareTable($table);
        $queries = $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated);
        static::assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
        static::assertSame(14, (int) $this->connection->fetchOne('SELECT calculated FROM sw_schema_scalar'));
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
        $this->connection->executeStatement('UPDATE sw_schema_scalar SET value = 8');
        static::assertSame(16, (int) $this->connection->fetchOne('SELECT calculated FROM sw_schema_scalar'));
    }

    public function testExplicitUniqueConstraintRoundTripAndColumnChange(): void
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('value', Types::INTEGER);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $table->addUniqueConstraint(['value'], 'unique_value');
        $prepared = $this->schema->prepareTable($table);
        foreach ($this->schema->createTableQueries($prepared) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['value' => 1, 'name' => 'first']);
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $prepared));
        $base = $this->connection->createSchemaManager()->introspectSchema();
        static::assertSame([], $this->schema->alterSchemaQueries($base, new Schema([$prepared])));
        $table->dropUniqueConstraint('unique_value');
        $table->addUniqueConstraint(['value', 'name'], 'unique_value');
        $updated = $this->schema->prepareTable($table);
        foreach ($this->schema->alterSchemaQueries($this->connection->createSchemaManager()->introspectSchema(), new Schema([$updated])) as $query) {
            $this->connection->executeStatement($query);
        }
        $this->connection->insert('sw_schema_scalar', ['value' => 1, 'name' => 'second']);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_scalar'));
        static::assertSame([], $this->schema->alterTableQueries($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_scalar'), $updated));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('sw_schema_scalar', ['value' => 1, 'name' => 'second']);
    }

    private function scalarTable(): Table
    {
        $table = new Table('sw_schema_scalar');
        $table->addColumn('id', Types::BINARY, ['length' => 16, 'fixed' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $table->addColumn('payload', Types::BLOB, ['notnull' => false]);
        $table->addColumn('enabled', Types::BOOLEAN, ['default' => 0]);
        $table->addColumn('counter', Types::INTEGER, ['unsigned' => true, 'default' => 7]);
        $table->addColumn('sequence', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('data', Types::JSON, ['notnull' => false]);
        $table->addColumn('amount', Types::DECIMAL, ['precision' => 20, 'scale' => 6, 'notnull' => false]);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $table->addUniqueIndex(['sequence'], 'unique_sequence');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');

        return $table;
    }

    private static function bytes(mixed $value): string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }
        static::assertIsString($value);

        return $value;
    }
}

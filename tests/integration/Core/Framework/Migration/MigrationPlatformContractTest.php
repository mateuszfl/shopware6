<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MakeVersionableMigrationHelper;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationDatabaseHelper;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;
use Shopware\Core\Migration\Traits\CreateMailTemplateTrait;
use Shopware\Core\Migration\Traits\EnsureThumbnailSizesTrait;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\MailSubjectUpdate;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\StateMachineMigration;
use Shopware\Core\Migration\Traits\StateMachineMigrationImporter;
use Shopware\Core\Migration\Traits\Translations;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\LegacySource\Migration1000000000Legacy as Legacy;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000001First as First;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000001FirstPg as FirstPg;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000002Second as Second;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000002SecondPg as SecondPg;

/**
 * Uses an empty disposable database; never boots the kernel or imports the historical schema.
 *
 * @internal
 */
#[Package('framework')]
class MigrationPlatformContractTest extends TestCase
{
    private Connection $connection;

    private MigrationVariantRegistry $registry;

    private MigrationCollection $collection;

    /**
     * @var list<string>
     */
    private array $ownedTables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['migration']), 'Use a separate empty contract database.');
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['migration_contract_event']));
        $update = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('update');
        $this->connection->executeStatement('CREATE TABLE migration (class VARCHAR(255) PRIMARY KEY, creation_timestamp INTEGER NOT NULL, ' . $update . ' TIMESTAMP(6) NULL, update_destructive TIMESTAMP(6) NULL, message TEXT NULL)');
        $this->ownedTables[] = 'migration';
        $this->connection->executeStatement('CREATE TABLE migration_contract_event (step VARCHAR(64) PRIMARY KEY, engine VARCHAR(32) NOT NULL)');
        $this->ownedTables[] = 'migration_contract_event';
        $this->registry = new MigrationVariantRegistry([
            [First::class, 1000000001, FirstPg::class],
            [Second::class, 1000000002, SecondPg::class],
        ]);
        $this->collection = $this->createCollection($this->registry);
        Second::$fail = false;
        Second::$typeError = false;
        Second::$commitInside = false;
    }

    protected function tearDown(): void
    {
        Second::$fail = false;
        Second::$typeError = false;
        Second::$commitInside = false;
        if ($this->ownedTables !== []) {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            foreach (array_reverse($this->ownedTables) as $table) {
                $this->connection->executeStatement('DROP TABLE ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table));
            }
        }
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    public function testDiscoverySyncRoutingHistoryAndRepeatedRuns(): void
    {
        static::assertSame([First::class, Second::class], array_keys($this->collection->getMigrationSteps()));
        $this->collection->sync();
        $this->collection->sync();
        static::assertSame(2, $this->collection->getTotalMigrationCount());
        static::assertSame([First::class], $this->collection->migrateInPlace(until: 1000000001));
        static::assertSame([Second::class], $this->collection->migrateInPlace(limit: 1));
        static::assertSame([], $this->collection->migrateInPlace());
        static::assertSame([First::class, Second::class], $this->collection->migrateDestructiveInPlace());
        static::assertSame([], $this->collection->migrateDestructiveInPlace());
        $expectedEngine = $this->isPostgreSQL() ? 'postgresql' : 'mysql';
        static::assertSame([$expectedEngine], $this->connection->fetchFirstColumn('SELECT DISTINCT engine FROM migration_contract_event'));
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        foreach ($this->connection->fetchAllAssociative('SELECT * FROM migration ORDER BY creation_timestamp') as $row) {
            static::assertNotNull($row['update']);
            static::assertNotNull($row['update_destructive']);
            static::assertNull($row['message']);
        }
        static::assertNotNull((new MigrationInfo($this->connection))->getFirstMigrationDate());
    }

    public function testAddColumnIsIdempotentOnBothPlatformsAndPreservesExistingRows(): void
    {
        /** @var non-empty-string $table */
        $table = 'migration_contract_add_column';
        $quotedTable = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table);
        $this->connection->executeStatement('CREATE TABLE ' . $quotedTable . ' (id INTEGER PRIMARY KEY)');
        $this->ownedTables[] = $table;
        $this->connection->insert($table, ['id' => 1]);
        $migration = new class {
            use AddColumnTrait;

            /**
             * @param non-empty-string $table
             */
            public function add(Connection $connection, string $table, string $column): bool
            {
                return $this->addColumn($connection, $table, $column, 'INTEGER', nullable: false, default: '0');
            }
        };

        static::assertTrue($migration->add($this->connection, $table, 'quantity'));
        static::assertFalse($migration->add($this->connection, $table, 'quantity'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT quantity FROM ' . $quotedTable . ' WHERE id = 1'));
    }

    public function testInheritanceUpdaterCreatesNullableBinaryUuidColumnWithLengthGuard(): void
    {
        $table = 'migration_contract_inheritance';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' (id INTEGER PRIMARY KEY)');
        $this->ownedTables[] = $table;
        $this->connection->insert($table, ['id' => 1]);
        $migration = new class {
            use InheritanceUpdaterTrait;

            public function update(Connection $connection, string $table): void
            {
                $this->updateInheritance($connection, $table, 'parent_id');
            }
        };
        $migration->update($this->connection, $table);

        static::assertTrue($this->connection->createSchemaManager()->introspectTableByUnquotedName($table)->hasColumn('parent_id'));
        static::assertNull($this->connection->fetchOne('SELECT parent_id FROM ' . $quote($table) . ' WHERE id = 1'));
        $validUuid = Uuid::randomBytes();
        $this->connection->update($table, ['parent_id' => $validUuid], ['id' => 1], ['parent_id' => ParameterType::BINARY]);
        static::assertSame($validUuid, MigrationDatabaseHelper::normalizeBinaryId($this->connection->fetchOne('SELECT parent_id FROM ' . $quote($table) . ' WHERE id = 1')));

        if ($this->isPostgreSQL()) {
            try {
                $this->connection->update($table, ['parent_id' => substr($validUuid, 0, 15)], ['id' => 1], ['parent_id' => ParameterType::BINARY]);
                static::fail('PostgreSQL inheritance UUID must reject values shorter than 16 bytes.');
            } catch (Exception) {
                static::assertSame($validUuid, MigrationDatabaseHelper::normalizeBinaryId($this->connection->fetchOne('SELECT parent_id FROM ' . $quote($table) . ' WHERE id = 1')));
            }
        }
    }

    public function testExecuteDdlSupportsExplicitGeneratedColumnAndTypeAlterationVariants(): void
    {
        $table = 'migration_contract_generated_column';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' (id INTEGER PRIMARY KEY, quantity INTEGER NOT NULL)');
        $this->ownedTables[] = $table;
        $this->connection->insert($table, ['id' => 1, 'quantity' => 2]);
        $migration = new /**
         * @internal
         */ class extends MigrationStep {
            public function getCreationTimestamp(): int
            {
                return 1000000012;
            }

            public function update(Connection $connection): void
            {
            }

            public function apply(Connection $connection, string $sql): void
            {
                $this->executeDdlStatement($connection, $sql);
            }
        };
        if ($this->isPostgreSQL()) {
            $migration->apply($this->connection, 'ALTER TABLE ' . $quote($table) . ' ALTER COLUMN ' . $quote('quantity') . ' TYPE BIGINT');
        } else {
            $migration->apply($this->connection, 'ALTER TABLE ' . $quote($table) . ' MODIFY ' . $quote('quantity') . ' BIGINT NOT NULL');
        }
        $migration->apply($this->connection, 'ALTER TABLE ' . $quote($table) . ' ADD COLUMN ' . $quote('quantity_plus_one') . ' BIGINT GENERATED ALWAYS AS (' . $quote('quantity') . ' + 1) STORED');

        $this->connection->executeStatement('UPDATE ' . $quote($table) . ' SET quantity = 3 WHERE id = 1');
        static::assertSame('4', (string) $this->connection->fetchOne('SELECT ' . $quote('quantity_plus_one') . ' FROM ' . $quote($table) . ' WHERE id = 1'));
        static::assertTrue($this->connection->createSchemaManager()->introspectTableByUnquotedName($table)->hasColumn('quantity_plus_one'));
        static::assertSame('3', (string) $this->connection->fetchOne('SELECT quantity FROM ' . $quote($table) . ' WHERE id = 1'));
    }

    public function testDestructiveColumnIndexAndForeignKeyDropsAreRepeatableOnNonemptyTables(): void
    {
        $parent = 'migration_contract_drop_parent';
        $child = 'migration_contract_drop_child';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($parent) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY)');
        $this->ownedTables[] = $parent;
        $this->connection->executeStatement('CREATE TABLE ' . $quote($child) . ' (id INTEGER PRIMARY KEY, parent_id ' . $idType . ' NOT NULL, removable VARCHAR(255) NULL, CONSTRAINT migration_contract_drop_fk FOREIGN KEY (parent_id) REFERENCES ' . $quote($parent) . ' (id))');
        $this->ownedTables[] = $child;
        $parentId = Uuid::randomBytes();
        $this->connection->insert($parent, ['id' => $parentId], ['id' => ParameterType::BINARY]);
        $this->connection->insert($child, ['id' => 1, 'parent_id' => $parentId, 'removable' => 'preserve row'], ['parent_id' => ParameterType::BINARY]);
        $this->connection->executeStatement('CREATE INDEX ' . $quote('migration_contract_drop_index') . ' ON ' . $quote($child) . ' (removable)');
        $migration = new /**
         * @internal
         */ class extends MigrationStep {
            public function getCreationTimestamp(): int
            {
                return 1000000011;
            }

            public function update(Connection $connection): void
            {
            }

            /**
             * @param non-empty-string $table
             */
            public function dropColumn(Connection $connection, string $table, string $column): bool
            {
                return $this->dropColumnIfExists($connection, $table, $column);
            }

            /**
             * @param non-empty-string $table
             */
            public function dropIndex(Connection $connection, string $table, string $index): bool
            {
                return $this->dropIndexIfExists($connection, $table, $index);
            }

            /**
             * @param non-empty-string $table
             */
            public function dropForeignKey(Connection $connection, string $table, string $key): bool
            {
                return $this->dropForeignKeyIfExists($connection, $table, $key);
            }
        };

        static::assertTrue($migration->dropForeignKey($this->connection, $child, 'migration_contract_drop_fk'));
        static::assertFalse($migration->dropForeignKey($this->connection, $child, 'migration_contract_drop_fk'));
        static::assertTrue($migration->dropIndex($this->connection, $child, 'migration_contract_drop_index'));
        static::assertFalse($migration->dropIndex($this->connection, $child, 'migration_contract_drop_index'));
        static::assertTrue($migration->dropColumn($this->connection, $child, 'removable'));
        static::assertFalse($migration->dropColumn($this->connection, $child, 'removable'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $quote($child)));
    }

    public function testMakeVersionableHelperBuildsAndExecutesForeignKeyDdlOnBothPlatforms(): void
    {
        $parent = 'migration_contract_versioned_parent';
        $child = 'migration_contract_versioned_child';
        $optionalChild = 'migration_contract_optional_child';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($parent) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY, scope_id ' . $idType . ' NOT NULL, UNIQUE (id, scope_id))');
        $this->ownedTables[] = $parent;
        $this->connection->executeStatement('CREATE TABLE ' . $quote($child) . ' (parent_id ' . $idType . ' NOT NULL, scope_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, PRIMARY KEY (parent_id, scope_id, language_id), CONSTRAINT migration_contract_parent_fk FOREIGN KEY (parent_id, scope_id) REFERENCES ' . $quote($parent) . ' (id, scope_id) ON DELETE CASCADE ON UPDATE CASCADE)');
        $this->ownedTables[] = $child;
        $this->connection->executeStatement('CREATE TABLE ' . $quote($optionalChild) . ' (id INTEGER PRIMARY KEY, parent_id ' . $idType . ' NULL, scope_id ' . $idType . ' NULL, CONSTRAINT migration_contract_optional_fk FOREIGN KEY (parent_id, scope_id) REFERENCES ' . $quote($parent) . ' (id, scope_id) ON DELETE SET NULL)');
        $this->ownedTables[] = $optionalChild;
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $scopeId = Uuid::randomBytes();
        $this->connection->insert($parent, ['id' => $liveVersionId, 'scope_id' => $scopeId], ['id' => ParameterType::BINARY, 'scope_id' => ParameterType::BINARY]);
        $this->connection->insert($child, ['parent_id' => $liveVersionId, 'scope_id' => $scopeId, 'language_id' => Uuid::randomBytes()], ['parent_id' => ParameterType::BINARY, 'scope_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
        $this->connection->insert($optionalChild, ['id' => 1, 'parent_id' => $liveVersionId, 'scope_id' => $scopeId], ['parent_id' => ParameterType::BINARY, 'scope_id' => ParameterType::BINARY]);

        $helper = new MakeVersionableMigrationHelper($this->connection);
        $relations = $helper->getRelationData($parent, 'id');
        static::assertCount(2, $relations);
        foreach ($helper->createSql($relations, $parent, 'version_id', Defaults::LIVE_VERSION) as $sql) {
            $this->connection->executeStatement($sql);
        }

        static::assertTrue($this->connection->createSchemaManager()->introspectTableByUnquotedName($parent)->hasColumn('version_id'));
        $schemaManager = $this->connection->createSchemaManager();
        static::assertTrue($schemaManager->introspectTableByUnquotedName($child)->hasColumn($parent . '_version_id'));
        static::assertTrue($schemaManager->introspectTableByUnquotedName($optionalChild)->hasColumn($parent . '_version_id'));
        $optionalForeignKeys = $schemaManager->introspectTableForeignKeyConstraintsByUnquotedName($optionalChild);
        static::assertCount(1, $optionalForeignKeys);
        $optionalForeignKey = reset($optionalForeignKeys);
        static::assertSame('SET NULL', $optionalForeignKey->getOnDeleteAction()->value);
        static::assertSame(['parent_id', 'scope_id', $parent . '_version_id'], array_map(static fn ($column): string => $column->getIdentifier()->getValue(), $optionalForeignKey->getReferencingColumnNames()));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $quote($child)));
        static::assertSame($this->isPostgreSQL() ? 1 : 0, (int) $this->connection->fetchOne('SELECT CASE WHEN ' . $quote($parent . '_version_id') . ' IS NOT NULL THEN 1 ELSE 0 END FROM ' . $quote($optionalChild) . ' WHERE id = 1'));
        $childPrimaryKey = $schemaManager->introspectTablePrimaryKeyConstraint(OptionallyQualifiedName::unquoted($child));
        static::assertNotNull($childPrimaryKey);
        static::assertSame(['parent_id', 'scope_id', 'language_id', $parent . '_version_id'], array_map(static fn ($column): string => $column->getIdentifier()->getValue(), $childPrimaryKey->getColumnNames()));
        $this->connection->delete($parent, ['id' => $liveVersionId], ['id' => ParameterType::BINARY]);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $quote($child)));
        static::assertSame($this->isPostgreSQL() ? 1 : 0, (int) $this->connection->fetchOne('SELECT CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END FROM ' . $quote($optionalChild) . ' WHERE id = 1'));
    }

    public function testMakeVersionableHelperMatchesForeignKeysByLocalColumnsAndPreservesDeleteActions(): void
    {
        $parent = 'migration_contract_fk_match_parent';
        $child = 'migration_contract_fk_match_child';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($parent) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY)');
        $this->ownedTables[] = $parent;
        $this->connection->executeStatement('CREATE TABLE ' . $quote($child) . ' (id INTEGER PRIMARY KEY, cascade_parent_id ' . $idType . ' NOT NULL, nullable_parent_id ' . $idType . ' NULL, CONSTRAINT migration_contract_cascade_fk FOREIGN KEY (cascade_parent_id) REFERENCES ' . $quote($parent) . ' (id) ON DELETE CASCADE, CONSTRAINT migration_contract_set_null_fk FOREIGN KEY (nullable_parent_id) REFERENCES ' . $quote($parent) . ' (id) ON DELETE SET NULL)');
        $this->ownedTables[] = $child;
        $parentId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->connection->insert($parent, ['id' => $parentId], ['id' => ParameterType::BINARY]);
        $this->connection->insert($child, ['id' => 1, 'cascade_parent_id' => $parentId, 'nullable_parent_id' => $parentId], ['cascade_parent_id' => ParameterType::BINARY, 'nullable_parent_id' => ParameterType::BINARY]);

        $helper = new MakeVersionableMigrationHelper($this->connection);
        $relations = $helper->getRelationData($parent, 'id');
        static::assertCount(2, $relations);
        foreach ($helper->createSql($relations, $parent, 'version_id', Defaults::LIVE_VERSION) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $keys = $this->connection->createSchemaManager()->introspectTableForeignKeyConstraintsByUnquotedName($child);
        $actionsByColumn = [];
        foreach ($keys as $key) {
            $column = $key->getReferencingColumnNames()[0]->getIdentifier()->getValue();
            $actionsByColumn[$column] = $key->getOnDeleteAction()->value;
        }
        static::assertSame('CASCADE', $actionsByColumn['cascade_parent_id']);
        static::assertSame('SET NULL', $actionsByColumn['nullable_parent_id']);
    }

    public function testPostgreSQLTriggerFunctionLifecycleOnlyDropsOwnedFunction(): void
    {
        $this->requirePostgreSQL();
        $table = 'migration_contract_trigger_target';
        $function = 'migration_contract_trigger_function';
        $trigger = 'migration_contract_trigger';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' (id INTEGER PRIMARY KEY, touched BOOLEAN NOT NULL DEFAULT FALSE)');
        $this->ownedTables[] = $table;
        $migration = new /**
         * @internal
         */ class extends MigrationStep {
            public function getCreationTimestamp(): int
            {
                return 1000000009;
            }

            public function update(Connection $connection): void
            {
            }

            public function createFunction(Connection $connection, string $name, string $body): void
            {
                $this->createTriggerFunction($connection, $name, $body);
            }

            public function dropFunction(Connection $connection, string $name): bool
            {
                return $this->dropTriggerFunctionIfExists($connection, $name);
            }

            public function removeNamedTrigger(Connection $connection, string $name): void
            {
                $this->removeTrigger($connection, $name);
            }
        };
        $previousServerValue = $_SERVER['BLUE_GREEN_DEPLOYMENT'] ?? null;
        $_SERVER['BLUE_GREEN_DEPLOYMENT'] = '1';

        try {
            $migration->createFunction($this->connection, $function, 'BEGIN NEW.touched := TRUE; RETURN NEW; END;');
            $this->connection->executeStatement('CREATE TRIGGER ' . $quote($trigger) . ' BEFORE INSERT ON ' . $quote($table) . ' FOR EACH ROW EXECUTE FUNCTION ' . $quote($function) . '()');
            try {
                $migration->createFunction($this->connection, $function, 'BEGIN NEW.touched := FALSE; RETURN NEW; END;');
                static::fail('A repeated function creation must not replace the existing migration function.');
            } catch (Exception) {
                // Existing function remains intact after the transactional duplicate failure.
            }
            $this->connection->executeStatement('CREATE FUNCTION migration_contract_unowned_trigger_function() RETURNS trigger LANGUAGE plpgsql AS $body$ BEGIN RETURN NEW; END; $body$');
            $this->connection->insert($table, ['id' => 1]);
            static::assertTrue((bool) $this->connection->fetchOne('SELECT touched FROM ' . $quote($table) . ' WHERE id = 1'));
            $migration->removeNamedTrigger($this->connection, $trigger);
            static::assertTrue($migration->dropFunction($this->connection, $function));
            static::assertFalse($migration->dropFunction($this->connection, $function));
            static::assertFalse($migration->dropFunction($this->connection, 'migration_contract_unowned_trigger_function'));
            $this->connection->executeStatement('DROP FUNCTION migration_contract_unowned_trigger_function()');
        } finally {
            if ($previousServerValue === null) {
                unset($_SERVER['BLUE_GREEN_DEPLOYMENT']);
            } else {
                $_SERVER['BLUE_GREEN_DEPLOYMENT'] = $previousServerValue;
            }
        }
    }

    public function testTranslationSeedPreservesReplaceDeleteCascadeAndBinaryUuidOnBothPlatforms(): void
    {
        $localeTable = 'locale';
        $languageTable = 'language';
        $translationTable = 'migration_contract_translation';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($localeTable) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY, code VARCHAR(16) NOT NULL)');
        $this->ownedTables[] = $localeTable;
        $this->connection->executeStatement('CREATE TABLE ' . $quote($languageTable) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY, locale_id ' . $idType . ' NOT NULL)');
        $this->ownedTables[] = $languageTable;
        $this->connection->executeStatement('CREATE TABLE ' . $quote($translationTable) . ' (unit_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, label VARCHAR(64) NOT NULL, created_at TIMESTAMP(3) NOT NULL, PRIMARY KEY (unit_id, language_id))');
        $this->ownedTables[] = $translationTable;
        $referenceTable = 'migration_contract_translation_reference';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($referenceTable) . ' (unit_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, FOREIGN KEY (unit_id, language_id) REFERENCES ' . $quote($translationTable) . ' (unit_id, language_id) ON DELETE CASCADE)');
        $this->ownedTables[] = $referenceTable;

        $deLocale = Uuid::randomBytes();
        $enLocale = Uuid::randomBytes();
        $deLanguage = Uuid::randomBytes();
        $enLanguage = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $translationId = Uuid::randomBytes();
        foreach ([[$localeTable, ['id' => $deLocale, 'code' => 'de-DE']], [$localeTable, ['id' => $enLocale, 'code' => 'en-GB']]] as [$table, $row]) {
            $this->connection->insert($table, $row, ['id' => ParameterType::BINARY]);
        }
        $this->connection->insert($languageTable, ['id' => $deLanguage, 'locale_id' => $deLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $this->connection->insert($languageTable, ['id' => $enLanguage, 'locale_id' => $enLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $migration = new class {
            use ImportTranslationsTrait;

            public function import(Connection $connection, string $id, string $english, string $german): void
            {
                $this->importTranslation('migration_contract_translation', new Translations(['unit_id' => $id, 'label' => $german], ['unit_id' => $id, 'label' => $english]), $connection);
            }
        };
        $migration->import($this->connection, $translationId, 'English', 'German');
        $this->connection->insert($referenceTable, ['unit_id' => $translationId, 'language_id' => $deLanguage], ['unit_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
        $migration->import($this->connection, $translationId, 'Updated English', 'Updated German');

        $rows = $this->connection->fetchAllAssociative('SELECT label FROM ' . $quote($translationTable));
        static::assertCount(2, $rows);
        $labels = array_column($rows, 'label');
        sort($labels, \SORT_STRING);
        static::assertSame(['Updated English', 'Updated German'], $labels);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $quote($referenceTable)));
    }

    public function testIndexerPrivilegeSeedUpdatesOnlyMatchingRolesWithBinaryIdBinding(): void
    {
        $table = 'acl_role';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $jsonType = $this->isPostgreSQL() ? 'JSONB' : 'JSON';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY, privileges ' . $jsonType . ' NOT NULL, updated_at TIMESTAMP(3) NULL)');
        $this->ownedTables[] = $table;
        $matchingId = Uuid::randomBytes();
        $unmatchedId = Uuid::randomBytes();
        $this->connection->insert($table, ['id' => $matchingId, 'privileges' => '["old"]'], ['id' => ParameterType::BINARY]);
        $this->connection->insert($table, ['id' => $unmatchedId, 'privileges' => '[]'], ['id' => ParameterType::BINARY]);
        $migration = new /**
         * @internal
         */ class extends MigrationStep {
            public function getCreationTimestamp(): int
            {
                return 1000000010;
            }

            public function update(Connection $connection): void
            {
            }

            public function register(Connection $connection): void
            {
                $this->addAdditionalPrivileges($connection, ['old' => ['new']]);
            }
        };

        $migration->register($this->connection);
        $idExpression = $this->isPostgreSQL() ? 'encode(id, \'hex\')' : 'LOWER(HEX(id))';
        $rows = $this->connection->fetchAllAssociative('SELECT ' . $idExpression . ' AS id, privileges, updated_at FROM ' . $quote($table));
        static::assertCount(2, $rows);
        foreach ($rows as $row) {
            if ($row['id'] === bin2hex($matchingId)) {
                $privileges = json_decode((string) $row['privileges'], true, 512, \JSON_THROW_ON_ERROR);
                sort($privileges);
                static::assertSame(['new', 'old'], $privileges, json_encode($rows, \JSON_THROW_ON_ERROR));
                static::assertNotNull($row['updated_at']);

                continue;
            }

            static::assertSame('[]', (string) $row['privileges']);
            static::assertNull($row['updated_at']);
        }
    }

    public function testIndexerQueueMergesOptionsFinishesItemsAndDeletesEmptyQueue(): void
    {
        $table = 'system_config';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $jsonType = $this->isPostgreSQL() ? 'JSONB' : 'JSON';
        $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY, configuration_key VARCHAR(255) NOT NULL, configuration_value ' . $jsonType . ' NOT NULL, sales_channel_id ' . $idType . ' NULL, created_at TIMESTAMP(3) NOT NULL, updated_at TIMESTAMP(3) NULL)');
        $this->ownedTables[] = $table;

        IndexerQueuer::registerIndexer($this->connection, 'contract.alpha', ['first']);
        IndexerQueuer::registerIndexer($this->connection, 'contract.alpha', ['second']);
        IndexerQueuer::registerIndexer($this->connection, 'contract.beta');
        $queuer = new IndexerQueuer($this->connection);
        static::assertEquals(['contract.alpha' => ['first', 'second'], 'contract.beta' => []], $queuer->getIndexers());

        $queuer->finishIndexer(['contract.alpha']);
        static::assertEquals(['contract.beta' => []], $queuer->getIndexers());
        $queuer->finishIndexer(['contract.beta']);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $quote($table)));
    }

    public function testMailUpdatePreservesCustomizedTranslationsAndBindsBinaryIds(): void
    {
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $booleanType = $this->isPostgreSQL() ? 'SMALLINT CHECK (system_default IN (0, 1))' : 'TINYINT(1)';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $definitions = [
            'locale' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, code VARCHAR(16) NOT NULL)',
            'language' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, locale_id ' . $idType . ' NOT NULL)',
            'mail_template_type' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, technical_name VARCHAR(64) NOT NULL)',
            'mail_template' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, mail_template_type_id ' . $idType . ' NOT NULL, system_default ' . $booleanType . ' NOT NULL, updated_at TIMESTAMP(3) NULL)',
            'mail_template_translation' => '(mail_template_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, subject VARCHAR(128) NULL, content_html TEXT NULL, content_plain TEXT NULL, updated_at TIMESTAMP(3) NULL, PRIMARY KEY (mail_template_id, language_id))',
        ];
        foreach ($definitions as $table => $definition) {
            $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' ' . $definition);
            $this->ownedTables[] = $table;
        }

        $deLocale = Uuid::randomBytes();
        $enLocale = Uuid::randomBytes();
        $deLanguage = Uuid::randomBytes();
        $enLanguage = Uuid::randomBytes();
        $systemLanguage = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->connection->insert('locale', ['id' => $deLocale, 'code' => 'de-DE'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('locale', ['id' => $enLocale, 'code' => 'en-GB'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $deLanguage, 'locale_id' => $deLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $enLanguage, 'locale_id' => $enLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $systemLanguage, 'locale_id' => $enLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);

        $typeId = Uuid::randomBytes();
        $templateId = Uuid::randomBytes();
        $this->connection->insert('mail_template_type', ['id' => $typeId, 'technical_name' => 'contract_mail'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('mail_template', ['id' => $templateId, 'mail_template_type_id' => $typeId, 'system_default' => 1], ['id' => ParameterType::BINARY, 'mail_template_type_id' => ParameterType::BINARY]);
        foreach ([[$systemLanguage, null], [$deLanguage, null], [$enLanguage, '2026-01-01 00:00:00.000']] as [$languageId, $updatedAt]) {
            $this->connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $languageId,
                'subject' => 'Old subject',
                'content_html' => 'Old html',
                'content_plain' => 'Old plain',
                'updated_at' => $updatedAt,
            ], ['mail_template_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
        }

        $migration = new class {
            use UpdateMailTrait;

            public function update(Connection $connection): void
            {
                $this->updateMail(new MailUpdate('contract_mail', 'New English plain', 'New English html', 'New German plain', 'New German html'), $connection);
                $this->updateMailSubject(new MailSubjectUpdate('contract_mail', 'New English subject', 'New German subject'), $connection);
            }
        };
        $migration->update($this->connection);
        $rows = $this->connection->fetchAllAssociative('SELECT language_id, subject, content_html, content_plain, updated_at FROM mail_template_translation');
        $byLanguage = [];
        foreach ($rows as $row) {
            $byLanguage[Uuid::fromBytesToHex($row['language_id'])] = $row;
        }
        static::assertSame('New English subject', $byLanguage[Defaults::LANGUAGE_SYSTEM]['subject']);
        static::assertSame('New English html', $byLanguage[Defaults::LANGUAGE_SYSTEM]['content_html']);
        static::assertSame('New German subject', $byLanguage[Uuid::fromBytesToHex($deLanguage)]['subject']);
        static::assertSame('New German plain', $byLanguage[Uuid::fromBytesToHex($deLanguage)]['content_plain']);
        static::assertSame('Old subject', $byLanguage[Uuid::fromBytesToHex($enLanguage)]['subject']);
        static::assertSame('Old html', $byLanguage[Uuid::fromBytesToHex($enLanguage)]['content_html']);
        static::assertNotNull($byLanguage[Uuid::fromBytesToHex($enLanguage)]['updated_at']);
    }

    public function testEnsureThumbnailSizesReusesExistingRowsAndBindsGeneratedIds(): void
    {
        $table = 'media_thumbnail_size';
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' (id ' . $idType . ' NOT NULL PRIMARY KEY, width INTEGER NOT NULL, height INTEGER NOT NULL, created_at TIMESTAMP(3) NOT NULL)');
        $this->ownedTables[] = $table;
        $migration = new class {
            use EnsureThumbnailSizesTrait;

            /** @param list<array{width: int, height: int}> $sizes
             * @return list<string>
             */
            public function ensure(array $sizes, Connection $connection): array
            {
                return $this->ensureThumbnailSizes($sizes, $connection);
            }
        };

        $first = $migration->ensure([['width' => 320, 'height' => 200]], $this->connection);
        $reusedInSameCall = $migration->ensure([['width' => 320, 'height' => 200], ['width' => 320, 'height' => 200]], $this->connection);
        $second = $migration->ensure([['width' => 320, 'height' => 200]], $this->connection);
        static::assertSame($first, $second);
        static::assertSame([$first[0], $first[0]], $reusedInSameCall);
        static::assertCount(1, $first);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $quote($table)));
    }

    public function testCreateMailTemplateSeedsLanguageRowsIdempotentlyWithByteaIds(): void
    {
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $definitions = [
            'locale' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, code VARCHAR(16) NOT NULL)',
            'language' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, locale_id ' . $idType . ' NOT NULL, created_at TIMESTAMP(3) NOT NULL)',
            'mail_template_type' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, technical_name VARCHAR(64) NOT NULL, available_entities JSON NULL, created_at TIMESTAMP(3) NOT NULL)',
            'mail_template_type_translation' => '(mail_template_type_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, name VARCHAR(128) NOT NULL, created_at TIMESTAMP(3) NOT NULL)',
            'mail_template' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, mail_template_type_id ' . $idType . ' NOT NULL, system_default ' . ($this->isPostgreSQL() ? 'SMALLINT CHECK (system_default IN (0, 1))' : 'TINYINT(1)') . ' NOT NULL, created_at TIMESTAMP(3) NOT NULL)',
            'mail_template_translation' => '(mail_template_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, sender_name VARCHAR(128) NOT NULL, subject VARCHAR(128) NOT NULL, description VARCHAR(255) NOT NULL, content_html TEXT NOT NULL, content_plain TEXT NOT NULL, created_at TIMESTAMP(3) NOT NULL)',
        ];
        foreach ($definitions as $table => $definition) {
            $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' ' . $definition);
            $this->ownedTables[] = $table;
        }
        $deLocale = Uuid::randomBytes();
        $enLocale = Uuid::randomBytes();
        $deLanguage = Uuid::randomBytes();
        $systemLanguage = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->connection->insert('locale', ['id' => $deLocale, 'code' => 'de-DE'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('locale', ['id' => $enLocale, 'code' => 'en-GB'], ['id' => ParameterType::BINARY]);
        $createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $this->connection->insert('language', ['id' => $deLanguage, 'locale_id' => $deLocale, 'created_at' => $createdAt], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $systemLanguage, 'locale_id' => $enLocale, 'created_at' => $createdAt], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $migration = new class {
            use CreateMailTemplateTrait;

            public function create(Connection $connection): void
            {
                $this->createMail($connection, new MailTemplateTypeCreateStruct('contract_mail', 'English type', 'German type'), new MailTemplateCreateStruct('contact_form', 'English subject', 'German subject', 'English description', 'German description', 'Shop', 'Shop'));
            }

            public function createWithFlag(Connection $connection, bool $systemDefault): void
            {
                $this->createMail($connection, new MailTemplateTypeCreateStruct('contract_mail_non_default', 'English type', 'German type'), new MailTemplateCreateStruct('contact_form', 'English subject', 'German subject', 'English description', 'German description', 'Shop', 'Shop', $systemDefault));
            }
        };
        $migration->create($this->connection);
        $migration->create($this->connection);
        $migration->createWithFlag($this->connection, false);

        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mail_template_type'));
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mail_template_type_translation'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mail_template'));
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mail_template_translation'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT system_default FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'contract_mail_non_default\')'));
    }

    public function testStateMachineMigrationImporterSeedsAndRepeatsBinaryRelations(): void
    {
        $idType = $this->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $quote = $this->connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $definitions = [
            'locale' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, code VARCHAR(16) NOT NULL)',
            'language' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, locale_id ' . $idType . ' NOT NULL)',
            'state_machine' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, technical_name VARCHAR(64) NOT NULL UNIQUE, initial_state_id ' . $idType . ' NULL, created_at TIMESTAMP(3) NOT NULL)',
            'state_machine_translation' => '(state_machine_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, name VARCHAR(128) NOT NULL, created_at TIMESTAMP(3) NOT NULL, PRIMARY KEY (state_machine_id, language_id))',
            'state_machine_state' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, state_machine_id ' . $idType . ' NOT NULL, technical_name VARCHAR(64) NOT NULL, created_at TIMESTAMP(3) NOT NULL, UNIQUE (state_machine_id, technical_name))',
            'state_machine_state_translation' => '(state_machine_state_id ' . $idType . ' NOT NULL, language_id ' . $idType . ' NOT NULL, name VARCHAR(128) NOT NULL, created_at TIMESTAMP(3) NOT NULL, PRIMARY KEY (state_machine_state_id, language_id))',
            'state_machine_transition' => '(id ' . $idType . ' NOT NULL PRIMARY KEY, state_machine_id ' . $idType . ' NOT NULL, action_name VARCHAR(64) NOT NULL, from_state_id ' . $idType . ' NOT NULL, to_state_id ' . $idType . ' NOT NULL, created_at TIMESTAMP(3) NOT NULL, UNIQUE (state_machine_id, action_name, from_state_id, to_state_id))',
        ];
        foreach ($definitions as $table => $definition) {
            $this->connection->executeStatement('CREATE TABLE ' . $quote($table) . ' ' . $definition);
            $this->ownedTables[] = $table;
        }
        $deLocale = Uuid::randomBytes();
        $enLocale = Uuid::randomBytes();
        $deLanguage = Uuid::randomBytes();
        $systemLanguage = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->connection->insert('locale', ['id' => $deLocale, 'code' => 'de-DE'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('locale', ['id' => $enLocale, 'code' => 'en-GB'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $deLanguage, 'locale_id' => $deLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $systemLanguage, 'locale_id' => $enLocale], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);

        $definition = new StateMachineMigration(
            'contract.machine',
            'Maschine',
            'Machine',
            [StateMachineMigration::state('open', 'Offen', 'Open'), StateMachineMigration::state('closed', 'Geschlossen', 'Closed')],
            [StateMachineMigration::transition('close', 'open', 'closed')],
            'open'
        );
        $importer = new StateMachineMigrationImporter($this->connection);
        $importer->importStateMachine($definition);
        $importer->importStateMachine($definition);

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM state_machine'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM state_machine_state'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM state_machine_transition'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM state_machine_translation'));
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM state_machine_state_translation'));
    }

    public function testNamespaceMatchingDoesNotExecuteNestedOrSiblingSources(): void
    {
        $this->collection->sync();
        foreach ([First::class . '\\Nested', str_replace('PlatformSource', 'PlatformSourceOther', First::class)] as $class) {
            $this->connection->insert('migration', ['class' => $class, 'creation_timestamp' => 1000000000]);
        }
        static::assertSame(2, $this->collection->getTotalMigrationCount());
        static::assertSame([First::class, Second::class], $this->collection->getExecutableMigrations());
        static::assertSame([First::class, Second::class], $this->collection->migrateInPlace());
    }

    public function testMissingVariantIsRejectedBeforeFirstSelectedStepRuns(): void
    {
        $this->requirePostgreSQL();
        // Sync with the complete registry: the counterparts must never enter history.
        $this->collection->sync();
        $incomplete = $this->createCollection(new MigrationVariantRegistry([[First::class, 1000000001, FirstPg::class]]));
        try {
            $incomplete->migrateInPlace();
            static::fail('Missing variant must stop the entire selected run.');
        } catch (\RuntimeException $e) {
            static::assertStringContainsString('No PostgreSQL migration variant for ' . Second::class, $e->getMessage());
        }
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        static::assertSame([First::class, Second::class], $this->collection->getExecutableMigrations());
        // The limit constrains preflight as well as execution.
        static::assertSame([First::class], $incomplete->migrateInPlace(limit: 1));
    }

    public function testHistoryTimestampMismatchStopsRunBeforeExecution(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        $this->connection->update('migration', ['creation_timestamp' => 1000000003], ['class' => Second::class]);
        try {
            $this->collection->migrateInPlace();
            static::fail('History identity must match the registered timestamp.');
        } catch (\RuntimeException $e) {
            static::assertStringContainsString('timestamp mismatch', $e->getMessage());
        }
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
    }

    public function testSqlFailureRecordsErrorAndResumesAfterRollback(): void
    {
        $this->collection->sync();
        Second::$fail = true;
        try {
            $this->collection->migrateInPlace();
            static::fail('The synthetic migration must fail.');
        } catch (Exception $e) {
            static::assertStringContainsString('migration_contract_missing_table', $e->getMessage());
        }
        static::assertFalse($this->connection->isTransactionActive());
        static::assertSame([Second::class], $this->collection->getExecutableMigrations());
        $failed = $this->connection->fetchAssociative('SELECT * FROM migration WHERE class = ?', [Second::class]);
        static::assertIsArray($failed);
        static::assertNull($failed['update']);
        static::assertStringContainsString('migration_contract_missing_table', $failed['message']);
        static::assertSame($this->isPostgreSQL() ? 1 : 2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        Second::$fail = false;
        static::assertSame([Second::class], $this->collection->migrateInPlace());
        static::assertNull($this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
        static::assertSame([], $this->collection->migrateInPlace());
    }

    public function testDestructiveFailureCanResumeWithoutRepeatingUpdate(): void
    {
        $this->collection->sync();
        $this->collection->migrateInPlace();
        Second::$fail = true;
        try {
            $this->collection->migrateDestructiveInPlace();
            static::fail('The destructive step must fail.');
        } catch (Exception) {
        }
        static::assertSame([Second::class], $this->collection->getExecutableDestructiveMigrations());
        static::assertSame([], $this->collection->getExecutableMigrations());
        static::assertFalse($this->connection->isTransactionActive());
        Second::$fail = false;
        static::assertSame([Second::class], $this->collection->migrateDestructiveInPlace());
        static::assertNull($this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
    }

    public function testThrowableRollsBackAndPreservesUtf8Diagnostic(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        Second::$typeError = true;
        try {
            $this->collection->migrateInPlace();
            static::fail('The synthetic TypeError must be propagated.');
        } catch (\TypeError $e) {
            static::assertSame('migration contract type error: żółć', $e->getMessage());
        }
        static::assertSame('migration contract type error: żółć', $this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
        static::assertSame(['first'], $this->connection->fetchFirstColumn('SELECT step FROM migration_contract_event'));
        Second::$typeError = false;
        Second::$commitInside = false;
        static::assertSame([Second::class], $this->collection->migrateInPlace());
    }

    public function testCallerTransactionIsNeverRolledBack(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        $this->connection->beginTransaction();
        $this->connection->insert('migration_contract_event', ['step' => 'caller', 'engine' => 'caller']);
        try {
            $this->collection->migrateInPlace();
            static::fail('Outer transactions must be rejected before execution.');
        } catch (MigrationException $e) {
            static::assertStringContainsString('without an active transaction', $e->getMessage());
        }
        static::assertTrue($this->connection->isTransactionActive());
        static::assertSame(['caller'], $this->connection->fetchFirstColumn('SELECT step FROM migration_contract_event'));
        $this->connection->rollBack();
    }

    public function testPluginSourceUsesSameRegistryAndHistoryAcrossLifecycleCollections(): void
    {
        $source = $this->source('ExamplePlugin');
        $loader = new MigrationCollectionLoader($this->connection, new MigrationRuntime($this->connection, new NullLogger(), $this->registry), new NullLogger());
        $loader->addSource($source);
        $install = $loader->collect('ExamplePlugin');
        $install->sync();
        static::assertSame([First::class, Second::class], $install->migrateInPlace());
        $activate = $loader->collect('ExamplePlugin');
        $activate->sync();
        static::assertSame([], $activate->migrateInPlace());
        // Plugin::removeMigrations removes the logical namespace, ready for reinstall.
        $this->connection->executeStatement('DELETE FROM migration');
        $this->connection->executeStatement('DELETE FROM migration_contract_event');
        $loader->collect('ExamplePlugin')->sync();
        static::assertSame([First::class, Second::class], $loader->collect('ExamplePlugin')->migrateInPlace());
    }

    public function testVersionedSourceRunsBeforeOlderLegacyTimestampAndLimitsRespectOrder(): void
    {
        $this->registry->register(Legacy::class, 1000000000, Legacy::class);
        $legacy = new MigrationSource('core', [__DIR__ . '/_fixtures/LegacySource' => __NAMESPACE__ . '\\_fixtures\\LegacySource']);
        $source = new MigrationSource('combined', [$this->source('core.V6_3'), $legacy]);
        $runtime = new MigrationRuntime($this->connection, new NullLogger(), $this->registry);
        $collection = new MigrationCollection($source, $runtime, $this->connection, new NullLogger());
        $collection->sync();

        static::assertSame([First::class, Second::class, Legacy::class], $collection->getExecutableMigrations());
        static::assertSame([First::class, Legacy::class], $collection->getExecutableMigrations(until: 1000000001));
        static::assertSame(1, $collection->getTotalMigrationCount(limit: 1));
        static::assertSame([First::class], $collection->migrateInPlace(until: 1000000001, limit: 1));
        static::assertSame([Second::class, Legacy::class], $collection->migrateInPlace());
        static::assertSame([First::class, Second::class, Legacy::class], $collection->migrateDestructiveInPlace());
    }

    public function testEmptyAndUnicodeNamespacesAreMatchedWithoutRegexEncodingAssumptions(): void
    {
        $namespace = __NAMESPACE__ . '\\Mígracje_百分!';
        $class = $namespace . '\\MigrationŻółć';
        $this->connection->insert('migration', ['class' => $class, 'creation_timestamp' => 1000000000]);
        $runtime = new MigrationRuntime($this->connection, new NullLogger(), $this->registry);
        static::assertSame([], $runtime->getExecutableMigrations(new MigrationSource('empty')));
        static::assertSame([$class], $runtime->getExecutableMigrations(new MigrationSource('unicode', [__DIR__ => $namespace])));
    }

    public function testPreKernelInstallerFactoryUsesStrictRoutingAndIncludesV68(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        $loader = (new MigrationCollectionFactory(\dirname(__DIR__, 5)))->getMigrationCollectionLoader($this->connection);
        static::assertArrayHasKey('core.V6_8', $loader->collectAll());
        $loader->addSource($this->source('installer-contract'));
        $this->expectExceptionObject(MigrationException::migrationError('No PostgreSQL migration variant for ' . First::class . ' (timestamp 1000000001).'));
        $loader->collect('installer-contract')->migrateInPlace();
    }

    public function testMigrationCommittingItsOwnBoundaryIsRejectedWithoutClaimingSuccess(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        Second::$commitInside = true;
        try {
            $this->collection->migrateInPlace();
            static::fail('Variants must not commit the runtime transaction.');
        } catch (MigrationException $e) {
            static::assertStringContainsString('changed its transaction boundary', $e->getMessage());
        }
        static::assertSame([Second::class], $this->collection->getExecutableMigrations());
        // An explicit COMMIT cannot be undone. Do not claim atomicity for unsupported variants.
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        static::assertStringContainsString('changed its transaction boundary', $this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
    }

    public function testDestructiveModesSelectSourceGroupsBeforeRouting(): void
    {
        $this->registry->register(Legacy::class, 1000000000, Legacy::class);
        $loader = new MigrationCollectionLoader($this->connection, new MigrationRuntime($this->connection, new NullLogger(), $this->registry), new NullLogger(), [
            new MigrationSource('core'),
            $this->source('core.V6_3'),
            new MigrationSource('core.V6_4', [__DIR__ . '/_fixtures/LegacySource' => __NAMESPACE__ . '\\_fixtures\\LegacySource']),
            new MigrationSource('core.V6_5'),
        ]);
        $all = $loader->collectAllForVersion('6.5.2');
        $all->sync();
        $all->migrateInPlace();
        $safe = $loader->collectAllForVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_SAFE);
        static::assertSame([First::class, Second::class], $safe->getExecutableDestructiveMigrations());
        static::assertSame([First::class, Second::class], $safe->migrateDestructiveInPlace());
        $blueGreen = $loader->collectAllForVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN);
        static::assertSame([Legacy::class], $blueGreen->getExecutableDestructiveMigrations());
        static::assertSame([Legacy::class], $blueGreen->migrateDestructiveInPlace());
        static::assertSame([], $all->migrateDestructiveInPlace());
    }

    private function createCollection(MigrationVariantRegistry $registry): MigrationCollection
    {
        return new MigrationCollection($this->source(), new MigrationRuntime($this->connection, new NullLogger(), $registry), $this->connection, new NullLogger());
    }

    private function source(string $name = 'contract'): MigrationSource
    {
        return new MigrationSource($name, [__DIR__ . '/_fixtures/PlatformSource' => __NAMESPACE__ . '\\_fixtures\\PlatformSource']);
    }

    private function isPostgreSQL(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    private function requirePostgreSQL(): void
    {
        if (!$this->isPostgreSQL()) {
            static::markTestSkipped('PostgreSQL transaction and strict routing contract.');
        }
    }
}

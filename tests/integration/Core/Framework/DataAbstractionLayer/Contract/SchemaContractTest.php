<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Schema\SchemaUpdater;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class SchemaContractTest extends TestCase
{
    private Connection $connection;

    private SchemaStatementLogger $logger;

    private DatabaseSchema $schema;

    private bool $ownsTables = false;

    protected function setUp(): void
    {
        $initial = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->logger = new SchemaStatementLogger();
        $configuration = new Configuration();
        $configuration->setMiddlewares([new Middleware($this->logger)]);
        $this->connection = DriverManager::getConnection($initial->getParams(), $configuration);
        $initial->close();
        $this->schema = new DatabaseSchema($this->connection);
        if ($this->schema->isPostgreSQL()) {
            static::assertStringStartsWith('18.6 ', (string) $this->connection->fetchOne('SHOW server_version'));
            $this->connection->executeStatement('CREATE SCHEMA sw_schema_lifecycle');
            $this->connection->executeStatement('SET search_path TO sw_schema_lifecycle');
        } else {
            static::assertSame('shopware_json_test', $this->connection->getDatabase());
            foreach ($this->tableNames() as $table) {
                static::assertFalse(TableHelper::tableExists($this->connection, $table), 'Fixture requires absent owned tables');
            }
        }
        (new DatabaseFunctionInstaller())->install($this->connection);
        $this->ownsTables = true;
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection) || !$this->ownsTables) {
            return;
        }
        if ($this->schema->isPostgreSQL()) {
            $this->connection->executeStatement('DROP SCHEMA sw_schema_lifecycle CASCADE');
        } else {
            foreach ($this->tableNames() as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
            }
        }
        $this->connection->close();
    }

    public function testDefinitionCreateUpdateAndVersionedForeignKeyRoundTrip(): void
    {
        $parent = new SchemaParentDefinition();
        $child = new SchemaChildDefinition();
        new StaticDefinitionInstanceRegistry([$parent, $child, VersionDefinition::class], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGatewayInterface::class));
        $generator = new MigrationQueryGenerator($this->connection, new SchemaBuilder($this->connection));
        foreach ([$parent, $child] as $definition) {
            $queries = $generator->generateQueries($definition);
            static::assertNotEmpty($queries);
            foreach ($queries as $query) {
                $this->connection->executeStatement($query);
            }
            static::assertSame([], $generator->generateQueries($definition));
        }
        foreach ($this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_parent')->getIndexes() as $index) {
            if ($index->getIndexedColumns()[0]->getColumnName()->getIdentifier()->getValue() !== 'auto_increment') {
                continue;
            }
            $indexName = $index->getObjectName()->toSQL($this->connection->getDatabasePlatform());
            $this->connection->executeStatement($this->schema->isPostgreSQL()
                ? 'ALTER INDEX ' . $indexName . ' RENAME TO legacy_auto_unique'
                : 'ALTER TABLE sw_schema_parent RENAME INDEX ' . $indexName . ' TO legacy_auto_unique');
        }
        static::assertSame([], $generator->generateQueries($parent), 'Equivalent legacy auto-increment unique keys must be preserved by name');
        $id = Uuid::randomBytes();
        $version = Uuid::randomBytes();
        $this->connection->insert('sw_schema_parent', ['id' => $id, 'version_id' => $version, 'name' => 'retained', 'created_at' => '2026-10-05 00:00:00.000'], ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY]);
        $this->connection->insert('sw_schema_child', ['id' => Uuid::randomBytes(), 'parent_id' => $id, 'parent_version_id' => $version, 'created_at' => '2026-10-05 00:00:00.000'], ['id' => ParameterType::BINARY, 'parent_id' => ParameterType::BINARY, 'parent_version_id' => ParameterType::BINARY]);
        try {
            $this->connection->insert('sw_schema_child', ['id' => Uuid::randomBytes(), 'parent_id' => $id, 'parent_version_id' => Uuid::randomBytes(), 'created_at' => '2026-10-05 00:00:00.000'], ['id' => ParameterType::BINARY, 'parent_id' => ParameterType::BINARY, 'parent_version_id' => ParameterType::BINARY]);
            static::fail('Versioned relation must reject another version');
        } catch (ForeignKeyConstraintViolationException) {
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_child'));
        }
        // An operator-created unique key must survive definition diffs.
        $this->connection->executeStatement('CREATE UNIQUE INDEX sw_schema_name_unique ON sw_schema_parent (name, version_id)');
        $updated = new SchemaParentDefinition(addNote: true);
        new StaticDefinitionInstanceRegistry([$updated], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGatewayInterface::class));
        foreach ($generator->generateQueries($updated) as $query) {
            $this->connection->executeStatement($query);
        }
        static::assertSame([], $generator->generateQueries($updated));
        static::assertTrue(TableHelper::indexExists($this->connection, 'sw_schema_parent', 'sw_schema_name_unique'));
        static::assertSame('retained', $this->connection->fetchOne('SELECT name FROM sw_schema_parent'));
        static::assertSame('new default', $this->connection->fetchOne('SELECT note FROM sw_schema_parent'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT auto_increment FROM sw_schema_parent'));
        if ($this->schema->isPostgreSQL()) {
            $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName('sw_schema_parent');
            static::assertSame('C', $table->getColumn('password')->getCollation());
            static::assertSame('sw_type_ci', $table->getColumn('name')->getCollation());
        }
        $this->connection->executeStatement('DELETE FROM sw_schema_parent');
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_schema_child'));
    }

    public function testCustomEntityCreateUpdateDeletePreservesDataAndRepeatedUpdateExecutesNoDdl(): void
    {
        $binary = $this->schema->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $json = $this->schema->isPostgreSQL() ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE custom_entity (name VARCHAR(64) PRIMARY KEY, fields $json NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE language (id $binary PRIMARY KEY)");
        $this->connection->executeStatement("CREATE TABLE sw_schema_reference (id $binary NOT NULL, version_id $binary NOT NULL, label VARCHAR(255), config $json, label_length INT GENERATED ALWAYS AS (CHAR_LENGTH(label)) VIRTUAL, PRIMARY KEY (id, version_id), CONSTRAINT chk_sw_core_guard CHECK (label <> 'invalid'))");
        $this->connection->executeStatement('CREATE INDEX existing_label ON sw_schema_reference (label)');
        $fields = [
            ['name' => 'title', 'type' => 'string', 'required' => false, 'translatable' => true],
            ['name' => 'enabled', 'type' => 'bool', 'required' => false],
            ['name' => 'count', 'type' => 'int', 'required' => false],
            ['name' => 'payload', 'type' => 'json', 'required' => false],
            ['name' => 'related', 'type' => 'many-to-many', 'reference' => 'sw_schema_reference', 'onDelete' => 'cascade'],
            ['name' => 'reference', 'type' => 'many-to-one', 'reference' => 'sw_schema_reference', 'onDelete' => 'set-null', 'required' => false],
            ['name' => 'children', 'type' => 'one-to-many', 'reference' => 'sw_schema_reference', 'onDelete' => 'set-null', 'required' => false],
        ];
        $this->connection->insert('custom_entity', ['name' => 'ce_schema', 'fields' => json_encode($fields, \JSON_THROW_ON_ERROR)]);
        $updater = new CustomEntitySchemaUpdater($this->connection, new LockFactory(new FlockStore()), new SchemaUpdater(new CustomEntityNameValidator(), $this->connection));
        $updater->update();
        $id = Uuid::randomBytes();
        $language = Uuid::randomBytes();
        $reference = Uuid::randomBytes();
        $version = Uuid::randomBytes();
        $this->connection->insert('language', ['id' => $language], ['id' => ParameterType::BINARY]);
        $this->connection->insert('ce_schema', ['id' => $id, 'enabled' => 1, 'count' => 4294967295, 'payload' => '{"retained":true}', 'created_at' => '2026-10-05 00:00:00.123'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('sw_schema_reference', ['id' => $reference, 'version_id' => $version, 'label' => 'untouched', 'ce_schema_children_id' => $id], ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY, 'ce_schema_children_id' => ParameterType::BINARY]);
        $this->connection->insert('ce_schema_translation', ['ce_schema_id' => $id, 'language_id' => $language, 'title' => 'translated', 'created_at' => '2026-10-05 00:00:00.123'], ['ce_schema_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
        $this->connection->insert('ce_schema_related', ['ce_schema_id' => $id, 'sw_schema_reference_id' => $reference, 'sw_schema_reference_version_id' => $version], ['ce_schema_id' => ParameterType::BINARY, 'sw_schema_reference_id' => ParameterType::BINARY, 'sw_schema_reference_version_id' => ParameterType::BINARY]);
        $this->logger->ddl = [];
        $updater->update();
        static::assertSame([], $this->logger->ddl, 'Repeated custom entity update must execute no DDL');
        static::assertSame('translated', $this->connection->fetchOne('SELECT title FROM ce_schema_translation'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'sw_schema_reference', 'existing_label'));
        static::assertSame(4294967295, (int) $this->connection->fetchOne('SELECT count FROM ce_schema'));
        static::assertSame(9, (int) $this->connection->fetchOne('SELECT label_length FROM sw_schema_reference'));
        if ($this->schema->isPostgreSQL()) {
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE conrelid = \'sw_schema_reference\'::regclass AND conname = \'chk_sw_sw_schema_reference_ce_schema_children_id_uuid\''));
        }

        $fields[] = ['name' => 'note', 'type' => 'string', 'required' => false];
        $this->connection->update('custom_entity', ['fields' => json_encode($fields, \JSON_THROW_ON_ERROR)], ['name' => 'ce_schema']);
        $updater->update();
        static::assertTrue(TableHelper::columnExists($this->connection, 'ce_schema', 'note'));
        static::assertSame(['retained' => true], json_decode($this->connection->fetchOne('SELECT payload FROM ce_schema'), true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ce_schema_related'));
        $this->logger->ddl = [];
        $updater->update();
        static::assertSame([], $this->logger->ddl);

        $this->connection->executeStatement('DELETE FROM custom_entity');
        $updater->update();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ce_schema'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'ce_schema_translation'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'ce_schema_related'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'sw_schema_reference', 'ce_schema_children_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'sw_schema_reference', 'existing_label'));
        static::assertSame('untouched', $this->connection->fetchOne('SELECT label FROM sw_schema_reference'));
        static::assertSame(9, (int) $this->connection->fetchOne('SELECT label_length FROM sw_schema_reference'));
        if ($this->schema->isPostgreSQL()) {
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE conrelid = \'sw_schema_reference\'::regclass AND conname = \'chk_sw_sw_schema_reference_ce_schema_children_id_uuid\''));
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE conrelid = \'sw_schema_reference\'::regclass AND conname = \'chk_sw_core_guard\''));
        }
        $this->logger->ddl = [];
        $updater->update();
        static::assertSame([], $this->logger->ddl);
    }

    public function testFailedCustomEntityUpdateReleasesTheLockAndCanBeRetried(): void
    {
        $json = $this->schema->isPostgreSQL() ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE custom_entity (name VARCHAR(64) PRIMARY KEY, fields $json NOT NULL)");
        $fields = [['name' => 'reference', 'type' => 'many-to-one', 'reference' => 'missing_table', 'onDelete' => 'set-null', 'required' => false]];
        $this->connection->insert('custom_entity', ['name' => 'ce_schema', 'fields' => json_encode($fields, \JSON_THROW_ON_ERROR)]);
        $lock = (new LockFactory(new FlockStore()))->createLock('custom-entity::schema-update', 30);
        $factory = static::createMock(LockFactory::class);
        $factory->expects($this->exactly(2))->method('createLock')->willReturn($lock);
        $updater = new CustomEntitySchemaUpdater($this->connection, $factory, new SchemaUpdater(new CustomEntityNameValidator(), $this->connection));
        try {
            $updater->update();
            static::fail('An association to an absent table must reject the schema update');
        } catch (CustomEntityException $exception) {
            static::assertSame(CustomEntityException::associationReferenceTableNotFound('missing_table')->getMessage(), $exception->getMessage());
        }
        static::assertFalse($lock->isAcquired());
        static::assertFalse(TableHelper::tableExists($this->connection, 'ce_schema'));

        $this->connection->update('custom_entity', ['fields' => '[]'], ['name' => 'ce_schema']);
        $updater->update();
        static::assertFalse($lock->isAcquired());
        static::assertTrue(TableHelper::tableExists($this->connection, 'ce_schema'));
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        return ['ce_schema_related', 'ce_schema_translation', 'sw_schema_child', 'sw_schema_reference', 'ce_schema', 'sw_schema_parent', 'language', 'custom_entity'];
    }
}

/**
 * @internal
 */
class SchemaStatementLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $ddl = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $sql = $context['sql'] ?? null;
        if (\is_string($sql) && preg_match('/^(CREATE|ALTER|DROP)\b/i', $sql) === 1) {
            $this->ddl[] = $sql;
        }
    }
}

/**
 * @internal
 */
class SchemaParentDefinition extends EntityDefinition
{
    public function __construct(private readonly bool $addNote = false)
    {
    }

    public function getEntityName(): string
    {
        return 'sw_schema_parent';
    }

    public function getDefaults(): array
    {
        return $this->addNote ? ['note' => 'new default'] : [];
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\VersionField(),
            (new Field\StringField('name', 'name'))->addFlags(new Field\Flag\Required()),
            new Field\BoolField('enabled', 'enabled'),
            new Field\AutoIncrementField(),
            new Field\CreatedAtField(),
            new Field\UpdatedAtField(),
            new Field\BlobField('payload', 'payload'),
            new Field\JsonField('data', 'data'),
            new Field\FloatField('amount', 'amount'),
            new Field\PasswordField('password', 'password'),
        ]);
        if ($this->addNote) {
            $fields->add(new Field\StringField('note', 'note'));
        }

        return $fields;
    }
}

/**
 * @internal
 */
class SchemaChildDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_schema_child';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\FkField('parent_id', 'parentId', SchemaParentDefinition::class),
            new Field\ReferenceVersionField(SchemaParentDefinition::class, 'parent_version_id'),
            (new Field\ManyToOneAssociationField('parent', 'parent_id', SchemaParentDefinition::class))->addFlags(new Field\Flag\CascadeDelete()),
        ]);
    }
}

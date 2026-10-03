<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductExceptionHandler;
use Shopware\Core\Content\Product\Exception\DuplicateProductNumberException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Sync\SyncFkResolver;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search;
use Shopware\Core\Framework\DataAbstractionLayer\TechnicalNameExceptionHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class EntityWriteContractTest extends TestCase
{
    private Connection $connection;

    private Write\EntityWriter $writer;

    private Dbal\EntityWriteGateway $gateway;

    private WriteContractDefinition $definition;

    private WriteMappingDefinition $mapping;

    private EventDispatcher $dispatcher;

    private Write\EntityWriteResultFactory $results;

    private WriteDetailDefinition $detail;

    private VersionManager $versions;

    private Dbal\EntityReader $reader;

    private bool $postgres;

    private RepositoryWriterFacade $facade;

    /**
     * @var list<string|null>
     */
    private array $observedSqlStates = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $endpoint = DatabaseEndpoint::observe($this->connection);
        static::assertFalse($endpoint->standby);
        static::assertStringStartsWith($endpoint->platform === 'postgresql' ? '18.6 ' : '8.4.11', $endpoint->version);
        $this->postgres = $endpoint->platform === 'postgresql';
        if ($this->postgres) {
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS sw_write_contract CASCADE');
            $this->connection->executeStatement('CREATE SCHEMA sw_write_contract');
            $this->connection->executeStatement('SET search_path TO sw_write_contract');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        } elseif ($this->connection->getDatabase() !== 'shopware_json_test') {
            throw new \RuntimeException('The write contract requires shopware_json_test on MySQL');
        }
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $boolean = $this->postgres ? 'BOOLEAN' : 'TINYINT(1)';
        $date = $this->postgres ? 'TIMESTAMP(3) WITHOUT TIME ZONE' : 'DATETIME(3)';
        $auto = $this->postgres ? 'BIGINT GENERATED BY DEFAULT AS IDENTITY UNIQUE' : 'BIGINT AUTO_INCREMENT UNIQUE';
        $blob = $this->postgres ? 'BYTEA' : 'LONGBLOB';
        $this->connection->executeStatement("CREATE TABLE sw_write_product (id $binary NOT NULL, version_id $binary NOT NULL, name VARCHAR(255) NOT NULL DEFAULT 'default', active $boolean NOT NULL DEFAULT TRUE, locked $boolean NOT NULL DEFAULT FALSE, parent_id $binary NULL, parent_version_id $binary NULL, content $blob NULL, payload $json NULL, custom_fields $json NULL, created_at $date NOT NULL, updated_at $date NULL, PRIMARY KEY (id, version_id), UNIQUE (name, version_id), CHECK (name <> 'invalid'))");
        $this->connection->executeStatement("CREATE TABLE sw_write_detail (id $binary PRIMARY KEY, product_id $binary NOT NULL, product_version_id $binary NOT NULL, name VARCHAR(255), created_at $date NOT NULL, updated_at $date NULL, FOREIGN KEY (product_id, product_version_id) REFERENCES sw_write_product(id, version_id) ON DELETE CASCADE)");
        $this->connection->executeStatement("CREATE TABLE sw_write_mapping (product_id $binary NOT NULL, product_version_id $binary NOT NULL, tag_id $binary NOT NULL, note VARCHAR(255) DEFAULT 'default', PRIMARY KEY (product_id, product_version_id, tag_id), FOREIGN KEY (product_id, product_version_id) REFERENCES sw_write_product(id, version_id) ON DELETE CASCADE)");
        $this->connection->executeStatement("CREATE TABLE sw_write_mapping_child (product_id $binary NOT NULL, product_version_id $binary NOT NULL, tag_id $binary NOT NULL, FOREIGN KEY (product_id, product_version_id, tag_id) REFERENCES sw_write_mapping(product_id, product_version_id, tag_id) ON DELETE CASCADE)");
        $this->connection->executeStatement("CREATE TABLE version (id $binary PRIMARY KEY, name VARCHAR(255), created_at $date NOT NULL, updated_at $date NULL)");
        $this->connection->executeStatement("CREATE TABLE version_commit (id $binary PRIMARY KEY, version_id $binary NOT NULL, user_id $binary, integration_id $binary, auto_increment $auto, is_merge $boolean DEFAULT FALSE, message VARCHAR(255), created_at $date NOT NULL, updated_at $date NULL)");
        $this->connection->executeStatement("CREATE TABLE version_commit_data (id $binary PRIMARY KEY, version_commit_id $binary NOT NULL, user_id $binary, integration_id $binary, auto_increment $auto, entity_name VARCHAR(255), entity_id $json, action VARCHAR(255), payload $json, created_at $date NOT NULL, updated_at $date NULL, FOREIGN KEY (version_commit_id) REFERENCES version_commit(id) ON DELETE CASCADE)");
        $this->connection->insert('version', ['id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'name' => 'live', 'created_at' => '2026-10-03 00:00:00.000'], ['id' => ParameterType::BINARY]);
        $this->buildRuntime();
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        while ($this->connection->getTransactionNestingLevel() > 0) {
            $this->connection->rollBack();
        }
        foreach (['sw_write_replace_guard', 'sw_write_attempt', 'sw_write_mapping_child', 'sw_write_mapping', 'sw_write_detail', 'sw_write_product', 'version_commit_data', 'version_commit', 'version'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
        }
        if ($this->postgres) {
            $this->connection->executeStatement('DROP SCHEMA sw_write_contract CASCADE');
        }
        $this->connection->close();
    }

    public function testWriterCrudBatchAndPartialJsonRoundTrip(): void
    {
        $id = '00000000000000000000000000000001';
        $other = 'ffffffffffffffffffffffffffffffff';
        $result = $this->writer->upsert($this->definition, [['id' => $id, 'name' => 'first', 'content' => "\x00\xff\x80binary", 'payload' => ['number' => 1.5], 'customFields' => ['retained' => true]], ['id' => $other, 'name' => 'second']], $this->context());
        static::assertCount(2, $result['sw_write_product']);
        $this->writer->upsert($this->definition, [['id' => $id, 'name' => 'changed'], ['id' => $id, 'customFields' => ['literal.name"\\' => ['x' => null], 'number' => 1.5, 'false' => false]]], $this->context());
        $entity = $this->reader->read($this->definition, new Search\Criteria([$id]), Context::createDefaultContext())->first();
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertSame('changed', $entity->get('name'));
        static::assertEquals(['retained' => true, 'literal.name"\\' => ['x' => null], 'number' => 1.5, 'false' => false], $entity->get('customFields'));
        static::assertSame(['number' => 1.5], $entity->get('payload'));
        static::assertSame("\x00\xff\x80binary", $entity->get('content'));
        $this->writer->update($this->definition, [['id' => $id, 'content' => "updated\x00\xfe"]], $this->context());
        static::assertSame("updated\x00\xfe", $this->reader->read($this->definition, new Search\Criteria([$id]), Context::createDefaultContext())->first()?->get('content'));
        $deleted = $this->writer->delete($this->definition, [['id' => $other]], $this->context());
        static::assertCount(1, $deleted->getDeleted()['sw_write_product']);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
    }

    public function testSyncWritesAndDeletesInOneBatch(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'delete']], $this->context());
        $result = $this->writer->sync([
            new SyncOperation('insert', 'sw_write_product', 'upsert', [['id' => self::id(2), 'name' => 'insert'], ['id' => self::id(3), 'name' => 'other']]),
            new SyncOperation('update', 'sw_write_product', 'upsert', [['id' => self::id(2), 'name' => 'updated']]),
            new SyncOperation('delete', 'sw_write_product', 'delete', [['id' => self::id(1)]]),
        ], $this->context());
        static::assertCount(2, $result->getWritten()['sw_write_product']);
        static::assertCount(1, $result->getDeleted()['sw_write_product']);
        static::assertSame(['other', 'updated'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product ORDER BY name'));
    }

    public function testReplacementRejectsUnauditedPostgreSqlConflicts(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement("CREATE TABLE sw_write_replace_guard (id $binary PRIMARY KEY, token VARCHAR(255) UNIQUE)");
        foreach ([false, true] as $explicitTarget) {
            $queue = new MultiInsertQueryQueue($this->connection, useReplace: true);
            if ($explicitTarget) {
                $queue->setConflictTarget('sw_write_replace_guard', ['id']);
            }
            $queue->addInsert('sw_write_replace_guard', ['id' => Uuid::fromHexToBytes(self::id(1)), 'token' => 'same'], ['id' => ParameterType::BINARY]);
            try {
                $queue->execute();
                static::assertFalse($this->postgres, 'PostgreSQL replacement must reject an unaudited conflict');
            } catch (DataAbstractionLayerException $exception) {
                static::assertTrue($this->postgres);
                static::assertSame('FRAMEWORK__INVALID_WRITE_OPERATION', $exception->getErrorCode());
            }
        }
        if ($this->postgres) {
            $this->connection->executeStatement('ALTER TABLE sw_write_replace_guard DROP CONSTRAINT sw_write_replace_guard_token_key');
            foreach (['(LOWER(token))', '(id) WHERE token IS NOT NULL'] as $index) {
                $this->connection->executeStatement('CREATE UNIQUE INDEX sw_write_special_conflict ON sw_write_replace_guard ' . $index);
                $queue = new MultiInsertQueryQueue($this->connection, useReplace: true);
                $queue->setConflictTarget('sw_write_replace_guard', ['id']);
                $queue->addInsert('sw_write_replace_guard', ['id' => Uuid::fromHexToBytes(self::id(1)), 'token' => 'same'], ['id' => ParameterType::BINARY]);
                try {
                    $queue->execute();
                    static::fail('Partial and expression conflicts must be rejected before replacement');
                } catch (DataAbstractionLayerException $exception) {
                    static::assertSame('FRAMEWORK__INVALID_WRITE_OPERATION', $exception->getErrorCode());
                }
                $this->connection->executeStatement('DROP INDEX sw_write_special_conflict');
            }
        }
        static::assertSame($this->postgres ? 0 : 1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_replace_guard'));
    }

    public function testRepositoryFacadeCrudAndSyncUseProductionWriter(): void
    {
        $this->facade->upsert('sw_write_product', [['id' => self::id(1), 'name' => 'created']]);
        $this->facade->upsert('sw_write_product', [['id' => self::id(1), 'name' => 'updated']]);
        $result = $this->facade->sync([
            ['key' => 'insert', 'entity' => 'sw_write_product', 'action' => 'upsert', 'payload' => [['id' => self::id(2), 'name' => 'synced']]],
            ['key' => 'delete', 'entity' => 'sw_write_product', 'action' => 'delete', 'payload' => [['id' => self::id(1)]]],
        ]);
        static::assertSame(['sw_write_product' => [self::id(2)]], $result->getData());
        static::assertSame(['sw_write_product' => [self::id(1)]], $result->getDeleted());
        static::assertSame(['synced'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product'));
        $this->facade->delete('sw_write_product', [['id' => self::id(2)]]);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
    }

    public function testVersionManagerCreatesMergesAndDeletesDraft(): void
    {
        $id = self::id(1);
        $context = $this->context();
        $this->versions->insert($this->definition, [['id' => $id, 'name' => 'live']], $context);
        $version = $this->versions->createVersion($this->definition, $id, $context, 'draft');
        $this->versions->update($this->definition, [['id' => $id, 'name' => 'draft']], $this->context()->createWithVersionId($version));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
        $this->versions->merge($version, $this->context());
        static::assertSame(['draft'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM version WHERE id = ?', [Uuid::fromHexToBytes($version)], [ParameterType::BINARY]));
    }

    public function testMappingReplacementCascadesAndResetsMissingValues(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'product']], $this->context());
        $primary = ['productId' => self::id(1), 'productVersionId' => Defaults::LIVE_VERSION, 'tagId' => self::id(2)];
        $this->writer->upsert($this->mapping, [$primary + ['note' => 'old']], $this->context());
        $raw = ['product_id' => Uuid::fromHexToBytes(self::id(1)), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'tag_id' => Uuid::fromHexToBytes(self::id(2))];
        $this->connection->insert('sw_write_mapping_child', $raw, array_fill_keys(array_keys($raw), ParameterType::BINARY));
        $this->writer->upsert($this->mapping, [$primary, $primary + ['note' => null]], $this->context());
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_mapping_child'));
        static::assertNull($this->connection->fetchOne('SELECT note FROM sw_write_mapping'));
    }

    public function testQueueDefaultsNullBinaryAndDuplicateLastRowWins(): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, chunkSize: 10);
        $queue->setConflictTarget('sw_write_product', ['id', 'version_id']);
        $queue->addUpdateFieldOnDuplicateKey('sw_write_product', 'name');
        $queue->addUpdateFieldOnDuplicateKey('sw_write_product', 'payload');
        $base = ['id' => Uuid::fromHexToBytes(self::id(1)), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'created_at' => '2026-10-03 00:00:00.000'];
        $types = ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY];
        $queue->addInsert('sw_write_product', $base + ['name' => 'first', 'payload' => '{"value":1}'], $types);
        $queue->addInsert('sw_write_product', $base + ['payload' => null], $types);
        $queue->execute();
        $queue->execute();
        static::assertSame(['name' => 'default', 'payload' => null], $this->connection->fetchAssociative('SELECT name, payload FROM sw_write_product'));
    }

    public function testUniqueFailureRollsBackEntireBatchAndConnectionIsReusable(): void
    {
        try {
            $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'same'], ['id' => self::id(2), 'name' => 'same']], $this->context());
            static::fail('Duplicate unique names must fail');
        } catch (UniqueConstraintViolationException $exception) {
            static::assertSame($this->postgres ? '23505' : '23000', $exception->getSQLState());
        }
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
        static::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->writer->insert($this->definition, [['id' => self::id(3), 'name' => 'valid']], $this->context());
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
    }

    public function testIgnoreDuplicatesDoesNotSuppressForeignKeyOrCheckViolations(): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, ignoreErrors: true);
        $queue->addInsert('sw_write_mapping', ['product_id' => Uuid::fromHexToBytes(self::id(1)), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'tag_id' => Uuid::fromHexToBytes(self::id(2))], ['product_id' => ParameterType::BINARY, 'product_version_id' => ParameterType::BINARY, 'tag_id' => ParameterType::BINARY]);
        if (!$this->postgres) {
            // The released MySQL INSERT IGNORE contract also suppresses FK errors. PostgreSQL ignores
            // only uniqueness conflicts; use the ordinary insert to verify MySQL FK conversion.
            $queue->execute();
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_mapping'));
            $queue = new MultiInsertQueryQueue($this->connection);
            $queue->addInsert('sw_write_mapping', ['product_id' => Uuid::fromHexToBytes(self::id(1)), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'tag_id' => Uuid::fromHexToBytes(self::id(2))], ['product_id' => ParameterType::BINARY, 'product_version_id' => ParameterType::BINARY, 'tag_id' => ParameterType::BINARY]);
        }
        try {
            $queue->execute();
            static::fail('An invalid FK must fail');
        } catch (ForeignKeyConstraintViolationException $exception) {
            static::assertSame($this->postgres ? '23503' : '23000', $exception->getSQLState());
        }
        try {
            $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'invalid']], $this->context());
            static::fail('The CHECK constraint must fail');
        } catch (DriverException $exception) {
            static::assertSame($this->postgres ? '23514' : 'HY000', $exception->getSQLState());
        }
        static::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    #[DataProvider('retryScopes')]
    public function testRetryReplaysBatchAndEmitsWriteAndDeleteEffectsOnceAfterCommit(bool $outer): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'update', 'content' => "\x00\xff"], ['id' => self::id(2), 'name' => 'delete']], $this->context());
        if ($this->postgres) {
            $this->connection->executeStatement('CREATE SEQUENCE sw_write_attempt_sequence');
            $this->connection->executeStatement('CREATE FUNCTION sw_write_fail_once() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF nextval(\'sw_write_attempt_sequence\') = 1 THEN RAISE EXCEPTION \'retry fixture\' USING ERRCODE = \'40001\'; END IF; RETURN NEW; END $$');
            $this->connection->executeStatement('CREATE TRIGGER sw_write_fail_once BEFORE UPDATE ON sw_write_product FOR EACH ROW EXECUTE FUNCTION sw_write_fail_once()');
        }
        $attempts = 0;
        $this->dispatcher->addListener(Write\Validation\PostWriteValidationEvent::class, function () use (&$attempts): void {
            ++$attempts;
            if (!$this->postgres && $attempts === 1) {
                $this->connection->executeStatement('SIGNAL SQLSTATE \'40001\' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = \'retry fixture\'');
            }
        });
        $success = 0;
        $deleted = 0;
        $errors = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, function (EntityWriteEvent $event) use (&$success, &$errors): void {
            $event->addSuccess(function () use (&$success): void {
                static::assertSame(0, $this->connection->getTransactionNestingLevel());
                ++$success;
            });
            $event->addError(static function () use (&$errors): void { ++$errors; });
        });
        $this->dispatcher->addListener(EntityDeleteEvent::class, static function (EntityDeleteEvent $event) use (&$deleted): void {
            $event->addSuccess(static function () use (&$deleted): void { ++$deleted; });
        });
        $existence = Write\EntityExistence::createForEntity('sw_write_product', []);
        $primary = ['id' => Uuid::fromHexToBytes(self::id(1)), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
        $insert = ['id' => Uuid::fromHexToBytes(self::id(3)), 'version_id' => $primary['version_id'], 'name' => 'insert', 'created_at' => '2026-10-03 00:00:00.000'];
        $context = $this->context();
        $update = new Write\Command\UpdateCommand($this->definition, ['name' => 'updated', 'content' => "\xfe\x00"], $primary, $existence, '/2');
        $update->requestChangeSet();
        $commands = [
            new Write\Command\InsertCommand($this->definition, $insert, array_intersect_key($insert, $primary), $existence, '/0'),
            new Write\Command\DeleteCommand($this->definition, ['id' => Uuid::fromHexToBytes(self::id(2)), 'version_id' => $primary['version_id']], $existence),
            $update,
        ];
        $execute = function () use ($commands, $context): void { $this->gateway->execute($commands, $context); };
        if ($outer) {
            RetryableTransaction::retryable($this->connection, $execute);
        } else {
            $execute();
        }
        static::assertSame(1, $success);
        static::assertSame(1, $deleted);
        static::assertSame(0, $errors);
        static::assertSame([], $context->getExceptions()->getExceptions());
        static::assertSame($primary['id'], $update->getChangeSet()?->getBefore('id'));
        static::assertSame("\x00\xff", $update->getChangeSet()->getBefore('content'));
        static::assertSame("\xfe\x00", $update->getChangeSet()->getAfter('content'));
        static::assertSame(['insert', 'updated'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product ORDER BY name'));
        static::assertSame(2, $this->postgres ? (int) $this->connection->fetchOne('SELECT last_value FROM sw_write_attempt_sequence') : $attempts);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function retryScopes(): iterable
    {
        yield 'gateway transaction' => [false];
        yield 'surrounding transaction' => [true];
    }

    public function testNestedRollbackDiscardsEffectsAndOuterRetryDoesNotDuplicateThem(): void
    {
        $success = 0;
        $error = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$success, &$error): void {
            $event->addSuccess(static function () use (&$success): void { ++$success; });
            $event->addError(static function () use (&$error): void { ++$error; });
        });
        RetryableTransaction::retryable($this->connection, function () use (&$success): void {
            try {
                RetryableTransaction::transactional($this->connection, function (): void {
                    $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'rolled back']], $this->context());
                    throw new \RuntimeException('rollback savepoint');
                });
            } catch (\RuntimeException $exception) {
                static::assertSame('rollback savepoint', $exception->getMessage());
            }
            $this->writer->insert($this->definition, [['id' => self::id(2), 'name' => 'committed']], $this->context());
            static::assertSame(0, $success);
        });
        static::assertSame(1, $success);
        static::assertSame(0, $error);
        static::assertSame(['committed'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product'));
    }

    public function testConcurrentJsonWritersRetainBothChanges(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'concurrent', 'customFields' => ['initial' => true]]], $this->context());
        $this->connection->beginTransaction();
        $this->writer->update($this->definition, [['id' => self::id(1), 'customFields' => ['left' => ['value' => 1]]]], $this->context());
        $code = <<<'CODE'
            require 'vendor/autoload.php';
            require 'tests/integration/Core/Framework/DataAbstractionLayer/Contract/EntityWriteContractTest.php';
            $connection = \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
            if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
                $connection->executeStatement('SET search_path TO sw_write_contract');
            }
            $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
            $registry = new \Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry($container, [], []);
            $definition = new \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\WriteContractDefinition();
            $registry->register($definition);
            $gateway = new \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway(2, $connection, new \Symfony\Component\EventDispatcher\EventDispatcher(), new \Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry([]), $registry);
            $primary = ['id' => hex2bin('00000000000000000000000000000001'), 'version_id' => hex2bin(\Shopware\Core\Defaults::LIVE_VERSION)];
            echo "ready\n";
            $gateway->execute([new \Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand($definition, 'custom_fields', ['right' => ['value' => 2]], $primary, \Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence::createForEntity('sw_write_product', []), '/')], \Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext::createFromContext(\Shopware\Core\Framework\Context::createDefaultContext()));
            echo "committed\n";
            CODE;
        $worker = new Process([\PHP_BINARY, '-r', $code], timeout: 15);
        $worker->start();
        try {
            $worker->waitUntil(static fn (string $type, string $output): bool => $type === Process::OUT && str_contains($output, 'ready'));
            // Give the second connection time to reach the row lock while the first write is uncommitted.
            usleep(100000);
            static::assertTrue($worker->isRunning());
            static::assertStringNotContainsString('committed', $worker->getOutput());
            $this->connection->commit();
            $worker->wait();
            static::assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $row = $this->connection->fetchOne('SELECT custom_fields FROM sw_write_product');
            static::assertEquals(['initial' => true, 'left' => ['value' => 1], 'right' => ['value' => 2]], json_decode($row, true, 512, \JSON_THROW_ON_ERROR));
        } finally {
            $worker->stop();
        }
    }

    public function testRealConcurrentDeadlockRetriesWholeTransaction(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'one'], ['id' => self::id(2), 'name' => 'two']], $this->context());
        $code = <<<'CODE'
            require 'vendor/autoload.php';
            $connection = \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
            if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
                $connection->executeStatement('SET search_path TO sw_write_contract');
            }
            \Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction::retryable($connection, function () use ($connection): void {
                $connection->executeStatement("UPDATE sw_write_product SET name = CONCAT(name, 'W') WHERE id = ?", [hex2bin('00000000000000000000000000000002')], [\Doctrine\DBAL\ParameterType::BINARY]);
                echo "locked\n";
                try {
                    $connection->executeStatement("UPDATE sw_write_product SET name = CONCAT(name, 'W') WHERE id = ?", [hex2bin('00000000000000000000000000000001')], [\Doctrine\DBAL\ParameterType::BINARY]);
                } catch (\Doctrine\DBAL\Exception\DriverException $exception) {
                    echo 'retry=' . $exception->getSQLState() . "\n";
                    throw $exception;
                }
                \Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction::onCommit($connection, static function (): void { echo "effect\n"; }, static function (): void {});
            });
            CODE;
        $worker = new Process([\PHP_BINARY, '-r', $code], timeout: 15);
        $attempts = 0;
        $effects = 0;
        try {
            RetryableTransaction::retryable($this->connection, function () use ($worker, &$attempts, &$effects): void {
                ++$attempts;
                $this->connection->executeStatement('UPDATE sw_write_product SET name = CONCAT(name, \'P\') WHERE id = ?', [Uuid::fromHexToBytes(self::id(1))], [ParameterType::BINARY]);
                if ($attempts === 1) {
                    $worker->start();
                    static::assertTrue($worker->waitUntil(static fn (string $type, string $output): bool => $type === Process::OUT && str_contains($output, 'locked')));
                }
                try {
                    $this->connection->executeStatement('UPDATE sw_write_product SET name = CONCAT(name, \'P\') WHERE id = ?', [Uuid::fromHexToBytes(self::id(2))], [ParameterType::BINARY]);
                } catch (DriverException $exception) {
                    $this->observedSqlStates[] = $exception->getSQLState();
                    throw $exception;
                }
                RetryableTransaction::onCommit($this->connection, static function () use (&$effects): void { ++$effects; }, static function (): void {});
            });
            $worker->wait();
            static::assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $deadlock = $this->postgres ? '40P01' : '40001';
            static::assertTrue(\in_array($deadlock, $this->observedSqlStates, true) || str_contains($worker->getOutput(), 'retry=' . $deadlock), $worker->getOutput());
            static::assertSame(1, $effects);
            static::assertSame(1, substr_count($worker->getOutput(), 'effect'));
            foreach ($this->connection->fetchFirstColumn('SELECT name FROM sw_write_product ORDER BY id') as $name) {
                static::assertSame(1, substr_count($name, 'P'));
                static::assertSame(1, substr_count($name, 'W'));
            }
            static::assertSame(0, $this->connection->getTransactionNestingLevel());
        } finally {
            $worker->stop();
        }
    }

    public function testConcurrentCommittedWriteRetriesSerializableSnapshot(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'base']], $this->context());
        $this->connection->setTransactionIsolation($this->postgres ? TransactionIsolationLevel::SERIALIZABLE : TransactionIsolationLevel::REPEATABLE_READ);
        $code = <<<'CODE'
            require 'vendor/autoload.php';
            $connection = \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
            if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
                $connection->executeStatement('SET search_path TO sw_write_contract');
            }
            $connection->executeStatement("UPDATE sw_write_product SET name = CONCAT(name, 'W') WHERE id = ?", [hex2bin('00000000000000000000000000000001')], [\Doctrine\DBAL\ParameterType::BINARY]);
            CODE;
        $worker = new Process([\PHP_BINARY, '-r', $code], timeout: 15);
        $attempts = 0;
        $effects = 0;
        try {
            RetryableTransaction::retryable($this->connection, function () use ($worker, &$attempts, &$effects): void {
                ++$attempts;
                $this->connection->fetchOne('SELECT name FROM sw_write_product');
                if ($attempts === 1) {
                    $worker->mustRun();
                }
                try {
                    $this->connection->executeStatement('UPDATE sw_write_product SET name = CONCAT(name, \'P\') WHERE id = ?', [Uuid::fromHexToBytes(self::id(1))], [ParameterType::BINARY]);
                } catch (DriverException $exception) {
                    $this->observedSqlStates[] = $exception->getSQLState();
                    throw $exception;
                }
                RetryableTransaction::onCommit($this->connection, static function () use (&$effects): void { ++$effects; }, static function (): void {});
            });
            static::assertSame($this->postgres ? ['40001'] : [], $this->observedSqlStates);
            static::assertSame($this->postgres ? 2 : 1, $attempts);
            static::assertSame(1, $effects);
            static::assertSame('baseWP', $this->connection->fetchOne('SELECT name FROM sw_write_product'));
        } finally {
            $worker->stop();
        }
    }

    public function testJsonPartialWritesPreserveTypesLiteralMembersAndRootShapes(): void
    {
        $members = ['key.with.dot"\\' => ['nested' => true], 'null' => null, 'text' => 'null', 'int' => \PHP_INT_MAX, 'float' => 1.5, 'list' => [1, '1', false], 'object' => new \stdClass()];
        foreach ([null, '{}', 'null', '[1,2]', '7', '"text"'] as $index => $root) {
            $id = self::id($index + 1);
            $this->writer->insert($this->definition, [['id' => $id, 'name' => 'root-' . $index]], $this->context());
            $primary = ['id' => Uuid::fromHexToBytes($id), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
            $this->connection->executeStatement('UPDATE sw_write_product SET custom_fields = ? WHERE id = ?', [$root, $primary['id']], [$root === null ? ParameterType::NULL : ParameterType::STRING, ParameterType::BINARY]);
            $command = new Write\Command\JsonUpdateCommand($this->definition, 'custom_fields', $members, $primary, Write\EntityExistence::createForEntity('sw_write_product', []), '/');
            $this->gateway->execute([$command], $this->context());
            $json = $this->connection->fetchOne('SELECT custom_fields FROM sw_write_product WHERE id = ?', [$primary['id']], [ParameterType::BINARY]);
            $expected = $root === null || $root === '{}' ? json_decode(json_encode($members, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR) : json_decode($root, false, 512, \JSON_THROW_ON_ERROR);
            static::assertEquals($expected, json_decode($json, false, 512, \JSON_THROW_ON_ERROR));
        }
        $this->expectException(\JsonException::class);
        (new DatabasePlatformResolver($this->connection))->dialect()->jsonSetMembers('custom_fields', ['non-finite' => \INF]);
    }

    public function testPostgreSqlUniqueErrorsReachExistingDomainHandlers(): void
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        // Constraint names are the existing domain contract, including table-scoped technical names.
        $this->connection->executeStatement('CREATE TABLE sw_write_error_product (product_number VARCHAR(255), version_id ' . $binary . ', CONSTRAINT ' . $dialect->quoteIdentifier('uniq.product.product_number__version_id') . ' UNIQUE (product_number, version_id))');
        $this->connection->executeStatement('CREATE TABLE payment_method (technical_name VARCHAR(255), CONSTRAINT ' . $dialect->quoteIdentifier('uniq.technical_name') . ' UNIQUE (technical_name))');
        try {
            $this->connection->insert('sw_write_error_product', ['product_number' => 'literal, SW-1', 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version_id' => ParameterType::BINARY]);
            try {
                $this->connection->insert('sw_write_error_product', ['product_number' => 'literal, SW-1', 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version_id' => ParameterType::BINARY]);
                static::fail('Duplicate product number must fail');
            } catch (UniqueConstraintViolationException $exception) {
                $mapped = (new ProductExceptionHandler())->matchException($exception);
                static::assertInstanceOf(DuplicateProductNumberException::class, $mapped);
            }
            $this->connection->insert($dialect->quoteIdentifier('payment_method'), ['technical_name' => 'literal, name']);
            try {
                $this->connection->insert($dialect->quoteIdentifier('payment_method'), ['technical_name' => 'literal, name']);
                static::fail('Duplicate technical name must fail');
            } catch (UniqueConstraintViolationException $exception) {
                $mapped = (new TechnicalNameExceptionHandler())->matchException($exception);
                static::assertInstanceOf(PaymentException::class, $mapped);
            }
        } finally {
            $this->connection->executeStatement('DROP TABLE payment_method');
            $this->connection->executeStatement('DROP TABLE sw_write_error_product');
        }
    }

    public function testLockValidatorRejectsLockedWritesAndAllowsUnlockedWrites(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'unlocked']], $this->context());
        $this->writer->update($this->definition, [['id' => self::id(1), 'name' => 'allowed']], $this->context());
        $this->connection->executeStatement('UPDATE sw_write_product SET locked = ?', [true], [ParameterType::BOOLEAN]);
        try {
            $this->writer->update($this->definition, [['id' => self::id(1), 'name' => 'forbidden']], $this->context());
            static::fail('Locked entities cannot be updated');
        } catch (Write\WriteException $exception) {
            $error = iterator_to_array($exception->getErrors())[0];
            static::assertSame(Write\Validation\LockValidator::VIOLATION_LOCKED, $error['code']);
        }
        static::assertSame('allowed', $this->connection->fetchOne('SELECT name FROM sw_write_product'));
        $this->connection->executeStatement('UPDATE sw_write_product SET locked = ?', [false], [ParameterType::BOOLEAN]);
        $this->writer->delete($this->definition, [['id' => self::id(1)]], $this->context());
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
    }

    public function testDeleteAndFkResultsResolveExistingParentsWithBinaryIds(): void
    {
        $this->writer->insert($this->definition, [['id' => self::id(1), 'name' => 'parent'], ['id' => self::id(2), 'name' => 'child', 'parentId' => self::id(1)]], $this->context());
        static::assertSame(['sw_write_product' => [self::id(1)]], $this->results->resolveDelete($this->definition, [['id' => self::id(2)]]));
        $this->writer->insert($this->detail, [['id' => self::id(3), 'productId' => self::id(1), 'productVersionId' => Defaults::LIVE_VERSION, 'name' => 'detail']], $this->context());
        static::assertSame(['sw_write_product' => [self::id(1)]], $this->results->resolveWrite($this->detail, [['id' => self::id(3)]]));
        $this->writer->update($this->detail, [['id' => self::id(3), 'name' => 'changed']], $this->context());
        static::assertSame('changed', $this->connection->fetchOne('SELECT name FROM sw_write_detail'));
        $this->writer->delete($this->definition, [['id' => self::id(2)]], $this->context());
        static::assertSame(['parent'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product'));
    }

    public function testOuterRetryRestoresAnExecutedQueueIncludingMultipleFlushes(): void
    {
        $queue = new MultiInsertQueryQueue($this->connection);
        $base = ['version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'created_at' => '2026-10-03 00:00:00.000'];
        $types = ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY];
        $queue->addInsert('sw_write_product', $base + ['id' => Uuid::fromHexToBytes(self::id(1)), 'name' => 'first'], $types);
        $attempts = 0;
        RetryableTransaction::retryable($this->connection, function () use ($queue, $base, $types, &$attempts): void {
            ++$attempts;
            $queue->execute();
            if ($attempts === 1) {
                $queue->addInsert('sw_write_product', $base + ['id' => Uuid::fromHexToBytes(self::id(2)), 'name' => 'second'], $types);
                $queue->execute();
                if ($this->postgres) {
                    $this->connection->executeStatement('DO $$ BEGIN RAISE EXCEPTION \'retry\' USING ERRCODE = \'40001\'; END $$');
                } else {
                    $this->connection->executeStatement('SIGNAL SQLSTATE \'40001\' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = \'retry\'');
                }
            }
        });
        static::assertSame(2, $attempts);
        static::assertSame(['first', 'second'], $this->connection->fetchFirstColumn('SELECT name FROM sw_write_product ORDER BY name'));
        $queue->execute();
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_write_product'));
    }

    private function buildRuntime(): void
    {
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $validator = Validation::createValidator();
        $clock = new NativeClock();
        $this->dispatcher = new EventDispatcher();
        $this->gateway = new Dbal\EntityWriteGateway(2, $this->connection, $this->dispatcher, new Dbal\ExceptionHandlerRegistry([]), $registry);
        $extractor = new Write\WriteCommandExtractor($this->gateway, $registry);
        foreach ([FieldSerializer\IdFieldSerializer::class, FieldSerializer\FkFieldSerializer::class, FieldSerializer\BoolFieldSerializer::class, FieldSerializer\IntFieldSerializer::class, FieldSerializer\JsonFieldSerializer::class] as $class) {
            $container->set($class, new $class($validator, $registry));
        }
        $container->set(FieldSerializer\StringFieldSerializer::class, new FieldSerializer\StringFieldSerializer($validator, $registry, new HtmlSanitizer()));
        $container->set(FieldSerializer\BlobFieldSerializer::class, new FieldSerializer\BlobFieldSerializer());
        $container->set(FieldSerializer\VersionFieldSerializer::class, new FieldSerializer\VersionFieldSerializer());
        $container->set(FieldSerializer\ReferenceVersionFieldSerializer::class, new FieldSerializer\ReferenceVersionFieldSerializer());
        $container->set(FieldSerializer\CreatedAtFieldSerializer::class, new FieldSerializer\CreatedAtFieldSerializer($validator, $registry, $clock));
        $container->set(FieldSerializer\UpdatedAtFieldSerializer::class, new FieldSerializer\UpdatedAtFieldSerializer($validator, $registry, $clock));
        $container->set(FieldSerializer\VersionDataPayloadFieldSerializer::class, new FieldSerializer\VersionDataPayloadFieldSerializer());
        foreach ([FieldSerializer\OneToManyAssociationFieldSerializer::class, FieldSerializer\ManyToOneAssociationFieldSerializer::class] as $class) {
            $container->set($class, new $class($extractor));
        }
        $attributes = static::createStub(CustomFieldService::class);
        $attributes->method('getCustomField')->willReturnCallback(static fn (string $name): Field\JsonField => new Field\JsonField($name, $name));
        $container->set(FieldSerializer\CustomFieldsSerializer::class, new FieldSerializer\CustomFieldsSerializer($registry, $validator, $attributes));
        $container->set(Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder::class, new Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder($attributes, $this->connection));
        $container->set(Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder::class, new Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder($this->connection));
        $container->set(Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder::class, new Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder($this->connection));
        $this->definition = new WriteContractDefinition();
        $this->mapping = new WriteMappingDefinition();
        $this->detail = new WriteDetailDefinition();
        $version = new VersionDefinition();
        $commit = new VersionCommitDefinition();
        $data = new VersionCommitDataDefinition();
        foreach ([$this->definition, $this->mapping, $this->detail, $version, $commit, $data] as $definition) {
            $container->set($definition::class, $definition);
        }
        foreach ([$this->definition, $this->mapping, $this->detail, $version, $commit, $data] as $definition) {
            $registry->register($definition);
        }
        $helper = new Dbal\EntityDefinitionQueryHelper($this->connection);
        $parser = new Search\Parser\SqlQueryParser($helper, $this->connection);
        $interpreter = new Search\Term\SearchTermInterpreter(new Search\Term\Tokenizer(new HtmlSanitizer(), 2), 2);
        $builder = new Dbal\CriteriaQueryBuilder($parser, $helper, $interpreter, new Search\Term\EntityScoreQueryBuilder(), new Dbal\JoinGroupBuilder(), new Dbal\FieldResolver\CriteriaPartResolver($this->connection, $parser));
        $container->set(Dbal\FieldResolver\ManyToOneAssociationFieldResolver::class, new Dbal\FieldResolver\ManyToOneAssociationFieldResolver($helper, $this->connection));
        $container->set(Dbal\FieldResolver\OneToManyAssociationFieldResolver::class, new Dbal\FieldResolver\OneToManyAssociationFieldResolver($this->connection));
        $hydrator = new Dbal\EntityHydrator($container);
        $container->set(Dbal\EntityHydrator::class, $hydrator);
        $this->reader = new Dbal\EntityReader($this->connection, $hydrator, $helper, $parser, $builder, new NullLogger(), new Dbal\CriteriaFieldsResolver());
        $searcher = new Dbal\EntitySearcher($this->connection, $helper, $builder);
        $languages = static::createStub(LanguageLoaderInterface::class);
        $languages->method('loadLanguages')->willReturn([Defaults::LANGUAGE_SYSTEM => ['id' => Defaults::LANGUAGE_SYSTEM, 'code' => 'en-GB']]);
        $this->results = new Write\EntityWriteResultFactory($registry, $this->connection);
        $this->writer = new Write\EntityWriter($extractor, new Dbal\EntityForeignKeyResolver($this->connection, $helper), $this->gateway, $languages, $registry, $this->results);
        $this->dispatcher->addSubscriber(new Write\Validation\LockValidator($this->connection, $registry));
        $this->versions = new VersionManager($this->writer, $this->reader, $searcher, $this->gateway, $this->dispatcher, new Serializer([new JsonSerializableNormalizer()], [new JsonEncoder()]), $registry, $commit, $data, $version, new LockFactory(new FlockStore()), $clock);
        $repository = new EntityRepository($this->definition, $this->reader, $this->versions, $searcher, static::createStub(Search\EntityAggregatorInterface::class), $this->dispatcher, new EntityLoadedEventFactory($registry));
        $container->set('sw_write_product.repository', $repository);
        $sync = new SyncService($this->writer, $this->dispatcher, $registry, $searcher, static::createStub(Search\RequestCriteriaBuilder::class), static::createStub(AclCriteriaValidator::class), new SyncFkResolver($registry, []), new SyncMetricsInstrumentor(static::createStub(Meter::class), new EntityGroupResolver()));
        $this->facade = new RepositoryWriterFacade($registry, $sync, Context::createDefaultContext());
    }

    private function context(): Write\WriteContext
    {
        return Write\WriteContext::createFromContext(Context::createDefaultContext());
    }

    private static function id(int $number): string
    {
        return str_pad(dechex($number), 32, '0', \STR_PAD_LEFT);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class WriteContractDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_write_product';
    }

    public function getEntityClass(): string
    {
        return ArrayEntity::class;
    }

    public function isInheritanceAware(): bool
    {
        return true;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\VersionField(),
            new Field\StringField('name', 'name'),
            new Field\BoolField('active', 'active'),
            new Field\LockedField(),
            new Field\ParentFkField(self::class),
            new Field\ReferenceVersionField(self::class, 'parent_version_id'),
            new Field\ParentAssociationField(self::class, 'id'),
            new Field\BlobField('content', 'content'),
            new Field\JsonField('payload', 'payload'),
            new Field\CustomFields(),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class WriteMappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_write_mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\FkField('product_id', 'productId', WriteContractDefinition::class))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            (new Field\ReferenceVersionField(WriteContractDefinition::class, 'product_version_id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            (new Field\IdField('tag_id', 'tagId'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\StringField('note', 'note'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class WriteDetailDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_write_detail';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return WriteContractDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            (new Field\FkField('product_id', 'productId', WriteContractDefinition::class))->addFlags(new Field\Flag\Required()),
            new Field\ReferenceVersionField(WriteContractDefinition::class, 'product_version_id'),
            new Field\ManyToOneAssociationField('product', 'product_id', WriteContractDefinition::class, 'id'),
            new Field\StringField('name', 'name'),
        ]);
    }
}

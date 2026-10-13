<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write;
use Shopware\Core\Framework\Event\NestedEventDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Shopware\Core\System\CustomEntity\CustomEntityCollection;
use Shopware\Core\System\CustomEntity\CustomEntityDefinition;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Schema\SchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\SystemSqlFixture;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class CustomEntitySqlContractTest extends TestCase
{
    use EnvTestBehaviour;

    private SystemSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    private CustomEntitySchemaUpdater $updater;

    private CustomEntityPersister $persister;

    protected function setUp(): void
    {
        $this->fixture = new SystemSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-12 12:13:14.123 UTC');
        $this->updater = new CustomEntitySchemaUpdater($this->connection, new LockFactory(new FlockStore()), new SchemaUpdater(new CustomEntityNameValidator(), $this->connection));
        $this->persister = new CustomEntityPersister($this->connection, new ArrayAdapter(), $this->clock);
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testAppLifecycleCreatesChangesAndRemovesSchemaAndData(): void
    {
        $app = $this->app();
        $lifecycle = $this->lifecycle();
        static::assertNotNull($lifecycle->updateApp($app));
        static::assertTrue(TableHelper::tableExists($this->connection, 'ce_system'));
        static::assertFalse($lifecycle->allowsDisabling($app));
        static::assertTrue($lifecycle->canRemoveAppData($app));
        $metadata = $this->connection->fetchAssociative('SELECT sw_uuid_hex(id) AS id, created_at FROM custom_entity');
        static::assertIsArray($metadata);
        $this->fixture->insert('currency', ['id' => SystemSqlContractTest::RANGE, 'iso_code' => 'EUR', 'factor' => '1']);
        $this->fixture->insert('ce_system', ['id' => SystemSqlContractTest::CUSTOMER, 'title' => 'retained', 'payload' => '{"false":false,"array":[1,2]}', 'currency_id' => SystemSqlContractTest::RANGE, 'created_at' => '2026-10-12 12:13:14.123']);
        static::assertFalse($lifecycle->canRemoveAppData($app));
        $fields = json_decode($this->connection->fetchOne('SELECT fields FROM custom_entity'), true, 512, \JSON_THROW_ON_ERROR);
        $fields[] = ['name' => 'note', 'type' => 'string', 'required' => false];
        $this->persister->update(['ce_system' => ['name' => 'ce_system', 'fields' => $fields, 'flags' => []]], AppEntity::class, $app->getId());
        $this->updater->update();
        static::assertTrue(TableHelper::columnExists($this->connection, 'ce_system', 'note'));
        static::assertSame('retained', $this->connection->fetchOne('SELECT title FROM ce_system'));
        static::assertEquals(['false' => false, 'array' => [1, 2]], json_decode($this->connection->fetchOne('SELECT payload FROM ce_system'), true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($metadata['id'], $this->connection->fetchOne('SELECT sw_uuid_hex(id) FROM custom_entity'));
        static::assertSame($metadata['created_at'], $this->connection->fetchOne('SELECT created_at FROM custom_entity'));
        $this->updater->update();
        static::assertSame('retained', $this->connection->fetchOne('SELECT title FROM ce_system'));
        $lifecycle->removeApp($app, Context::createDefaultContext(), keepUserData: false);
        static::assertFalse(TableHelper::tableExists($this->connection, 'ce_system'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM custom_entity'));
        $this->updater->update();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ce_system'));
    }

    public function testKeepUserDataDetachesTheAppWithoutDroppingItsRows(): void
    {
        $app = $this->app();
        $lifecycle = $this->lifecycle();
        $lifecycle->updateApp($app);
        $this->fixture->insert('ce_system', ['id' => SystemSqlContractTest::CUSTOMER, 'title' => 'kept', 'created_at' => '2026-10-12 12:13:14.123']);
        $lifecycle->removeApp($app, Context::createDefaultContext(), keepUserData: true);
        static::assertNull($this->connection->fetchOne('SELECT app_id FROM custom_entity'));
        static::assertSame('2026-10-12 12:13:14.123', $this->connection->fetchOne('SELECT deleted_at FROM custom_entity'));
        $this->updater->update();
        static::assertSame('kept', $this->connection->fetchOne('SELECT title FROM ce_system'));
        $lifecycle->removeApp($app, Context::createDefaultContext(), keepUserData: false);
        static::assertSame('kept', $this->connection->fetchOne('SELECT title FROM ce_system'));
    }

    public function testRejectedMetadataReplacementRetainsThePreviousDefinition(): void
    {
        $this->persister->update(['ce_system' => ['name' => 'ce_system', 'fields' => [], 'flags' => []]]);
        $id = $this->connection->fetchOne('SELECT sw_uuid_hex(id) FROM custom_entity');
        try {
            $this->persister->update(['ce_system' => ['name' => 'ce_system', 'fields' => [], 'flags' => [\NAN]]]);
            static::fail('Invalid JSON must reject the update');
        } catch (\JsonException) {
            static::assertSame($id, $this->connection->fetchOne('SELECT sw_uuid_hex(id) FROM custom_entity'));
        }
    }

    public function testRestrictForeignKeyRejectsDeletionAndConnectionRecovers(): void
    {
        $this->lifecycle()->updateApp($this->app());
        $this->fixture->insert('currency', ['id' => SystemSqlContractTest::RANGE, 'iso_code' => 'EUR', 'factor' => '1']);
        $this->fixture->insert('ce_system', ['id' => SystemSqlContractTest::CUSTOMER, 'title' => 'restricted', 'currency_id' => SystemSqlContractTest::RANGE, 'created_at' => '2026-10-12 12:13:14.123']);
        try {
            $this->connection->transactional(fn () => $this->connection->executeStatement('DELETE FROM currency'));
            static::fail('The generated restrict constraint must protect its reference');
        } catch (ForeignKeyConstraintViolationException) {
            static::assertSame('restricted', $this->connection->fetchOne('SELECT title FROM ce_system'));
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM currency'));
        }
    }

    public function testRuntimeAndMaintenanceFactoriesUseTheForeignKeyExceptionContract(): void
    {
        $app = $this->app();
        $this->lifecycle()->updateApp($app);
        $this->fixture->insert('currency', ['id' => SystemSqlContractTest::RANGE, 'iso_code' => 'EUR', 'factor' => '1']);
        $this->fixture->insert('ce_system', ['id' => SystemSqlContractTest::CUSTOMER, 'title' => 'restricted', 'currency_id' => SystemSqlContractTest::RANGE, 'created_at' => '2026-10-12 12:13:14.123']);
        $this->setEnvVars(['DATABASE_URL' => getenv('DATABASE_CONTRACT_URL'), 'DATABASE_REPLICA_0_URL' => '']);
        $connections = [
            DatabaseConnectionFactory::create(),
            \Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory::createConnection(DatabaseConnectionInformation::fromEnv()),
        ];
        foreach ($connections as $connection) {
            $table = $this->fixture->schema->isPostgreSQL() ? 'sw_system_contract.currency' : 'currency';
            try {
                $connection->transactional(fn () => $connection->executeStatement('DELETE FROM ' . $table));
                static::fail('Both connection factories must surface the foreign key violation');
            } catch (ForeignKeyConstraintViolationException $exception) {
                static::assertSame($this->fixture->schema->isPostgreSQL() ? '23001' : '23000', $exception->getSQLState());
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table));
            } finally {
                $connection->close();
            }
        }
    }

    private function app(): AppEntity
    {
        $this->fixture->insert('app', ['id' => SystemSqlContractTest::CHANNEL, 'name' => 'SystemContract', 'active' => 1]);
        $app = new AppEntity();
        $app->setId(SystemSqlContractTest::CHANNEL);
        $app->setName('SystemContract');

        return $app;
    }

    private function lifecycle(): CustomEntityLifecycleService
    {
        return new CustomEntityLifecycleService(
            $this->persister,
            $this->updater,
            new CustomEntityEnrichmentService(new AdminUiXmlSchemaValidator()),
            new CustomEntityXmlSchemaValidator(new CustomEntityNameValidator()),
            new StaticSourceResolver(['SystemContract' => new Filesystem(__DIR__ . '/_fixtures/app')]),
            $this->connection,
            $this->repository(),
            $this->clock
        );
    }

    /**
     * Real production CustomEntityDefinition with the DAL reader, writer and version manager.
     *
     * @return EntityRepository<CustomEntityCollection>
     */
    private function repository(): EntityRepository
    {
        $connection = $this->connection;
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $version = new VersionDefinition();
        $commit = new VersionCommitDefinition();
        $commitData = new VersionCommitDataDefinition();
        $definitions = [new CustomEntityDefinition(), new AppDefinition(), new PluginDefinition(), $version, $commit, $commitData];
        foreach ($definitions as $definition) {
            $container->set($definition::class, $definition);
        }
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
        $validator = Validation::createValidator();
        $clock = $this->clock;
        $dispatcher = new NestedEventDispatcher(new EventDispatcher());
        $gateway = new Dbal\EntityWriteGateway(250, $connection, $dispatcher, new Dbal\ExceptionHandlerRegistry([]), $registry);
        $extractor = new Write\WriteCommandExtractor($gateway, $registry);
        foreach ([FieldSerializer\IdFieldSerializer::class, FieldSerializer\FkFieldSerializer::class, FieldSerializer\DateTimeFieldSerializer::class, FieldSerializer\IntFieldSerializer::class, FieldSerializer\JsonFieldSerializer::class, FieldSerializer\BoolFieldSerializer::class] as $class) {
            $container->set($class, new $class($validator, $registry));
        }
        $container->set(FieldSerializer\StringFieldSerializer::class, new FieldSerializer\StringFieldSerializer($validator, $registry, new HtmlSanitizer()));
        $container->set(FieldSerializer\VersionDataPayloadFieldSerializer::class, new FieldSerializer\VersionDataPayloadFieldSerializer());
        $container->set(FieldSerializer\VersionFieldSerializer::class, new FieldSerializer\VersionFieldSerializer());
        $container->set(FieldSerializer\ReferenceVersionFieldSerializer::class, new FieldSerializer\ReferenceVersionFieldSerializer());
        $container->set(FieldSerializer\CreatedAtFieldSerializer::class, new FieldSerializer\CreatedAtFieldSerializer($validator, $registry, $clock));
        $container->set(FieldSerializer\UpdatedAtFieldSerializer::class, new FieldSerializer\UpdatedAtFieldSerializer($validator, $registry, $clock));
        foreach ([FieldSerializer\OneToManyAssociationFieldSerializer::class, FieldSerializer\ManyToOneAssociationFieldSerializer::class, FieldSerializer\OneToOneAssociationFieldSerializer::class] as $class) {
            $container->set($class, new $class($extractor));
        }
        $container->set(Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder::class, new Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder($connection));
        $container->set(Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder::class, new Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder($connection));
        $helper = new Dbal\EntityDefinitionQueryHelper($connection);
        $parser = new Search\Parser\SqlQueryParser($helper, $connection);
        $builder = new Dbal\CriteriaQueryBuilder($parser, $helper, new Search\Term\SearchTermInterpreter(new Search\Term\Tokenizer(new HtmlSanitizer(), 2), 2), new Search\Term\EntityScoreQueryBuilder(), new Dbal\JoinGroupBuilder(), new Dbal\FieldResolver\CriteriaPartResolver($connection, $parser));
        $container->set(Dbal\FieldResolver\ManyToOneAssociationFieldResolver::class, new Dbal\FieldResolver\ManyToOneAssociationFieldResolver($helper, $connection));
        $container->set(Dbal\FieldResolver\OneToManyAssociationFieldResolver::class, new Dbal\FieldResolver\OneToManyAssociationFieldResolver($connection));
        $hydrator = new Dbal\EntityHydrator($container);
        $container->set(Dbal\EntityHydrator::class, $hydrator);
        $reader = new Dbal\EntityReader($connection, $hydrator, $helper, $parser, $builder, new NullLogger(), new Dbal\CriteriaFieldsResolver());
        $searcher = new Dbal\EntitySearcher($connection, $helper, $builder);
        $languages = static::createStub(LanguageLoaderInterface::class);
        $languages->method('loadLanguages')->willReturn([Defaults::LANGUAGE_SYSTEM => ['id' => Defaults::LANGUAGE_SYSTEM, 'code' => 'en-GB']]);
        $writer = new Write\EntityWriter($extractor, new Dbal\EntityForeignKeyResolver($connection, $helper), $gateway, $languages, $registry, new Write\EntityWriteResultFactory($registry, $connection));
        $versions = new VersionManager($writer, $reader, $searcher, $gateway, $dispatcher, new Serializer([new JsonSerializableNormalizer()], [new JsonEncoder()]), $registry, $commit, $commitData, $version, new LockFactory(new FlockStore()), $clock);
        $aggregator = static::createStub(Search\EntityAggregatorInterface::class);
        $loadedEvents = new EntityLoadedEventFactory($registry);
        /** @var EntityRepository<CustomEntityCollection> $repository */
        $repository = new EntityRepository($definitions[0], $reader, $versions, $searcher, $aggregator, $dispatcher, $loadedEvents);

        return $repository;
    }
}

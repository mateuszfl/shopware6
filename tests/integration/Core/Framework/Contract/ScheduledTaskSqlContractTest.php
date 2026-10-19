<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Doctrine\Messenger\DoctrineTransportFactory;
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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthGateway;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Tests\Integration\Core\Framework\Contract\_fixtures\FrameworkSqlFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
class ScheduledTaskSqlContractTest extends TestCase
{
    private FrameworkSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        foreach (Yaml::parseFile(__DIR__ . '/../../../../../src/Core/Framework/Resources/config/packages/feature.yaml')['shopware']['feature']['flags'] as $flag) {
            Feature::registerFeature($flag['name'], $flag);
        }
        $this->fixture = new FrameworkSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-18 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testSchedulerQueuesRealDalTaskAndWorkerRetriesIt(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            $repository = $this->repository();
            $id = Uuid::randomHex();
            $repository->create([[
                'id' => $id,
                'name' => FrameworkContractScheduledTask::getTaskName(),
                'scheduledTaskClass' => FrameworkContractScheduledTask::class,
                'runInterval' => 60,
                'defaultRunInterval' => 60,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => $this->clock->now()->modify('-1 second'),
            ]], Context::createDefaultContext());
            $transport = (new DoctrineTransportFactory($this->connection))->createTransport('doctrine://default', ['auto_setup' => false], new PhpSerializer());
            $senders = new ServiceLocator(['async' => static fn () => $transport]);
            $senderBus = new MessageBus([new SendMessageMiddleware(new SendersLocator([FrameworkContractScheduledTask::class => ['async']], $senders))]);
            $scheduler = new TaskScheduler($repository, $senderBus, new ParameterBag(), new NullLogger(), 12, $this->clock);
            $scheduler->queueScheduledTasks();
            static::assertSame(ScheduledTaskDefinition::STATUS_QUEUED, $this->connection->fetchOne('SELECT status FROM scheduled_task'));
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
            $handler = new FrameworkContractScheduledHandler($repository, new NullLogger());
            $handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($repository, new NullLogger(), $this->clock));
            $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([FrameworkContractScheduledTask::class => [$handler]]))]);
            $events = new EventDispatcher();
            $events->addSubscriber(new SendFailedMessageForRetryListener($senders, new ServiceLocator(['async' => static fn () => new MultiplierRetryStrategy(1, 0)])));
            $failedStates = [];
            $events->addListener(WorkerMessageFailedEvent::class, function () use (&$failedStates): void {
                $failedStates[] = $this->connection->fetchOne('SELECT status FROM scheduled_task');
            }, -100);
            $worker = new Worker(['async' => $transport], $bus, $events, new NullLogger());
            $events->addListener(WorkerMessageHandledEvent::class, static fn () => $worker->stop());
            $worker->run(['sleep' => 1000, 'time_limit' => 5]);
            static::assertSame(2, $handler->attempts);
            static::assertSame([ScheduledTaskDefinition::STATUS_FAILED], $failedStates);
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
            $state = $this->connection->fetchAssociative('SELECT status, last_execution_time, next_execution_time FROM scheduled_task');
            static::assertIsArray($state);
            static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $state['status']);
            static::assertSame('2026-10-18 12:00:00.000', (new \DateTimeImmutable($state['last_execution_time']))->format(Defaults::STORAGE_DATE_TIME_FORMAT));
            static::assertSame('2026-10-18 12:00:59.000', (new \DateTimeImmutable($state['next_execution_time']))->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        });
    }

    public function testRegistryAndHealthUseRealDalMetadata(): void
    {
        $repository = $this->repository();
        $registry = new TaskRegistry([new FrameworkContractScheduledTask()], $repository, new ParameterBag(), $this->clock);
        $registry->registerTasks();
        static::assertCount(1, $registry->getAllTasks(Context::createDefaultContext()));
        $registry->registerTasks();
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM scheduled_task'));
        $this->connection->executeStatement('UPDATE scheduled_task SET next_execution_time = :past', ['past' => '2026-10-18 11:59:10']);
        $health = new ScheduledTaskHealthGateway($this->connection);
        static::assertSame(50, $health->getMaxLatenessSeconds($this->clock->now()));
        $this->connection->executeStatement('UPDATE scheduled_task SET status = :status', ['status' => ScheduledTaskDefinition::STATUS_FAILED]);
        static::assertSame(1, $health->countFailed());
        static::assertSame(0, $health->getMaxLatenessSeconds($this->clock->now()));
    }

    /**
     * @return EntityRepository<ScheduledTaskCollection>
     */
    private function repository(): EntityRepository
    {
        $connection = $this->connection;
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $version = new VersionDefinition();
        $commit = new VersionCommitDefinition();
        $commitData = new VersionCommitDataDefinition();
        $definitions = [new ScheduledTaskDefinition(), $version, $commit, $commitData];
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
        /** @var EntityRepository<ScheduledTaskCollection> $repository */
        $repository = new EntityRepository($definitions[0], $reader, $versions, $searcher, $aggregator, $dispatcher, $loadedEvents);

        return $repository;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class FrameworkContractScheduledTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'contract.scheduled';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class FrameworkContractScheduledHandler extends ScheduledTaskHandler
{
    public int $attempts = 0;

    public function run(): void
    {
        if (++$this->attempts === 1) {
            throw new \RuntimeException('Retry the first execution');
        }
    }
}

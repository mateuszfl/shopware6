<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Indexing\FlowBuilder;
use Shopware\Core\Content\Flow\Indexing\FlowPayloadUpdater;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\ProductVariantsSubscriber;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\Infrastructure\Command\UpdatePathCommand;
use Shopware\Core\Content\Media\Infrastructure\Path\SqlMediaLocationBuilder;
use Shopware\Core\Content\Media\Infrastructure\Path\SqlMediaPathStorage;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Newsletter\Subscriber\NewsletterRecipientSalutationSubscriber;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Content\Product\Api\ProductNumberFkResolver;
use Shopware\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTaskHandler;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\KeywordLoader;
use Shopware\Core\Content\Product\Stock\OrderStockSubscriber;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGeneration;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Seo\HreflangLoader;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlRequestContext;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Sitemap\Provider\LandingPageUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class ContentSqlContractTest extends TestCase
{
    private Connection $connection;

    private bool $postgres;

    private EventDispatcher $dispatcher;

    /**
     * @var list<string>
     */
    private array $tables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->postgres = (new DatabasePlatformResolver($this->connection))->capabilities()->supportsJsonb;
        if ($this->postgres) {
            static::assertStringStartsWith('18.6 ', (string) $this->connection->fetchOne('SHOW server_version'));
            $this->connection->executeStatement('CREATE SCHEMA sw_content_contract');
            $this->connection->executeStatement('SET search_path TO sw_content_contract');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        } else {
            static::assertSame('shopware_json_test', $this->connection->getDatabase());
            static::assertMatchesRegularExpression('/^(8\.4\.11|11\.8\.9-MariaDB)/', (string) $this->connection->fetchOne('SELECT VERSION()'));
            $this->connection->executeStatement('SET SESSION sql_mode = \'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY\'');
        }
        (new DatabaseFunctionInstaller())->install($this->connection);
        $this->dispatcher = new EventDispatcher();
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        foreach (array_reverse($this->tables) as $table) {
            $this->connection->executeStatement('DROP TABLE ' . $this->connection->quoteSingleIdentifier($table));
        }
        if ($this->postgres) {
            $this->connection->executeStatement('DROP SCHEMA sw_content_contract CASCADE');
        }
        $this->connection->close();
    }

    /**
     * @return iterable<string, array{mixed, mixed, bool|null}>
     */
    public static function overlapCases(): iterable
    {
        yield 'two arrays' => [['one', 'two'], ['two', 'three'], true];
        yield 'no overlap' => [['one'], ['two'], false];
        yield 'scalar array' => ['one', ['one'], true];
        yield 'array scalar' => [['one'], 'one', true];
        yield 'numeric types' => [[1], [1.0], true];
        yield 'typed string' => [[1], ['1'], false];
        yield 'typed boolean' => [[true], [1], false];
        yield 'JSON null' => [null, [null], true];
        yield 'SQL null left' => ['sql-null', [null], null];
        yield 'SQL null right' => [[null], 'sql-null', null];
        yield 'nested arrays are immediate values' => [[[1, 2]], [1], false];
        yield 'equal nested array' => [[[1, 2]], [[1, 2]], true];
        yield 'partial nested array' => [[[1, 2]], [[1]], false];
        yield 'objects share pair' => [['a' => 1, 'b' => 2], ['a' => 1, 'c' => 3], true];
        yield 'objects differ' => [['a' => 1], ['a' => '1'], false];
        yield 'object as array member' => [[['a' => 1]], ['a' => 1], true];
        yield 'array object needs full equality' => [[['a' => 1, 'b' => 2]], ['a' => 1], false];
        yield 'empty arrays' => [[], [], false];
        yield 'empty objects' => [(object) [], (object) [], false];
        yield 'unicode' => [['ä'], ['Ä'], false];
    }

    #[DataProvider('overlapCases')]
    public function testJsonOverlapPreservesImmediateTypedValues(mixed $left, mixed $right, ?bool $expected): void
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $actual = $this->connection->fetchOne('SELECT ' . $dialect->jsonOverlaps(':left', ':right'), [
            'left' => $left === 'sql-null' ? null : json_encode($left, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION),
            'right' => $right === 'sql-null' ? null : json_encode($right, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION),
        ], ['left' => $left === 'sql-null' ? ParameterType::NULL : ParameterType::STRING, 'right' => $right === 'sql-null' ? ParameterType::NULL : ParameterType::STRING]);
        static::assertSame($expected, $actual === null ? null : (bool) $actual);
    }

    public function testCmsMediaSearchHandlesVersionsWildcardsAndEmptyBatches(): void
    {
        $this->cmsTables();
        $live = Defaults::LIVE_VERSION;
        $draft = self::id(99);
        $this->insert('cms_slot', ['id' => self::id(1), 'version_id' => $live, 'type' => 'image']);
        $this->insert('cms_slot', ['id' => self::id(1), 'version_id' => $draft, 'type' => 'text']);
        $this->insert('cms_slot_translation', ['cms_slot_id' => self::id(1), 'cms_slot_version_id' => $live, 'config' => ['media' => ['value' => self::id(10)]]]);
        $this->insert('cms_slot_translation', ['cms_slot_id' => self::id(1), 'cms_slot_version_id' => $draft, 'config' => ['media' => ['value' => self::id(11)]]]);
        $this->insert('cms_slot', ['id' => self::id(2), 'version_id' => $live, 'type' => 'image-gallery']);
        $this->insert('cms_slot_translation', ['cms_slot_id' => self::id(2), 'cms_slot_version_id' => $live, 'config' => ['sliderItems' => ['value' => [['missing' => true], ['mediaId' => self::id(12)], ['mediaId' => self::id(13)], ['mediaId' => self::id(12)]]]]]);
        $this->insert('cms_slot_translation', ['cms_slot_id' => self::id(55), 'cms_slot_version_id' => $live, 'config' => ['media' => ['value' => self::id(14)]]]);
        $this->insert('cms_slot', ['id' => self::id(3), 'version_id' => $live, 'type' => 'image']);
        $this->insert('cms_slot_translation', ['cms_slot_id' => self::id(3), 'cms_slot_version_id' => $live, 'config' => null]);
        $this->insert('product_translation', ['slot_config' => ['incomplete' => [], 'slot.a"' => ['media' => ['value' => self::id(15)]]]]);
        $this->insert('category_translation', ['slot_config' => ['first' => [], 'second' => ['sliderItems' => ['value' => [['mediaId' => self::id(16)], ['wrong' => self::id(17)]]]]]]);
        $this->insert('landing_page_translation', ['slot_config' => ['landing' => ['media' => ['value' => self::id(18)]]]]);
        $event = new UnusedMediaSearchEvent(array_map(self::id(...), range(10, 18)), Context::createDefaultContext());
        $subscriber = new UnusedMediaSubscriber($this->connection);
        $subscriber->removeUsedMedia($event);
        static::assertSame([self::id(11), self::id(14), self::id(17)], $event->getUnusedIds());
        $event->markAsUsed($event->getUnusedIds());
        $subscriber->removeUsedMedia($event);
        static::assertSame([], $event->getUnusedIds());
    }

    public function testCustomMediaFieldsUseLiteralKeysAndTranslatedTables(): void
    {
        $this->create('custom_field_set', 'id {binary} PRIMARY KEY');
        $this->create('custom_field_set_relation', 'set_id {binary}, entity_name VARCHAR(255)');
        $this->create('custom_field', 'name VARCHAR(255), set_id {binary}, type VARCHAR(64), config {json}');
        $this->create('product_translation', 'custom_fields {json}');
        $this->create('media', 'custom_fields {json}');
        $registry = $this->registry([new ContentTranslatedProductDefinition(), new ContentProductTranslationDefinition(), new ContentMediaDefinition()]);
        $key = 'literal.key"\\';
        $this->insert('custom_field_set', ['id' => self::id(1)]);
        foreach (['product', 'media'] as $entity) {
            $this->insert('custom_field_set_relation', ['set_id' => self::id(1), 'entity_name' => $entity]);
        }
        foreach ([[$key, ['customFieldType' => 'media']], ['single', ['entity' => 'media', 'componentName' => 'sw-entity-single-select']], ['multi', ['entity' => 'media', 'componentName' => 'sw-entity-multi-id-select']]] as [$name, $config]) {
            $this->insert('custom_field', ['name' => $name, 'set_id' => self::id(1), 'type' => 'select', 'config' => $config]);
        }
        $this->insert('product_translation', ['custom_fields' => [$key => self::id(10), 'literal' => ['key"\\' => self::id(19)], 'single' => self::id(11), 'multi' => [self::id(12), self::id(13), self::id(12)]]]);
        $this->insert('media', ['custom_fields' => ['multi' => self::id(14)]]);
        $this->insert('media', ['custom_fields' => ['multi' => null]]);
        $this->insert('media', ['custom_fields' => ['multi' => [[self::id(15)], ['id' => self::id(16)], true, 17]]]);
        $this->insert('media', ['custom_fields' => null]);
        $event = new UnusedMediaSearchEvent(array_map(self::id(...), range(10, 19)), Context::createDefaultContext());
        $subscriber = new CustomFieldsUnusedMediaSubscriber($this->connection, $registry);
        $subscriber->removeUsedMedia($event);
        static::assertSame(array_map(self::id(...), range(15, 19)), $event->getUnusedIds());
        $event->markAsUsed($event->getUnusedIds());
        $subscriber->removeUsedMedia($event);
        static::assertSame([], $event->getUnusedIds());
    }

    public function testPurgerDeletesOnlyUnusedMediaAcrossFolderAndKeysetBatches(): void
    {
        $this->cmsTables();
        $this->create('media_default_folder', 'id {binary} PRIMARY KEY, entity VARCHAR(255)');
        $this->create('media_folder', 'id {binary} PRIMARY KEY, parent_id {binary}, default_folder_id {binary}, name VARCHAR(255), created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL');
        $this->create('media', 'id {binary} PRIMARY KEY, media_folder_id {binary}, created_at TIMESTAMP NULL, uploaded_at TIMESTAMP NULL, updated_at TIMESTAMP NULL');
        $this->create('content_media_usage', 'id {binary} PRIMARY KEY, media_id {binary}, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL');
        $this->insert('media_default_folder', ['id' => self::id(1), 'entity' => 'product']);
        $this->insert('media_folder', ['id' => self::id(2), 'default_folder_id' => self::id(1), 'name' => 'root']);
        $this->insert('media_folder', ['id' => self::id(3), 'parent_id' => self::id(2), 'name' => 'child']);
        $this->insert('media_folder', ['id' => self::id(4), 'parent_id' => self::id(3), 'name' => 'grandchild']);
        $this->insert('media_folder', ['id' => self::id(5), 'name' => 'outside']);
        foreach (range(10, 17) as $id) {
            $this->insert('media', ['id' => self::id($id), 'media_folder_id' => self::id($id === 17 ? 5 : 2 + $id % 3), 'created_at' => '2026-01-01 00:00:00', 'uploaded_at' => $id === 16 ? '2026-10-08 00:00:00' : '2026-01-01 00:00:00']);
        }
        $this->insert('content_media_usage', ['id' => self::id(20), 'media_id' => self::id(10)]);
        $this->insert('cms_slot', ['id' => self::id(21), 'version_id' => Defaults::LIVE_VERSION, 'type' => 'image-gallery']);
        $this->insert('cms_slot_translation', ['cms_slot_id' => self::id(21), 'cms_slot_version_id' => Defaults::LIVE_VERSION, 'config' => ['sliderItems' => ['value' => [['mediaId' => self::id(11)], ['mediaId' => self::id(12)]]]]]);
        $this->dispatcher->addSubscriber(new UnusedMediaSubscriber($this->connection));
        $repository = $this->mediaRepository();
        $purger = new UnusedMediaPurger($repository, $this->connection, $this->dispatcher, new MockClock('2026-10-08'));
        $unused = [];
        foreach ($purger->getNotUsedMedia(limit: 2, gracePeriodDays: 2, folderEntity: 'product') as $batch) {
            $unused = [...$unused, ...array_map(static fn ($media) => $media->getId(), $batch)];
        }
        static::assertSame(array_map(self::id(...), [13, 14, 15]), $unused);
        static::assertSame(3, $purger->deleteNotUsedMedia(limit: 2, gracePeriodDays: 2, folderEntity: 'product'));
        static::assertSame(array_map(self::id(...), [10, 11, 12, 16, 17]), $this->hexIds('media'));
        static::assertSame(0, $purger->deleteNotUsedMedia(limit: 2, gracePeriodDays: 2, folderEntity: 'product'));
    }

    public function testFlowPayloadRoundTripAndStableSequenceTies(): void
    {
        $this->create('flow', 'id {binary} PRIMARY KEY, active SMALLINT, invalid SMALLINT DEFAULT 0, payload {blob}, event_name VARCHAR(255), name VARCHAR(255), priority INT');
        $this->create('flow_sequence', 'id {binary} PRIMARY KEY, flow_id {binary}, parent_id {binary}, rule_id {binary}, app_flow_action_id {binary}, display_group INT, position INT, action_name VARCHAR(255), config {json}, true_case SMALLINT');
        foreach ([1, 2, 3] as $id) {
            $this->insert('flow', ['id' => self::id($id), 'active' => $id === 3 ? 0 : 1, 'event_name' => 'test.event', 'name' => 'flow', 'priority' => 1]);
        }
        foreach ([12, 11] as $id) {
            $this->insert('flow_sequence', ['id' => self::id($id), 'flow_id' => self::id(1), 'display_group' => 1, 'position' => 1, 'action_name' => 'action.test', 'config' => ['unicode' => 'żółć'], 'true_case' => 0]);
        }
        $cache = static::createMock(CacheInvalidator::class);
        $cache->expects($this->exactly(2))->method('invalidate');
        $updater = new FlowPayloadUpdater($this->connection, new FlowBuilder(), $cache);
        $first = $updater->update([self::id(1), self::id(2), self::id(3)]);
        static::assertSame([self::id(1), self::id(2)], array_keys($first));
        static::assertFalse($first[self::id(1)]['invalid']);
        $second = $updater->update([self::id(1), self::id(2)]);
        static::assertSame($first, $second);
        static::assertSame([], $updater->update([]));
        $loaded = (new FlowLoader($this->connection, new NullLogger()))->load();
        static::assertCount(2, $loaded['test.event']);
        static::assertSame([self::id(11), self::id(12)], array_keys($loaded['test.event'][0]['payload']->getFlat()));
        static::assertSame([], $loaded['test.event'][1]['payload']->getFlat());
    }

    public function testSeoPersistenceProtectsManualPathsAndResolvesBinaryIds(): void
    {
        $this->seoTable();
        $repository = StaticEntityRepository::of(SeoUrlCollection::class, [], new SeoUrlDefinition());
        $persister = new SeoUrlPersister($this->connection, $repository, $this->dispatcher, new MockClock('2026-10-08'));
        $channel = new SalesChannelEntity();
        $channel->setId(self::id(50));
        $context = Context::createDefaultContext();
        $url = ['foreignKey' => self::id(1), 'salesChannelId' => self::id(50), 'pathInfo' => '/detail/' . self::id(1), 'seoPathInfo' => 'manual', 'isModified' => true, 'isDeleted' => false];
        $persister->updateSeoUrls($context, 'detail', [self::id(1)], [$url], $channel);
        $persister->updateSeoUrls($context, 'detail', [self::id(1)], [array_replace($url, ['seoPathInfo' => 'automatic', 'isModified' => false])], $channel);
        static::assertSame(['manual'], $this->connection->fetchFirstColumn('SELECT seo_path_info FROM seo_url'));
        $persister->forceUpdateSeoUrls($context, 'detail', [self::id(1)], [array_replace($url, ['seoPathInfo' => 'automatic', 'isModified' => false])], $channel);
        $resolved = (new SeoResolver($this->connection))->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, self::id(50), '/manual'));
        static::assertFalse($resolved->isCanonical);
        static::assertSame('/automatic', $resolved->canonicalPathInfo);
        static::assertSame(16, \strlen($resolved->id ?? ''));
        $persister->updateSeoUrls($context, 'detail', [self::id(1)], [], $channel);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM seo_url WHERE is_deleted = 1'));
    }

    public function testSeoPathTakeoverRecoversPreviousCanonicalFromBinaryRows(): void
    {
        $this->seoTable();
        $repository = StaticEntityRepository::of(SeoUrlCollection::class, [], new SeoUrlDefinition());
        $persister = new SeoUrlPersister($this->connection, $repository, $this->dispatcher, new MockClock('2026-10-08'));
        $channel = new SalesChannelEntity();
        $channel->setId(self::id(50));
        $context = Context::createDefaultContext();
        $url = ['foreignKey' => self::id(1), 'salesChannelId' => self::id(50), 'pathInfo' => '/a', 'seoPathInfo' => 'first', 'isDeleted' => false];
        $persister->updateSeoUrls($context, 'detail', [self::id(1)], [$url], $channel);
        $persister->updateSeoUrls($context, 'detail', [self::id(1)], [array_replace($url, ['seoPathInfo' => 'second'])], $channel);
        $persister->updateSeoUrls($context, 'detail', [self::id(2)], [array_replace($url, ['foreignKey' => self::id(2), 'pathInfo' => '/b', 'seoPathInfo' => 'second'])], $channel);
        static::assertSame(['first', 'second'], $this->connection->fetchFirstColumn('SELECT seo_path_info FROM seo_url WHERE is_canonical = 1 ORDER BY seo_path_info'));
        static::assertSame('/a', (new SeoResolver($this->connection))->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, self::id(50), '/first'))->pathInfo);
    }

    public function testStockUsesUpdatedStockForAvailabilityAndPreservesDraft(): void
    {
        $this->create('product', 'id {binary}, version_id {binary}, parent_id {binary}, stock INT, sales INT, available_stock INT, available SMALLINT, is_closeout SMALLINT, min_purchase INT, updated_at TIMESTAMP NULL, PRIMARY KEY(id, version_id)');
        $live = Defaults::LIVE_VERSION;
        $draft = self::id(99);
        $this->insert('product', ['id' => self::id(1), 'version_id' => $live, 'stock' => 5, 'sales' => 0, 'available_stock' => 5, 'available' => 1, 'is_closeout' => 1, 'min_purchase' => 2]);
        $this->insert('product', ['id' => self::id(1), 'version_id' => $draft, 'stock' => 100, 'sales' => 0, 'available_stock' => 100, 'available' => 1, 'is_closeout' => 1, 'min_purchase' => 2]);
        $this->insert('product', ['id' => self::id(2), 'version_id' => $live, 'parent_id' => self::id(1), 'stock' => 1, 'sales' => 0, 'available_stock' => 1, 'available' => 1]);
        $storage = new StockStorage($this->connection, $this->dispatcher);
        $storage->alter([new StockAlteration(self::id(10), self::id(1), 0, 4)], Context::createDefaultContext());
        $row = $this->connection->fetchAssociative('SELECT stock, sales, available_stock, available FROM product WHERE id = :id AND version_id = :version', ['id' => Uuid::fromHexToBytes(self::id(1)), 'version' => Uuid::fromHexToBytes($live)], ['id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]);
        static::assertEquals(['stock' => 1, 'sales' => 4, 'available_stock' => 1, 'available' => 0], $row);
        $storage->index([self::id(2)], Context::createDefaultContext());
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT available FROM product WHERE parent_id IS NOT NULL'));
        static::assertSame(100, (int) $this->connection->fetchOne('SELECT stock FROM product WHERE version_id = :version', ['version' => Uuid::fromHexToBytes($draft)], ['version' => ParameterType::BINARY]));
        $storage->alter([new StockAlteration(self::id(10), self::id(1), 4, 0)], Context::createDefaultContext());
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT available FROM product WHERE id = :id AND version_id = :version', ['id' => Uuid::fromHexToBytes(self::id(1)), 'version' => Uuid::fromHexToBytes($live)], ['id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]));
    }

    public function testKeywordCleanupAndDetectionAcrossMultipleBatches(): void
    {
        $this->create('product_keyword_dictionary', 'id {binary} PRIMARY KEY, keyword VARCHAR(255), reversed VARCHAR(255), language_id {binary}');
        $this->create('product_search_keyword', 'keyword VARCHAR(255)');
        foreach (range(1, 1003) as $id) {
            $this->insert('product_keyword_dictionary', ['id' => self::id($id), 'keyword' => 'remove' . $id, 'reversed' => strrev('remove' . $id), 'language_id' => Defaults::LANGUAGE_SYSTEM]);
        }
        $this->insert('product_keyword_dictionary', ['id' => self::id(2000), 'keyword' => 'keep', 'reversed' => 'peek', 'language_id' => Defaults::LANGUAGE_SYSTEM]);
        $this->insert('product_search_keyword', ['keyword' => 'keep']);
        $task = new CleanupProductKeywordDictionaryTaskHandler(StaticEntityRepository::of(ScheduledTaskCollection::class), new NullLogger(), $this->connection);
        $task->run();
        static::assertSame(['keep'], $this->connection->fetchFirstColumn('SELECT keyword FROM product_keyword_dictionary'));
        $loader = new KeywordLoader($this->connection);
        $rows = $loader->fetch([['normal' => ['ke%'], 'reversed' => ['never%']], ['normal' => [], 'reversed' => ['pee%']]], Context::createDefaultContext());
        static::assertSame('keep', $rows[0][0]);
        static::assertSame([1, 1], array_map(intval(...), \array_slice($rows[0], 1)));
        static::assertSame([], $loader->fetch([], Context::createDefaultContext()));
        static::assertSame([], $loader->fetch([['normal' => [], 'reversed' => []]], Context::createDefaultContext()));
        $task->run();
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_keyword_dictionary'));
    }

    public function testMediaLocationAndPathStorageIgnoreMissingRelations(): void
    {
        $this->create('media', 'id {binary} PRIMARY KEY, file_extension VARCHAR(32), file_name VARCHAR(255), uploaded_at TIMESTAMP NULL, path VARCHAR(255)');
        $this->create('media_thumbnail', 'id {binary} PRIMARY KEY, media_id {binary}, width INT, height INT, path VARCHAR(255)');
        $this->insert('media', ['id' => self::id(1), 'file_extension' => 'png', 'file_name' => 'żółć', 'uploaded_at' => null]);
        $this->insert('media_thumbnail', ['id' => self::id(2), 'media_id' => self::id(1), 'width' => 100, 'height' => 200]);
        $this->insert('media_thumbnail', ['id' => self::id(3), 'media_id' => self::id(99), 'width' => 100, 'height' => 200]);
        $builder = new SqlMediaLocationBuilder($this->dispatcher, $this->connection);
        static::assertSame([self::id(1)], array_keys($builder->media([self::id(1), self::id(1), self::id(99)])));
        static::assertSame([self::id(2)], array_keys($builder->thumbnails([self::id(2), self::id(3)])));
        $storage = new SqlMediaPathStorage($this->connection);
        $storage->media([self::id(1) => 'media/path.png']);
        $storage->thumbnails([self::id(2) => 'thumbnail/path.png']);
        static::assertSame('media/path.png', $this->connection->fetchOne('SELECT path FROM media'));
        static::assertSame(['thumbnail/path.png', null], $this->connection->fetchFirstColumn('SELECT path FROM media_thumbnail ORDER BY width, id'));
    }

    public function testNewsletterSalutationHandlesMissingDefaultsAndPreservesExistingValue(): void
    {
        $this->create('newsletter_recipient', 'id {binary} PRIMARY KEY, salutation_id {binary}');
        $this->create('salutation', 'id {binary} PRIMARY KEY, salutation_key VARCHAR(255)');
        $this->insert('newsletter_recipient', ['id' => self::id(1)]);
        $this->insert('newsletter_recipient', ['id' => self::id(2), 'salutation_id' => self::id(9)]);
        $context = Context::createDefaultContext();
        $event = new EntityWrittenEvent('newsletter_recipient', [new EntityWriteResult(self::id(1), ['id' => self::id(1)], 'newsletter_recipient', EntityWriteResult::OPERATION_INSERT), new EntityWriteResult(self::id(2), ['id' => self::id(2)], 'newsletter_recipient', EntityWriteResult::OPERATION_INSERT)], $context);
        $subscriber = new NewsletterRecipientSalutationSubscriber($this->connection);
        $subscriber->setDefaultSalutation($event);
        static::assertSame([null, self::id(9)], $this->connection->fetchFirstColumn('SELECT ' . (new DatabasePlatformResolver($this->connection))->dialect()->hex('salutation_id') . ' FROM newsletter_recipient ORDER BY id'));
        $this->insert('salutation', ['id' => self::id(5), 'salutation_key' => 'not_specified']);
        $subscriber->setDefaultSalutation($event);
        static::assertSame([self::id(5), self::id(9)], $this->connection->fetchFirstColumn('SELECT ' . (new DatabasePlatformResolver($this->connection))->dialect()->hex('salutation_id') . ' FROM newsletter_recipient ORDER BY id'));
    }

    public function testExportSchedulingIgnoresMissingRelationsAndResetsOnlyStaleRuns(): void
    {
        $this->create('sales_channel', 'id {binary} PRIMARY KEY, type_id {binary}, active SMALLINT');
        $this->create('sales_channel_domain', 'id {binary} PRIMARY KEY, sales_channel_id {binary}');
        $this->create('product_export', 'id {binary} PRIMARY KEY, sales_channel_id {binary}, sales_channel_domain_id {binary}, storefront_sales_channel_id {binary}, generate_by_cronjob SMALLINT, generated_at TIMESTAMP NULL, next_generation_at TIMESTAMP NULL, ' . $this->connection->quoteSingleIdentifier('interval') . ' INT, is_running SMALLINT, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL');
        $this->insert('sales_channel', ['id' => self::id(1), 'type_id' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT, 'active' => 1]);
        $this->insert('sales_channel', ['id' => self::id(2), 'type_id' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON, 'active' => 1]);
        $this->insert('sales_channel_domain', ['id' => self::id(3), 'sales_channel_id' => self::id(1)]);
        foreach (range(10, 14) as $id) {
            $this->insert('product_export', ['id' => self::id($id), 'sales_channel_id' => self::id(2), 'sales_channel_domain_id' => self::id($id === 14 ? 99 : 3), 'generate_by_cronjob' => 1, 'generated_at' => $id === 10 ? null : '2026-10-08 00:00:00', 'next_generation_at' => '2026-10-09 00:00:00', 'interval' => 300, 'is_running' => $id === 11 || $id === 12 ? 1 : 0, 'created_at' => '2026-10-01 00:00:00', 'updated_at' => $id === 12 ? '2026-10-08 12:00:00' : null]);
        }
        $bus = static::createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))->method('dispatch')->willReturnCallback(static function (object $message): Envelope {
            static::assertInstanceOf(ProductExportPartialGeneration::class, $message);
            static::assertContains($message->getProductExportId(), [self::id(10), self::id(11)]);
            static::assertSame(self::id(1), $message->getSalesChannelId());

            return new Envelope($message);
        });
        $handler = new ProductExportGenerateTaskHandler(StaticEntityRepository::of(ScheduledTaskCollection::class), new NullLogger(), $this->connection, $bus, new MockClock('2026-10-08 12:00:00'));
        $handler->run();
        static::assertSame([0, 0, 1, 0, 0], array_map(intval(...), $this->connection->fetchFirstColumn('SELECT is_running FROM product_export ORDER BY id')));
    }

    public function testImportVariantCleanupKeepsTheOtherVersion(): void
    {
        $this->create('product_option', 'product_id {binary}, product_version_id {binary}, property_group_option_id {binary}');
        $this->create('product_configurator_setting', 'id {binary}, product_id {binary}, product_version_id {binary}');
        $parentId = self::id(1);
        $optionId = self::id(2);
        $groupId = self::id(3);
        $variantId = Uuid::fromStringToHex($parentId . '.0');
        foreach ([Defaults::LIVE_VERSION, self::id(99)] as $version) {
            $this->insert('product_option', ['product_id' => $variantId, 'product_version_id' => $version, 'property_group_option_id' => self::id(9)]);
            $this->insert('product_configurator_setting', ['id' => self::id(5), 'product_id' => $parentId, 'product_version_id' => $version]);
        }
        $context = Context::createDefaultContext();
        $sync = static::createMock(SyncServiceInterface::class);
        $sync->expects($this->once())->method('sync')->willReturnCallback(static function (array $operations) use ($variantId): SyncResult {
            static::assertInstanceOf(SyncOperation::class, $operations[0]);
            static::assertSame($variantId, $operations[0]->getPayload()[0]['id']);

            return new SyncResult([]);
        });
        $subscriber = new ProductVariantsSubscriber($sync, $this->connection, StaticEntityRepository::of(PropertyGroupCollection::class, [[$groupId]]), StaticEntityRepository::of(PropertyGroupOptionCollection::class, [[$optionId]]));
        $written = new EntityWrittenContainerEvent($context, new NestedEventCollection([new EntityWrittenEvent('product', [new EntityWriteResult($parentId, ['productNumber' => 'parent'], 'product', EntityWriteResult::OPERATION_INSERT)], $context)]), []);
        $subscriber->onAfterImportRecord(new ImportExportAfterImportRecordEvent($written, [], ['variants' => 'size: M'], new Config([], ['sourceEntity' => 'product'], []), $context));
        static::assertSame([self::id(99)], $this->connection->fetchFirstColumn('SELECT ' . (new DatabasePlatformResolver($this->connection))->dialect()->hex('product_version_id') . ' FROM product_option'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_configurator_setting'));
    }

    public function testNumberRangeConfigurationUsesChannelOverrideAndBinaryIds(): void
    {
        $global = $this->connection->quoteSingleIdentifier('global');
        $this->create('number_range_type', 'id {binary} PRIMARY KEY, technical_name VARCHAR(255), ' . $global . ' SMALLINT');
        $this->create('number_range', 'id {binary} PRIMARY KEY, type_id {binary}, pattern VARCHAR(255), ' . $global . ' SMALLINT');
        $this->create('number_range_sales_channel', 'number_range_id {binary}, sales_channel_id {binary}');
        $this->insert('number_range_type', ['id' => self::id(1), 'technical_name' => 'customer', 'global' => 0]);
        $this->insert('number_range', ['id' => self::id(2), 'type_id' => self::id(1), 'pattern' => 'global-{n}', 'global' => 1]);
        $this->insert('number_range', ['id' => self::id(3), 'type_id' => self::id(1), 'pattern' => 'channel-{n}', 'global' => 0]);
        $this->insert('number_range_sales_channel', ['number_range_id' => self::id(3), 'sales_channel_id' => self::id(4)]);
        $service = new CustomerNumberRangeConfigService($this->connection);
        static::assertSame(['id' => self::id(3), 'pattern' => 'channel-{n}'], $service->getPatternConfig(self::id(4)));
        static::assertSame(self::id(2), $service->getPatternConfigId(null));
        $service->reset();
        static::assertSame(self::id(2), $service->getPatternConfigId(self::id(99)));
    }

    public function testFlowStateActionUsesNumericShippingCostsAndVersionedMachine(): void
    {
        $this->create('order_delivery', 'id {binary}, version_id {binary}, order_id {binary}, state_id {binary}, shipping_costs {json}, created_at TIMESTAMP NULL');
        $this->create('state_machine', 'id {binary}, technical_name VARCHAR(255)');
        $this->create('state_machine_state', 'id {binary}, technical_name VARCHAR(255), state_machine_id {binary}');
        $this->create('state_machine_transition', 'from_state_id {binary}, to_state_id {binary}, action_name VARCHAR(255)');
        $this->insert('state_machine', ['id' => self::id(1), 'technical_name' => 'order_delivery.state']);
        $this->insert('state_machine_state', ['id' => self::id(2), 'technical_name' => 'open', 'state_machine_id' => self::id(1)]);
        $this->insert('state_machine_state', ['id' => self::id(3), 'technical_name' => 'done', 'state_machine_id' => self::id(1)]);
        $this->insert('state_machine_transition', ['from_state_id' => self::id(2), 'to_state_id' => self::id(3), 'action_name' => 'complete']);
        foreach ([10 => 9, 11 => 100, 12 => null, 13 => 100, 14 => 100] as $id => $price) {
            $this->insert('order_delivery', ['id' => self::id($id), 'version_id' => Defaults::LIVE_VERSION, 'order_id' => self::id(4), 'state_id' => self::id(2), 'shipping_costs' => ['totalPrice' => $price], 'created_at' => $id === 14 ? '2026-10-07 00:00:00' : '2026-10-08 00:00:00']);
        }
        $service = static::createMock(OrderService::class);
        $service->expects($this->once())->method('orderDeliveryStateTransition')->with(self::id(11), 'complete');
        $flow = new StorableFlow('test', Context::createDefaultContext(), [], [OrderAware::ORDER_ID => self::id(4)]);
        $flow->setConfig(['order_delivery' => 'done']);
        (new SetOrderStateAction($this->connection, $service))->handleFlow($flow);
    }

    public function testProductCombinationLoadersKeepInheritanceAndVersionIsolation(): void
    {
        $this->create('product', 'id {binary}, version_id {binary}, parent_id {binary}, parent_version_id {binary}, active SMALLINT, is_closeout SMALLINT, option_ids {json}, product_number VARCHAR(255), available SMALLINT, visibilities {binary}, type VARCHAR(64), states {json}, PRIMARY KEY(id,version_id)');
        $this->create('product_visibility', 'product_id {binary}, product_version_id {binary}, sales_channel_id {binary}');
        $this->insert('product', ['id' => self::id(1), 'version_id' => Defaults::LIVE_VERSION, 'active' => 1, 'is_closeout' => 1]);
        foreach ([2, 3] as $id) {
            $this->insert('product', ['id' => self::id($id), 'version_id' => Defaults::LIVE_VERSION, 'parent_id' => self::id(1), 'parent_version_id' => Defaults::LIVE_VERSION, 'option_ids' => [self::id(10 + $id)], 'product_number' => 'variant' . $id, 'available' => $id === 2 ? 1 : 0, 'visibilities' => self::id($id), 'type' => 'physical']);
            $this->insert('product_visibility', ['product_id' => self::id($id), 'product_version_id' => Defaults::LIVE_VERSION, 'sales_channel_id' => self::id(50)]);
        }
        $context = $this->salesChannelContext();
        $stock = new StockStorage($this->connection, $this->dispatcher);
        $loader = new AvailableCombinationLoader($this->connection, $stock, new StaticSystemConfigService(['core.listing.hideCloseoutProductsWhenOutOfStock' => true]));
        $result = $loader->loadCombinations(self::id(1), $context);
        static::assertTrue($result->hasOptionId(self::id(12)));
        static::assertFalse($result->hasOptionId(self::id(13)));
        $draft = self::id(99);
        $this->insert('product', ['id' => self::id(4), 'version_id' => $draft, 'parent_id' => self::id(1), 'parent_version_id' => Defaults::LIVE_VERSION, 'option_ids' => [self::id(14)], 'product_number' => 'draft-variant', 'available' => 1, 'visibilities' => self::id(1), 'type' => 'physical']);
        $this->insert('product_visibility', ['product_id' => self::id(1), 'product_version_id' => Defaults::LIVE_VERSION, 'sales_channel_id' => self::id(50)]);
        $draftContext = static::createStub(SalesChannelContext::class);
        $draftContext->method('getContext')->willReturn(Context::createDefaultContext()->createWithVersionId($draft));
        $draftContext->method('getSalesChannelId')->willReturn(self::id(50));
        static::assertTrue($loader->loadCombinations(self::id(1), $draftContext)->hasOptionId(self::id(14)));
        $combinations = (new VariantCombinationLoader($this->connection))->load(self::id(1), Context::createDefaultContext());
        static::assertSame([self::id(2), self::id(3)], array_keys($combinations));
        static::assertSame('variant2', $combinations[self::id(2)]['productNumber']);
        $reference = new FkReference('/productId', 'product', 'productNumber', 'variant2', false);
        (new ProductNumberFkResolver($this->connection))->resolve([$reference]);
        static::assertSame(self::id(2), $reference->resolved);
    }

    public function testCmsDefaultValidationAndSlotInheritanceUseExistingLanguagesAndPages(): void
    {
        $this->create('cms_page', 'id {binary}, version_id {binary}');
        $this->create('language', 'id {binary}, parent_id {binary}');
        $this->insert('cms_page', ['id' => self::id(1), 'version_id' => Defaults::LIVE_VERSION]);
        $subscriber = new CmsPageDefaultChangeSubscriber($this->connection);
        $subscriber->validateChangeOfDefaultCmsPage(new BeforeSystemConfigChangedEvent(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, self::id(1), null));
        $this->insert('language', ['id' => Defaults::LANGUAGE_SYSTEM, 'parent_id' => self::id(2)]);
        $this->insert('language', ['id' => self::id(2), 'parent_id' => null]);
        $parent = new ProductTranslationEntity();
        $parent->setLanguageId(self::id(2));
        $parent->setUniqueIdentifier('parent');
        $parent->setSlotConfig(['slot' => ['media' => ['value' => 'parent'], 'text' => ['value' => 'inherited']]]);
        $child = new ProductTranslationEntity();
        $child->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $child->setUniqueIdentifier('child');
        $child->setSlotConfig(['slot' => ['media' => ['value' => 'child']]]);
        $builder = new EntityCmsSlotConfigInheritanceBuilder($this->connection);
        static::assertSame(['slot' => ['media' => ['value' => 'child'], 'text' => ['value' => 'inherited']]], $builder->build(new ProductTranslationCollection([$parent, $child]), $this->salesChannelContext()));
        static::assertNull($builder->build(null, $this->salesChannelContext()));
    }

    public function testHreflangAndSeoPlaceholdersRetainLanguageAndChannelIds(): void
    {
        $this->seoTable();
        $this->create('sales_channel_domain', 'id {binary}, sales_channel_id {binary}, language_id {binary}, url VARCHAR(255), hreflang_use_only_locale SMALLINT');
        $this->create('language', 'id {binary}, locale_id {binary}');
        $this->create('locale', 'id {binary}, code VARCHAR(64)');
        foreach ([1 => 'en-GB', 2 => 'de-DE'] as $id => $locale) {
            $this->insert('locale', ['id' => self::id($id), 'code' => $locale]);
            $this->insert('language', ['id' => self::id($id), 'locale_id' => self::id($id)]);
            $this->insert('sales_channel_domain', ['id' => self::id($id), 'sales_channel_id' => self::id(50), 'language_id' => self::id($id), 'url' => 'https://shop.test/' . $id, 'hreflang_use_only_locale' => $id === 2 ? 1 : 0]);
            $this->insert('seo_url', ['id' => self::id($id), 'language_id' => self::id($id), 'sales_channel_id' => self::id(50), 'foreign_key' => self::id(10), 'route_name' => 'detail', 'path_info' => '/detail', 'seo_path_info' => 'translated-' . $id, 'is_canonical' => 1, 'is_deleted' => 0]);
        }
        $context = $this->salesChannelContext();
        $context->getSalesChannel()->setHreflangActive(true);
        $context->getSalesChannel()->setHreflangDefaultDomainId(self::id(1));
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/base/detail');
        $loader = new HreflangLoader($router, $this->connection);
        $links = $loader->load(new HreflangLoaderParameter('detail', [], $context, basePath: '/base'));
        static::assertSame(['x-default', 'en-GB', 'de'], array_map(static fn ($link) => $link->getLocale(), $links->getElements()));
        static::assertSame('https://shop.test/2/translated-2', $links->last()?->getUrl());
        static::assertCount(3, $loader->load(new HreflangLoaderParameter('home', [], $context, homepage: true)));
        $this->insert('seo_url', ['id' => self::id(3), 'language_id' => Defaults::LANGUAGE_SYSTEM, 'sales_channel_id' => self::id(50), 'foreign_key' => self::id(11), 'route_name' => 'detail', 'path_info' => '/detail/11', 'seo_path_info' => 'eleven', 'is_canonical' => 1, 'is_deleted' => 0]);
        $handler = new SeoUrlPlaceholderHandler(new RequestStack(), $router, $this->connection);
        static::assertSame('https://shop.test/eleven', $handler->replace(SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/11#', 'https://shop.test', $context));
    }

    public function testRemoteThumbnailsHandleNumericDimensionsAndMissingConfigurations(): void
    {
        $this->create('media_folder_configuration_media_thumbnail_size', 'media_folder_configuration_id {binary}, media_thumbnail_size_id {binary}');
        $this->create('media_thumbnail_size', 'id {binary}, width INT, height INT');
        $this->create('media_folder', 'id {binary}, media_folder_configuration_id {binary}');
        $this->create('media_folder_configuration', 'id {binary}, create_thumbnails SMALLINT');
        $this->insert('media_thumbnail_size', ['id' => self::id(1), 'width' => 100, 'height' => 200]);
        $this->insert('media_folder_configuration_media_thumbnail_size', ['media_folder_configuration_id' => self::id(2), 'media_thumbnail_size_id' => self::id(1)]);
        $this->insert('media_folder_configuration', ['id' => self::id(2), 'create_thumbnails' => 1]);
        $this->insert('media_folder', ['id' => self::id(3), 'media_folder_configuration_id' => self::id(2)]);
        $this->insert('media_folder', ['id' => self::id(4), 'media_folder_configuration_id' => self::id(99)]);
        $generator = static::createStub(AbstractMediaUrlGenerator::class);
        $generator->method('generate')->willReturn([self::id(10) => '/media.png', self::id(11) => '/other.png']);
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('publicUrl')->willReturn('https://cdn.test/');
        $media = new MediaEntity();
        $media->assign(['id' => self::id(10), 'path' => 'media.png', 'private' => false, 'mediaFolderId' => self::id(3), 'createdAt' => new \DateTimeImmutable('2026-10-08')]);
        $orphan = new MediaEntity();
        $orphan->assign(['id' => self::id(11), 'path' => 'other.png', 'private' => false, 'mediaFolderId' => self::id(4), 'createdAt' => new \DateTimeImmutable('2026-10-08')]);
        (new RemoteThumbnailLoader($generator, $this->connection, $filesystem, new ExtensionDispatcher($this->dispatcher), '{mediaUrl}/{mediaPath}/{width}x{height}'))->load([$media, $orphan]);
        static::assertSame('https://cdn.test/media.png/100x200', $media->getThumbnails()?->first()?->getUrl());
        static::assertSame(self::id(1), $media->getThumbnails()->first()->getMediaThumbnailSizeId());
        static::assertCount(0, $orphan->getThumbnails() ?? []);
    }

    public function testLandingPageSitemapUsesVersionedRelationsAndSeoFallback(): void
    {
        $this->seoTable();
        $this->create('landing_page', 'id {binary}, version_id {binary}, active SMALLINT, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL');
        $this->create('landing_page_sales_channel', 'landing_page_id {binary}, landing_page_version_id {binary}, sales_channel_id {binary}');
        foreach (range(1, 3) as $id) {
            $this->insert('landing_page', ['id' => self::id($id), 'version_id' => Defaults::LIVE_VERSION, 'active' => 1, 'created_at' => '2026-10-08']);
            $this->insert('landing_page_sales_channel', ['landing_page_id' => self::id($id), 'landing_page_version_id' => $id === 3 ? self::id(99) : Defaults::LIVE_VERSION, 'sales_channel_id' => self::id(50)]);
        }
        $this->insert('seo_url', ['id' => self::id(1), 'language_id' => Defaults::LANGUAGE_SYSTEM, 'sales_channel_id' => self::id(50), 'foreign_key' => self::id(1), 'route_name' => 'landing', 'seo_path_info' => 'page-one', 'is_canonical' => 1, 'is_deleted' => 0]);
        $config = static::createStub(ConfigHandler::class);
        $config->method('get')->willReturn([]);
        $routes = static::createStub(EntityRouteResolver::class);
        $routes->method('getRouteNameForEntityName')->willReturn('landing');
        $routes->method('generateUrl')->willReturn('fallback-two');
        $provider = new LandingPageUrlProvider($config, $this->connection, $routes, $this->dispatcher);
        $urls = $provider->getUrls($this->salesChannelContext(), 10);
        static::assertSame(['page-one', 'fallback-two'], array_map(static fn ($url) => $url->getLoc(), $urls->getUrls()));
        static::assertNull($urls->getNextOffset());
    }

    public function testMediaPathCommandPassesHexIdsAndPreservesExistingPaths(): void
    {
        $this->create('media', 'id {binary}, path VARCHAR(255)');
        $this->create('media_thumbnail', 'id {binary}, path VARCHAR(255)');
        foreach (['media', 'media_thumbnail'] as $table) {
            $this->insert($table, ['id' => self::id(1), 'path' => null]);
            $this->insert($table, ['id' => self::id(2), 'path' => 'existing']);
        }
        $updater = static::createMock(MediaPathUpdater::class);
        $updater->expects($this->once())->method('updateMedia')->with([self::id(1)]);
        $updater->expects($this->once())->method('updateThumbnails')->with([self::id(1)]);
        $tester = new CommandTester(new UpdatePathCommand($updater, $this->connection));
        static::assertSame(0, $tester->execute([]));
    }

    public function testOrderStockSqlUsesBinaryItemIdsForTransitionsAndWriteCallbacks(): void
    {
        $this->create('product', 'id {binary}, version_id {binary}, parent_id {binary}, stock INT, sales INT, available_stock INT, available SMALLINT, is_closeout SMALLINT, min_purchase INT, updated_at TIMESTAMP NULL, PRIMARY KEY(id,version_id)');
        $this->create('order', 'id {binary}, version_id {binary}, state_id {binary}');
        $this->create('state_machine_state', 'id {binary}, technical_name VARCHAR(255)');
        $this->create('order_line_item', 'id {binary}, version_id {binary}, order_id {binary}, quantity INT, referenced_id VARCHAR(32), type VARCHAR(64)');
        $this->insert('product', ['id' => self::id(1), 'version_id' => Defaults::LIVE_VERSION, 'stock' => 5, 'sales' => 2, 'available_stock' => 5, 'available' => 1, 'is_closeout' => 1, 'min_purchase' => 1]);
        $this->insert('order', ['id' => self::id(2), 'version_id' => Defaults::LIVE_VERSION, 'state_id' => self::id(3)]);
        $this->insert('state_machine_state', ['id' => self::id(3), 'technical_name' => 'open']);
        $this->insert('order_line_item', ['id' => self::id(4), 'version_id' => Defaults::LIVE_VERSION, 'order_id' => self::id(2), 'quantity' => 2, 'referenced_id' => self::id(1), 'type' => 'product']);
        $context = Context::createDefaultContext();
        $storage = new StockStorage($this->connection, $this->dispatcher);
        $subscriber = new OrderStockSubscriber($this->connection, $storage, true);
        $open = new StateMachineStateEntity();
        $open->setTechnicalName('open');
        $cancelled = new StateMachineStateEntity();
        $cancelled->setTechnicalName('cancelled');
        $subscriber->stateChanged(new StateMachineTransitionEvent('order', self::id(2), $open, $cancelled, $context));
        static::assertSame(7, (int) $this->connection->fetchOne('SELECT stock FROM product'));
        $subscriber->stateChanged(new StateMachineTransitionEvent('order', self::id(2), $cancelled, $open, $context));
        static::assertSame(5, (int) $this->connection->fetchOne('SELECT stock FROM product'));
        $definition = new ContentOrderItemDefinition();
        $this->registry([$definition]);
        $command = new Write\Command\UpdateCommand($definition, ['quantity' => 4], ['id' => Uuid::fromHexToBytes(self::id(4)), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], new Write\EntityExistence('order_line_item', ['id' => self::id(4)], true, false, false, []), '/0');
        $event = EntityWriteEvent::create(Write\WriteContext::createFromContext($context), [$command]);
        $subscriber->beforeWriteOrderItems($event);
        $this->connection->executeStatement('UPDATE order_line_item SET quantity = 4');
        $event->success();
        static::assertSame(['stock' => 3, 'available_stock' => 3, 'sales' => 4], array_map(intval(...), $this->connection->fetchAssociative('SELECT stock, available_stock, sales FROM product') ?: []));
    }

    public function testSeoCanonicalTakeoversNormalizeMultipleBinaryRowsWithGlobalAndChannelPaths(): void
    {
        $this->seoTable();
        $persister = new SeoUrlPersister($this->connection, StaticEntityRepository::of(SeoUrlCollection::class, [], new SeoUrlDefinition()), $this->dispatcher, new MockClock('2026-10-08'));
        $channel = new SalesChannelEntity();
        $channel->setId(self::id(50));
        $context = Context::createDefaultContext();
        foreach ([1, 2] as $id) {
            $url = ['foreignKey' => self::id($id), 'salesChannelId' => self::id(50), 'pathInfo' => '/detail/' . $id, 'seoPathInfo' => 'old-' . $id, 'isDeleted' => false];
            $persister->updateSeoUrls($context, 'detail', [self::id($id)], [$url], $channel);
            $persister->updateSeoUrls($context, 'detail', [self::id($id)], [array_replace($url, ['seoPathInfo' => 'current-' . $id])], $channel);
        }
        $this->insert('seo_url', ['id' => self::id(90), 'language_id' => Defaults::LANGUAGE_SYSTEM, 'sales_channel_id' => null, 'foreign_key' => self::id(9), 'route_name' => 'detail', 'path_info' => '/global', 'seo_path_info' => 'current-1', 'is_canonical' => 1, 'is_deleted' => 0]);
        $urls = [
            ['foreignKey' => self::id(3), 'salesChannelId' => self::id(50), 'pathInfo' => '/detail/3', 'seoPathInfo' => 'current-1', 'isDeleted' => false],
            ['foreignKey' => self::id(4), 'salesChannelId' => self::id(50), 'pathInfo' => '/detail/4', 'seoPathInfo' => 'current-2', 'isDeleted' => false],
            ['foreignKey' => self::id(5), 'salesChannelId' => null, 'pathInfo' => '/global-new', 'seoPathInfo' => 'current-1', 'isDeleted' => false],
        ];
        $persister->updateSeoUrls($context, 'detail', [self::id(3), self::id(4), self::id(5)], $urls, $channel);
        static::assertSame(['old-1', 'old-2', 'current-1', 'current-2', 'current-1', 'current-1'], $this->connection->fetchFirstColumn('SELECT seo_path_info FROM seo_url WHERE is_canonical = 1 ORDER BY foreign_key'));
        static::assertSame(6, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM seo_url WHERE is_canonical = 1 AND is_deleted = 0'));
    }

    public function testProductDetailLoadsRequestedChildWithBinaryParentId(): void
    {
        $this->create('product', 'id {binary}, version_id {binary}, parent_id {binary}, variant_listing_config {json}, PRIMARY KEY(id,version_id)');
        $this->insert('product', ['id' => self::id(2), 'version_id' => Defaults::LIVE_VERSION, 'parent_id' => self::id(1), 'variant_listing_config' => ['displayParent' => true]]);
        $context = $this->salesChannelContext();
        $context->method('getVersionId')->willReturn(Defaults::LIVE_VERSION);
        $product = new SalesChannelProductEntity();
        $product->setId(self::id(2));
        $product->setParentId(self::id(1));
        $product->internalSetEntityData('product', new FieldVisibility([]));
        /** @var SalesChannelRepository<SalesChannelProductCollection>&Stub $products */
        $products = static::createStub(SalesChannelRepository::class);
        $products->method('search')->willReturn(new Search\EntitySearchResult('product', 1, new SalesChannelProductCollection([$product]), null, new Search\Criteria(), $context->getContext()));
        $route = new ProductDetailRoute(
            $products,
            new StaticEntityRepository([new ProductTranslationCollection()]),
            new StaticSystemConfigService(),
            $this->connection,
            static::createStub(ProductConfiguratorLoader::class),
            static::createStub(CategoryBreadcrumbBuilder::class),
            static::createStub(SalesChannelCmsPageLoaderInterface::class),
            static::createStub(EntityCmsSlotConfigInheritanceBuilder::class),
            new SalesChannelProductDefinition(),
            static::createStub(AbstractProductCloseoutFilterFactory::class),
            $this->dispatcher,
            static::createStub(CacheTagCollector::class),
        );

        $response = $route->load(self::id(2), new Request(['skipConfigurator' => true, 'skipCmsPage' => true]), $context, new Search\Criteria());

        static::assertSame(self::id(2), $response->getProduct()->getId());
        static::assertSame(self::id(1), $response->getProduct()->getParentId());
    }

    private function salesChannelContext(): SalesChannelContext&Stub
    {
        $channel = new SalesChannelEntity();
        $channel->setId(self::id(50));
        $channel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getSalesChannel')->willReturn($channel);
        $context->method('getSalesChannelId')->willReturn(self::id(50));
        $context->method('getLanguageId')->willReturn(Defaults::LANGUAGE_SYSTEM);

        return $context;
    }

    private function cmsTables(): void
    {
        $this->create('cms_slot', 'id {binary}, version_id {binary}, type VARCHAR(64), PRIMARY KEY(id, version_id)');
        $this->create('cms_slot_translation', 'cms_slot_id {binary}, cms_slot_version_id {binary}, config {json}');
        $this->create('product_translation', 'slot_config {json}');
        $this->create('category_translation', 'slot_config {json}');
        $this->create('landing_page_translation', 'slot_config {json}');
    }

    private function seoTable(): void
    {
        $this->create('seo_url', 'id {binary} PRIMARY KEY, language_id {binary}, sales_channel_id {binary}, foreign_key {binary}, route_name VARCHAR(255), path_info VARCHAR(255), seo_path_info VARCHAR(255), is_canonical SMALLINT, is_modified SMALLINT, is_deleted SMALLINT, created_at TIMESTAMP NULL, UNIQUE(language_id,sales_channel_id,seo_path_info), UNIQUE(language_id,sales_channel_id,foreign_key,route_name,is_canonical)');
    }

    private function create(string $table, string $fields): void
    {
        $fields = strtr($fields, ['{binary}' => $this->postgres ? 'BYTEA' : 'BINARY(16)', '{json}' => $this->postgres ? 'JSONB' : 'JSON', '{blob}' => $this->postgres ? 'BYTEA' : 'LONGBLOB']);
        $this->connection->executeStatement('CREATE TABLE ' . $this->connection->quoteSingleIdentifier($table) . ' (' . $fields . ')');
        $this->tables[] = $table;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insert(string $table, array $values): void
    {
        $types = [];
        foreach ($values as $column => &$value) {
            if (\is_array($value) || \is_object($value)) {
                $value = json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
            } elseif (\is_string($value) && Uuid::isValid($value) && ($column === 'id' || (str_ends_with($column, '_id') && $column !== 'referenced_id') || $column === 'foreign_key' || $column === 'visibilities')) {
                $value = Uuid::fromHexToBytes($value);
                $types[$column] = ParameterType::BINARY;
            }
        }
        unset($value);
        $this->connection->insert($this->connection->quoteSingleIdentifier($table), array_combine(array_map($this->connection->quoteSingleIdentifier(...), array_keys($values)), array_values($values)), array_combine(array_map($this->connection->quoteSingleIdentifier(...), array_keys($types)), array_values($types)));
    }

    private static function id(int $id): string
    {
        return str_pad(dechex($id), 32, '0', \STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    private function hexIds(string $table): array
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();

        return $this->connection->fetchFirstColumn('SELECT ' . $dialect->hex('id') . ' FROM ' . $dialect->quoteIdentifier($table) . ' ORDER BY id');
    }

    /**
     * @param list<EntityDefinition> $definitions
     */
    private function registry(array $definitions): DefinitionInstanceRegistry
    {
        $container = new ContainerBuilder();
        foreach ($definitions as $definition) {
            $container->set($definition::class, $definition);
        }
        $registry = new DefinitionInstanceRegistry($container, [], []);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }

    /**
     * @return EntityRepository<MediaCollection>
     */
    private function mediaRepository(): EntityRepository
    {
        $media = new ContentMediaDefinition();
        $definitions = [$media, new ContentFolderDefinition(), new ContentMediaUsageDefinition()];
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        foreach ($definitions as $definition) {
            $container->set($definition::class, $definition);
        }
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
        $validator = Validation::createValidator();
        $clock = new MockClock('2026-10-08');
        $gateway = new Dbal\EntityWriteGateway(250, $this->connection, $this->dispatcher, new Dbal\ExceptionHandlerRegistry([]), $registry);
        $extractor = new Write\WriteCommandExtractor($gateway, $registry);
        foreach ([FieldSerializer\IdFieldSerializer::class, FieldSerializer\FkFieldSerializer::class] as $class) {
            $container->set($class, new $class($validator, $registry));
        }
        $container->set(FieldSerializer\ManyToOneAssociationFieldSerializer::class, new FieldSerializer\ManyToOneAssociationFieldSerializer($extractor));
        $container->set(FieldSerializer\OneToManyAssociationFieldSerializer::class, new FieldSerializer\OneToManyAssociationFieldSerializer($extractor));
        $container->set(FieldSerializer\DateTimeFieldSerializer::class, new FieldSerializer\DateTimeFieldSerializer($validator, $registry));
        $container->set(FieldSerializer\CreatedAtFieldSerializer::class, new FieldSerializer\CreatedAtFieldSerializer($validator, $registry, $clock));
        $container->set(FieldSerializer\UpdatedAtFieldSerializer::class, new FieldSerializer\UpdatedAtFieldSerializer($validator, $registry, $clock));
        $container->set(Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder::class, new Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder($this->connection));
        $helper = new Dbal\EntityDefinitionQueryHelper($this->connection);
        $parser = new Search\Parser\SqlQueryParser($helper, $this->connection);
        $interpreter = new Search\Term\SearchTermInterpreter(new Search\Term\Tokenizer(new HtmlSanitizer(), 2), 2);
        $builder = new Dbal\CriteriaQueryBuilder($parser, $helper, $interpreter, new Search\Term\EntityScoreQueryBuilder(), new Dbal\JoinGroupBuilder(), new Dbal\FieldResolver\CriteriaPartResolver($this->connection, $parser));
        $container->set(Dbal\FieldResolver\ManyToOneAssociationFieldResolver::class, new Dbal\FieldResolver\ManyToOneAssociationFieldResolver($helper, $this->connection));
        $container->set(Dbal\FieldResolver\OneToManyAssociationFieldResolver::class, new Dbal\FieldResolver\OneToManyAssociationFieldResolver($this->connection));
        $hydrator = new Dbal\EntityHydrator($container);
        $container->set(Dbal\EntityHydrator::class, $hydrator);
        $reader = new Dbal\EntityReader($this->connection, $hydrator, $helper, $parser, $builder, new NullLogger(), new Dbal\CriteriaFieldsResolver());
        $searcher = new Dbal\EntitySearcher($this->connection, $helper, $builder);
        $languages = static::createStub(LanguageLoaderInterface::class);
        $writer = new Write\EntityWriter($extractor, new Dbal\EntityForeignKeyResolver($this->connection, $helper), $gateway, $languages, $registry, new Write\EntityWriteResultFactory($registry, $this->connection));
        $versions = new VersionManager($writer, $reader, $searcher, $gateway, $this->dispatcher, new Serializer(), $registry, new VersionCommitDefinition(), new VersionCommitDataDefinition(), new VersionDefinition(), new LockFactory(new FlockStore()), $clock);

        /** @var EntityRepository<MediaCollection> $repository */
        $repository = new EntityRepository($media, $reader, $versions, $searcher, static::createStub(Search\EntityAggregatorInterface::class), $this->dispatcher, new EntityLoadedEventFactory($registry));

        return $repository;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ContentMediaDefinition extends MediaDefinition
{
    /**
     * @return class-string<MediaCollection>
     */
    public function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public function getHydratorClass(): string
    {
        return Dbal\EntityHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\FkField('media_folder_id', 'mediaFolderId', ContentFolderDefinition::class),
            new Field\ManyToOneAssociationField('mediaFolder', 'media_folder_id', ContentFolderDefinition::class, 'id'),
            new Field\OneToManyAssociationField('usages', ContentMediaUsageDefinition::class, 'media_id'),
            new Field\DateTimeField('uploaded_at', 'uploadedAt'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ContentFolderDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'media_folder';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required())]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ContentMediaUsageDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'content_media_usage';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()), new Field\FkField('media_id', 'mediaId', ContentMediaDefinition::class)]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ContentTranslatedProductDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    public function getTranslationDefinitionClass(): ?string
    {
        return ContentProductTranslationDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([new Field\TranslatedField('customFields'), new Field\TranslationsAssociationField(ContentProductTranslationDefinition::class, 'product_id')]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ContentProductTranslationDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product_translation';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([new Field\CustomFields()]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ContentOrderItemDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'order_line_item';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey()), new Field\VersionField()]);
    }
}

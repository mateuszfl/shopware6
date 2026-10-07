<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexingMessage;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\SystemDefaultValidator;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFileExtensionWriteValidator;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexer;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexingMessage;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\AbstractProductStreamUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeyword;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexerSubscriber;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class IndexingContractTest extends TestCase
{
    private Connection $connection;

    private StaticDefinitionInstanceRegistry $definitions;

    private IndexingProductDefinition $product;

    private IndexingCategoryDefinition $category;

    private EventDispatcher $dispatcher;

    private bool $postgres;

    private string $engine;

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
            $this->engine = 'PostgreSQL 18.6';
            $this->connection->executeStatement('CREATE SCHEMA sw_indexers_contract');
            $this->connection->executeStatement('SET search_path TO sw_indexers_contract');
        } else {
            static::assertSame('shopware_json_test', $this->connection->getDatabase());
            $this->connection->executeStatement('SET SESSION sql_mode = \'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY\'');
            $this->connection->executeStatement('SET SESSION group_concat_max_len = 320000');
            $version = (string) $this->connection->fetchOne('SELECT VERSION()');
            static::assertMatchesRegularExpression('/^(8\.4\.11|11\.8\.9-MariaDB)/', $version);
            $this->engine = str_contains($version, 'MariaDB') ? 'MariaDB 11.8.9' : 'MySQL 8.4.11';
        }
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $blob = $this->postgres ? 'BYTEA' : 'LONGBLOB';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->create('product', "id $binary NOT NULL, version_id $binary NOT NULL, parent_id $binary, parent_version_id $binary, child_count INT DEFAULT 0, active SMALLINT DEFAULT 1 CHECK(active IN(0,1)), price $json, prices $binary, categories $binary, manufacturer $binary, product_manufacturer_id $binary, unit_id $binary, purchase_unit DECIMAL(10,3), reference_unit DECIMAL(10,3), min_purchase INT DEFAULT 1, cheapest_price $blob, cheapest_price_accessor $json, variant_listing_config $json, display_group VARCHAR(64), category_tree $json, category_ids $json, stream_ids $json, rating_average DECIMAL(10,3), updated_at TIMESTAMP NULL, PRIMARY KEY(id, version_id)");
        $this->create('category', "id $binary NOT NULL, version_id $binary NOT NULL, parent_id $binary, parent_version_id $binary, child_count INT DEFAULT 0, path TEXT, level INT, PRIMARY KEY(id, version_id)");
        $this->create('language', "id $binary PRIMARY KEY, parent_id $binary, active SMALLINT DEFAULT 1 CHECK(active IN(0,1))");
        $this->create('category_translation', "category_id $binary, category_version_id $binary, language_id $binary, name VARCHAR(255), breadcrumb $json, created_at TIMESTAMP NULL, PRIMARY KEY(category_id, category_version_id, language_id)");
        $this->create('product_category', "product_id $binary, product_version_id $binary, category_id $binary, category_version_id $binary, PRIMARY KEY(product_id, product_version_id, category_id, category_version_id), FOREIGN KEY(product_id, product_version_id) REFERENCES product(id, version_id) ON DELETE CASCADE");
        $this->create('product_category_tree', "product_id $binary, product_version_id $binary, category_id $binary, category_version_id $binary, PRIMARY KEY(product_id, product_version_id, category_id, category_version_id), FOREIGN KEY(product_id, product_version_id) REFERENCES product(id, version_id) ON DELETE CASCADE");
        $this->create('product_price', "id $binary PRIMARY KEY, product_id $binary, product_version_id $binary, rule_id $binary, price $json, quantity_start INT DEFAULT 1, quantity_end INT");
        $this->create('product_visibility', "product_id $binary, product_version_id $binary, sales_channel_id $binary");
        $this->create('product_review', "id $binary PRIMARY KEY, product_id $binary, product_version_id $binary, points INT, status SMALLINT CHECK(status IN(0,1))");
        $this->create('property_group_option', "id $binary PRIMARY KEY, property_group_id $binary");
        $this->create('product_option', "product_id $binary, product_version_id $binary, property_group_option_id $binary");
        $this->create('product_stream', "id $binary PRIMARY KEY, invalid SMALLINT DEFAULT 0 CHECK(invalid IN(0,1)), api_filter $json");
        $this->create('product_stream_mapping', "product_id $binary, product_version_id $binary, product_stream_id $binary, PRIMARY KEY(product_id, product_version_id, product_stream_id), FOREIGN KEY(product_id, product_version_id) REFERENCES product(id, version_id) ON DELETE CASCADE, FOREIGN KEY(product_stream_id) REFERENCES product_stream(id) ON DELETE CASCADE");
        $this->connection->executeStatement('CREATE INDEX sw_index_product_parent ON product(parent_id, version_id)');
        $this->product = new IndexingProductDefinition();
        $this->category = new IndexingCategoryDefinition();
        $this->definitions = new StaticDefinitionInstanceRegistry([
            ProductDefinition::class => $this->product, CategoryDefinition::class => $this->category,
            ProductCategoryDefinition::class, ProductPriceDefinition::class, ProductManufacturerDefinition::class,
            ProductStreamDefinition::class, ProductStreamMappingDefinition::class,
        ], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGatewayInterface::class));
        $this->dispatcher = new EventDispatcher();
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        if ($this->postgres) {
            $this->connection->executeStatement('DROP SCHEMA sw_indexers_contract CASCADE');
        } else {
            foreach (array_reverse($this->tables) as $table) {
                $this->connection->executeStatement('DROP TABLE ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table));
            }
        }
        $this->connection->close();
    }

    public function testSharedStatementHelpersKeepLegacyAndExplicitBinaryNullAndBlobBindings(): void
    {
        $this->insert('product', ['id' => $this->id(1), 'version_id' => Defaults::LIVE_VERSION]);
        $binary = Uuid::fromHexToBytes($this->id(1));
        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $blob = "\x00\xffserialized\x80";
        $query = new RetryableQuery($this->connection, $this->connection->prepare('UPDATE product SET cheapest_price = :blob WHERE id = :id AND version_id = :version'), ['blob' => ParameterType::BINARY, 'id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]);
        $query->execute(['blob' => $blob, 'id' => $binary, 'version' => $version]);
        $actual = StatementHelper::executeQuery($this->connection->prepare('SELECT cheapest_price FROM product WHERE id = :id'), ['id' => $binary], ['id' => ParameterType::BINARY])->fetchOne();
        static::assertSame($blob, \is_resource($actual) ? stream_get_contents($actual) : $actual);
        $legacy = new RetryableQuery($this->connection, $this->connection->prepare('UPDATE product SET min_purchase = :quantity, display_group = :label'));
        $legacy->execute(['quantity' => 3, 'label' => 'legacy']);
        static::assertSame('legacy', $this->connection->fetchOne('SELECT display_group FROM product'));
        StatementHelper::executeStatement($this->connection->prepare('UPDATE product SET display_group = :label'), ['label' => null], ['label' => ParameterType::NULL]);
        static::assertNull($this->connection->fetchOne('SELECT display_group FROM product'));
    }

    public function testCategoryAfterReferenceFailureIsTranslatedWithoutMaskingOtherConstraints(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement('ALTER TABLE category ADD after_category_id ' . $binary);
        $constraint = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('fk.category.after_category_id');
        $this->connection->executeStatement('ALTER TABLE category ADD CONSTRAINT ' . $constraint . ' FOREIGN KEY (after_category_id, version_id) REFERENCES category(id, version_id)');
        $this->insert('category', ['id' => $this->id(1), 'version_id' => Defaults::LIVE_VERSION]);
        $handler = new CategoryNonExistentExceptionHandler();
        try {
            $this->connection->executeStatement('UPDATE category SET after_category_id = :after', ['after' => Uuid::fromHexToBytes($this->id(2))], ['after' => ParameterType::BINARY]);
            static::fail('The actual database must reject a missing after-category reference.');
        } catch (ForeignKeyConstraintViolationException $exception) {
            $translated = $handler->matchException($exception);
            static::assertInstanceOf(CategoryException::class, $translated);
            static::assertSame(CategoryException::AFTER_CATEGORY_NOT_FOUND, $translated->getErrorCode());
        }
        static::assertNull($handler->matchException(new \RuntimeException('unrelated failure')));
    }

    public function testPortableLandingPageIndexerUsesRealIteratorAndDispatchesEachBatch(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $this->create('landing_page', "id $binary, version_id $binary, PRIMARY KEY(id,version_id)");
        $definition = new IndexingLandingPageDefinition();
        $this->definitions->register($definition, LandingPageDefinition::class);
        for ($i = 1; $i <= 53; ++$i) {
            $this->insert('landing_page', ['id' => $this->id($i), 'version_id' => Defaults::LIVE_VERSION]);
        }
        $seen = [];
        $this->dispatcher->addListener(LandingPageIndexerEvent::class, static function (LandingPageIndexerEvent $event) use (&$seen): void {
            $seen = [...$seen, ...$event->getIds()];
        });
        $indexer = new LandingPageIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(LandingPageCollection::class, [], $definition), $this->dispatcher);
        $registry = new EntityIndexerRegistry([$indexer], new MessageBus(), $this->dispatcher, new IndexerMetricsInstrumentor(static::createStub(Meter::class)));
        $registry->index(false);
        static::assertCount(53, $seen);
        static::assertSame($this->id(53), $seen[52]);
    }

    public function testMeasurementInventoryCandidatesArePortableDalMetadata(): void
    {
        $compiler = new AttributeEntityCompiler();
        $display = $compiler->compile(MeasurementDisplayUnitEntity::class)[0];
        $system = $compiler->compile(MeasurementSystemEntity::class)[0];
        static::assertSame('measurement_display_unit', $display['entity_name']);
        static::assertSame('measurement_system', $system['entity_name']);
        $fields = array_column($display['fields'], 'class', 'name');
        static::assertSame(Field\IdField::class, $fields['id']);
        static::assertSame(Field\BoolField::class, $fields['default']);
        static::assertSame(Field\FloatField::class, $fields['factor']);
        static::assertSame(Field\IntField::class, $fields['precision']);
        $fields = array_column($system['fields'], 'class', 'name');
        static::assertSame(Field\IdField::class, $fields['id']);
        static::assertSame(Field\StringField::class, $fields['technicalName']);
    }

    public function testDraftTreeUpdateBindsIndependentPathsForEveryFieldAndRecursiveChild(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $order = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('order');
        $this->create('indexing_multi_path', "id $binary, version_id $binary, parent_id $binary, parent_version_id $binary, token VARCHAR(255), path TEXT, $order TEXT, level INT, PRIMARY KEY(id,version_id)");
        $definition = new IndexingMultiPathDefinition();
        $this->definitions->register($definition);
        $draft = $this->id(900);
        $parent = $this->id(1);
        $child = $this->id(2);
        $grandchild = $this->id(3);
        $this->insert('indexing_multi_path', ['id' => $parent, 'version_id' => $draft, 'token' => 'root\'quoted']);
        $this->insert('indexing_multi_path', ['id' => $child, 'version_id' => $draft, 'parent_id' => $parent, 'parent_version_id' => $draft, 'token' => 'child-token']);
        $this->insert('indexing_multi_path', ['id' => $grandchild, 'version_id' => $draft, 'parent_id' => $child, 'parent_version_id' => $draft, 'token' => 'leaf']);
        $this->insert('indexing_multi_path', ['id' => $child, 'version_id' => Defaults::LIVE_VERSION, 'token' => 'live', 'path' => 'live-id-path']);
        $this->connection->executeStatement('UPDATE indexing_multi_path SET ' . $order . ' = :path WHERE version_id = :version', ['path' => 'live-token-path', 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]);
        $updater = new TreeUpdater($this->definitions, $this->connection);
        $context = Context::createDefaultContext()->createWithVersionId($draft);
        $updater->batchUpdate([$child], $definition->getEntityName(), $context, true);
        $updater->batchUpdate([$child], $definition->getEntityName(), $context, true);
        $rows = $this->connection->fetchAllAssociative('SELECT ' . IndexingSql::hex($this->connection, 'id') . ' AS id, path, ' . $order . ' AS token_path, level FROM indexing_multi_path WHERE version_id = :version ORDER BY id', ['version' => Uuid::fromHexToBytes($draft)], ['version' => ParameterType::BINARY]);
        static::assertSame('|' . $parent . '|', $rows[1]['path']);
        static::assertSame('|root\'quoted|', $rows[1]['token_path']);
        static::assertSame('|' . $parent . '|' . $child . '|', $rows[2]['path']);
        static::assertSame('|root\'quoted|child-token|', $rows[2]['token_path']);
        static::assertSame(3, (int) $rows[2]['level']);
        $live = $this->connection->fetchAssociative('SELECT path, ' . $order . ' AS token_path FROM indexing_multi_path WHERE version_id = :version', ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]);
        static::assertSame(['path' => 'live-id-path', 'token_path' => 'live-token-path'], $live);
    }

    public function testProductRegistryFullIncrementalAndRedeliveryRunsInheritanceMappingPricesAndVersions(): void
    {
        $parent = $this->id(1);
        $child = $this->id(2);
        $draft = $this->id(900);
        $this->insert('product', ['id' => $parent, 'version_id' => Defaults::LIVE_VERSION, 'price' => $this->prices(20), 'product_manufacturer_id' => $this->id(50)]);
        $this->insert('product', ['id' => $child, 'version_id' => Defaults::LIVE_VERSION, 'parent_id' => $parent, 'parent_version_id' => Defaults::LIVE_VERSION, 'price' => $this->prices(10)]);
        $this->insert('product', ['id' => $parent, 'version_id' => $draft, 'price' => $this->prices(99), 'product_manufacturer_id' => $this->id(51), 'updated_at' => '2001-01-01 00:00:00']);
        $this->insert('category', ['id' => $this->id(10), 'version_id' => Defaults::LIVE_VERSION, 'path' => '|' . $this->id(9) . '|']);
        $this->insert('product_category', ['product_id' => $parent, 'product_version_id' => Defaults::LIVE_VERSION, 'category_id' => $this->id(10), 'category_version_id' => Defaults::LIVE_VERSION]);
        $this->insert('product_price', ['id' => $this->id(70), 'product_id' => $parent, 'product_version_id' => Defaults::LIVE_VERSION, 'rule_id' => $this->id(80), 'price' => $this->prices(8)]);
        $indexer = $this->productIndexer();
        $registry = new EntityIndexerRegistry([$indexer], new MessageBus(), $this->dispatcher, new IndexerMetricsInstrumentor(static::createStub(Meter::class)));
        $registry->index(false, [ProductIndexer::STOCK_UPDATER, ProductIndexer::SEARCH_KEYWORD_UPDATER, 'product.states']);
        $before = $this->productState();
        static::assertSame([$this->id(10)], $this->json('SELECT category_ids FROM product WHERE parent_id IS NOT NULL'));
        static::assertSame([$this->id(9), $this->id(10)], $this->json('SELECT category_tree FROM product WHERE parent_id IS NOT NULL'));
        static::assertSame($this->id(50), $this->connection->fetchOne('SELECT ' . IndexingSql::hex($this->connection, 'manufacturer') . ' FROM product WHERE parent_id IS NOT NULL'));
        static::assertEquals(8, $this->json('SELECT cheapest_price_accessor FROM product WHERE parent_id IS NOT NULL')['rule' . $this->id(80)]['currency' . Defaults::CURRENCY]['gross']);
        $message = new ProductIndexingMessage([$parent, $child, $child], null, Context::createDefaultContext());
        $message->addSkip(ProductIndexer::STOCK_UPDATER, ProductIndexer::SEARCH_KEYWORD_UPDATER, 'product.states');
        $indexer->handle($message);
        $indexer->handle($message);
        static::assertSame($before, $this->productState());
        static::assertSame('2001-01-01 00:00:00', substr((string) $this->connection->fetchOne('SELECT updated_at FROM product WHERE version_id = :version', ['version' => Uuid::fromHexToBytes($draft)], ['version' => ParameterType::BINARY]), 0, 19));
        $this->connection->executeStatement('DELETE FROM product_category');
        $indexer->handle($message);
        static::assertNull($this->connection->fetchOne('SELECT category_ids FROM product WHERE parent_id IS NOT NULL'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_category_tree'));
    }

    public function testCategoryRegistryBatchesBreadcrumbsAndRepairsMovedAndDeletedMappings(): void
    {
        $this->insert('language', ['id' => Defaults::LANGUAGE_SYSTEM]);
        $root = $this->id(1);
        $other = $this->id(2);
        foreach ([$root, $other] as $id) {
            $this->insert('category', ['id' => $id, 'version_id' => Defaults::LIVE_VERSION]);
            $this->insert('category_translation', ['category_id' => $id, 'category_version_id' => Defaults::LIVE_VERSION, 'language_id' => Defaults::LANGUAGE_SYSTEM, 'name' => 'root-' . $id]);
        }
        $ids = [];
        for ($i = 10; $i < 513; ++$i) {
            $ids[] = $id = $this->id($i);
            $this->insert('category', ['id' => $id, 'version_id' => Defaults::LIVE_VERSION, 'parent_id' => $root, 'parent_version_id' => Defaults::LIVE_VERSION]);
            $this->insert('category_translation', ['category_id' => $id, 'category_version_id' => Defaults::LIVE_VERSION, 'language_id' => Defaults::LANGUAGE_SYSTEM, 'name' => 'Żółć-' . $i]);
        }
        $indexer = $this->categoryIndexer();
        $registry = new EntityIndexerRegistry([$indexer], new MessageBus(), $this->dispatcher, new IndexerMetricsInstrumentor(static::createStub(Meter::class)));
        $registry->index(false);
        static::assertSame(505, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM category_translation WHERE breadcrumb IS NOT NULL'));
        static::assertSame(503, (int) $this->connection->fetchOne('SELECT child_count FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($root)], ['id' => ParameterType::BINARY]));
        $before = $this->connection->fetchAllAssociative('SELECT path, level FROM category ORDER BY id');
        $message = new CategoryIndexingMessage([$root, $other, ...$ids], null, Context::createDefaultContext());
        $indexer->handle($message);
        $indexer->handle($message);
        static::assertSame($before, $this->connection->fetchAllAssociative('SELECT path, level FROM category ORDER BY id'));
        $this->connection->executeStatement('UPDATE category SET parent_id = :parent WHERE id = :id', ['parent' => Uuid::fromHexToBytes($other), 'id' => Uuid::fromHexToBytes($ids[0])], ['parent' => ParameterType::BINARY, 'id' => ParameterType::BINARY]);
        $indexer->handle(new CategoryIndexingMessage([$root, $other, $ids[0]]));
        static::assertSame('|' . $other . '|', $this->connection->fetchOne('SELECT path FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($ids[0])], ['id' => ParameterType::BINARY]));
        static::assertSame([$other, $ids[0]], array_keys($this->json('SELECT breadcrumb FROM category_translation WHERE category_id = :id', ['id' => Uuid::fromHexToBytes($ids[0])], ['id' => ParameterType::BINARY])));
    }

    public function testCheapestPriceUsesReferencedParentVersionAndDoesNotRedispatchEquivalentJson(): void
    {
        $parent = $this->id(1);
        $child = $this->id(2);
        $draft = $this->id(900);
        $this->insert('product', ['id' => $parent, 'version_id' => Defaults::LIVE_VERSION, 'price' => $this->prices(20), 'child_count' => 1, 'purchase_unit' => 2]);
        $this->insert('product', ['id' => $parent, 'version_id' => $draft, 'price' => $this->prices(99), 'child_count' => 1, 'purchase_unit' => 999]);
        $this->insert('product', ['id' => $child, 'version_id' => $draft, 'parent_id' => $parent, 'parent_version_id' => Defaults::LIVE_VERSION, 'price' => $this->prices(10)]);
        $context = Context::createDefaultContext()->createWithVersionId($draft);
        $events = [];
        $this->dispatcher->addListener(ProductIndexerEvent::class, static function ($event) use (&$events): void { $events[] = $event; });
        $updater = new CheapestPriceUpdater($this->connection, new CheapestPriceQuantitySelector(), $this->dispatcher);
        $updater->update([$parent], $context);
        static::assertCount(1, $events);
        $container = $this->connection->fetchOne('SELECT cheapest_price FROM product WHERE id = :id AND version_id = :version', ['id' => Uuid::fromHexToBytes($parent), 'version' => Uuid::fromHexToBytes($draft)], ['id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]);
        $container = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, \is_resource($container) ? stream_get_contents($container) : $container);
        $resolved = $container->resolve($context);
        static::assertNotNull($resolved);
        static::assertSame(2.0, $resolved->getPurchase());
        $updater->update([$parent], $context);
        static::assertCount(1, $events);
        static::assertNull($this->connection->fetchOne('SELECT cheapest_price FROM product WHERE version_id = :version', ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]));
    }

    public function testVariantGroupsHashUsesOrderedOptionsAndLeavesDraftAlone(): void
    {
        $parent = $this->id(1);
        $child = $this->id(2);
        $group = $this->id(4);
        $option = $this->id(5);
        $this->insert('product', ['id' => $parent, 'version_id' => Defaults::LIVE_VERSION, 'variant_listing_config' => ['configuratorGroupConfig' => [['id' => $group, 'expressionForListings' => true]]]]);
        $this->insert('product', ['id' => $child, 'version_id' => Defaults::LIVE_VERSION, 'parent_id' => $parent, 'parent_version_id' => Defaults::LIVE_VERSION]);
        $this->insert('product', ['id' => $child, 'version_id' => $this->id(900), 'parent_id' => $parent, 'parent_version_id' => $this->id(900), 'display_group' => 'untouched']);
        $this->insert('property_group_option', ['id' => $option, 'property_group_id' => $group]);
        $this->insert('product_option', ['product_id' => $child, 'product_version_id' => Defaults::LIVE_VERSION, 'property_group_option_id' => $option]);
        $updater = new VariantListingUpdater($this->connection);
        $updater->update([$parent], Context::createDefaultContext());
        static::assertSame(hash('sha256', $parent . $option), $this->connection->fetchOne('SELECT display_group FROM product WHERE version_id = :version AND parent_id IS NOT NULL', ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]));
        static::assertSame('untouched', $this->connection->fetchOne('SELECT display_group FROM product WHERE version_id = :version', ['version' => Uuid::fromHexToBytes($this->id(900))], ['version' => ParameterType::BINARY]));
        static::assertNull($this->connection->fetchOne('SELECT display_group FROM product WHERE parent_id IS NULL'));
    }

    public function testStreamCompilationBatchMappingsRemovalAndRedeliveryRespectVersion(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->create('product_stream_filter', "id $binary PRIMARY KEY, product_stream_id $binary, parent_id $binary, position INT, type VARCHAR(64), field VARCHAR(64), operator VARCHAR(64), value TEXT, parameters $json");
        $stream = $this->id(800);
        $draft = $this->id(900);
        $this->insert('product_stream', ['id' => $stream]);
        $this->insert('product_stream_filter', ['id' => $this->id(802), 'product_stream_id' => $stream, 'position' => 1, 'type' => 'equals', 'field' => 'id', 'value' => $this->id(2)]);
        $this->insert('product_stream_filter', ['id' => $this->id(801), 'product_stream_id' => $stream, 'position' => 1, 'type' => 'equals', 'field' => 'id', 'value' => $this->id(1)]);
        $streamDefinition = $this->definitions->get(ProductStreamDefinition::class);
        $compiler = new ProductStreamIndexer($this->connection, new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(ProductStreamCollection::class, [], $streamDefinition), new Serializer([], [new JsonEncoder()]), $this->product, $this->dispatcher);
        $compiler->handle(new ProductStreamIndexingMessage([$stream]));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT invalid FROM product_stream'));
        static::assertSame('multi', $this->json('SELECT api_filter FROM product_stream')[0]['type']);
        static::assertSame($this->id(1), $this->json('SELECT api_filter FROM product_stream')[0]['queries'][0]['value']);
        static::assertSame($this->id(2), $this->json('SELECT api_filter FROM product_stream')[1]['queries'][0]['value']);
        $ids = [];
        for ($i = 1; $i <= 503; ++$i) {
            $ids[] = $id = $this->id($i);
            $this->insert('product', ['id' => $id, 'version_id' => Defaults::LIVE_VERSION]);
        }
        $this->insert('product', ['id' => $ids[0], 'version_id' => $draft]);
        $this->insert('product_stream_mapping', ['product_id' => $ids[0], 'product_version_id' => $draft, 'product_stream_id' => $stream]);
        $language = new LanguageEntity();
        $language->setId(Defaults::LANGUAGE_SYSTEM);
        $language->setParentId(null);
        $languages = StaticEntityRepository::of(LanguageCollection::class, array_fill(0, 4, new LanguageCollection([$language])));
        $products = StaticEntityRepository::of(ProductCollection::class, [$ids, $ids], $this->product);
        $mapping = new ProductStreamUpdater($this->connection, $this->product, $products, new MessageBus(), new ManyToManyIdFieldUpdater($this->definitions, $this->connection), $languages, true);
        $mapping->updateProducts($ids, Context::createDefaultContext());
        $mapping->updateProducts($ids, Context::createDefaultContext());
        static::assertSame(504, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_stream_mapping'));
        $this->connection->executeStatement('DELETE FROM product_stream_filter');
        $compiler->handle(new ProductStreamIndexingMessage([$stream]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT invalid FROM product_stream'));
        $mapping->handle(new ProductStreamMappingIndexingMessage($stream));
        $mapping->handle(new ProductStreamMappingIndexingMessage($stream));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_stream_mapping'));
        static::assertSame(503, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product WHERE version_id = :version AND stream_ids IS NULL', ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]));
    }

    public function testNewsletterAggregationDeletionAndRecipientUpdatesPreserveTypedJsonAndConsent(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->create('customer', "id $binary PRIMARY KEY, email VARCHAR(255), first_name VARCHAR(255), last_name VARCHAR(255), newsletter_sales_channel_ids $json");
        $this->create('newsletter_recipient', "id $binary PRIMARY KEY, email VARCHAR(255), first_name VARCHAR(255), last_name VARCHAR(255), sales_channel_id $binary, status VARCHAR(64), confirmed_at TIMESTAMP NULL");
        $customer = $this->id(1);
        $first = $this->id(10);
        $second = $this->id(11);
        $channel = $this->id(20);
        $this->insert('customer', ['id' => $customer, 'email' => 'żółć@example.com', 'first_name' => 'Żółć', 'last_name' => 'A']);
        foreach ([$first, $second] as $id) {
            $this->insert('newsletter_recipient', ['id' => $id, 'email' => 'żółć@example.com', 'sales_channel_id' => $channel, 'status' => 'optIn', 'confirmed_at' => '2020-01-01 00:00:00']);
        }
        $updater = new CustomerNewsletterSalesChannelsUpdater($this->connection);
        $definition = new IndexingNewsletterRecipientDefinition();
        $this->definitions->register($definition, NewsletterRecipientDefinition::class);
        $indexer = new NewsletterRecipientIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(NewsletterRecipientCollection::class, [], $definition), $updater, $this->dispatcher);
        $registry = new EntityIndexerRegistry([$indexer], new MessageBus(), $this->dispatcher, new IndexerMetricsInstrumentor(static::createStub(Meter::class)));
        $registry->index(false);
        static::assertEquals([$first => $channel, $second => $channel], $this->json('SELECT newsletter_sales_channel_ids FROM customer'));
        $updater->update([$first]);
        static::assertCount(2, $this->json('SELECT newsletter_sales_channel_ids FROM customer'));
        $this->connection->executeStatement('DELETE FROM newsletter_recipient WHERE id = :id', ['id' => Uuid::fromHexToBytes($first)], ['id' => ParameterType::BINARY]);
        $message = new NewsletterRecipientIndexingMessage([$first], null);
        $message->setDeletedNewsletterRecipients(true);
        $indexer->handle($message);
        $indexer->handle($message);
        static::assertSame([$second => $channel], $this->json('SELECT newsletter_sales_channel_ids FROM customer'));
        $this->connection->executeStatement('UPDATE customer SET email = \'changed@example.com\'');
        $updater->updateCustomersRecipient([$customer]);
        static::assertSame('changed@example.com', $this->connection->fetchOne('SELECT email FROM newsletter_recipient'));
        static::assertSame('notSet', $this->connection->fetchOne('SELECT status FROM newsletter_recipient'));
        static::assertNull($this->connection->fetchOne('SELECT confirmed_at FROM newsletter_recipient'));
        $updater->update([$customer], true);
        static::assertNull($this->connection->fetchOne('SELECT newsletter_sales_channel_ids FROM customer'));
        $updater->delete([]);
    }

    public function testDeterministicLargeMappingAggregatesAndInheritedRemovalHaveNoTruncation(): void
    {
        $parent = $this->id(1);
        $child = $this->id(2);
        $this->insert('product', ['id' => $parent, 'version_id' => Defaults::LIVE_VERSION]);
        $this->insert('product', ['id' => $child, 'version_id' => Defaults::LIVE_VERSION, 'parent_id' => $parent, 'parent_version_id' => Defaults::LIVE_VERSION]);
        $expected = [];
        for ($i = 1100; $i >= 100; --$i) {
            $expected[] = $category = $this->id($i);
            $this->insert('product_category', ['product_id' => $parent, 'product_version_id' => Defaults::LIVE_VERSION, 'category_id' => $category, 'category_version_id' => Defaults::LIVE_VERSION]);
        }
        sort($expected, \SORT_STRING);
        $this->insert('product_category', ['product_id' => $parent, 'product_version_id' => Defaults::LIVE_VERSION, 'category_id' => $this->id(100), 'category_version_id' => $this->id(900)]);
        $inheritance = new InheritanceUpdater($this->connection, $this->definitions);
        $many = new ManyToManyIdFieldUpdater($this->definitions, $this->connection);
        $inheritance->update('product', [$parent, $child], Context::createDefaultContext());
        $many->update('product', [$parent, $child], Context::createDefaultContext(), 'categoryIds');
        static::assertSame($expected, $this->json('SELECT category_ids FROM product WHERE parent_id IS NOT NULL'));
        $this->connection->executeStatement('DELETE FROM product_category');
        $inheritance->update('product', [$parent, $child], Context::createDefaultContext());
        $many->update('product', [$parent, $child], Context::createDefaultContext());
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product WHERE category_ids IS NULL AND stream_ids IS NULL'));
    }

    public function testRulePayloadAreasAndPluginResetKeepBinarySerializationAndNestedRules(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $blob = $this->postgres ? 'BYTEA' : 'LONGBLOB';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->create('rule', "id $binary PRIMARY KEY, payload $blob, invalid SMALLINT CHECK(invalid IN(0,1)), areas $json, updated_at TIMESTAMP NULL");
        $this->create('rule_condition', "id $binary PRIMARY KEY, rule_id $binary, parent_id $binary, script_id $binary, type VARCHAR(64), position INT, value $json");
        $this->create('app_script_condition', "id $binary PRIMARY KEY, active SMALLINT CHECK(active IN(0,1)), script TEXT, identifier VARCHAR(64), updated_at TIMESTAMP NULL");
        $ruleId = $this->id(1);
        $condition = $this->id(2);
        $this->insert('rule', ['id' => $ruleId]);
        $this->insert('rule_condition', ['id' => $condition, 'rule_id' => $ruleId, 'type' => 'andContainer', 'position' => 0]);
        $this->insert('rule_condition', ['id' => $this->id(4), 'parent_id' => $condition, 'rule_id' => $ruleId, 'type' => 'andContainer', 'position' => 1]);
        $this->insert('rule_condition', ['id' => $this->id(3), 'parent_id' => $condition, 'rule_id' => $ruleId, 'type' => 'orContainer', 'position' => 1]);
        $registry = new RuleConditionRegistry([new AndRule(), new OrRule()]);
        $clock = new MockClock('2026-10-07 00:00:00');
        $payload = new RulePayloadUpdater($this->connection, $registry, $clock);
        static::assertFalse($payload->update([$ruleId])[$ruleId]['invalid']);
        $stored = $this->connection->fetchOne('SELECT payload FROM rule');
        $stored = Serialization::assertUnserializedInstanceOf(AndRule::class, \is_resource($stored) ? stream_get_contents($stored) : $stored);
        static::assertInstanceOf(AndRule::class, $stored);
        $nested = $stored->getRules()[0];
        static::assertInstanceOf(AndRule::class, $nested);
        static::assertCount(2, $nested->getRules());
        static::assertInstanceOf(OrRule::class, $nested->getRules()[0]);
        static::assertInstanceOf(AndRule::class, $nested->getRules()[1]);
        $definition = new IndexingRuleDefinition();
        $this->definitions->register($definition, RuleDefinition::class);
        $this->insert('product_price', ['id' => $this->id(5), 'rule_id' => $ruleId]);
        $areas = new RuleAreaUpdater($this->connection, $definition, $registry, static::createStub(CacheInvalidator::class), $this->definitions, $clock);
        $indexer = new RuleIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(RuleCollection::class, [], $definition), $payload, $areas, $this->dispatcher);
        $indexers = new EntityIndexerRegistry([$indexer], new MessageBus(), $this->dispatcher, new IndexerMetricsInstrumentor(static::createStub(Meter::class)));
        $indexers->index(false);
        static::assertSame(['product'], $this->json('SELECT areas FROM rule'));
        $this->connection->executeStatement('DELETE FROM product_price');
        $areas->update([$ruleId]);
        static::assertSame([], $this->json('SELECT areas FROM rule'));
        $entity = (new RuleEntity())->assign(['id' => $ruleId, '_uniqueIdentifier' => $ruleId, 'payload' => null, 'invalid' => false]);
        $subscriber = new RulePayloadSubscriber($payload, new Container());
        $subscriber->unserialize(new EntityLoadedEvent($definition, [$entity], Context::createDefaultContext()));
        static::assertInstanceOf(AndRule::class, $entity->getPayload());
        $refresh = new RuleIndexerSubscriber($this->connection, static::createStub(CartRuleLoader::class), $clock);
        $refresh->refreshPlugin();
        static::assertNull($this->connection->fetchOne('SELECT payload FROM rule'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT invalid FROM rule'));
    }

    public function testKeywordDictionaryReplacementCoversBothConflictsAndTypedLargeBatches(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $this->create('product_keyword_dictionary', "id $binary, language_id $binary, keyword VARCHAR(500), PRIMARY KEY(id,language_id), UNIQUE(language_id, keyword), FOREIGN KEY(language_id) REFERENCES language(id) ON DELETE CASCADE");
        $this->create('product_search_keyword', "id $binary, version_id $binary, product_version_id $binary, language_id $binary, product_id $binary, keyword VARCHAR(500), ranking DECIMAL(10,3), created_at TIMESTAMP NULL, PRIMARY KEY(id,version_id), UNIQUE(product_id,product_version_id,language_id,keyword)");
        $this->create('product_search_config', "id $binary PRIMARY KEY, language_id $binary");
        $this->create('product_search_config_field', "product_search_config_id $binary, field VARCHAR(255), tokenize SMALLINT CHECK(tokenize IN(0,1)), ranking DECIMAL(10,3), searchable SMALLINT CHECK(searchable IN(0,1))");
        $this->insert('language', ['id' => Defaults::LANGUAGE_SYSTEM]);
        $this->insert('product_search_config', ['id' => $this->id(9), 'language_id' => Defaults::LANGUAGE_SYSTEM]);
        $this->insert('product_search_config_field', ['product_search_config_id' => $this->id(9), 'field' => 'id', 'tokenize' => 0, 'ranking' => 1, 'searchable' => 1]);
        $language = new LanguageEntity();
        $language->setId(Defaults::LANGUAGE_SYSTEM);
        $language->setParentId(null);
        $product = new ProductEntity();
        $product->setId($this->id(1));
        $analyzed = new AnalyzedKeywordCollection();
        for ($i = 0; $i < 103; ++$i) {
            $analyzed->add(new AnalyzedKeyword('żółć-' . $i, 1.5));
        }
        $analyzer = static::createStub(ProductSearchKeywordAnalyzerInterface::class);
        $analyzer->method('analyze')->willReturn($analyzed);
        $languages = StaticEntityRepository::of(LanguageCollection::class, array_fill(0, 2, new LanguageCollection([$language])));
        $products = StaticEntityRepository::of(ProductCollection::class, [new ProductCollection([$product]), new ProductCollection(), new ProductCollection([$product]), new ProductCollection()], $this->product);
        $updater = new SearchKeywordUpdater($this->connection, $languages, $products, $analyzer, new MockClock('2026-10-07 00:00:00'));
        $updater->update([$product->getId()], Context::createDefaultContext());
        $updater->update([$product->getId()], Context::createDefaultContext());
        static::assertSame(103, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_search_keyword'));
        static::assertSame(103, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_keyword_dictionary'));
        static::assertEquals(1.5, $this->connection->fetchOne('SELECT MIN(ranking) FROM product_search_keyword'));

        $this->insert('product_keyword_dictionary', ['id' => $this->id(200), 'language_id' => Defaults::LANGUAGE_SYSTEM, 'keyword' => 'collision-a']);
        $this->insert('product_keyword_dictionary', ['id' => $this->id(201), 'language_id' => Defaults::LANGUAGE_SYSTEM, 'keyword' => 'collision-b']);
        $queue = new MultiInsertQueryQueue($this->connection, 1, false, true);
        $queue->setConflictTarget('product_keyword_dictionary', ['id', 'language_id']);
        $queue->setReplacementConflicts('product_keyword_dictionary', [['id', 'language_id'], ['language_id', 'keyword']]);
        $types = ['id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY];
        $queue->addInsert('product_keyword_dictionary', ['id' => Uuid::fromHexToBytes($this->id(200)), 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'keyword' => 'collision-b'], $types);
        $queue->addInsert('product_keyword_dictionary', ['id' => Uuid::fromHexToBytes($this->id(202)), 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'keyword' => 'collision-b'], $types);
        $queue->execute();
        static::assertSame(104, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_keyword_dictionary'));
        static::assertSame('collision-b', $this->connection->fetchOne('SELECT keyword FROM product_keyword_dictionary WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->id(202))], ['id' => ParameterType::BINARY]));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_keyword_dictionary WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->id(200))], ['id' => ParameterType::BINARY]));
        $queue->addInsert('product_keyword_dictionary', ['id' => Uuid::fromHexToBytes($this->id(203)), 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'keyword' => 'rollback'], $types);
        $queue->addInsert('product_keyword_dictionary', ['id' => Uuid::fromHexToBytes($this->id(202)), 'language_id' => Uuid::fromHexToBytes($this->id(999)), 'keyword' => 'collision-b'], $types);
        try {
            $queue->execute();
            static::fail('Replacement must not suppress a foreign key violation.');
        } catch (ForeignKeyConstraintViolationException) {
            static::assertSame(104, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_keyword_dictionary'));
        }
        if ($this->postgres) {
            $queue->setReplacementConflicts('product_keyword_dictionary', [['id', 'language_id'], ['language_id', 'keyword'], ['language_id']]);
            try {
                $queue->execute();
                static::fail('A nonunique replacement target could delete unrelated rows.');
            } catch (DataAbstractionLayerException $exception) {
                static::assertStringContainsString('not a unique key', $exception->getMessage());
            }
        }
    }

    public function testMediaIndexersWriteBinaryCollectionsAndFolderInheritance(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $blob = $this->postgres ? 'BYTEA' : 'LONGBLOB';
        $this->create('media_folder', "id $binary PRIMARY KEY, parent_id $binary, media_folder_configuration_id $binary, use_parent_configuration SMALLINT CHECK(use_parent_configuration IN(0,1)), child_count INT DEFAULT 0, path TEXT");
        $this->create('media_folder_configuration', "id $binary PRIMARY KEY, media_thumbnail_sizes_ro $blob");
        $this->create('media', "id $binary PRIMARY KEY, thumbnails_ro $blob, private SMALLINT CHECK(private IN(0,1))");
        $this->definitions->register(new IndexingMediaFolderDefinition(), MediaFolderDefinition::class);
        $this->definitions->register(new MediaDefinition());
        $mediaId = $this->id(1);
        $configId = $this->id(2);
        $folderId = $this->id(3);
        $childId = $this->id(4);
        $this->insert('media', ['id' => $mediaId, 'private' => 1]);
        $this->insert('media_folder_configuration', ['id' => $configId]);
        $this->insert('media_folder', ['id' => $folderId, 'media_folder_configuration_id' => $configId, 'use_parent_configuration' => 0]);
        $this->insert('media_folder', ['id' => $childId, 'parent_id' => $folderId, 'media_folder_configuration_id' => $this->id(20), 'use_parent_configuration' => 1]);
        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($this->id(9));
        $thumbnail->setMediaId($mediaId);
        $thumbnailCollection = new MediaThumbnailCollection([$thumbnail]);
        $mediaIndexer = new MediaIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(MediaCollection::class), StaticEntityRepository::of(MediaThumbnailCollection::class, [$thumbnailCollection]), $this->connection, $this->dispatcher, false);
        $mediaIndexer->handle(new MediaIndexingMessage([$mediaId]));
        $stored = $this->connection->fetchOne('SELECT thumbnails_ro FROM media');
        static::assertSame([$thumbnail->getId()], array_values(Serialization::assertUnserializedInstanceOf(MediaThumbnailCollection::class, \is_resource($stored) ? stream_get_contents($stored) : $stored)->getIds()));
        $configuration = new MediaFolderConfigurationEntity();
        $configuration->setId($configId);
        $configuration->setMediaThumbnailSizes(new MediaThumbnailSizeCollection());
        $configIndexer = new MediaFolderConfigurationIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(MediaFolderConfigurationCollection::class, [new MediaFolderConfigurationCollection([$configuration])]), $this->connection, $this->dispatcher);
        $configIndexer->handle(new MediaIndexingMessage([$configId]));
        $stored = $this->connection->fetchOne('SELECT media_thumbnail_sizes_ro FROM media_folder_configuration');
        static::assertCount(0, Serialization::assertUnserializedInstanceOf(MediaThumbnailSizeCollection::class, \is_resource($stored) ? stream_get_contents($stored) : $stored));
        $folder = new MediaFolderIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(MediaFolderCollection::class), $this->connection, $this->dispatcher, new ChildCountUpdater($this->definitions, $this->connection), new TreeUpdater($this->definitions, $this->connection));
        $folder->handle(new MediaIndexingMessage([$folderId, $childId]));
        static::assertSame($configId, $this->connection->fetchOne('SELECT ' . IndexingSql::hex($this->connection, 'media_folder_configuration_id') . ' FROM media_folder WHERE parent_id IS NOT NULL'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT child_count FROM media_folder WHERE parent_id IS NULL'));
    }

    public function testMediaAndDefaultProfileValidatorsReadActualBinaryPrimaryKeys(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $this->create('media', "id $binary PRIMARY KEY, private SMALLINT CHECK(private IN(0,1))");
        $this->create('import_export_profile', "id $binary PRIMARY KEY, system_default SMALLINT CHECK(system_default IN(0,1))");
        $this->insert('media', ['id' => $this->id(1), 'private' => 1]);
        $this->insert('import_export_profile', ['id' => $this->id(2), 'system_default' => 1]);
        $media = new MediaDefinition();
        $profile = new ImportExportProfileDefinition();
        $this->definitions->register($media);
        $this->definitions->register($profile);
        $command = new UpdateCommand($media, ['file_extension' => 'pdf'], ['id' => Uuid::fromHexToBytes($this->id(1))], static::createStub(EntityExistence::class), '/0');
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        $validator = new MediaFileExtensionWriteValidator(new MediaFileExtensionListProvider($this->dispatcher, ['png'], ['pdf']), $this->connection);
        $validator->preValidate($event);
        static::assertCount(0, $event->getExceptions()->getExceptions());
        $delete = new DeleteCommand($profile, ['id' => Uuid::fromHexToBytes($this->id(2))], static::createStub(EntityExistence::class));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$delete]);
        (new SystemDefaultValidator($this->connection))->preValidate($event);
        static::assertCount(1, $event->getExceptions()->getExceptions());
    }

    public function testDescriptionTeaserAndLegacyStatesRunWithVersionedBinaryKeysAndDuplicateDownloads(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("ALTER TABLE product ADD type VARCHAR(64), ADD states $json");
        $this->create('product_download', "id $binary PRIMARY KEY, product_id $binary, product_version_id $binary");
        $this->create('product_translation', "product_id $binary, product_version_id $binary, language_id $binary, description TEXT, description_teaser TEXT, PRIMARY KEY(product_id, product_version_id, language_id)");
        $product = $this->id(1);
        $draft = $this->id(900);
        $this->insert('product', ['id' => $product, 'version_id' => Defaults::LIVE_VERSION]);
        $this->insert('product', ['id' => $product, 'version_id' => $draft, 'type' => 'physical']);
        foreach ([2, 3] as $id) {
            $this->insert('product_download', ['id' => $this->id($id), 'product_id' => $product, 'product_version_id' => Defaults::LIVE_VERSION]);
        }
        $states = new StatesUpdater($this->connection, $this->dispatcher);
        Feature::silent('v6.8.0.0', static fn () => $states->update([$product], Context::createDefaultContext()));
        static::assertSame('digital', $this->connection->fetchOne('SELECT type FROM product WHERE version_id = :version', ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]));
        static::assertSame('physical', $this->connection->fetchOne('SELECT type FROM product WHERE version_id = :version', ['version' => Uuid::fromHexToBytes($draft)], ['version' => ParameterType::BINARY]));
        $this->insert('product_translation', ['product_id' => $product, 'product_version_id' => Defaults::LIVE_VERSION, 'language_id' => Defaults::LANGUAGE_SYSTEM, 'description' => '<p>Żółć</p>', 'description_teaser' => null]);
        $indexer = new ProductDescriptionTeaserIndexer(new IteratorFactory($this->connection, $this->definitions), $this->connection, new ProductDescriptionTeaserBuilder(new HtmlSanitizer(cacheEnabled: false)));
        $indexer->handle(new ProductIndexingMessage([$product]));
        $indexer->handle(new ProductIndexingMessage([$product]));
        static::assertSame('Żółć', $this->connection->fetchOne('SELECT description_teaser FROM product_translation'));
    }

    public function testDraftCategoryPathsAndBreadcrumbsLeaveLiveVersionUnchanged(): void
    {
        $draft = $this->id(900);
        $root = $this->id(1);
        $child = $this->id(2);
        $this->insert('language', ['id' => Defaults::LANGUAGE_SYSTEM]);
        foreach ([Defaults::LIVE_VERSION, $draft] as $version) {
            foreach ([$root, $child] as $id) {
                $this->insert('category', ['id' => $id, 'version_id' => $version, 'parent_id' => $id === $root ? null : $root, 'parent_version_id' => $id === $root ? null : $version]);
                $this->insert('category_translation', ['category_id' => $id, 'category_version_id' => $version, 'language_id' => Defaults::LANGUAGE_SYSTEM, 'name' => $version === $draft ? 'draft' : 'live']);
            }
        }
        $context = Context::createDefaultContext()->createWithVersionId($draft);
        $this->categoryIndexer()->handle(new CategoryIndexingMessage([$root, $child], null, $context));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM category_translation WHERE breadcrumb IS NOT NULL'));
        static::assertSame('|' . $root . '|', $this->connection->fetchOne('SELECT path FROM category WHERE id = :id AND version_id = :version', ['id' => Uuid::fromHexToBytes($child), 'version' => Uuid::fromHexToBytes($draft)], ['id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM category WHERE version_id = :version AND path IS NOT NULL', ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['version' => ParameterType::BINARY]));
    }

    public function testLargeProductFullAndIncrementalDenormalizationTraversesEveryBatchAndMeasuresPlans(): void
    {
        foreach ([10, 11] as $number) {
            $this->insert('category', ['id' => $this->id($number), 'version_id' => Defaults::LIVE_VERSION]);
        }
        $ids = [];
        for ($i = 100; $i < 603; ++$i) {
            $ids[] = $id = $this->id($i);
            $this->insert('product', ['id' => $id, 'version_id' => Defaults::LIVE_VERSION, 'price' => $this->prices(10)]);
            foreach ([10, 11] as $category) {
                $this->insert('product_category', ['product_id' => $id, 'product_version_id' => Defaults::LIVE_VERSION, 'category_id' => $this->id($category), 'category_version_id' => Defaults::LIVE_VERSION]);
            }
        }
        $indexer = $this->productIndexer();
        $registry = new EntityIndexerRegistry([$indexer], new MessageBus(), $this->dispatcher, new IndexerMetricsInstrumentor(static::createStub(Meter::class)));
        $start = hrtime(true);
        $registry->index(false, [ProductIndexer::STOCK_UPDATER, ProductIndexer::SEARCH_KEYWORD_UPDATER, 'product.states']);
        $fullMilliseconds = (hrtime(true) - $start) / 1e6;
        static::assertSame(1006, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_category_tree'));
        static::assertSame(503, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product WHERE cheapest_price_accessor IS NOT NULL AND category_ids IS NOT NULL AND display_group IS NOT NULL'));
        $before = $this->productState();
        $start = hrtime(true);
        $message = new ProductIndexingMessage($ids);
        $message->addSkip(ProductIndexer::STOCK_UPDATER, ProductIndexer::SEARCH_KEYWORD_UPDATER, 'product.states');
        $indexer->handle($message);
        static::assertSame($before, $this->productState());
        $incrementalMilliseconds = (hrtime(true) - $start) / 1e6;
        $plan = $this->connection->fetchAllAssociative('EXPLAIN SELECT product.id, product.version_id FROM product LEFT JOIN product_category mapping ON mapping.product_id = product.categories AND mapping.product_version_id = product.version_id WHERE product.id = :id AND product.version_id = :version', ['id' => Uuid::fromHexToBytes($ids[0]), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]);
        static::assertNotEmpty($plan);
        fwrite(\STDERR, "\n[indexers-measurement] " . json_encode(['engine' => $this->engine, 'products' => 503, 'mappingRows' => 1006, 'fullMs' => round($fullMilliseconds, 2), 'incrementalMs' => round($incrementalMilliseconds, 2), 'peakBytes' => memory_get_peak_usage(true), 'explain' => $plan], \JSON_THROW_ON_ERROR) . "\n");
        $this->connection->executeStatement('DELETE FROM product_category');
        $indexer->handle($message);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product_category_tree'));
    }

    private function productIndexer(): ProductIndexer
    {
        return new ProductIndexer(new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(ProductCollection::class, [], $this->product), $this->connection, new VariantListingUpdater($this->connection), new ProductCategoryDenormalizer($this->connection), new InheritanceUpdater($this->connection, $this->definitions), new RatingAverageUpdater($this->connection), static::createStub(SearchKeywordUpdater::class), new ChildCountUpdater($this->definitions, $this->connection), new ManyToManyIdFieldUpdater($this->definitions, $this->connection), static::createStub(AbstractStockStorage::class), $this->dispatcher, new CheapestPriceUpdater($this->connection, new CheapestPriceQuantitySelector(), $this->dispatcher), static::createStub(AbstractProductStreamUpdater::class), new MessageBus(), null, new MockClock('2026-10-07 00:00:00'));
    }

    private function categoryIndexer(): CategoryIndexer
    {
        return new CategoryIndexer($this->connection, new IteratorFactory($this->connection, $this->definitions), StaticEntityRepository::of(CategoryCollection::class, [], $this->category), new ChildCountUpdater($this->definitions, $this->connection), new TreeUpdater($this->definitions, $this->connection), new CategoryBreadcrumbUpdater($this->connection), $this->dispatcher, new MessageBus());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productState(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT ' . IndexingSql::hex($this->connection, 'id') . ' AS id, ' . IndexingSql::hex($this->connection, 'version_id') . ' AS version_id, child_count, display_group, category_tree, category_ids, cheapest_price_accessor FROM product ORDER BY id, version_id');
        foreach ($rows as &$row) {
            foreach (['category_tree', 'category_ids', 'cheapest_price_accessor'] as $key) {
                $row[$key] = $row[$key] === null ? null : json_decode($row[$key], true, 512, \JSON_THROW_ON_ERROR);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insert(string $table, array $values): void
    {
        $types = [];
        foreach ($values as $key => &$value) {
            if (\is_array($value)) {
                $value = json_encode($value, \JSON_THROW_ON_ERROR);
            } elseif (\is_string($value) && (str_ends_with($key, '_id') || $key === 'id') && Uuid::isValid($value)) {
                $value = Uuid::fromHexToBytes($value);
                $types[$key] = ParameterType::BINARY;
            }
        }
        $this->connection->insert($table, $values, $types);
    }

    private function create(string $table, string $fields): void
    {
        static::assertFalse($this->connection->createSchemaManager()->tablesExist([$table]));
        $this->connection->executeStatement('CREATE TABLE ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table) . ' (' . $fields . ')');
        $this->tables[] = $table;
    }

    private function id(int $number): string
    {
        return str_pad(dechex($number), 32, '0', \STR_PAD_LEFT);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function prices(float $gross): array
    {
        return [['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $gross / 2, 'linked' => true]];
    }

    /** @param array<string, mixed> $parameters
     * @param array<string, ParameterType> $types
     *
     * @return array<mixed>
     */
    private function json(string $sql, array $parameters = [], array $types = []): array
    {
        return json_decode($this->connection->fetchOne($sql, $parameters, $types), true, 512, \JSON_THROW_ON_ERROR);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingProductDefinition extends ProductDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey()), new Field\VersionField(), new Field\ParentFkField(ProductDefinition::class), new Field\ReferenceVersionField(ProductDefinition::class, 'parent_version_id'),
            new Field\IntField('child_count', 'childCount'), new Field\FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class),
            (new Field\ManyToOneAssociationField('manufacturer', 'product_manufacturer_id', ProductManufacturerDefinition::class, 'id'))->addFlags(new Flag\Inherited()),
            (new Field\OneToManyAssociationField('prices', ProductPriceDefinition::class, 'product_id'))->addFlags(new Flag\Inherited()),
            (new Field\ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id'))->addFlags(new Flag\Inherited()),
            new Field\ManyToManyIdField('category_ids', 'categoryIds', 'categories'),
            new Field\ManyToManyAssociationField('streams', ProductStreamDefinition::class, ProductStreamMappingDefinition::class, 'product_id', 'product_stream_id'),
            new Field\ManyToManyIdField('stream_ids', 'streamIds', 'streams'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingCategoryDefinition extends CategoryDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey()), new Field\VersionField(), new Field\ParentFkField(CategoryDefinition::class), new Field\ReferenceVersionField(CategoryDefinition::class, 'parent_version_id'), new Field\TreePathField('path', 'path'), new Field\TreeLevelField('level', 'level'), new Field\IntField('child_count', 'childCount')]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingRuleDefinition extends RuleDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey()), (new Field\OneToManyAssociationField('prices', ProductPriceDefinition::class, 'rule_id'))->addFlags(new Flag\RuleAreas('product'))]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingMediaFolderDefinition extends MediaFolderDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey()), new Field\ParentFkField(self::class), new Field\TreePathField('path', 'path')]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingLandingPageDefinition extends LandingPageDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey()), new Field\VersionField()]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingNewsletterRecipientDefinition extends NewsletterRecipientDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey())]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class IndexingMultiPathDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'indexing_multi_path';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Flag\PrimaryKey()), new Field\VersionField(), new Field\ParentFkField(self::class), new Field\ReferenceVersionField(self::class, 'parent_version_id'), new Field\StringField('token', 'token'), new Field\TreePathField('path', 'path'), new Field\TreePathField('order', 'tokenPath', 'token'), new Field\TreeLevelField('level', 'level')]);
    }
}

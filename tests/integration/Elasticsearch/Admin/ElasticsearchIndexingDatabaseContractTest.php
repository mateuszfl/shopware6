<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\CategoryAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\CmsPageAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\CustomerAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\CustomerGroupAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\LandingPageAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ManufacturerAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\MediaAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\NewsletterRecipientAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\OrderAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PaymentMethodAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ProductStreamAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PropertyGroupAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\SalesChannelAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ShippingMethodAdminSearchIndexer;
use Shopware\Elasticsearch\Framework\ClientFactory;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchStatusCommand;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Product\CustomFieldSetGateway;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\ProductCustomFieldsUsedUpdater;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\StopwordTokenFilter;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Unit\Core\System\Language\Stubs\StaticLanguageLoader;
use Shopware\Tests\Unit\Core\System\Language\Stubs\StaticSalesChannelLanguageLoader;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ElasticsearchIndexingDatabaseContractTest extends TestCase
{
    private Connection $connection;

    private string $schema;

    private string $originalSchema;

    private bool $postgres;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $this->schema = 'sw_es_indexer_' . Uuid::randomHex();
        $this->originalSchema = $this->postgres
            ? (string) $this->connection->fetchOne('SELECT current_schema()')
            : (string) $this->connection->getDatabase();
        $quotedSchema = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($this->schema);

        $this->connection->executeStatement($this->postgres ? 'CREATE SCHEMA ' . $quotedSchema : 'CREATE DATABASE ' . $quotedSchema);
        $this->connection->executeStatement($this->postgres ? 'SET search_path TO ' . $quotedSchema : 'USE ' . $quotedSchema);
        (new DatabaseFunctionInstaller())->install($this->connection);

        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE customer_group (id $binary NOT NULL PRIMARY KEY)");
        $this->connection->executeStatement("CREATE TABLE customer_group_translation (customer_group_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        foreach (['payment_method', 'sales_channel', 'shipping_method'] as $table) {
            $this->connection->executeStatement("CREATE TABLE $table (id $binary NOT NULL PRIMARY KEY)");
            $this->connection->executeStatement("CREATE TABLE {$table}_translation ({$table}_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        }
        $this->connection->executeStatement("CREATE TABLE product_stream_translation (product_stream_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE newsletter_recipient (id $binary NOT NULL PRIMARY KEY, email VARCHAR(255), first_name VARCHAR(255), last_name VARCHAR(255), status VARCHAR(32), city VARCHAR(255), zip_code VARCHAR(64), street VARCHAR(255), sales_channel_id $binary NULL, language_id $binary NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE newsletter_recipient_tag (newsletter_recipient_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE customer (id $binary NOT NULL PRIMARY KEY, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255), company VARCHAR(255), customer_number VARCHAR(64), active BOOLEAN, affiliate_code VARCHAR(64), campaign_code VARCHAR(64), customer_group_id $binary NULL, salutation_id $binary NULL, bound_sales_channel_id $binary NULL, requested_customer_group_id $binary NULL, default_billing_address_id $binary NULL, default_shipping_address_id $binary NULL, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE customer_address (id $binary NOT NULL PRIMARY KEY, customer_id $binary NULL, country_id $binary NULL, first_name VARCHAR(255), last_name VARCHAR(255), company VARCHAR(255), city VARCHAR(255), street VARCHAR(255), zipcode VARCHAR(64), phone_number VARCHAR(64), additional_address_line1 VARCHAR(255), additional_address_line2 VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE customer_tag (customer_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE country (id $binary NOT NULL PRIMARY KEY)");
        $this->connection->executeStatement("CREATE TABLE country_translation (country_id $binary NOT NULL, name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE media (id $binary NOT NULL PRIMARY KEY, media_folder_id $binary NULL, private BOOLEAN, file_name VARCHAR(255), file_extension VARCHAR(32), file_size BIGINT, path VARCHAR(255), created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE media_translation (media_id $binary NOT NULL, language_id $binary NOT NULL, title VARCHAR(255), alt VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE media_folder (id $binary NOT NULL PRIMARY KEY, name VARCHAR(255), path VARCHAR(255), default_folder_id $binary NULL)");
        $this->connection->executeStatement("CREATE TABLE media_default_folder (id $binary NOT NULL PRIMARY KEY, entity VARCHAR(64))");
        $this->connection->executeStatement("CREATE TABLE media_tag (media_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $order = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('order');
        $this->connection->executeStatement("CREATE TABLE $order (id $binary NOT NULL, version_id $binary NOT NULL, order_number VARCHAR(64), amount_total DECIMAL(10,2), order_date_time TIMESTAMP NULL, state_id $binary NULL, sales_channel_id $binary NULL, affiliate_code VARCHAR(64), campaign_code VARCHAR(64), created_at TIMESTAMP NULL, billing_address_id $binary NULL, billing_address_version_id $binary NULL, primary_order_transaction_id $binary NULL, primary_order_transaction_version_id $binary NULL, primary_order_delivery_id $binary NULL, primary_order_delivery_version_id $binary NULL, PRIMARY KEY (id, version_id))");
        $this->connection->executeStatement("CREATE TABLE order_customer (id $binary NOT NULL PRIMARY KEY, order_id $binary NOT NULL, order_version_id $binary NOT NULL, customer_id $binary NULL, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255), company VARCHAR(255), customer_number VARCHAR(64))");
        $this->connection->executeStatement("CREATE TABLE order_tag (order_id $binary NOT NULL, order_version_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE order_address (id $binary NOT NULL PRIMARY KEY, order_id $binary NULL, order_version_id $binary NULL, version_id $binary NULL, country_id $binary NULL, city VARCHAR(255), street VARCHAR(255), zipcode VARCHAR(64), phone_number VARCHAR(64), additional_address_line1 VARCHAR(255), additional_address_line2 VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE document (id $binary NOT NULL PRIMARY KEY, order_id $binary NOT NULL, document_number VARCHAR(64))");
        $this->connection->executeStatement("CREATE TABLE order_line_item (id $binary NOT NULL PRIMARY KEY, order_id $binary NOT NULL, order_version_id $binary NOT NULL, product_id $binary NULL, promotion_id $binary NULL, payload $json NULL)");
        $this->connection->executeStatement("CREATE TABLE order_transaction (id $binary NOT NULL, version_id $binary NOT NULL, state_id $binary NULL, payment_method_id $binary NULL, PRIMARY KEY(id, version_id))");
        $this->connection->executeStatement("CREATE TABLE order_delivery (id $binary NOT NULL, version_id $binary NOT NULL, state_id $binary NULL, shipping_method_id $binary NULL, shipping_order_address_id $binary NULL, shipping_order_address_version_id $binary NULL, tracking_codes TEXT NULL, PRIMARY KEY(id, version_id))");
        $this->connection->executeStatement("CREATE TABLE category (id $binary NOT NULL PRIMARY KEY, parent_id $binary NULL, active BOOLEAN NOT NULL, visible BOOLEAN NOT NULL, type VARCHAR(32) NOT NULL, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE category_tag (category_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE cms_page (id $binary NOT NULL PRIMARY KEY, type VARCHAR(32) NOT NULL, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE cms_page_translation (cms_page_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE landing_page (id $binary NOT NULL PRIMARY KEY, active BOOLEAN NOT NULL, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE landing_page_translation (landing_page_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE landing_page_tag (landing_page_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE product_manufacturer (id $binary NOT NULL PRIMARY KEY, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE promotion (id $binary NOT NULL PRIMARY KEY, code VARCHAR(64) NOT NULL, active BOOLEAN NOT NULL, valid_from TIMESTAMP NULL, valid_until TIMESTAMP NULL, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE promotion_translation (promotion_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE property_group (id $binary NOT NULL PRIMARY KEY, filterable BOOLEAN NOT NULL, created_at TIMESTAMP NULL)");
        $this->connection->executeStatement("CREATE TABLE property_group_translation (property_group_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $platform = $this->connection->getDatabasePlatform();
        $this->connection->executeStatement('CREATE TABLE admin_elasticsearch_index_task (id ' . $binary . ' NOT NULL PRIMARY KEY, entity VARCHAR(64) NOT NULL, ' . $platform->quoteSingleIdentifier('index') . ' VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, doc_count INT NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE elasticsearch_index_task (id ' . $binary . ' NOT NULL PRIMARY KEY, entity VARCHAR(64) NOT NULL, ' . $platform->quoteSingleIdentifier('index') . ' VARCHAR(255) NOT NULL, alias VARCHAR(255) NOT NULL, doc_count INT NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE indexing_parent_contract (id INT NOT NULL PRIMARY KEY)');
        $this->connection->executeStatement('CREATE TABLE indexing_child_contract (parent_id INT NOT NULL, value VARCHAR(64) NOT NULL)');
        $this->connection->executeStatement("CREATE TABLE custom_field_set (id $binary NOT NULL PRIMARY KEY, app_id $binary NULL)");
        $this->connection->executeStatement("CREATE TABLE custom_field_set_relation (set_id $binary NOT NULL, entity_name VARCHAR(64) NOT NULL)");
        $boolean = $this->postgres ? 'BOOLEAN' : 'TINYINT';
        $this->connection->executeStatement("CREATE TABLE custom_field (id $binary NOT NULL PRIMARY KEY, set_id $binary NOT NULL, name VARCHAR(64) NOT NULL, type VARCHAR(32) NOT NULL, active $boolean NOT NULL, include_in_search $boolean NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE product_sorting (id $binary NOT NULL PRIMARY KEY, fields $json NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE product_stream (id $binary NOT NULL PRIMARY KEY, api_filter $json NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE product_stream_filter (id $binary NOT NULL PRIMARY KEY, field VARCHAR(255) NOT NULL)");
        $this->createProductDefinitionSchema($binary, $json);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }

        $quotedSchema = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($this->originalSchema);
        $this->connection->executeStatement($this->postgres ? 'SET search_path TO ' . $quotedSchema : 'USE ' . $quotedSchema);
        $quotedSchema = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($this->schema);
        $this->connection->executeStatement($this->postgres ? 'DROP SCHEMA ' . $quotedSchema . ' CASCADE' : 'DROP DATABASE ' . $quotedSchema);
        $this->connection->close();
    }

    public function testFetchAggregatesUniqueTranslationsOnBothDatabasePlatforms(): void
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('customer_group', ['id' => $id], ['id' => ParameterType::BINARY]);
        foreach (['First Name', 'First Name', 'Second Name'] as $name) {
            $this->connection->insert('customer_group_translation', ['customer_group_id' => $id, 'name' => $name], [
                'customer_group_id' => ParameterType::BINARY,
                'name' => Types::STRING,
            ]);
        }

        $indexer = new CustomerGroupAdminSearchIndexer(
            $this->connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            10,
        );

        static::assertSame([
            Uuid::fromBytesToHex($id) => [
                'id' => Uuid::fromBytesToHex($id),
                'text' => Uuid::fromBytesToHex($id) . ' first name second name',
                'completion' => ['first name second name'],
            ],
        ], $indexer->fetch([Uuid::fromBytesToHex($id)]));

        $client = ClientFactory::createClient(getenv('OPENSEARCH_URL') ?: 'http://127.0.0.1:9200', new NullLogger(), false, ['verify_server_cert' => true]);
        $aliasPrefix = 'task017-' . Uuid::randomHex();
        $alias = $aliasPrefix . '-customer-group-listing';
        $iterator = new class(Uuid::fromBytesToHex($id), new QueryBuilder($this->connection)) implements IterableQuery {
            private bool $fetched = false;

            public bool $wasFetched = false;

            public function fetch(): array
            {
                $this->wasFetched = true;
                if ($this->fetched) {
                    return [];
                }
                $this->fetched = true;

                return [$this->id];
            }

            public function fetchCount(): int
            {
                return 1;
            }

            public function getQuery(): QueryBuilder
            {
                return $this->query;
            }

            public function __construct(private readonly string $id, private readonly QueryBuilder $query)
            {
            }

            public function getOffset(): array
            {
                return ['offset' => 0];
            }
        };
        $iteratorFactory = static::createStub(IteratorFactory::class);
        $iteratorFactory->method('createIterator')->willReturn($iterator);
        $indexer = new CustomerGroupAdminSearchIndexer($this->connection, $iteratorFactory, static::createStub(EntityRepository::class), 10);
        $registry = new AdminSearchRegistry(
            ['customer_group' => $indexer],
            $this->connection,
            static::createStub(MessageBusInterface::class),
            new EventDispatcher(),
            $client,
            new AdminElasticsearchHelper(true, false, $aliasPrefix, 'test', true, new NullLogger()),
            new NullLogger(),
            ['settings' => ['analysis' => [
                'analyzer' => [
                    'sw_admin_completion_index_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase']],
                    'sw_admin_completion_search_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase']],
                    'sw_ngram_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase']],
                    'sw_whitespace_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase']],
                ],
                'normalizer' => ['sw_lowercase_normalizer' => ['type' => 'custom', 'filter' => ['lowercase']]],
            ]]],
            [],
            'test',
            new MockClock('2026-10-19T10:00:00+00:00')
        );
        try {
            $registry->iterate(new AdminIndexingBehavior(noQueue: true));
            static::assertTrue($iterator->wasFetched);
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM admin_elasticsearch_index_task'));
            $platform = $this->connection->getDatabasePlatform();
            $task = $this->connection->fetchAssociative('SELECT ' . $platform->quoteSingleIdentifier('alias') . ', ' . $platform->quoteSingleIdentifier('index') . ' FROM admin_elasticsearch_index_task');
            static::assertIsArray($task);
            static::assertSame($alias, $task['alias']);
            static::assertNotEmpty($task['index']);
            $client->indices()->refresh(['index' => $alias]);
            static::assertSame(1, $client->count(['index' => $alias])['count']);
            $result = $client->search(['index' => $alias, 'body' => ['query' => ['match' => ['text' => 'first name']]]]);
            static::assertSame(Uuid::fromBytesToHex($id), $result['hits']['hits'][0]['_id'] ?? null, json_encode($result, \JSON_THROW_ON_ERROR));

            $this->connection->insert('customer_group_translation', ['customer_group_id' => $id, 'name' => 'Incremental Name'], [
                'customer_group_id' => ParameterType::BINARY,
                'name' => Types::STRING,
            ]);
            $context = Context::createDefaultContext();
            $nestedEvents = new NestedEventCollection([
                new EntityWrittenEvent('customer_group', [new EntityWriteResult(Uuid::fromBytesToHex($id), [], 'customer_group', EntityWriteResult::OPERATION_UPDATE)], $context),
                new EntityWrittenEvent('customer_group_translation', [new EntityWriteResult(Uuid::fromBytesToHex($id), ['name' => 'Incremental Name'], 'customer_group_translation', EntityWriteResult::OPERATION_UPDATE)], $context),
            ]);
            $event = new EntityWrittenContainerEvent($context, $nestedEvents, []);
            $registry->refresh($event);
            $result = $client->search(['index' => $alias, 'body' => ['query' => ['match' => ['text' => 'incremental name']]]]);
            static::assertSame(Uuid::fromBytesToHex($id), $result['hits']['hits'][0]['_id']);
        } finally {
            if ($client->indices()->existsAlias(['name' => $alias])) {
                foreach (array_keys($client->indices()->getAlias(['name' => $alias])) as $index) {
                    $client->indices()->delete(['index' => $index]);
                }
            }
        }
    }

    public function testSimpleAdminIndexersFetchDistinctTranslationsOnBothPlatforms(): void
    {
        $indexers = [
            'payment_method' => PaymentMethodAdminSearchIndexer::class,
            'sales_channel' => SalesChannelAdminSearchIndexer::class,
            'shipping_method' => ShippingMethodAdminSearchIndexer::class,
            'product_stream' => ProductStreamAdminSearchIndexer::class,
        ];
        foreach ($indexers as $entity => $indexerClass) {
            $id = Uuid::randomBytes();
            $hex = Uuid::fromBytesToHex($id);
            $entityData = ['id' => $id];
            $entityTypes = ['id' => ParameterType::BINARY];
            if ($entity === 'product_stream') {
                $entityData['api_filter'] = '{}';
                $entityTypes['api_filter'] = Types::JSON;
            }
            $this->connection->insert($entity, $entityData, $entityTypes);
            foreach (['Repeated Name', 'Repeated Name', 'Second Name'] as $name) {
                $this->connection->insert($entity . '_translation', [$entity . '_id' => $id, 'name' => $name], [$entity . '_id' => ParameterType::BINARY]);
            }

            $indexer = new $indexerClass($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), 10);
            static::assertSame([
                $hex => [
                    'id' => $hex,
                    'text' => $hex . ' repeated name second name',
                    'completion' => ['repeated name second name'],
                ],
            ], $indexer->fetch([$hex]), $entity);
        }
    }

    public function testTranslatedAdminIndexersFetchDocumentsWithEmptyTagRelations(): void
    {
        $indexers = [
            'category' => [CategoryAdminSearchIndexer::class, 'category_translation', 'category_id', ['parent_id' => null, 'active' => true, 'visible' => true, 'type' => 'page', 'created_at' => null]],
            'cms_page' => [CmsPageAdminSearchIndexer::class, 'cms_page_translation', 'cms_page_id', ['type' => 'page', 'created_at' => null]],
            'landing_page' => [LandingPageAdminSearchIndexer::class, 'landing_page_translation', 'landing_page_id', ['active' => true, 'created_at' => null]],
            'product_manufacturer' => [ManufacturerAdminSearchIndexer::class, 'product_manufacturer_translation', 'product_manufacturer_id', ['created_at' => null]],
            'promotion' => [PromotionAdminSearchIndexer::class, 'promotion_translation', 'promotion_id', ['code' => 'PROMO', 'active' => true, 'valid_from' => null, 'valid_until' => null, 'created_at' => null]],
            'property_group' => [PropertyGroupAdminSearchIndexer::class, 'property_group_translation', 'property_group_id', ['filterable' => true, 'created_at' => null]],
        ];

        foreach ($indexers as $entity => [$indexerClass, $translationTable, $translationIdColumn, $extraColumns]) {
            $id = Uuid::randomBytes();
            $hex = Uuid::fromBytesToHex($id);
            $baseData = ['id' => $id, ...$extraColumns];
            $baseTypes = ['id' => ParameterType::BINARY];
            foreach (['parent_id'] as $binaryColumn) {
                if (\array_key_exists($binaryColumn, $baseData)) {
                    $baseTypes[$binaryColumn] = ParameterType::BINARY;
                }
            }
            $this->connection->insert($entity, $baseData, $baseTypes);
            $translationData = [$translationIdColumn => $id, 'language_id' => Uuid::randomBytes(), 'name' => 'Contract Name'];
            $translationTypes = [$translationIdColumn => ParameterType::BINARY, 'language_id' => ParameterType::BINARY];
            if ($entity === 'product_manufacturer') {
                $translationData['product_manufacturer_version_id'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
                $translationTypes['product_manufacturer_version_id'] = ParameterType::BINARY;
            }
            if ($entity === 'category') {
                $translationData['category_version_id'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
                $translationTypes['category_version_id'] = ParameterType::BINARY;
            }
            $this->connection->insert($translationTable, $translationData, $translationTypes);

            $indexer = new $indexerClass($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), static::createStub(ElasticsearchFieldBuilder::class), 10);
            $documents = $indexer->fetch([$hex]);
            static::assertArrayHasKey($hex, $documents, $entity);
            static::assertSame($hex, $documents[$hex]['id'], $entity);
            static::assertStringContainsString('contract name', $documents[$hex]['text'], $entity);
            static::assertArrayHasKey('completion', $documents[$hex], $entity);
        }
    }

    public function testNewsletterRecipientIndexerFetchesWithEmptyTagRelations(): void
    {
        $id = Uuid::randomBytes();
        $hex = Uuid::fromBytesToHex($id);
        $this->connection->insert('newsletter_recipient', [
            'id' => $id,
            'email' => 'contract@example.test',
            'first_name' => 'Test',
            'last_name' => 'Recipient',
            'status' => 'direct',
            'city' => 'Warsaw',
            'zip_code' => '00-001',
            'street' => 'Test street',
            'sales_channel_id' => null,
            'language_id' => null,
            'created_at' => null,
            'updated_at' => null,
        ], ['id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
        $indexer = new NewsletterRecipientAdminSearchIndexer($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), 10);
        $documents = $indexer->fetch([$hex]);

        static::assertSame($hex, $documents[$hex]['id']);
        static::assertStringContainsString('contract@example.test', $documents[$hex]['text']);
    }

    public function testCustomerAndMediaAdminIndexersFetchDocumentsWithEmptyRelations(): void
    {
        $customerId = Uuid::randomBytes();
        $customerHex = Uuid::fromBytesToHex($customerId);
        $this->connection->insert('customer', ['id' => $customerId, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'company' => 'Analytical Engines', 'customer_number' => 'C-1', 'active' => true, 'affiliate_code' => null, 'campaign_code' => null, 'customer_group_id' => null, 'salutation_id' => null, 'bound_sales_channel_id' => null, 'requested_customer_group_id' => null, 'default_billing_address_id' => null, 'default_shipping_address_id' => null, 'created_at' => null], ['id' => ParameterType::BINARY, 'active' => Types::BOOLEAN]);
        $customerIndexer = new CustomerAdminSearchIndexer($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), 10);
        $customer = $customerIndexer->fetch([$customerHex]);
        static::assertSame($customerHex, $customer[$customerHex]['id']);
        static::assertStringContainsString('ada@example.test', $customer[$customerHex]['text']);

        $mediaId = Uuid::randomBytes();
        $mediaHex = Uuid::fromBytesToHex($mediaId);
        $this->connection->insert('media', ['id' => $mediaId, 'media_folder_id' => null, 'private' => false, 'file_name' => 'manual', 'file_extension' => 'pdf', 'file_size' => 1, 'path' => '/manual.pdf', 'created_at' => null], ['id' => ParameterType::BINARY, 'media_folder_id' => ParameterType::BINARY, 'private' => Types::BOOLEAN]);
        $mediaIndexer = new MediaAdminSearchIndexer($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), static::createStub(ElasticsearchFieldBuilder::class), 10);
        $media = $mediaIndexer->fetch([$mediaHex]);
        static::assertSame($mediaHex, $media[$mediaHex]['id']);
        static::assertStringContainsString('manual', $media[$mediaHex]['text']);
    }

    public function testOrderAdminIndexerFetchesOrderWithEmptyRelations(): void
    {
        $id = Uuid::randomBytes();
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $hex = Uuid::fromBytesToHex($id);
        $this->connection->executeStatement(
            'INSERT INTO ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('order') . ' (id, version_id, order_number, amount_total, order_date_time) VALUES (:id, :version, :number, :amount, :date)',
            ['id' => $id, 'version' => $versionId, 'number' => '10001', 'amount' => 12.50, 'date' => '2026-10-19 10:00:00'],
            ['id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]
        );
        $indexer = new OrderAdminSearchIndexer($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), 10);
        $documents = $indexer->fetch([$hex]);

        static::assertArrayHasKey($hex, $documents);
        static::assertSame($hex, $documents[$hex]['id']);
        static::assertStringContainsString('10001', $documents[$hex]['text']);
    }

    public function testProductAdminIndexerFetchesTranslatedProductDocument(): void
    {
        $id = Uuid::randomBytes();
        $hex = Uuid::fromBytesToHex($id);
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->connection->insert('product', [
            'id' => $id,
            'version_id' => $liveVersionId,
            'active' => 1,
            'available' => 1,
            'product_number' => 'SW-ADMIN-1',
            'ean' => null,
            'manufacturer_number' => null,
            'sales' => 0,
            'type' => 'physical',
            'states' => null,
            'manufacturer' => null,
            'visibilities' => null,
            'category_ids' => '[]',
            'stock' => 5,
            'price' => '{}',
            'cover' => null,
            'created_at' => null,
            'updated_at' => null,
            'child_count' => 0,
            'variant_listing_config' => '{}',
            'cheapest_price_accessor' => '{}',
            'category_tree' => '[]',
            'option_ids' => '[]',
            'property_ids' => '[]',
            'tag_ids' => '[]',
            'stream_ids' => '[]',
            'tax_id' => null,
            'shipping_free' => 0,
            'is_closeout' => 0,
            'available_stock' => 5,
            'rating_average' => 0,
            'auto_increment' => 1,
            'display_group' => null,
            'release_date' => null,
        ], [
            'id' => ParameterType::BINARY,
            'version_id' => ParameterType::BINARY,
            'category_ids' => Types::JSON,
            'price' => Types::JSON,
            'states' => Types::JSON,
            'variant_listing_config' => Types::JSON,
            'cheapest_price_accessor' => Types::JSON,
            'category_tree' => Types::JSON,
            'option_ids' => Types::JSON,
            'property_ids' => Types::JSON,
            'tag_ids' => Types::JSON,
            'stream_ids' => Types::JSON,
        ]);
        $this->connection->insert('product_translation', [
            'product_id' => $id,
            'product_version_id' => $liveVersionId,
            'language_id' => $languageId,
            'name' => 'Admin Indexed Product',
            'custom_search_keywords' => null,
        ], ['product_id' => ParameterType::BINARY, 'product_version_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);

        $indexer = new ProductAdminSearchIndexer($this->connection, static::createStub(IteratorFactory::class), static::createStub(EntityRepository::class), static::createStub(ElasticsearchFieldBuilder::class), 10);
        $documents = $indexer->fetch([$hex]);
        static::assertArrayHasKey($hex, $documents);
        static::assertSame($hex, $documents[$hex]['id']);
        static::assertSame(['admin indexed product'], $documents[$hex]['completion']);
        static::assertSame('sw-admin-1', $documents[$hex]['textBoosted']);
    }

    public function testJsonObjectAggregateDeduplicatesAndReturnsEmptyArrayForEmptyRelations(): void
    {
        $this->connection->insert('indexing_parent_contract', ['id' => 1]);
        $this->connection->insert('indexing_parent_contract', ['id' => 2]);
        foreach (['same', 'same', 'other'] as $value) {
            $this->connection->insert('indexing_child_contract', ['parent_id' => 1, 'value' => $value]);
        }

        $aggregate = IndexingSql::jsonObjectAggregate($this->connection, ['value' => 'child.value'], distinct: true, filter: 'child.parent_id IS NOT NULL');
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT parent.id, ' . $aggregate . ' AS children FROM indexing_parent_contract parent LEFT JOIN indexing_child_contract child ON child.parent_id = parent.id GROUP BY parent.id ORDER BY parent.id'
        );

        $children = json_decode((string) $rows[1], true, 512, \JSON_THROW_ON_ERROR);
        usort($children, static fn (array $left, array $right): int => $left['value'] <=> $right['value']);
        static::assertSame([['value' => 'other'], ['value' => 'same']], $children);
        static::assertSame([], json_decode((string) $rows[2], true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testCustomFieldSetGatewayUsesBinaryIdsAcrossAllFetchMethods(): void
    {
        $setId = Uuid::randomBytes();
        $fieldId = Uuid::randomBytes();
        $appId = Uuid::randomBytes();
        $languageId = Uuid::randomBytes();
        $this->connection->insert('custom_field_set', ['id' => $setId, 'app_id' => $appId], ['id' => ParameterType::BINARY, 'app_id' => ParameterType::BINARY]);
        $this->connection->insert('custom_field_set_relation', ['set_id' => $setId, 'entity_name' => 'product'], ['set_id' => ParameterType::BINARY]);
        $this->connection->insert('custom_field', ['id' => $fieldId, 'set_id' => $setId, 'name' => 'contract_field', 'type' => 'text', 'active' => true, 'include_in_search' => true], ['id' => ParameterType::BINARY, 'set_id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $languageId, 'parent_id' => null, 'active' => true], ['id' => ParameterType::BINARY, 'parent_id' => ParameterType::BINARY]);

        $gateway = new CustomFieldSetGateway($this->connection);
        $setHex = Uuid::fromBytesToHex($setId);
        $fieldHex = Uuid::fromBytesToHex($fieldId);
        static::assertSame([$setHex => [['id' => $fieldHex, 'name' => 'contract_field', 'type' => 'text']]], $gateway->fetchCustomFieldsForSets([$setHex]));
        static::assertSame([$setHex], $gateway->fetchAppOwnedFieldSetIds([$setHex]));
        static::assertSame([$fieldHex => $setHex], $gateway->fetchFieldSetIds([$fieldHex]));
        static::assertSame([$setHex => ['product']], $gateway->fetchFieldSetEntityMappings([$setHex]));
        static::assertSame([Uuid::fromBytesToHex($languageId)], $gateway->fetchLanguageIds());
    }

    public function testIndexingUtilsReadsJsonFieldsOnBothPlatforms(): void
    {
        $setId = Uuid::randomBytes();
        $this->connection->insert('custom_field_set', ['id' => $setId, 'app_id' => null], ['id' => ParameterType::BINARY, 'app_id' => ParameterType::BINARY]);
        $this->connection->insert('custom_field_set_relation', ['set_id' => $setId, 'entity_name' => 'product'], ['set_id' => ParameterType::BINARY]);
        foreach (['sorting_field', 'stream_field'] as $fieldName) {
            $this->connection->insert('custom_field', ['id' => Uuid::randomBytes(), 'set_id' => $setId, 'name' => $fieldName, 'type' => 'text', 'active' => true, 'include_in_search' => true], ['id' => ParameterType::BINARY, 'set_id' => ParameterType::BINARY, 'active' => Types::BOOLEAN, 'include_in_search' => Types::BOOLEAN]);
        }
        $this->connection->insert('product_sorting', ['id' => Uuid::randomBytes(), 'fields' => json_encode([['field' => 'customFields.sorting_field']], \JSON_THROW_ON_ERROR)], ['id' => ParameterType::BINARY, 'fields' => Types::JSON]);
        $this->connection->insert('product_stream', ['id' => Uuid::randomBytes(), 'api_filter' => json_encode(['queries' => [['field' => 'customFields.stream_field']]], \JSON_THROW_ON_ERROR)], ['id' => ParameterType::BINARY, 'api_filter' => Types::JSON]);

        $utils = new ElasticsearchIndexingUtils($this->connection, new EventDispatcher(), new ParameterBag([]));
        static::assertSame(['sorting_field' => 'text', 'stream_field' => 'text'], $utils->getCustomFieldTypes('product', Context::createDefaultContext()));
    }

    public function testProductCustomFieldUpdaterFetchesSortingAndStreamFilterIdsAsBinary(): void
    {
        $fieldId = Uuid::randomBytes();
        $sortingId = Uuid::randomBytes();
        $filterId = Uuid::randomBytes();
        $this->connection->insert('custom_field', ['id' => $fieldId, 'set_id' => Uuid::randomBytes(), 'name' => 'used_field', 'type' => 'text', 'active' => true, 'include_in_search' => false], ['id' => ParameterType::BINARY, 'set_id' => ParameterType::BINARY, 'active' => Types::BOOLEAN, 'include_in_search' => Types::BOOLEAN]);
        $this->connection->insert('product_sorting', ['id' => $sortingId, 'fields' => json_encode([['field' => 'customFields.used_field']], \JSON_THROW_ON_ERROR)], ['id' => ParameterType::BINARY, 'fields' => Types::JSON]);
        $this->connection->insert('product_stream_filter', ['id' => $filterId, 'field' => 'customFields.used_field'], ['id' => ParameterType::BINARY]);

        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper->method('allowIndexing')->willReturn(true);
        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->exactly(2))->method('createFieldsInIndices');
        $updater = new ProductCustomFieldsUsedUpdater($elasticsearchHelper, $mappingHelper, $this->connection);
        $context = Context::createDefaultContext();

        $updater->productSortingWritten(new EntityWrittenEvent('product_sorting', [new EntityWriteResult(Uuid::fromBytesToHex($sortingId), ['fields' => []], 'product_sorting', EntityWriteResult::OPERATION_INSERT)], $context));
        $updater->productStreamFilterWritten(new EntityWrittenEvent('product_stream_filter', [new EntityWriteResult(Uuid::fromBytesToHex($filterId), [], 'product_stream_filter', EntityWriteResult::OPERATION_INSERT)], $context));
    }

    public function testStopwordTokenFilterReadsLanguageConfigWithBinaryUuid(): void
    {
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->connection->insert('product_search_config', ['language_id' => $languageId, 'min_search_length' => 3], ['language_id' => ParameterType::BINARY]);

        static::assertSame(['abc', 'word'], (new StopwordTokenFilter($this->connection))->filter(['a', 'ab', 'abc', 'word'], Context::createDefaultContext()));
    }

    public function testAliasTaskDeletesBinaryTaskIdAfterSwitchingOpenSearchAlias(): void
    {
        $client = ClientFactory::createClient(getenv('OPENSEARCH_URL') ?: 'http://127.0.0.1:9200', new NullLogger(), false, ['verify_server_cert' => true]);
        $index = 'task017-alias-' . Uuid::randomHex();
        $alias = 'task017-alias-current-' . Uuid::randomHex();
        $taskId = Uuid::randomBytes();
        try {
            $client->indices()->create(['index' => $index]);
            $platform = $this->connection->getDatabasePlatform();
            $this->connection->executeStatement(
                'INSERT INTO elasticsearch_index_task (id, entity, ' . $platform->quoteSingleIdentifier('index') . ', ' . $platform->quoteSingleIdentifier('alias') . ', doc_count) VALUES (:id, :entity, :index, :alias, :docCount)',
                ['id' => $taskId, 'entity' => 'product', 'index' => $index, 'alias' => $alias, 'docCount' => 0],
                ['id' => ParameterType::BINARY, 'entity' => ParameterType::STRING, 'index' => ParameterType::STRING, 'alias' => ParameterType::STRING, 'docCount' => ParameterType::INTEGER]
            );

            $handler = new CreateAliasTaskHandler(
                static::createStub(EntityRepository::class),
                new NullLogger(),
                $client,
                $this->connection,
                static::createStub(ElasticsearchHelper::class),
                ['settings' => ['index' => ['number_of_replicas' => 0]]],
                new EventDispatcher(),
            );
            $handler->run();

            static::assertTrue($client->indices()->existsAlias(['name' => $alias]));
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM elasticsearch_index_task'));
        } finally {
            if ($client->indices()->existsAlias(['name' => $alias])) {
                $client->indices()->deleteAlias(['index' => $index, 'name' => $alias]);
            }
            if ($client->indices()->exists(['index' => $index])) {
                $client->indices()->delete(['index' => $index]);
            }
        }
    }

    public function testElasticsearchStatusCommandBindsBinaryLiveVersionId(): void
    {
        $client = ClientFactory::createClient(getenv('OPENSEARCH_URL') ?: 'http://127.0.0.1:9200', new NullLogger(), false, ['verify_server_cert' => true]);
        $tester = new CommandTester(new ElasticsearchStatusCommand($client, $this->connection));

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('Indexing', $tester->getDisplay());
    }

    public function testProductDefinitionFetchHandlesListingTypesFallbackPricesAndRelations(): void
    {
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $optionId = Uuid::randomBytes();
        $propertyId = Uuid::randomBytes();
        $optionGroupId = Uuid::randomBytes();
        $propertyGroupId = Uuid::randomBytes();
        $fallbackLanguageId = Uuid::randomBytes();
        $fallbackLanguageHex = Uuid::fromBytesToHex($fallbackLanguageId);
        $channelId = Uuid::randomBytes();
        $this->connection->insert('language', ['id' => $systemLanguageId, 'parent_id' => null, 'active' => 1], ['id' => ParameterType::BINARY, 'parent_id' => ParameterType::BINARY]);
        $this->connection->insert('language', ['id' => $fallbackLanguageId, 'parent_id' => $systemLanguageId, 'active' => 1], ['id' => ParameterType::BINARY, 'parent_id' => ParameterType::BINARY]);

        $ids = [];
        foreach ([true, false, 1, 0, null, 'missing', true, false] as $index => $value) {
            $id = Uuid::randomBytes();
            $ids[] = Uuid::fromBytesToHex($id);
            $parentId = $index === 7 ? Uuid::fromHexToBytes($ids[0]) : null;
            $config = $value === 'missing' ? [] : ['displayParent' => $value];
            $this->connection->insert('product', [
                'id' => $id,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $parentId,
                'active' => 1,
                'available' => 1,
                'sales' => 0,
                'product_number' => 'SW-' . \count($ids),
                'shipping_free' => 0,
                'is_closeout' => 0,
                'rating_average' => 0,
                'available_stock' => 5,
                'stock' => 5,
                'auto_increment' => \count($ids),
                'child_count' => $index === 7 ? 0 : 1,
                'variant_listing_config' => $config,
                'cheapest_price_accessor' => $index === 2 ? ['rule' => ['currency' => ['gross' => 19.99, 'net' => 10, 'percentage' => ['gross' => 20, 'net' => 10]]]] : [],
                'category_tree' => [],
                'category_ids' => [],
                'option_ids' => $index === 0 ? [Uuid::fromBytesToHex($optionId)] : [],
                'property_ids' => $index === 0 ? [Uuid::fromBytesToHex($propertyId)] : [],
                'tag_ids' => [],
                'stream_ids' => [],
                'states' => [],
                'visibilities' => $id,
            ], [
                'id' => ParameterType::BINARY,
                'version_id' => ParameterType::BINARY,
                'parent_id' => ParameterType::BINARY,
                'variant_listing_config' => Types::JSON,
                'cheapest_price_accessor' => Types::JSON,
                'category_tree' => Types::JSON,
                'category_ids' => Types::JSON,
                'option_ids' => Types::JSON,
                'property_ids' => Types::JSON,
                'tag_ids' => Types::JSON,
                'stream_ids' => Types::JSON,
                'states' => Types::JSON,
                'visibilities' => ParameterType::BINARY,
            ]);
            if ($index !== 7) {
                $this->connection->insert('product_translation', [
                    'product_id' => $id,
                    'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'language_id' => $systemLanguageId,
                    'name' => 'Listing ' . \count($ids),
                ], ['product_id' => ParameterType::BINARY, 'product_version_id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
            }
            foreach ($index === 6 ? [] : [20, 20] as $visibility) {
                $this->connection->insert('product_visibility', ['product_id' => $id, 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'sales_channel_id' => $channelId, 'visibility' => $visibility], ['product_id' => ParameterType::BINARY, 'product_version_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
            }
        }

        foreach ([[$optionId, $optionGroupId, 'Blue'], [$propertyId, $propertyGroupId, 'Cotton']] as [$id, $groupId, $name]) {
            $this->connection->insert('property_group_option', ['id' => $id, 'property_group_id' => $groupId], [
                'id' => ParameterType::BINARY,
                'property_group_id' => ParameterType::BINARY,
            ]);
            foreach ([$name, $name] as $translationName) {
                $this->connection->insert('property_group_option_translation', [
                    'property_group_option_id' => $id,
                    'language_id' => $systemLanguageId,
                    'name' => $translationName,
                ], [
                    'property_group_option_id' => ParameterType::BINARY,
                    'language_id' => ParameterType::BINARY,
                ]);
            }
        }

        $registry = new StaticDefinitionInstanceRegistry([ProductDefinition::class], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGatewayInterface::class));
        $definition = new ElasticsearchProductDefinition(
            $registry->get(ProductDefinition::class),
            $this->connection,
            static::createStub(ProductSearchQueryBuilder::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            new ElasticsearchFieldMapper(new ElasticsearchIndexingUtils($this->connection, new EventDispatcher(), new ParameterBag([]))),
            new StaticSalesChannelLanguageLoader([$fallbackLanguageHex => [Uuid::fromBytesToHex($channelId)]]),
            false,
            'test',
            new StaticLanguageLoader([
                Defaults::LANGUAGE_SYSTEM => ['id' => Defaults::LANGUAGE_SYSTEM, 'code' => 'en-GB', 'parentId' => null],
                $fallbackLanguageHex => ['id' => $fallbackLanguageHex, 'code' => 'en-US', 'parentId' => Defaults::LANGUAGE_SYSTEM],
            ])
        );

        $documents = $definition->fetch(Uuid::fromHexToBytesList($ids), Context::createDefaultContext());
        static::assertSame([$ids[0], $ids[2], $ids[7]], array_keys($documents));
        static::assertSame(['Listing 1', 'Listing 3', 'Listing 1'], array_values(array_column(array_column($documents, 'name'), Defaults::LANGUAGE_SYSTEM)));
        static::assertSame('Listing 1', $documents[$ids[7]]['name'][$fallbackLanguageHex]);
        static::assertCount(1, $documents[$ids[0]]['visibilities']);
        static::assertSame([], $documents[$ids[0]]['categories']);
        static::assertSame(['cheapest_price_rule_currency_gross' => 19.99, 'cheapest_price_rule_currency_net' => 10, 'cheapest_price_rule_currency_gross_percentage' => 20, 'cheapest_price_rule_currency_net_percentage' => 10], array_intersect_key($documents[$ids[2]], array_fill_keys([
            'cheapest_price_rule_currency_gross', 'cheapest_price_rule_currency_net', 'cheapest_price_rule_currency_gross_percentage', 'cheapest_price_rule_currency_net_percentage',
        ], true)));
        static::assertSame([[
            'id' => Uuid::fromBytesToHex($optionId),
            '_count' => 1,
            'groupId' => Uuid::fromBytesToHex($optionGroupId),
            'group' => ['id' => Uuid::fromBytesToHex($optionGroupId), '_count' => 1],
            'name' => [Defaults::LANGUAGE_SYSTEM => 'Blue'],
        ]], $documents[$ids[0]]['options']);
        static::assertSame([[
            'id' => Uuid::fromBytesToHex($propertyId),
            '_count' => 1,
            'groupId' => Uuid::fromBytesToHex($propertyGroupId),
            'group' => ['id' => Uuid::fromBytesToHex($propertyGroupId), '_count' => 1],
            'name' => [Defaults::LANGUAGE_SYSTEM => 'Cotton'],
        ]], $documents[$ids[0]]['properties']);
    }

    private function createProductDefinitionSchema(string $binary, string $json): void
    {
        $this->connection->executeStatement("CREATE TABLE language (id $binary PRIMARY KEY, parent_id $binary NULL, active SMALLINT NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE product_search_config (language_id $binary NOT NULL, min_search_length INT NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE property_group_option (id $binary NOT NULL PRIMARY KEY, property_group_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE property_group_option_translation (property_group_option_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE product (id $binary NOT NULL, version_id $binary NOT NULL, parent_id $binary NULL, active SMALLINT, available SMALLINT, sales INT, manufacturer_number VARCHAR(255), available_stock INT, rating_average DECIMAL(10,2), product_number VARCHAR(255), manufacturer $binary NULL, delivery_time_id $binary NULL, shipping_free SMALLINT, is_closeout SMALLINT, product_media_id $binary NULL, cover $binary NULL, price $json NULL, weight DECIMAL(10,2), length DECIMAL(10,2), height DECIMAL(10,2), width DECIMAL(10,2), release_date TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, category_tree $json, category_ids $json, option_ids $json, property_ids $json, tag_ids $json, stream_ids $json, tax_id $binary NULL, stock INT, ean VARCHAR(255), mark_as_topseller SMALLINT, auto_increment INT, display_group VARCHAR(64), cheapest_price_accessor $json, child_count INT, variant_listing_config $json, type VARCHAR(32), states $json, visibilities $binary NULL, tags $binary NULL, categories $binary NULL, PRIMARY KEY(id, version_id))");
        $this->connection->executeStatement("CREATE TABLE product_visibility (product_id $binary NOT NULL, product_version_id $binary NOT NULL, sales_channel_id $binary NOT NULL, visibility INT)");
        $this->connection->executeStatement("CREATE TABLE product_tag (product_id $binary NOT NULL, product_version_id $binary NOT NULL, tag_id $binary NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE tag (id $binary NOT NULL, name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE product_translation (product_id $binary NOT NULL, product_version_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255), description TEXT, meta_title VARCHAR(255), meta_description TEXT, custom_fields $json, custom_search_keywords $json, PRIMARY KEY(product_id, product_version_id, language_id))");
        $this->connection->executeStatement("CREATE TABLE product_manufacturer_translation (product_manufacturer_id $binary NOT NULL, product_manufacturer_version_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255), PRIMARY KEY(product_manufacturer_id, product_manufacturer_version_id, language_id))");
        $this->connection->executeStatement("CREATE TABLE product_category (product_id $binary, product_version_id $binary, category_id $binary, category_version_id $binary)");
        $this->connection->executeStatement("CREATE TABLE category_translation (category_id $binary NOT NULL, category_version_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255), PRIMARY KEY(category_id, category_version_id, language_id))");
        $this->connection->executeStatement("CREATE TABLE delivery_time_translation (delivery_time_id $binary NOT NULL, language_id $binary NOT NULL, name VARCHAR(255), PRIMARY KEY(delivery_time_id, language_id))");
    }
}

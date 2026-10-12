<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Command\CartMigrateCommand;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\Customer\CleanupCustomerRecoveryTaskHandler;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerMetaFieldSubscriber;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Checkout\Document\Api\DocumentTypeTechnicalNameFkResolver;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentBaseConfigSyncSubscriber;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentTypeNameSyncSubscriber;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\DocumentV2\Type\InvoiceDocumentType;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Payment\Api\PaymentMethodTechnicalNameFkResolver;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionCodeGateway;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionExclusionUpdater;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;
use Shopware\Core\Checkout\Shipping\Api\ShippingMethodTechnicalNameFkResolver;
use Shopware\Core\Content\Product\Stock\OrderStockSubscriber;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Event\NestedEventDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Redis\RedisStub;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * Real DAL writes and production persister/subscribers. Only conversion, language loading
 * and unused aggregations are collaborators: storefront calculation/payment are outside this contract.
 *
 * @internal
 */
#[Package('checkout')]
class CheckoutPersistenceContractTest extends TestCase
{
    public const CUSTOMER = '0000000000000000000000000000ff01';
    public const PRODUCT = '0000000000000000000000000000ff02';
    public const PROMOTION = '0000000000000000000000000000ff03';
    public const CODE = '0000000000000000000000000000ff04';
    public const STATE = '0000000000000000000000000000ff05';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::connection();
        $pg = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        if ($pg) {
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS sw_checkout_contract CASCADE');
            $this->connection->executeStatement('CREATE SCHEMA sw_checkout_contract');
            $this->connection->executeStatement('SET search_path TO sw_checkout_contract');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        } else {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['exclusion_attempt', 'version_commit_data', 'version_commit', 'version', 'custom_field', 'order_line_item', 'order_customer', 'order', 'promotion_individual_code', 'promotion', 'product', 'customer', 'customer_recovery', 'state_machine_state', 'cart', 'document', 'document_type', 'product_review', 'document_base_config', 'document_base_config_sales_channel', 'payment_method', 'shipping_method'] as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $this->connection->quoteSingleIdentifier($table));
            }
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
        (new DatabaseFunctionInstaller())->install($this->connection);
        $binary = $pg ? 'BYTEA' : 'BINARY(16)';
        $json = $pg ? 'JSONB' : 'JSON';
        $date = $pg ? 'TIMESTAMP(3)' : 'DATETIME(3)';
        $order = $this->connection->quoteSingleIdentifier('order');
        $identity = $pg ? 'BIGINT GENERATED ALWAYS AS IDENTITY UNIQUE' : 'BIGINT AUTO_INCREMENT UNIQUE';
        $this->connection->executeStatement("CREATE TABLE cart (auto_increment $identity, token VARCHAR(255) PRIMARY KEY, payload " . ($pg ? 'BYTEA' : 'LONGBLOB') . ", compressed SMALLINT NOT NULL, rule_ids $json, created_at $date NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE version (id $binary PRIMARY KEY, name VARCHAR(255), created_at $date NOT NULL, updated_at $date)");
        $this->connection->executeStatement("CREATE TABLE version_commit (id $binary PRIMARY KEY, version_id $binary, user_id $binary, integration_id $binary, auto_increment $identity, is_merge SMALLINT DEFAULT 0, message VARCHAR(255), created_at $date NOT NULL, updated_at $date)");
        $this->connection->executeStatement("CREATE TABLE version_commit_data (id $binary PRIMARY KEY, version_commit_id $binary, user_id $binary, integration_id $binary, auto_increment $identity, entity_name VARCHAR(255), entity_id $json, action VARCHAR(255), payload $json, created_at $date NOT NULL, updated_at $date)");
        $this->connection->executeStatement('CREATE TABLE custom_field (name VARCHAR(255), allow_cart_expose SMALLINT)');
        $this->connection->executeStatement("CREATE TABLE state_machine_state (id $binary PRIMARY KEY, technical_name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE customer (id $binary PRIMARY KEY, order_count INT DEFAULT 0, order_total_amount DECIMAL(12,2) DEFAULT 0, last_order_date $date, review_count INT DEFAULT 0)");
        $this->connection->executeStatement("CREATE TABLE product (id $binary, version_id $binary, parent_id $binary, stock INT NOT NULL, available_stock INT NOT NULL, sales INT NOT NULL, available SMALLINT NOT NULL, is_closeout SMALLINT, min_purchase INT, created_at $date, updated_at $date, PRIMARY KEY(id, version_id))");
        $this->connection->executeStatement("CREATE TABLE promotion (id $binary PRIMARY KEY, order_count INT DEFAULT 0, orders_per_customer_count $json, exclusion_ids $json, created_at $date, updated_at $date)");
        $this->connection->executeStatement("CREATE TABLE promotion_individual_code (id $binary PRIMARY KEY, promotion_id $binary NOT NULL, code VARCHAR(255) UNIQUE NOT NULL, payload $json, created_at $date, updated_at $date, FOREIGN KEY(promotion_id) REFERENCES promotion(id))");
        $this->connection->executeStatement("CREATE TABLE $order (id $binary, version_id $binary, order_number VARCHAR(255) NOT NULL UNIQUE, state_id $binary NOT NULL, order_date_time $date, amount_total DECIMAL(12,2), currency_factor DECIMAL(12,4), language_id $binary, deep_link_code VARCHAR(255), created_at $date, updated_at $date, PRIMARY KEY(id, version_id), FOREIGN KEY(state_id) REFERENCES state_machine_state(id))");
        $this->connection->executeStatement("CREATE TABLE order_customer (id $binary, version_id $binary, order_id $binary NOT NULL, order_version_id $binary NOT NULL, customer_id $binary, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255), created_at $date, updated_at $date, PRIMARY KEY(id, version_id), FOREIGN KEY(order_id,order_version_id) REFERENCES $order(id,version_id) ON DELETE CASCADE, FOREIGN KEY(customer_id) REFERENCES customer(id))");
        $this->connection->executeStatement("CREATE TABLE order_line_item (id $binary, version_id $binary, order_id $binary NOT NULL, order_version_id $binary NOT NULL, referenced_id VARCHAR(255), type VARCHAR(255), quantity INT, promotion_id $binary, payload $json, created_at $date, updated_at $date, PRIMARY KEY(id, version_id), FOREIGN KEY(order_id,order_version_id) REFERENCES $order(id,version_id) ON DELETE CASCADE, FOREIGN KEY(promotion_id) REFERENCES promotion(id))");
        $this->insert('state_machine_state', ['id' => self::STATE, 'technical_name' => 'open']);
        $this->insert('customer', ['id' => self::CUSTOMER]);
        $this->insert('product', ['id' => self::PRODUCT, 'version_id' => Defaults::LIVE_VERSION, 'stock' => 1, 'available_stock' => 1, 'sales' => 0, 'available' => 1, 'is_closeout' => 1, 'min_purchase' => 1]);
        $this->insert('promotion', ['id' => self::PROMOTION]);
        $this->insert('promotion_individual_code', ['id' => self::CODE, 'promotion_id' => self::PROMOTION, 'code' => 'ONE']);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS sw_checkout_contract CASCADE');
        } else {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['exclusion_attempt', 'version_commit_data', 'version_commit', 'version', 'custom_field', 'order_line_item', 'order_customer', 'order', 'promotion_individual_code', 'promotion', 'product', 'customer', 'customer_recovery', 'state_machine_state', 'cart', 'document', 'document_type', 'product_review', 'document_base_config', 'document_base_config_sales_channel', 'payment_method', 'shipping_method'] as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $this->connection->quoteSingleIdentifier($table));
            }
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
        $this->connection->close();
    }

    public static function connection(): Connection
    {
        $connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection->executeStatement('SET search_path TO sw_checkout_contract');
        }

        return $connection;
    }

    public function testCompressedCartRoundTripUpdateDeleteAndPrune(): void
    {
        $dispatcher = new EventDispatcher();
        $clock = new MockClock('2026-10-09 12:13:14.123 UTC');
        $persister = new CartPersister($this->connection, $dispatcher, new CartSerializationCleaner($this->connection, $dispatcher), new CartCompressor(true, 'gzip'), $clock);
        $context = Generator::generateSalesChannelContext();
        $context->setRuleIds([self::PROMOTION]);
        $cart = new Cart('binary-cart');
        $cart->add((new LineItem('binary', 'custom'))->setPayload(['bytes' => str_repeat("\x00\xff\\'", 1000)]));
        $persister->save($cart, $context);
        static::assertTrue($cart->isPersisted());
        static::assertTrue($persister->exists('binary-cart', $context));
        $loaded = $persister->load('binary-cart', $context);
        static::assertSame($cart->getLineItems()->first()?->getPayload(), $loaded->getLineItems()->first()?->getPayload());
        static::assertSame([self::PROMOTION], $loaded->getRuleIds());
        static::assertSame('2026-10-09 12:13:14.123', $this->connection->fetchOne('SELECT created_at FROM cart WHERE token = ?', ['binary-cart']));
        $identity = $this->connection->fetchOne('SELECT auto_increment FROM cart WHERE token = ?', ['binary-cart']);
        $loaded->getLineItems()->first()?->setPayload(['changed' => true]);
        $persister->save($loaded, $context);
        static::assertTrue($persister->load('binary-cart', $context)->getLineItems()->first()?->getPayload()['changed']);
        static::assertSame($identity, $this->connection->fetchOne('SELECT auto_increment FROM cart WHERE token = ?', ['binary-cart']));
        $persister->delete('binary-cart', $context);
        $persister->save($loaded, $context);
        static::assertFalse($persister->exists('binary-cart', $context));
        $old = new Cart('old-cart');
        $old->add(new LineItem('old', 'custom'));
        $persister->save($old, $context);
        $clock->sleep(60 * 60 * 24 * 2);
        $persister->prune(1);
        static::assertFalse($persister->exists('old-cart', $context));
    }

    public function testPublicOrderPersisterWritesReferencesStockAndCodeAtomically(): void
    {
        $runtime = self::runtime($this->connection, self::order(10));
        static::assertSame(self::id(10), self::persist($runtime['persister']));
        $this->assertDomainState(1, 0, 1);
        $order = $runtime['orders']->search(new Search\Criteria([self::id(10)]), Context::createDefaultContext())->first();
        static::assertNotNull($order);
        static::assertSame(Defaults::LIVE_VERSION, $order->get('versionId'));
        static::assertSame('ORDER-10', $order->get('orderNumber'));
        $payload = json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(self::id(10), $payload['orderId']);
        static::assertSame(self::CUSTOMER, $payload['customerId']);
        static::assertSame('Fixture Customer', $payload['customerName']);
        static::assertFalse($this->connection->isTransactionActive());
    }

    public function testFailureAfterStockAndCodeRollsBackEverything(): void
    {
        $runtime = self::runtime($this->connection, self::order(11));
        $observed = false;
        $runtime['dispatcher']->addListener(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, function () use (&$observed): void {
            $observed = (int) $this->connection->fetchOne('SELECT stock FROM product') === 0
                && $this->connection->fetchOne('SELECT payload FROM promotion_individual_code') !== null;
            throw new \RuntimeException('after code and stock');
        }, -100);
        try {
            self::persist($runtime['persister']);
            static::fail('The failing subscriber must abort checkout');
        } catch (\RuntimeException $exception) {
            static::assertSame('after code and stock', $exception->getMessage());
        }
        static::assertTrue($observed);
        $this->assertDomainState(0, 1, 0);
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
        static::assertFalse($this->connection->isTransactionActive());
        self::persist(self::runtime($this->connection, self::order(12))['persister']);
        $this->assertDomainState(1, 0, 1);
    }

    public function testConcurrentSameCodeRejectsLoserAndRollsBackItsStock(): void
    {
        $results = $this->concurrentOrders(self::order(20), self::order(21));
        static::assertSame(['ok', 'rejected'], array_column($results, 'status'));
        $this->assertDomainState(1, 0, 1);
        static::assertSame(self::id(20), json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR)['orderId']);
    }

    public function testConcurrentDifferentCodesCountBothOrdersAndRetainNegativeStockPolicy(): void
    {
        $this->insert('promotion_individual_code', ['id' => self::id(60), 'promotion_id' => self::PROMOTION, 'code' => 'TWO']);
        $results = $this->concurrentOrders(self::order(22), self::order(23, 'TWO'));
        static::assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertDomainState(2, -1, 2);
        $counts = json_decode((string) $this->connection->fetchOne('SELECT orders_per_customer_count FROM promotion'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([self::CUSTOMER => 2], $counts);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(DISTINCT order_number) FROM ' . $this->connection->quoteSingleIdentifier('order')));
    }

    public function testNoCodeAndDraftDoNotRedeemIndividualCodes(): void
    {
        self::persist(self::runtime($this->connection, self::order(30, ''))['persister']);
        $this->assertDomainState(1, 0, 1);
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
        $draft = Context::createDefaultContext()->createWithVersionId(self::id(99));
        $runtime = self::runtime($this->connection, self::order(31));
        $runtime['orders']->create([self::order(31)], $draft);
        $this->assertDomainState(2, 0, 1);
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
    }

    public function testDeletingDiscountReleasesOnlyItsOrderCodeAndResetsCounts(): void
    {
        $runtime = self::runtime($this->connection, self::order(32));
        self::persist($runtime['persister']);
        $runtime['lines']->delete([['id' => self::id(1032)]], Context::createDefaultContext());
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT order_count FROM promotion'));
        static::assertNull($this->connection->fetchOne('SELECT orders_per_customer_count FROM promotion'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT stock FROM product'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): void
    {
        $types = [];
        foreach ($data as $key => $value) {
            if ($key === 'id' || str_ends_with($key, '_id')) {
                $data[$key] = Uuid::fromHexToBytes((string) $value);
                $types[$key] = ParameterType::BINARY;
            }
        }
        $this->connection->insert($this->connection->quoteSingleIdentifier($table), $data, $types);
    }

    public static function id(int $number): string
    {
        return str_pad(dechex($number), 32, '0', \STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(int $number, string $code = 'ONE'): array
    {
        return ['id' => self::id($number), 'orderNumber' => 'ORDER-' . $number, 'stateId' => self::STATE,
            'orderCustomer' => ['id' => self::id(2000 + $number), 'customerId' => self::CUSTOMER, 'firstName' => 'Fixture', 'lastName' => 'Customer', 'email' => 'fixture@example.test'],
            'lineItems' => [
                ['id' => self::id(3000 + $number), 'type' => 'product', 'quantity' => 1, 'referencedId' => self::PRODUCT],
                ['id' => self::id(1000 + $number), 'type' => 'promotion', 'quantity' => 1, 'promotionId' => self::PROMOTION, 'payload' => ['code' => $code]],
            ],
        ];
    }

    public static function persist(OrderPersister $persister): string
    {
        $cart = new Cart('checkout');
        $cart->add(new LineItem('product', 'product', self::PRODUCT));

        return $persister->persist($cart, Generator::generateSalesChannelContext());
    }

    /**
     * @param array<string, mixed> $order
     *
     * @return array{persister: OrderPersister, orders: EntityRepository<OrderCollection>, lines: EntityRepository<OrderLineItemCollection>, codes: EntityRepository<PromotionIndividualCodeCollection>, dispatcher: NestedEventDispatcher}
     */
    public static function runtime(Connection $connection, array $order): array
    {
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $version = new VersionDefinition();
        $commit = new VersionCommitDefinition();
        $commitData = new VersionCommitDataDefinition();
        $definitions = [new CheckoutOrderDefinition(), new CheckoutCustomerDefinition(), new CheckoutLineDefinition(), new CheckoutCodeDefinition(), new CheckoutPromotionDefinition(), new CheckoutBuyerDefinition(), $version, $commit, $commitData];
        foreach ($definitions as $definition) {
            $container->set($definition::class, $definition);
        }
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
        $validator = Validation::createValidator();
        $clock = new MockClock('2026-10-09');
        $dispatcher = new NestedEventDispatcher(new EventDispatcher());
        $gateway = new Dbal\EntityWriteGateway(250, $connection, $dispatcher, new Dbal\ExceptionHandlerRegistry([]), $registry);
        $extractor = new Write\WriteCommandExtractor($gateway, $registry);
        foreach ([FieldSerializer\IdFieldSerializer::class, FieldSerializer\FkFieldSerializer::class, FieldSerializer\IntFieldSerializer::class, FieldSerializer\JsonFieldSerializer::class, FieldSerializer\BoolFieldSerializer::class] as $class) {
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
        // EntityRepository's constructor does not bind its collection template.
        // These real definitions provide the collection classes declared below.
        /** @var EntityRepository<OrderCollection> $orders */
        $orders = new EntityRepository($definitions[0], $reader, $versions, $searcher, $aggregator, $dispatcher, $loadedEvents);
        /** @var EntityRepository<OrderCustomerCollection> $customers */
        $customers = new EntityRepository($definitions[1], $reader, $versions, $searcher, $aggregator, $dispatcher, $loadedEvents);
        /** @var EntityRepository<OrderLineItemCollection> $lines */
        $lines = new EntityRepository($definitions[2], $reader, $versions, $searcher, $aggregator, $dispatcher, $loadedEvents);
        /** @var EntityRepository<PromotionIndividualCodeCollection> $codes */
        $codes = new EntityRepository($definitions[3], $reader, $versions, $searcher, $aggregator, $dispatcher, $loadedEvents);
        $dispatcher->addSubscriber(new OrderStockSubscriber($connection, new StockStorage($connection, $dispatcher), true));
        $dispatcher->addSubscriber(new PromotionIndividualCodeRedeemer($codes, $customers, new PromotionCodeGateway($connection)));
        $dispatcher->addSubscriber(new PromotionRedemptionUpdater($connection));
        $converter = static::createStub(OrderConverter::class);
        $converter->method('convertToOrder')->willReturn($order);

        return ['persister' => new OrderPersister($orders, $converter, new CartSerializationCleaner($connection, $dispatcher), $connection), 'orders' => $orders, 'lines' => $lines, 'codes' => $codes, 'dispatcher' => $dispatcher];
    }

    public function testConcurrentCartUpsertsKeepOneCompletePayloadAndMetadata(): void
    {
        $first = self::order(50);
        $second = self::order(51);
        $results = $this->concurrentOrders($first, $second, 'cart');
        static::assertSame(['ok', 'ok'], array_column($results, 'status'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cart'));
        $dispatcher = new EventDispatcher();
        $persister = new CartPersister($this->connection, $dispatcher, new CartSerializationCleaner($this->connection, $dispatcher), new CartCompressor(false, 'gzip'), new MockClock());
        $loaded = $persister->load('contended-cart', Generator::generateSalesChannelContext());
        static::assertSame(['writer' => self::id(51), 'binary' => "\x00\xffpayload"], $loaded->getLineItems()->first()?->getPayload());
        static::assertSame([self::id(51)], $loaded->getRuleIds());
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT compressed FROM cart'));
        static::assertSame('2026-10-09 13:00:01.123', $this->connection->fetchOne('SELECT created_at FROM cart'));
    }

    public function testExclusionRetryRestartsRepairAfterDatabaseDeadlock(): void
    {
        $b = self::id(72);
        $this->insert('promotion', ['id' => $b]);
        $this->connection->executeStatement('UPDATE promotion SET exclusion_ids = :value WHERE id = :id', ['value' => json_encode([$b], \JSON_THROW_ON_ERROR), 'id' => Uuid::fromHexToBytes(self::PROMOTION)], ['id' => ParameterType::BINARY]);
        $pg = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $maintenance = $pg ? $this->connection : ContractConnectionFactory::create('DATABASE_FUNCTION_INSTALL_URL');
        static::assertSame($this->connection->getDatabase(), $maintenance->getDatabase());
        if ($pg) {
            $this->connection->executeStatement('CREATE SEQUENCE exclusion_attempt');
            $this->connection->executeStatement(<<<'SQL'
                CREATE FUNCTION fail_exclusion_once() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF nextval('exclusion_attempt') = 1 THEN
                        RAISE EXCEPTION 'retry exclusion graph' USING ERRCODE = '40P01';
                    END IF;
                    RETURN NEW;
                END $$
            SQL);
            $this->connection->executeStatement('CREATE TRIGGER exclusion_retry BEFORE UPDATE ON promotion FOR EACH ROW EXECUTE FUNCTION fail_exclusion_once()');
        } else {
            $this->connection->executeStatement('SET @exclusion_attempt = 0');
            $maintenance->executeStatement(<<<'SQL'
                CREATE TRIGGER exclusion_retry BEFORE UPDATE ON promotion FOR EACH ROW
                BEGIN
                    SET @exclusion_attempt = @exclusion_attempt + 1;
                    IF @exclusion_attempt = 1 THEN
                        SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'retry exclusion graph';
                    END IF;
                END
            SQL);
        }
        try {
            (new PromotionExclusionUpdater($this->connection))->update([self::PROMOTION]);
            static::assertSame(2, (int) $this->connection->fetchOne($pg ? 'SELECT last_value FROM exclusion_attempt' : 'SELECT @exclusion_attempt'));
            static::assertSame([self::PROMOTION], json_decode((string) $this->connection->fetchOne('SELECT exclusion_ids FROM promotion WHERE id = :id', ['id' => Uuid::fromHexToBytes($b)], ['id' => ParameterType::BINARY]), true, 512, \JSON_THROW_ON_ERROR));
            static::assertFalse($this->connection->isTransactionActive());
        } finally {
            $maintenance->executeStatement($pg ? 'DROP TRIGGER exclusion_retry ON promotion' : 'DROP TRIGGER exclusion_retry');
            if (!$pg) {
                $maintenance->close();
            }
            if ($pg) {
                $this->connection->executeStatement('DROP SEQUENCE exclusion_attempt');
            }
        }
    }

    public function testDeletingLineItemDoesNotReleaseCodeBelongingToAnotherOrder(): void
    {
        $runtime = self::runtime($this->connection, self::order(52));
        self::persist($runtime['persister']);
        $other = ['orderId' => self::id(53)];
        $this->connection->executeStatement('UPDATE promotion_individual_code SET payload = :payload', ['payload' => json_encode($other, \JSON_THROW_ON_ERROR)]);
        $runtime['lines']->delete([['id' => self::id(1052)]], Context::createDefaultContext());
        static::assertSame($other, json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT order_count FROM promotion'));
    }

    public function testCartStorageMigrationPreservesCompressedBytesRulesAndIdentity(): void
    {
        $redis = new class extends RedisStub {
            public function _prefix(string $key): string
            {
                return $key;
            }
        };
        $compressor = new CartCompressor(true, 'gzip');
        $cart = new Cart('migrated');
        $cart->add((new LineItem('binary', 'custom'))->setPayload(['binary' => "\x00\xff\\'cart"]));
        [$compressed, $content] = $compressor->serialize(['cart' => $cart, 'rule_ids' => [self::PROMOTION]]);
        $key = RedisCartPersister::PREFIX . 'migrated';
        $redis->set($key, serialize(['compressed' => $compressed, 'content' => $content]));
        $command = new CartMigrateCommand($redis, $this->connection, 2, static::createStub(RedisConnectionFactory::class), $compressor, new MockClock('2026-10-09 10:00:00.123'));
        $tester = new CommandTester($command);
        static::assertSame(0, $tester->execute(['from' => 'redis']));
        $identity = $this->connection->fetchOne('SELECT auto_increment FROM cart');
        static::assertSame(0, $tester->execute(['from' => 'redis']));
        static::assertSame($identity, $this->connection->fetchOne('SELECT auto_increment FROM cart'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cart'));
        static::assertSame('2026-10-09 10:00:00.123', $this->connection->fetchOne('SELECT created_at FROM cart'));
        $redis->del($key);
        static::assertSame(0, $tester->execute(['from' => 'sql']));
        $stored = $redis->get($key);
        static::assertIsString($stored);
        $encoded = Serialization::assertUnserializedIsArray($stored);
        static::assertIsString($encoded['content']);
        static::assertIsInt($encoded['compressed']);
        $restored = $compressor->unserialize($encoded['content'], $encoded['compressed']);
        static::assertIsArray($restored);
        static::assertInstanceOf(Cart::class, $restored['cart']);
        static::assertSame([self::PROMOTION], $restored['rule_ids']);
        static::assertSame($cart->getLineItems()->first()?->getPayload(), $restored['cart']->getLineItems()->first()?->getPayload());
    }

    public function testDatabaseDeadlockAfterCodeClaimRollsBackWithoutReplayingSubscribers(): void
    {
        $runtime = self::runtime($this->connection, self::order(46));
        $calls = 0;
        $runtime['dispatcher']->addListener(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, function () use (&$calls): void {
            ++$calls;
            if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->connection->executeStatement('DO $$ BEGIN RAISE EXCEPTION \'checkout failure\' USING ERRCODE = \'40P01\'; END $$');
            } else {
                $this->connection->executeStatement('SIGNAL SQLSTATE \'40001\' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = \'checkout failure\'');
            }
        }, -100);
        try {
            self::persist($runtime['persister']);
            static::fail('The deadlock must abort checkout');
        } catch (DeadlockException) {
            static::assertSame(1, $calls);
        }
        $this->assertDomainState(0, 1, 0);
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
        static::assertFalse($this->connection->isTransactionActive());
    }

    public function testValidationFailureBeforeClaimsLeavesNoDomainEffects(): void
    {
        $order = self::order(47);
        $order['lineItems'][0]['quantity'] = 'invalid';
        try {
            self::persist(self::runtime($this->connection, $order)['persister']);
            static::fail('Invalid line items must fail DAL validation');
        } catch (Write\WriteException) {
            static::assertFalse($this->connection->isTransactionActive());
        }
        $this->assertDomainState(0, 1, 0);
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
    }

    public function testConcurrentExclusionRepairsSerializeTheWholeGraph(): void
    {
        $b = self::id(70);
        $c = self::id(71);
        $this->insert('promotion', ['id' => $b, 'exclusion_ids' => json_encode([$c], \JSON_THROW_ON_ERROR)]);
        $this->insert('promotion', ['id' => $c]);
        $this->connection->executeStatement('UPDATE promotion SET exclusion_ids = :value WHERE id = :id', ['value' => json_encode([$b], \JSON_THROW_ON_ERROR), 'id' => Uuid::fromHexToBytes(self::PROMOTION)], ['id' => ParameterType::BINARY]);
        $first = self::order(48);
        $first['id'] = self::PROMOTION;
        $second = self::order(49);
        $second['id'] = $b;
        $results = $this->concurrentOrders($first, $second, 'exclusion');
        static::assertSame(['ok', 'ok'], array_column($results, 'status'));
        $graph = array_map(static fn (string $json): array => json_decode($json, true, 512, \JSON_THROW_ON_ERROR), $this->connection->fetchAllKeyValue('SELECT sw_uuid_hex(id), exclusion_ids FROM promotion'));
        static::assertSame([$b], $graph[self::PROMOTION]);
        static::assertSame([$b], $graph[$c]);
        sort($graph[$b]);
        $expected = [self::PROMOTION, $c];
        sort($expected);
        static::assertSame($expected, $graph[$b]);
    }

    public function testAtomicCodeClaimIsIdempotentAndRollbackReleasesIt(): void
    {
        $gateway = new PromotionCodeGateway($this->connection);
        $payload = ['orderId' => self::id(40), 'customerId' => self::CUSTOMER, 'customerName' => 'Fixture Customer'];
        $this->connection->beginTransaction();
        $gateway->claim(self::CODE, 'ONE', $payload);
        $gateway->claim(self::CODE, 'ONE', $payload);
        static::assertSame($payload, json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR));
        $this->connection->rollBack();
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));
        $gateway->claim(self::CODE, 'ONE', $payload);
        try {
            $gateway->claim(self::CODE, 'ONE', ['orderId' => self::id(41)]);
            static::fail('A second order cannot claim the code');
        } catch (PromotionException $exception) {
            static::assertSame('CHECKOUT__CODE_ALREADY_REDEEMED', $exception->getErrorCode());
        }
        static::assertSame($payload, json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testConcurrentConditionalCodeClaimRejectsSecondWriterWithoutPromotionLock(): void
    {
        $results = $this->concurrentOrders(self::order(42), self::order(43), 'claim');
        static::assertSame(['ok', 'rejected'], array_column($results, 'status'));
        static::assertSame(self::id(42), json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR)['orderId']);
        $this->assertDomainState(0, 1, 0);
    }

    public function testPromotionExclusionsRepairAppendAndRemoveCrossReferences(): void
    {
        $a = self::PROMOTION;
        $b = self::id(70);
        $c = self::id(71);
        $this->insert('promotion', ['id' => $b, 'exclusion_ids' => json_encode([$a, $c], \JSON_THROW_ON_ERROR)]);
        $this->insert('promotion', ['id' => $c, 'exclusion_ids' => json_encode([$a], \JSON_THROW_ON_ERROR)]);
        $this->connection->executeStatement('UPDATE promotion SET exclusion_ids = :value WHERE id = :id', ['id' => Uuid::fromHexToBytes($a), 'value' => json_encode([$b, self::id(999), 'invalid'], \JSON_THROW_ON_ERROR)], ['id' => ParameterType::BINARY]);
        $updater = new PromotionExclusionUpdater($this->connection);
        $updater->update([$a]);
        $exclusions = $this->connection->fetchAllKeyValue('SELECT sw_uuid_hex(id), exclusion_ids FROM promotion');
        static::assertSame([$b], json_decode($exclusions[$a], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame([$a, $c], json_decode($exclusions[$b], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame([], json_decode($exclusions[$c], true, 512, \JSON_THROW_ON_ERROR));
        $updater->update([$a]);
        static::assertSame([$a, $c], json_decode((string) $this->connection->fetchOne('SELECT exclusion_ids FROM promotion WHERE id = :id', ['id' => Uuid::fromHexToBytes($b)], ['id' => ParameterType::BINARY]), true, 512, \JSON_THROW_ON_ERROR));
        $this->connection->executeStatement('UPDATE promotion SET exclusion_ids = NULL WHERE id = :id', ['id' => Uuid::fromHexToBytes($a)], ['id' => ParameterType::BINARY]);
        $updater->update([$a]);
        static::assertSame([$c], json_decode((string) $this->connection->fetchOne('SELECT exclusion_ids FROM promotion WHERE id = :id', ['id' => Uuid::fromHexToBytes($b)], ['id' => ParameterType::BINARY]), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testCustomerReviewAndCompletedOrderMetadataUseBinaryIds(): void
    {
        $binary = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement("CREATE TABLE product_review (id $binary PRIMARY KEY, customer_id $binary, status SMALLINT)");
        $this->insert('product_review', ['id' => self::id(80), 'customer_id' => self::CUSTOMER, 'status' => 1]);
        $this->insert('product_review', ['id' => self::id(81), 'customer_id' => self::CUSTOMER, 'status' => 0]);
        (new ProductReviewCountService($this->connection))->updateReviewCount([self::id(80), self::id(81)]);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT review_count FROM customer'));
        self::persist(self::runtime($this->connection, self::order(44, ''))['persister']);
        $this->insert('state_machine_state', ['id' => self::id(82), 'technical_name' => 'completed']);
        $order = $this->connection->quoteSingleIdentifier('order');
        $this->connection->executeStatement("UPDATE $order SET state_id = :state, amount_total = 123.45, currency_factor = 1.5, order_date_time = '2026-10-09 11:22:33.123'", ['state' => Uuid::fromHexToBytes(self::id(82))], ['state' => ParameterType::BINARY]);
        $open = new StateMachineStateEntity();
        $open->setTechnicalName('open');
        $completed = new StateMachineStateEntity();
        $completed->setTechnicalName('completed');
        $subscriber = new CustomerMetaFieldSubscriber($this->connection);
        $subscriber->fillCustomerMetaDataFields(new StateMachineTransitionEvent('order', self::id(44), $open, $completed, Context::createDefaultContext()));
        $customer = $this->connection->fetchAssociative('SELECT order_count, order_total_amount, last_order_date FROM customer');
        static::assertIsArray($customer);
        static::assertSame(1, (int) $customer['order_count']);
        static::assertSame(82.3, (float) $customer['order_total_amount']);
        static::assertSame('2026-10-09 11:22:33.123', $customer['last_order_date']);
        $this->connection->executeStatement("UPDATE $order SET state_id = :state", ['state' => Uuid::fromHexToBytes(self::STATE)], ['state' => ParameterType::BINARY]);
        $subscriber->fillCustomerMetaDataFields(new StateMachineTransitionEvent('order', self::id(44), $completed, $open, Context::createDefaultContext()));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT order_count FROM customer'));
        static::assertNull($this->connection->fetchOne('SELECT last_order_date FROM customer'));
    }

    public function testPaymentShippingAndDocumentTechnicalNameResolversKeepHexIds(): void
    {
        $binary = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'BYTEA' : 'BINARY(16)';
        foreach (['payment_method' => PaymentMethodTechnicalNameFkResolver::class, 'shipping_method' => ShippingMethodTechnicalNameFkResolver::class, 'document_type' => DocumentTypeTechnicalNameFkResolver::class] as $table => $resolver) {
            $this->connection->executeStatement("CREATE TABLE $table (id $binary PRIMARY KEY, technical_name VARCHAR(255))");
            $this->insert($table, ['id' => self::id(90), 'technical_name' => 'fixture']);
            $reference = new FkReference('/0/id', $table, 'technical_name', 'fixture', false);
            $missing = new FkReference('/0/id', $table, 'technical_name', 'missing', false);
            (new $resolver($this->connection))->resolve([$reference, $missing]);
            static::assertSame(self::id(90), $reference->resolved);
            static::assertNull($missing->resolved);
        }
    }

    public function testInvoiceReferencesPreserveSnapshotMatchAndLiveFallback(): void
    {
        self::persist(self::runtime($this->connection, self::order(45, ''))['persister']);
        $this->createDocumentTables();
        $version = self::id(100);
        $this->insert('document_type', ['id' => self::id(90), 'technical_name' => 'invoice']);
        $this->insert('document', ['id' => self::id(101), 'document_type_id' => self::id(90), 'order_id' => self::id(45), 'order_version_id' => Defaults::LIVE_VERSION, 'document_number' => 'INV-LIVE', 'config' => '{}', 'sent' => 1, 'created_at' => '2026-10-09 00:00:00.000']);
        $this->insert('order', ['id' => self::id(45), 'version_id' => $version, 'order_number' => 'ORDER-SNAPSHOT', 'state_id' => self::STATE, 'deep_link_code' => 'snapshot-link']);
        $this->insert('document', ['id' => self::id(102), 'document_type_id' => self::id(90), 'order_id' => self::id(45), 'order_version_id' => $version, 'document_number' => 'INV-SNAPSHOT', 'config' => '{}', 'sent' => 1, 'created_at' => '2026-10-09 00:00:00.000']);
        $loader = new ReferenceInvoiceLoader($this->connection);
        $invoice = $loader->load(self::id(45), self::id(102), 'snapshot-link');
        static::assertSame(['id', 'orderId', 'orderVersionId', 'versionId', 'deepLinkCode', 'config', 'documentNumber'], array_keys($invoice));
        static::assertSame($version, $invoice['orderVersionId']);
        static::assertSame($version, $invoice['versionId']);
        $resolver = new ReferencedDocumentResolver($loader, $this->connection);
        static::assertSame('INV-SNAPSHOT', $resolver->resolve(self::id(45), self::id(102))->documentNumber);
        static::assertSame(Defaults::LIVE_VERSION, $resolver->resolve(self::id(45), self::id(101))->orderVersionId);
        $this->connection->executeStatement('DELETE FROM ' . $this->connection->quoteSingleIdentifier('order') . ' WHERE version_id = :version', ['version' => Uuid::fromHexToBytes($version)], ['version' => ParameterType::BINARY]);
        static::assertSame(Defaults::LIVE_VERSION, $loader->load(self::id(45), self::id(102))['orderVersionId']);
        $this->expectException(DocumentV2Exception::class);
        $resolver->resolve(self::id(45), self::id(102));
    }

    public function testDocumentV2PartialConfigAndTypeNameReadBinaryWriteCommands(): void
    {
        $this->createDocumentTables();
        $binary = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'BYTEA' : 'BINARY(16)';
        $json = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE document_base_config (id $binary PRIMARY KEY, config $json)");
        $this->insert('document_base_config', ['id' => self::id(110), 'config' => '{"pageSize":"A4","company":"unchanged","plugin":{"enabled":true}}']);
        $this->insert('document_type', ['id' => self::id(111), 'technical_name' => 'invoice']);
        $definition = new DocumentBaseConfigDefinition();
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $container->set($definition::class, $definition);
        $registry->register($definition);
        $command = new Write\Command\UpdateCommand($definition, ['page_size' => 'A5', 'document_type_id' => Uuid::fromHexToBytes(self::id(111))], ['id' => Uuid::fromHexToBytes(self::id(110))], new Write\EntityExistence('document_base_config', ['id' => self::id(110)], true, false, false, []), '/0');
        $event = EntityWriteEvent::create(Write\WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        (new DocumentBaseConfigSyncSubscriber($this->connection))->syncDocumentBaseConfig($event);
        (new DocumentTypeNameSyncSubscriber($this->connection))->writeTypeName($event);
        static::assertSame('invoice', $command->getPayload()['type_name']);
        $config = json_decode($command->getPayload()['config'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('A5', $config['pageSize']);
        static::assertSame('unchanged', $config['company']);
        static::assertSame(['enabled' => true], $config['plugin']);
    }

    public function testDocumentFilenameValidationReadsStoredScopesAndAssignedConfigsWithBinaryIds(): void
    {
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $postgres ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE document_base_config (id $binary PRIMARY KEY, name VARCHAR(255), type_name VARCHAR(255), global SMALLINT, filename_infixes $json)");
        $this->connection->executeStatement("CREATE TABLE document_base_config_sales_channel (document_base_config_id $binary)");
        $global = self::id(120);
        $assigned = self::id(121);
        $this->insert('document_base_config', ['id' => $global, 'name' => 'Global', 'type_name' => 'invoice', 'global' => 1, 'filename_infixes' => '{"pdf":"_pdf","zugferd_embedded_pdf":"_zugferd"}']);
        $this->insert('document_base_config', ['id' => $assigned, 'name' => 'Assigned', 'type_name' => 'invoice', 'global' => 0, 'filename_infixes' => '{"pdf":"_zugferd"}']);
        $this->insert('document_base_config_sales_channel', ['document_base_config_id' => $assigned]);
        $definition = new DocumentBaseConfigDefinition();
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $container->set($definition::class, $definition);
        $registry->register($definition);
        $apps = static::createStub(AppFeatureStorage::class);
        $apps->method('forActiveApps')->willReturn([]);
        $renderers = [];
        foreach ([DocumentFormat::PDF, DocumentFormat::ZUGFERD_EMBEDDED_PDF] as $format) {
            $renderer = static::createStub(AbstractDocumentRenderer::class);
            $renderer->method('getFormat')->willReturn($format->value);
            $renderer->method('getFileExtension')->willReturn('pdf');
            $renderers[] = $renderer;
        }
        $rendererRegistry = new DocumentRendererRegistry($renderers);
        $validator = new DocumentBaseConfigValidator(new MockClock(), $this->connection, new DocumentTypeRegistry([new InvoiceDocumentType()], $apps), static fn (): DocumentRendererRegistry => $rendererRegistry);

        // A partial channel write loads its persisted scope, then inherits global infixes.
        $command = new Write\Command\UpdateCommand($definition, ['filename_infixes' => '{"pdf":"_zugferd"}'], ['id' => Uuid::fromHexToBytes($assigned)], new Write\EntityExistence('document_base_config', ['id' => $assigned], true, false, false, []), '/0');
        $event = new PreWriteValidationEvent(Write\WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        $validator->validate($event);
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        static::assertCount(2, $exceptions[0]->getViolations());

        // A global scope write reads stored infixes and checks assigned channel overrides.
        $command = new Write\Command\UpdateCommand($definition, ['global' => 1], ['id' => Uuid::fromHexToBytes($global)], new Write\EntityExistence('document_base_config', ['id' => $global], true, false, false, []), '/0');
        $event = new PreWriteValidationEvent(Write\WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        $validator->validate($event);
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        static::assertCount(1, $exceptions[0]->getViolations());
        static::assertSame('/0/filenameInfixes/zugferd_embedded_pdf', $exceptions[0]->getViolations()->get(0)->getPropertyPath());
    }

    public function testRecoveryCleanupDeletesEveryExpiredBatchAndPreservesRecentRows(): void
    {
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $date = $postgres ? 'TIMESTAMP(3)' : 'DATETIME(3)';
        $this->connection->executeStatement("CREATE TABLE customer_recovery (id $binary PRIMARY KEY, created_at $date)");
        $this->connection->transactional(function (): void {
            for ($number = 200; $number < 1203; ++$number) {
                $this->insert('customer_recovery', ['id' => self::id($number), 'created_at' => '2026-10-10 12:00:00.000']);
            }
        });
        $this->insert('customer_recovery', ['id' => self::id(1203), 'created_at' => '2026-10-10 12:00:00.001']);
        $handler = new CleanupCustomerRecoveryTaskHandler(StaticEntityRepository::of(ScheduledTaskCollection::class), new NullLogger(), $this->connection, new MockClock('2026-10-12 12:00:00.000'));

        $handler->run();

        static::assertSame([self::id(1203)], $this->connection->fetchFirstColumn('SELECT sw_uuid_hex(id) FROM customer_recovery'));
        $handler->run();
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM customer_recovery'));
    }

    private function createDocumentTables(): void
    {
        $pg = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $binary = $pg ? 'BYTEA' : 'BINARY(16)';
        $json = $pg ? 'JSONB' : 'JSON';
        $date = $pg ? 'TIMESTAMP(3)' : 'DATETIME(3)';
        $this->connection->executeStatement("CREATE TABLE document_type (id $binary PRIMARY KEY, technical_name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE document (id $binary PRIMARY KEY, order_id $binary, order_version_id $binary, document_type_id $binary, referenced_document_id $binary, document_number VARCHAR(255), config $json, sent SMALLINT, created_at $date)");
    }

    private function assertDomainState(int $orders, int $stock, int $redemptions): void
    {
        static::assertSame($orders, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->connection->quoteSingleIdentifier('order')));
        static::assertSame($orders, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_customer'));
        static::assertSame($stock, (int) $this->connection->fetchOne('SELECT stock FROM product'));
        static::assertSame($stock, (int) $this->connection->fetchOne('SELECT available_stock FROM product'));
        static::assertSame(1 - $stock, (int) $this->connection->fetchOne('SELECT sales FROM product'));
        static::assertSame($redemptions, (int) $this->connection->fetchOne('SELECT order_count FROM promotion'));
    }

    /**
     * @param array<string, mixed> $first
     * @param array<string, mixed> $second
     *
     * @return list<array{status: string}>
     */
    private function concurrentOrders(array $first, array $second, string $operation = 'order'): array
    {
        $worker = __DIR__ . '/_fixtures/checkout-worker.php';
        $filesystem = new Filesystem();
        $release = $filesystem->tempnam(sys_get_temp_dir(), 'checkout-release-');
        $filesystem->remove($release);
        $one = new Process([\PHP_BINARY, $worker, json_encode($first, \JSON_THROW_ON_ERROR), $release, 'hold', $operation]);
        $two = new Process([\PHP_BINARY, $worker, json_encode($second, \JSON_THROW_ON_ERROR), $release, 'run', $operation]);
        $one->setTimeout(20);
        $two->setTimeout(20);
        try {
            $one->start();
            static::assertTrue($one->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'holding')), $one->getErrorOutput() . $one->getOutput());
            $two->start();
            static::assertTrue($two->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'started')), $two->getErrorOutput() . $two->getOutput());
            // Confirm the second is blocked by the first inside the database operation.
            $this->assertDatabaseLockWait($one, $two);
            $filesystem->touch($release);
            $one->wait();
            $two->wait();
            static::assertSame(0, $one->getExitCode(), $one->getErrorOutput() . $one->getOutput());
            static::assertSame(0, $two->getExitCode(), $two->getErrorOutput() . $two->getOutput());

            $results = [];
            foreach ([$one, $two] as $process) {
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode($lines[array_key_last($lines)], true, 512, \JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            $one->stop();
            $two->stop();
            $filesystem->remove($release);
        }
    }

    private function assertDatabaseLockWait(Process $blocker, Process $waiter): void
    {
        preg_match('/started:(\d+)/', $blocker->getOutput(), $blockerId);
        preg_match('/started:(\d+)/', $waiter->getOutput(), $waiterId);
        static::assertArrayHasKey(1, $blockerId);
        static::assertArrayHasKey(1, $waiterId);
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        // MySQL performance_schema inspection uses the fixture maintenance account.
        // Checkout workers retain the ordinary application account.
        $observer = ContractConnectionFactory::create($postgres ? 'DATABASE_CONTRACT_URL' : 'DATABASE_FUNCTION_INSTALL_URL');
        $sql = $postgres
            ? 'SELECT COUNT(*) FROM unnest(pg_blocking_pids(:waiter)) blocker(id) WHERE id = :blocker'
            : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits waits
               INNER JOIN performance_schema.threads requesting ON requesting.THREAD_ID = waits.REQUESTING_THREAD_ID
               INNER JOIN information_schema.innodb_trx blocking ON blocking.TRX_ID = waits.BLOCKING_ENGINE_TRANSACTION_ID
               WHERE requesting.PROCESSLIST_ID = :waiter AND blocking.TRX_MYSQL_THREAD_ID = :blocker';
        try {
            $deadline = microtime(true) + 10;
            do {
                $waiting = (int) $observer->fetchOne($sql, ['waiter' => (int) $waiterId[1], 'blocker' => (int) $blockerId[1]], ['waiter' => ParameterType::INTEGER, 'blocker' => ParameterType::INTEGER]);
                if ($waiting > 0) {
                    return;
                }
                usleep(10000);
            } while ($waiter->isRunning() && microtime(true) < $deadline);

            static::fail('The second checkout operation did not wait for the first database lock: ' . $waiter->getErrorOutput() . $waiter->getOutput());
        } finally {
            $observer->close();
        }
    }
}

/**
 * @internal
 */
#[Package('checkout')]
class CheckoutOrderDefinition extends OrderDefinition
{
    public function getHydratorClass(): string
    {
        return Dbal\EntityHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()), new Field\VersionField(),
            new Field\StringField('order_number', 'orderNumber'), new Field\IdField('state_id', 'stateId'),
            new Field\OneToManyAssociationField('lineItems', CheckoutLineDefinition::class, 'order_id'),
            new Field\OneToOneAssociationField('orderCustomer', 'id', 'order_id', CheckoutCustomerDefinition::class, false),
        ]);
    }
}
/**
 * @internal
 */
#[Package('checkout')]
class CheckoutCustomerDefinition extends OrderCustomerDefinition
{
    protected function getParentDefinitionClass(): ?string
    {
        return CheckoutOrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()), new Field\VersionField(),
            new Field\FkField('order_id', 'orderId', CheckoutOrderDefinition::class), (new Field\ReferenceVersionField(CheckoutOrderDefinition::class))->addFlags(new Field\Flag\Required()),
            new Field\IdField('customer_id', 'customerId'), new Field\StringField('first_name', 'firstName'), new Field\StringField('last_name', 'lastName'), new Field\StringField('email', 'email'),
        ]);
    }
}
/**
 * @internal
 */
#[Package('checkout')]
class CheckoutLineDefinition extends OrderLineItemDefinition
{
    protected function getParentDefinitionClass(): ?string
    {
        return CheckoutOrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()), new Field\VersionField(),
            new Field\FkField('order_id', 'orderId', CheckoutOrderDefinition::class), (new Field\ReferenceVersionField(CheckoutOrderDefinition::class))->addFlags(new Field\Flag\Required()),
            new Field\StringField('type', 'type'), new Field\StringField('referenced_id', 'referencedId'), new Field\IntField('quantity', 'quantity'), new Field\IdField('promotion_id', 'promotionId'), new Field\JsonField('payload', 'payload'),
        ]);
    }
}
/**
 * @internal
 */
#[Package('checkout')]
class CheckoutCodeDefinition extends PromotionIndividualCodeDefinition
{
    protected function getParentDefinitionClass(): ?string
    {
        return CheckoutPromotionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\FkField('promotion_id', 'promotionId', CheckoutPromotionDefinition::class), new Field\StringField('code', 'code'), new Field\JsonField('payload', 'payload'),
        ]);
    }
}
/**
 * @internal
 */
#[Package('checkout')]
class CheckoutPromotionDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'promotion';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required())]);
    }
}
/**
 * @internal
 */
#[Package('checkout')]
class CheckoutBuyerDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'customer';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required())]);
    }
}

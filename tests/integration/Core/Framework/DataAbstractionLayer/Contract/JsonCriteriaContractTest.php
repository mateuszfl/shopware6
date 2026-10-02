<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class JsonCriteriaContractTest extends TestCase
{
    private const CURRENCY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const RULE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private Connection $connection;

    private EntityDefinitionQueryHelper $helper;

    private CriteriaQueryBuilder $builder;

    private EntityAggregator $aggregator;

    private JsonCriteriaDefinition $definition;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $endpoint = DatabaseEndpoint::observe($this->connection);
        static::assertFalse($endpoint->standby);
        static::assertStringStartsWith($endpoint->platform === 'postgresql' ? '18.6 ' : '8.4.', $endpoint->version);
        $pg = $endpoint->platform === 'postgresql';
        if ($pg) {
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS sw_json_criteria CASCADE');
            $this->connection->executeStatement('CREATE SCHEMA sw_json_criteria');
            $this->connection->executeStatement('SET search_path TO sw_json_criteria');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        }
        if (!$pg && $this->connection->getDatabase() !== 'shopware_json_test') {
            throw new \RuntimeException('The JSON Criteria suite requires its dedicated shopware_json_test database');
        }
        $json = $pg ? 'JSONB' : 'JSON';
        $binary = $pg ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement("CREATE TABLE sw_json_product (id $binary PRIMARY KEY, payload $json, items $json, config $json, custom_fields $json, price $json, cheapest_price_accessor $json)");
        $this->connection->executeStatement("CREATE TABLE currency (id $binary PRIMARY KEY, factor DECIMAL(10, 4))");
        $this->connection->executeStatement('CREATE TABLE custom_field (name VARCHAR(255), type VARCHAR(255), active INT)');
        $this->connection->insert('currency', ['id' => Uuid::fromHexToBytes(self::CURRENCY), 'factor' => '3.0'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('custom_field', ['name' => 'literal.name"\\', 'type' => 'float', 'active' => 1]);
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        foreach ([JsonFieldAccessorBuilder::class, PriceFieldAccessorBuilder::class, ConfigJsonFieldAccessorBuilder::class, DefaultFieldAccessorBuilder::class] as $class) {
            $container->set($class, new $class($this->connection));
        }
        $container->set(CustomFieldsAccessorBuilder::class, new CustomFieldsAccessorBuilder(new CustomFieldService($this->connection), $this->connection));
        $container->set(CheapestPriceAccessorBuilder::class, new CheapestPriceAccessorBuilder(2, new NullLogger(), $this->connection));
        $validator = Validation::createValidator();
        foreach ([JsonFieldSerializer::class, PriceFieldSerializer::class, ConfigJsonFieldSerializer::class, ListFieldSerializer::class, IdFieldSerializer::class] as $class) {
            $container->set($class, new $class($validator, $registry));
        }
        $container->set(PHPUnserializeFieldSerializer::class, new PHPUnserializeFieldSerializer());
        $container->set(CustomFieldsSerializer::class, new CustomFieldsSerializer($registry, $validator, new CustomFieldService($this->connection)));
        $this->definition = new JsonCriteriaDefinition();
        $registry->register($this->definition);
        $this->helper = new EntityDefinitionQueryHelper($this->connection);
        $parser = new SqlQueryParser($this->helper, $this->connection);
        $interpreter = new SearchTermInterpreter(new Tokenizer(new HtmlSanitizer(), 2), 2);
        $scoreBuilder = new EntityScoreQueryBuilder();
        $this->builder = new CriteriaQueryBuilder($parser, $this->helper, $interpreter, $scoreBuilder, new JoinGroupBuilder(), new CriteriaPartResolver($this->connection, $parser));
        $this->aggregator = new EntityAggregator($this->connection, $this->helper, $registry, $this->builder, false, $interpreter, $scoreBuilder);
        $this->context = Context::createDefaultContext();
        foreach ([10, 2, null, 0] as $index => $number) {
            $payload = ['amount' => $number, 'flag' => $index % 2 === 0, 'group' => $index < 2 ? 'first' : 'second', 'text' => $index === 0 ? 'café 100%_\\' : 'other'];
            if ($index === 3) {
                unset($payload['amount']);
            }
            $payload['hostile\' OR 1=1 --'] = $index;
            $payload['key.extensions.with.dot'] = $index;
            $price = ['gross' => 10.024 + $index, 'net' => 8.014 + $index, 'listPrice' => ['gross' => 20, 'net' => 16], 'percentage' => ['gross' => 25, 'net' => 30]];
            $cheapest = ['ruledefault' => ['currency' . Defaults::CURRENCY => $price], 'rule' . self::RULE => ['currency' . Defaults::CURRENCY => array_replace($price, ['gross' => 5.025, 'net' => 4.014])]];
            $this->connection->insert('sw_json_product', ['id' => Uuid::fromHexToBytes(self::id($index)), 'payload' => json_encode($payload, \JSON_THROW_ON_ERROR), 'items' => json_encode([$index, (string) $index, $index === 0], \JSON_THROW_ON_ERROR), 'config' => '{"_value":{"amount":"12.5"}}', 'custom_fields' => json_encode(['literal.name"\\' => '7.25'], \JSON_THROW_ON_ERROR), 'price' => json_encode(['c' . Defaults::CURRENCY => $price], \JSON_THROW_ON_ERROR), 'cheapest_price_accessor' => json_encode($cheapest, \JSON_THROW_ON_ERROR)], ['id' => ParameterType::BINARY]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            foreach (['sw_json_product', 'currency', 'custom_field'] as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
            }
            if ((new DatabasePlatformResolver($this->connection))->capabilities()->supportsJsonb) {
                $this->connection->executeStatement('DROP SCHEMA sw_json_criteria CASCADE');
            }
            $this->connection->close();
        }
    }

    public function testCriteriaBindNestedPathsAndCompareNullRangeAndText(): void
    {
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('payload.amount', 10)));
        static::assertSame([self::id(2), self::id(3)], $this->ids(new EqualsFilter('payload.amount', null)));
        static::assertSame([self::id(0), self::id(1)], $this->ids(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('payload.amount', null)])));
        static::assertSame([self::id(1)], $this->ids(new RangeFilter('payload.amount', [RangeFilter::GT => 1, RangeFilter::LT => 5])));
        static::assertSame([self::id(0)], $this->ids(new ContainsFilter('payload.text', 'CAFE')));
        static::assertSame([self::id(0)], $this->ids(new ContainsFilter('payload.text', '100%_\\')));
        static::assertSame([self::id(2)], $this->ids(new EqualsFilter('payload."key.extensions.with.dot"', 2)));
        $hostile = 'payload.' . json_encode('hostile\' OR 1=1 --', \JSON_THROW_ON_ERROR);
        static::assertSame([self::id(2)], $this->ids(new EqualsFilter($hostile, 2)));
        $query = $this->builder->build(new QueryBuilder($this->connection), $this->definition, (new Criteria())->addFilter(new EqualsFilter($hostile, 2)), $this->context);
        $query->select('id');
        static::assertStringNotContainsString('hostile', $query->getSQL());
        static::assertNotEmpty($query->getParameters());
    }

    public function testListContainsPreservesCandidateTypesAndEmptyAnyIsFalse(): void
    {
        static::assertSame([self::id(1)], $this->ids(new EqualsFilter('items', 1)));
        static::assertSame([self::id(1)], $this->ids(new EqualsFilter('items', '1')));
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('items', true)));
        static::assertSame([self::id(1), self::id(2), self::id(3)], $this->ids(new EqualsFilter('items', false)));
        static::assertSame([self::id(0), self::id(3)], $this->ids(new EqualsAnyFilter('items', [true, 3])));
        static::assertSame([], $this->ids(new EqualsAnyFilter('items', [])));
        $this->connection->executeStatement('UPDATE sw_json_product SET items = ? WHERE id = ?', ['["7"]', Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame([], $this->ids(new EqualsFilter('items', 7)));
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('items', '7')));
    }

    public function testConfigAndCustomFieldsUseTheirDeclaredScalarMappings(): void
    {
        static::assertSame([self::id(0), self::id(1), self::id(2), self::id(3)], $this->ids(new RangeFilter('config.amount', [RangeFilter::GT => 12])));
        $accessor = 'customFields.' . json_encode('literal.name"\\', \JSON_THROW_ON_ERROR);
        static::assertSame([self::id(0), self::id(1), self::id(2), self::id(3)], $this->ids(new RangeFilter($accessor, [RangeFilter::GT => 7])));
    }

    public function testListContainsDescendsThroughArraysWithoutMatchingObjectValuesOrChangingTypes(): void
    {
        $this->connection->executeStatement('UPDATE sw_json_product SET items = ? WHERE id = ?', ['[[7], ["8"], [true], {"ignored":9}]', Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('items', 7)));
        static::assertSame([], $this->ids(new EqualsFilter('items', '7')));
        static::assertSame([], $this->ids(new EqualsFilter('items', 8)));
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('items', '8')));
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('items', true)));
        static::assertSame([], $this->ids(new EqualsFilter('items', 9)));
        static::assertSame([self::id(0)], $this->ids(new EqualsAnyFilter('items', [7, 9])));

        $this->connection->executeStatement('UPDATE sw_json_product SET items = ? WHERE id = ?', ['[[null], false]', Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame([self::id(0)], $this->ids(new EqualsAnyFilter('items', [null])));
        static::assertSame([], $this->ids(new EqualsFilter('items', null)));
        $this->connection->executeStatement('UPDATE sw_json_product SET items = NULL WHERE id = ?', [Uuid::fromHexToBytes(self::id(0))], [ParameterType::BINARY]);
        static::assertSame([], $this->ids(new EqualsAnyFilter('items', [null])));
        static::assertSame([self::id(0)], $this->ids(new EqualsFilter('items', null)));
    }

    public function testSortingsAndNestedAggregationsTransportAllPathParameters(): void
    {
        $criteria = (new Criteria())->addFilter(new RangeFilter('payload.amount', [RangeFilter::GTE => 0]))->addSorting(new FieldSorting('payload.amount'));
        $query = $this->builder->build(new QueryBuilder($this->connection), $this->definition, $criteria, $this->context);
        $query->select((new DatabasePlatformResolver($this->connection))->dialect()->hex(['sw_json_product', 'id']));
        static::assertSame([self::id(1), self::id(0)], $query->executeQuery()->fetchFirstColumn());
        $query = $this->builder->build(new QueryBuilder($this->connection), $this->definition, (new Criteria())->addSorting(new FieldSorting('payload.amount'), new FieldSorting('id')), $this->context);
        $query->select((new DatabasePlatformResolver($this->connection))->dialect()->hex(['sw_json_product', 'id']));
        static::assertSame([self::id(2), self::id(3), self::id(1), self::id(0)], $query->executeQuery()->fetchFirstColumn());
        $criteria = (new Criteria())->addAggregation(new TermsAggregation('groups', 'payload.group', sorting: new FieldSorting('payload.group'), aggregation: new FilterAggregation('filtered', new AvgAggregation('average', 'payload.amount'), [new RangeFilter('payload.amount', [RangeFilter::GTE => 0])])));
        $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertCount(1, $result->getBuckets());
        $bucket = $result->getBuckets()[0];
        static::assertSame('first', $bucket->getKey());
        static::assertSame(2, $bucket->getCount());
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        static::assertSame(6.0, $bucket->getResult()->getAvg());
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation(new StatsAggregation('prices', 'price.gross', true, true, true, true)), $this->context)->get('prices');
        static::assertInstanceOf(StatsResult::class, $result);
        static::assertSame(10.02, (float) $result->getMin());
        static::assertSame(13.02, (float) $result->getMax());
        static::assertSame(46.08, $result->getSum());
    }

    public function testPricesSelectTaxListPercentageCurrencyFallbackAndCashRounding(): void
    {
        static::assertSame(10.02, $this->price('price'));
        static::assertSame(8.01, $this->price('price.net'));
        static::assertSame(20.0, $this->price('price.listPrice.gross'));
        static::assertSame(75.0, $this->price('price.percentage'));
        static::assertSame(70.0, $this->price('price.percentage.net'));
        static::assertSame(30.07, $this->price('price.' . self::CURRENCY));
        $this->context = new Context(new SystemSource(), currencyId: self::CURRENCY, currencyFactor: 2.0);
        static::assertSame(20.05, $this->price('price'));
        $this->context->setRounding(new CashRoundingConfig(2, 0.05, false));
        static::assertSame(20.05, $this->price('price'));
        $this->context->setTaxState(CartPrice::TAX_STATE_NET);
        static::assertSame(16.03, $this->price('price'));
        $this->context->setRounding(new CashRoundingConfig(2, 0.05, true));
        static::assertSame(16.05, $this->price('price'));
        $this->connection->executeStatement('UPDATE sw_json_product SET price = ? WHERE id = ?', [json_encode(['c' . Defaults::CURRENCY => ['gross' => 10], 'c' . self::CURRENCY => ['gross' => null]], \JSON_THROW_ON_ERROR), Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame(20.0, $this->price('price.gross'));
    }

    public function testPriceNullMissingNativeCurrencyAndNonCentIntervals(): void
    {
        $this->context = new Context(new SystemSource(), currencyId: self::CURRENCY, currencyFactor: 2.0);
        $document = ['c' . Defaults::CURRENCY => ['gross' => 10.025, 'net' => 8.025, 'listPrice' => ['gross' => 20]], 'c' . self::CURRENCY => ['gross' => 0, 'net' => 3.015, 'listPrice' => null]];
        $this->connection->executeStatement('UPDATE sw_json_product SET price = ? WHERE id = ?', [json_encode($document, \JSON_THROW_ON_ERROR), Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame(0.0, $this->price('price'));
        static::assertSame(3.02, $this->price('price.net'));
        static::assertSame(40.0, $this->price('price.listPrice'));
        static::assertNull($this->price('price.percentage'));
        $document['c' . self::CURRENCY]['gross'] = null;
        $this->connection->executeStatement('UPDATE sw_json_product SET price = ? WHERE id = ?', [json_encode($document, \JSON_THROW_ON_ERROR), Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame(20.05, $this->price('price'));
        unset($document['c' . self::CURRENCY]['gross']);
        $this->connection->executeStatement('UPDATE sw_json_product SET price = ? WHERE id = ?', [json_encode($document, \JSON_THROW_ON_ERROR), Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame(20.05, $this->price('price'));
        $this->context->setRounding(new CashRoundingConfig(2, 0.25, true));
        static::assertSame(20.0, $this->price('price'));
        $this->context->setRounding(new CashRoundingConfig(3, 0.25, true));
        static::assertSame(20.05, $this->price('price'));
        $this->context->setRounding(new CashRoundingConfig(0, 1.0, true));
        static::assertSame(20.0, $this->price('price'));
    }

    public function testCheapestPriceUsesRulePriorityCurrencyFallbackAndSharedRounding(): void
    {
        $this->context->setRuleIds([self::RULE]);
        static::assertSame(5.03, $this->price('cheapestPrice'));
        static::assertSame(4.01, $this->price('cheapestPrice.net'));
        static::assertSame(20.0, $this->price('cheapestPrice.listPrice'));
        static::assertSame(75.0, $this->price('cheapestPrice.percentage'));
        $this->context = new Context(new SystemSource(), ruleIds: [self::RULE], currencyId: self::CURRENCY, currencyFactor: 2.0);
        $this->context->setRounding(new CashRoundingConfig(2, 0.05, true));
        static::assertSame(10.05, $this->price('cheapestPrice'));
        $this->context->setRuleIds(['cccccccccccccccccccccccccccccccc']);
        static::assertSame(20.05, $this->price('cheapestPrice'));
    }

    public function testCheapestCurrencyNullFallbackAndPriceRangeAggregation(): void
    {
        $this->context = new Context(new SystemSource(), ruleIds: [self::RULE], currencyId: self::CURRENCY, currencyFactor: 2.0);
        $cheapest = ['rule' . self::RULE => ['currency' . self::CURRENCY => ['gross' => 0, 'net' => 3.015], 'currency' . Defaults::CURRENCY => ['gross' => 5.025, 'net' => 4.014]], 'ruledefault' => ['currency' . Defaults::CURRENCY => ['gross' => 10.024]]];
        $this->connection->executeStatement('UPDATE sw_json_product SET cheapest_price_accessor = ? WHERE id = ?', [json_encode($cheapest, \JSON_THROW_ON_ERROR), Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame(0.0, $this->price('cheapestPrice'));
        static::assertSame(3.02, $this->price('cheapestPrice.net'));
        static::assertNull($this->price('cheapestPrice.listPrice'));
        $cheapest['rule' . self::RULE]['currency' . self::CURRENCY]['gross'] = null;
        $this->connection->executeStatement('UPDATE sw_json_product SET cheapest_price_accessor = ? WHERE id = ?', [json_encode($cheapest, \JSON_THROW_ON_ERROR), Uuid::fromHexToBytes(self::id(0))], [ParameterType::STRING, ParameterType::BINARY]);
        static::assertSame(10.05, $this->price('cheapestPrice'));
        $this->context = Context::createDefaultContext();
        $aggregation = new RangeAggregation('prices', 'price', [['key' => 'low', 'to' => 12], ['key' => 'high', 'from' => 12], ['key' => 'all']]);
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation($aggregation), $this->context)->get('prices');
        static::assertInstanceOf(RangeResult::class, $result);
        static::assertSame(['low' => 2, 'high' => 2, 'all' => 4], $result->getRanges());
    }

    public function testJsonObjectAndDistinctObjectArrayPreserveShapesAndBindKeys(): void
    {
        $query = (new QueryBuilder($this->connection))->from('sw_json_product');
        $amount = $this->helper->getFieldAccessor('payload.amount', $this->definition, 'sw_json_product', $this->context, $query);
        $query->select(SqlHelper::object(['literal"\\' => $amount, 'document' => 'payload'], 'object.alias', $this->connection, $query));
        $query->where('id = :id')->setParameter('id', Uuid::fromHexToBytes(self::id(0)), ParameterType::BINARY);
        $json = json_decode($query->executeQuery()->fetchOne(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(10.0, (float) $json['literal"\\']);
        static::assertIsArray($json['document']);
        $query = (new QueryBuilder($this->connection))->from('sw_json_product');
        $query->select(SqlHelper::objectArray(['value' => 'config'], 'objects', $this->connection, $query));
        static::assertSame([['value' => ['_value' => ['amount' => '12.5']]]], json_decode($query->executeQuery()->fetchOne(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function ids(Filter $filter): array
    {
        $query = $this->builder->build(new QueryBuilder($this->connection), $this->definition, (new Criteria())->addFilter($filter), $this->context);
        $query->select((new DatabasePlatformResolver($this->connection))->dialect()->hex(['sw_json_product', 'id']))->orderBy('id');

        return $query->executeQuery()->fetchFirstColumn();
    }

    private function price(string $accessor): ?float
    {
        $query = (new QueryBuilder($this->connection))->from('sw_json_product');
        $query->select($this->helper->getFieldAccessor($accessor, $this->definition, 'sw_json_product', $this->context, $query));
        $query->where('id = :id')->setParameter('id', Uuid::fromHexToBytes(self::id(0)), ParameterType::BINARY);
        $value = $query->executeQuery()->fetchOne();

        return $value === null ? null : (float) $value;
    }

    private static function id(int $index): string
    {
        return str_pad(dechex($index + 1), 32, '0', \STR_PAD_LEFT);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class JsonCriteriaDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_json_product';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new JsonField('payload', 'payload', [new FloatField('amount', 'amount'), new BoolField('flag', 'flag')]),
            new ListField('items', 'items'),
            new ConfigJsonField('config', 'config', [new FloatField('amount', 'amount')]),
            new CustomFields(),
            new PriceField('price', 'price'),
            new CheapestPriceField('cheapest_price_accessor', 'cheapestPrice'),
        ]);
    }
}

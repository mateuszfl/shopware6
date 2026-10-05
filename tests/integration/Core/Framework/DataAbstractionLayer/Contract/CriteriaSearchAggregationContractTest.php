<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class CriteriaSearchAggregationContractTest extends TestCase
{
    private Connection $connection;

    private EntitySearcher $searcher;

    private EntityAggregator $aggregator;

    private CriteriaQueryBuilder $builder;

    private SearchContractDefinition $definition;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $endpoint = DatabaseEndpoint::observe($this->connection);
        static::assertFalse($endpoint->standby);
        static::assertStringStartsWith($endpoint->platform === 'postgresql' ? '18.6 ' : '8.4.', $endpoint->version);
        $pg = $endpoint->platform === 'postgresql';
        if (!$pg) {
            $this->connection->executeStatement('SET SESSION sql_mode = \'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY,NO_ENGINE_SUBSTITUTION\'');
            static::assertStringContainsString('ONLY_FULL_GROUP_BY', $this->connection->fetchOne('SELECT @@SESSION.sql_mode'));
        }
        if ($pg) {
            $this->connection->executeStatement('CREATE SCHEMA sw_search_collation');
            $this->connection->executeStatement('SET search_path TO sw_search_collation');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        }
        $text = $pg ? 'VARCHAR(255) COLLATE sw_type_ci' : 'VARCHAR(255) COLLATE utf8mb4_unicode_ci';
        $binary = $pg ? 'BYTEA' : 'BINARY(16)';
        $date = $pg ? 'TIMESTAMP(3) WITHOUT TIME ZONE' : 'DATETIME(3)';
        $json = $pg ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE sw_search_contract (id $binary PRIMARY KEY, label $text, category $text, amount DECIMAL(12, 3), created_at $date, items $json)");
        $this->connection->executeStatement("CREATE TABLE sw_search_child (id $binary PRIMARY KEY, product_id $binary, label $text, amount DECIMAL(12, 3))");
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        $container->set(DefaultFieldAccessorBuilder::class, new DefaultFieldAccessorBuilder($this->connection));
        $container->set(JsonFieldAccessorBuilder::class, new JsonFieldAccessorBuilder($this->connection));
        $container->set(OneToManyAssociationFieldResolver::class, new OneToManyAssociationFieldResolver($this->connection));
        foreach ([IdFieldSerializer::class, FloatFieldSerializer::class, DateTimeFieldSerializer::class, FkFieldSerializer::class, ListFieldSerializer::class] as $serializer) {
            $container->set($serializer, new $serializer(Validation::createValidator(), $registry));
        }
        $container->set(StringFieldSerializer::class, new StringFieldSerializer(Validation::createValidator(), $registry, new HtmlSanitizer()));
        $container->set(OneToManyAssociationFieldSerializer::class, static::createStub(OneToManyAssociationFieldSerializer::class));
        $this->definition = new SearchContractDefinition();
        $registry->register($this->definition);
        $registry->register(new SearchChildContractDefinition());
        $helper = new EntityDefinitionQueryHelper($this->connection);
        $parser = new SqlQueryParser($helper, $this->connection);
        $interpreter = new SearchTermInterpreter(new Tokenizer(new HtmlSanitizer(), 2), 2);
        $scoreBuilder = new EntityScoreQueryBuilder();
        $this->builder = new CriteriaQueryBuilder($parser, $helper, $interpreter, $scoreBuilder, new JoinGroupBuilder(), new CriteriaPartResolver($this->connection, $parser));
        $this->searcher = new EntitySearcher($this->connection, $helper, $this->builder);
        $this->aggregator = new EntityAggregator($this->connection, $helper, $registry, $this->builder, true, $interpreter, $scoreBuilder);
        $this->context = Context::createDefaultContext();
        foreach ([
            ['item2', 'alpha', 10, '2020-12-31 12:34:56'],
            ['item10', 'alpha', 20, '2021-01-01 02:05:00'],
            ['item2', 'beta', 5, '2021-01-04 00:00:00'],
            ['item10', 'beta', 20, '2021-03-28 00:30:00'],
            [null, null, null, '2021-03-28 01:30:00'],
            ['100%_\\', '', 0, null],
        ] as $i => [$label, $category, $amount, $dateValue]) {
            $this->connection->insert('sw_search_contract', ['id' => Uuid::fromHexToBytes(self::id($i)), 'label' => $label, 'category' => $category, 'amount' => $amount, 'created_at' => $dateValue, 'items' => json_encode([$i, (string) $i], \JSON_THROW_ON_ERROR)], ['id' => ParameterType::BINARY]);
        }
        foreach ([[0, 'red', 2], [0, 'blue', 3], [1, 'red', 4], [2, 'green', 5], [4, 'red', null], [5, 'blue', 7]] as $i => [$parent, $label, $amount]) {
            $this->connection->insert('sw_search_child', ['id' => Uuid::fromHexToBytes(self::id($i + 20)), 'product_id' => Uuid::fromHexToBytes(self::id($parent)), 'label' => $label, 'amount' => $amount], ['id' => ParameterType::BINARY, 'product_id' => ParameterType::BINARY]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_search_child');
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_search_contract');
            if ((new DatabasePlatformResolver($this->connection))->capabilities()->supportsJsonb) {
                $this->connection->executeStatement('DROP SCHEMA sw_search_collation CASCADE');
            }
            $this->connection->close();
        }
    }

    public function testNestedFiltersAndIndependentAssociationPredicatesRetainTheirLogic(): void
    {
        $criteria = (new Criteria())->addFilter(new OrFilter([
            new AndFilter([new EqualsFilter('children.label', 'red'), new RangeFilter('amount', [RangeFilter::GTE => 15])]),
            new AndFilter([new EqualsFilter('children.label', 'green'), new RangeFilter('amount', [RangeFilter::LT => 10])]),
        ]));
        static::assertSame([self::id(1), self::id(2)], $this->ids($criteria));
        static::assertSame([self::id(0)], $this->ids((new Criteria())->addFilter(new EqualsFilter('children.label', 'red'), new EqualsFilter('children.label', 'blue'))));
        static::assertSame([self::id(2), self::id(3), self::id(5)], $this->ids((new Criteria())->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('children.label', 'red')]))));
        static::assertSame([self::id(0), self::id(1), self::id(4)], $this->ids((new Criteria())->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('children.label', 'red')])]))));
        static::assertSame([self::id(3)], $this->ids((new Criteria())->addFilter(new EqualsFilter('children.id', null))));
    }

    public function testContainsListsNullAndRangeUseProductionSearcher(): void
    {
        static::assertSame([self::id(5)], $this->ids((new Criteria())->addFilter(new ContainsFilter('label', '100%_\\'))));
        static::assertSame([self::id(1)], $this->ids((new Criteria())->addFilter(new EqualsFilter('items', 1))));
        static::assertSame([self::id(1), self::id(2)], $this->ids((new Criteria())->addFilter(new EqualsAnyFilter('items', ['1', 2]))));
        static::assertSame([], $this->ids((new Criteria())->addFilter(new EqualsAnyFilter('id', []))));
        static::assertSame([self::id(0), self::id(1), self::id(2), self::id(3), self::id(5)], $this->ids((new Criteria())->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('amount', null)]))));
        static::assertSame([self::id(0), self::id(1), self::id(3)], $this->ids((new Criteria())->addFilter(new RangeFilter('amount', [RangeFilter::GT => 5, RangeFilter::LTE => 20]))));
    }

    public function testStablePaginationNullOrderingAndExactTotals(): void
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('amount'))->setLimit(2)->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        foreach ([[self::id(4), self::id(5)], [self::id(2), self::id(0)], [self::id(1), self::id(3)], []] as $page => $expected) {
            $criteria->setOffset($page * 2);
            $result = $this->searcher->search($this->definition, $criteria, $this->context);
            static::assertSame($expected, $result->getIds());
            static::assertSame(6, $result->getTotal());
        }
        static::assertSame([self::id(1), self::id(3), self::id(0), self::id(2), self::id(5), self::id(4)], $this->ids((new Criteria())->addSorting(new FieldSorting('amount', FieldSorting::DESCENDING))));
        $criteria = (new Criteria())->addSorting(new FieldSorting('children.label'))->setLimit(1)->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $result = $this->searcher->search($this->definition, $criteria, $this->context);
        static::assertSame([self::id(3)], $result->getIds());
        static::assertSame(6, $result->getTotal());
        static::assertSame([self::id(0), self::id(1), self::id(2)], $this->ids((new Criteria())->setLimit(3)->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES)));
        static::assertSame([self::id(2), self::id(0)], $this->ids(new Criteria([self::id(2), self::id(0)])));
    }

    public function testGroupedRepresentativesFollowTheSortingTupleAndPrimaryKey(): void
    {
        $criteria = (new Criteria())->addGroupField(new FieldGrouping('category'))->addSorting(new FieldSorting('amount', FieldSorting::DESCENDING))->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $result = $this->searcher->search($this->definition, $criteria, $this->context);
        static::assertSame([self::id(1), self::id(3), self::id(5), self::id(4)], $result->getIds());
        static::assertSame(4, $result->getTotal());
        static::assertSame([self::id(4), self::id(0), self::id(2), self::id(5)], $this->ids((new Criteria())->addGroupField(new FieldGrouping('category'))->addSorting(new FieldSorting('label', naturalSorting: true))));
        $criteria = (new Criteria())->addSorting(new CountSorting('children.id', FieldSorting::DESCENDING));
        static::assertSame([self::id(0), self::id(1), self::id(2), self::id(4), self::id(5), self::id(3)], $this->ids($criteria));
        static::assertSame([self::id(4), self::id(0), self::id(1), self::id(2), self::id(3), self::id(5)], $this->ids((new Criteria())->addSorting(new CountSorting('amount'))));
    }

    public function testScoringAssociatedFieldsAndGroupedSearchKeepAllQueriesAndFractionalScores(): void
    {
        $criteria = (new Criteria())->addQuery(
            new ScoreQuery(new EqualsFilter('children.label', 'red'), 1.5, 'children.amount'),
            new ScoreQuery(new EqualsFilter('children.label', 'blue'), 1.25, 'children.amount'),
        );
        $result = $this->searcher->search($this->definition, $criteria, $this->context);
        static::assertSame([self::id(5), self::id(0), self::id(1)], $result->getIds());
        static::assertEqualsWithDelta(6.75, $result->getScore(self::id(0)), 0.0001);
        $grouped = (clone $criteria)->addGroupField(new FieldGrouping('category'));
        static::assertSame([self::id(5), self::id(0)], $this->ids($grouped));
        static::assertSame([self::id(0), self::id(2)], $this->ids((new Criteria())->setTerm('item2')));
        $criteria->addAggregation(new TermsAggregation('groups', 'category'));
        $terms = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $terms);
        static::assertSame(['', 'alpha'], array_map(static fn ($bucket) => $bucket->getKey(), $terms->getBuckets()));
    }

    public function testNestedTermsLimitsSelectCompleteParentsAndEachChildLevel(): void
    {
        $nested = new TermsAggregation('labels', 'label', limit: 2, sorting: new FieldSorting('label'));
        $aggregation = new TermsAggregation('groups', 'category', limit: 1, sorting: new FieldSorting('_count', FieldSorting::DESCENDING), aggregation: $nested);
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation($aggregation), $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertCount(1, $result->getBuckets());
        $parent = $result->getBuckets()[0];
        static::assertSame('alpha', $parent->getKey());
        static::assertSame(2, $parent->getCount());
        static::assertInstanceOf(TermsResult::class, $parent->getResult());
        static::assertSame(['item10', 'item2'], array_map(static fn ($bucket) => $bucket->getKey(), $parent->getResult()->getBuckets()));
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation(new TermsAggregation('ids', 'id')), $this->context)->get('ids');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertSame(array_map(self::id(...), range(0, 5)), array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation(new TermsAggregation('groups', 'category')), $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertSame([null, '', 'alpha', 'beta'], array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
    }

    public function testBucketSortRanksPreserveTextTypesCollationUnicodeLengthAndLowercaseDirection(): void
    {
        foreach (['02', '2', 'café', 'CAFE', 'é', 'zz'] as $i => $label) {
            $this->connection->update('sw_search_contract', ['label' => $label], ['id' => Uuid::fromHexToBytes(self::id($i))], ['id' => ParameterType::BINARY]);
        }
        foreach ([
            ['asc', false, [0, 1, 2, 3, 4, 5]],
            ['desc', false, [5, 4, 2, 3, 1, 0]],
            ['asc', true, [1, 4, 0, 5, 2, 3]],
        ] as [$direction, $natural, $expected]) {
            $sorting = new FieldSorting('label', $direction, $natural);
            $criteria = (new Criteria())->addAggregation(new TermsAggregation('ids', 'id', sorting: $sorting));
            $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('ids');
            static::assertInstanceOf(TermsResult::class, $result);
            static::assertSame(array_map(self::id(...), $expected), array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
            static::assertSame(array_map(self::id(...), $expected), $this->ids((new Criteria())->addSorting($sorting)));
        }
        $keys = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation(new TermsAggregation('labels', 'label')), $this->context)->get('labels');
        static::assertInstanceOf(TermsResult::class, $keys);
        static::assertSame(['02', '2', 'CAFE', 'é', 'zz'], array_map(static fn ($bucket) => $bucket->getKey(), $keys->getBuckets()));
        $aggregation = new TermsAggregation('groups', 'category', sorting: new FieldSorting('label', 'desc'), aggregation: new TermsAggregation('ids', 'id', limit: 1, sorting: new FieldSorting('label', 'desc')));
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation($aggregation), $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertSame(['', null, 'beta', 'alpha'], array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
    }

    public function testNestedCollatedParentKeysMergeAcrossDifferentChildSpellings(): void
    {
        $this->connection->update('sw_search_contract', ['category' => 'café'], ['id' => Uuid::fromHexToBytes(self::id(0))], ['id' => ParameterType::BINARY]);
        $this->connection->update('sw_search_contract', ['category' => 'CAFE'], ['id' => Uuid::fromHexToBytes(self::id(1))], ['id' => ParameterType::BINARY]);
        $criteria = (new Criteria([self::id(0), self::id(1)]))->addAggregation(new TermsAggregation('groups', 'category', limit: 1, aggregation: new TermsAggregation('labels', 'label')));
        $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertCount(1, $result->getBuckets());
        $bucket = $result->getBuckets()[0];
        static::assertSame('CAFE', $bucket->getKey());
        static::assertSame(2, $bucket->getCount());
        static::assertInstanceOf(TermsResult::class, $bucket->getResult());
        static::assertSame(['item10', 'item2'], array_map(static fn ($child) => $child->getKey(), $bucket->getResult()->getBuckets()));
    }

    public function testBucketSortingRejectsAnInvalidDirection(): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::invalidSortingDirection('sideways'));
        $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation(new TermsAggregation('groups', 'category', sorting: new FieldSorting('label', 'sideways'))), $this->context);
    }

    public function testStatsRangeAndAssociatedAggregationKeepBoundValuesAndUnfilteredChildren(): void
    {
        $criteria = (new Criteria())->addAggregation(
            new StatsAggregation('stats', 'amount', true, true, true, true),
            new RangeAggregation('range', 'amount', [['key' => 'low', 'to' => 10.5], ['key' => 'high', 'from' => 10.5], ['key' => 'all']]),
        );
        $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context);
        $stats = $result->get('stats');
        static::assertInstanceOf(StatsResult::class, $stats);
        static::assertSame(0.0, (float) $stats->getMin());
        static::assertSame(20.0, (float) $stats->getMax());
        static::assertSame(11.0, $stats->getAvg());
        static::assertSame(55.0, $stats->getSum());
        $range = $result->get('range');
        static::assertInstanceOf(RangeResult::class, $range);
        static::assertSame(['low' => 3, 'high' => 2, 'all' => 6], $range->getRanges());
        $criteria = (new Criteria())->addFilter(new EqualsFilter('children.label', 'red'))->addAggregation(new TermsAggregation('children', 'children.label'));
        $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('children');
        static::assertInstanceOf(TermsResult::class, $result);
        static::assertSame(['blue', 'red'], array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
        $criteria = (new Criteria())->addFilter(new EqualsFilter('children.label', 'red'))->addAggregation(new TermsAggregation('groups', 'category', aggregation: new TermsAggregation('children', 'children.label')));
        $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('groups');
        static::assertInstanceOf(TermsResult::class, $result);
        $alpha = $result->getBuckets()[1]->getResult();
        static::assertInstanceOf(TermsResult::class, $alpha);
        static::assertSame(['blue', 'red'], array_map(static fn ($bucket) => $bucket->getKey(), $alpha->getBuckets()));
    }

    public function testHistogramUsesIsoWeeksAndKeepsNullSeparateFromCalendarDates(): void
    {
        $aggregation = new DateHistogramAggregation('weeks', 'createdAt', DateHistogramAggregation::PER_WEEK, aggregation: new TermsAggregation('groups', 'category'));
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation($aggregation), $this->context)->get('weeks');
        static::assertInstanceOf(DateHistogramResult::class, $result);
        static::assertSame([null, '2020 53', '2021 01', '2021 12'], array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
        static::assertSame([1, 2, 1, 2], array_map(static fn ($bucket) => $bucket->getCount(), $result->getBuckets()));
        $result = $this->aggregator->aggregate($this->definition, (new Criteria())->addAggregation(new DateHistogramAggregation('hours', 'createdAt', DateHistogramAggregation::PER_HOUR, timeZone: 'Europe/Warsaw')), $this->context)->get('hours');
        static::assertInstanceOf(DateHistogramResult::class, $result);
        static::assertSame([null, '2020-12-31 13:00:00', '2021-01-01 03:00:00', '2021-01-04 01:00:00', '2021-03-28 01:00:00', '2021-03-28 03:00:00'], array_map(static fn ($bucket) => $bucket->getKey(), $result->getBuckets()));
    }

    /**
     * @param DateHistogramAggregation::PER_* $interval
     */
    #[DataProvider('calendarIntervals')]
    public function testEveryHistogramIntervalHasCanonicalKeys(string $interval, string $expected): void
    {
        $criteria = (new Criteria([self::id(0)]))->addAggregation(new DateHistogramAggregation('dates', 'createdAt', $interval));
        $result = $this->aggregator->aggregate($this->definition, $criteria, $this->context)->get('dates');
        static::assertInstanceOf(DateHistogramResult::class, $result);
        static::assertSame($expected, $result->getBuckets()[0]->getKey());
    }

    /**
     * @return iterable<string, array{DateHistogramAggregation::PER_*, string}>
     */
    public static function calendarIntervals(): iterable
    {
        yield 'minute' => ['minute', '2020-12-31 12:34:00'];
        yield 'hour' => ['hour', '2020-12-31 12:00:00'];
        yield 'day' => ['day', '2020-12-31 00:00:00'];
        yield 'week' => ['week', '2020 53'];
        yield 'month' => ['month', '2020-12-01 00:00:00'];
        yield 'quarter' => ['quarter', '2020 4'];
        yield 'year' => ['year', '2020-01-01 00:00:00'];
    }

    /**
     * @return list<string>
     */
    private function ids(Criteria $criteria): array
    {
        return $this->searcher->search($this->definition, $criteria, $this->context)->getIds();
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
class SearchContractDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_search_contract';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new SearchRanking(500)),
            new StringField('category', 'category'),
            new FloatField('amount', 'amount'),
            new DateTimeField('created_at', 'createdAt'),
            new ListField('items', 'items'),
            new OneToManyAssociationField('children', SearchChildContractDefinition::class, 'product_id'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class SearchChildContractDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_search_child';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('product_id', 'productId', SearchContractDefinition::class),
            new StringField('label', 'label'),
            new FloatField('amount', 'amount'),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractScenario;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class EntityReaderContractTest extends TestCase
{
    private Connection $connection;

    private EntityReader $reader;

    private DefinitionInstanceRegistry $registry;

    private EntityDefinitionQueryHelper $helper;

    private CriteriaQueryBuilder $builder;

    private ReaderProductDefinition $product;

    private Context $context;

    private bool $ownsJsonCollationSchema = false;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $endpoint = DatabaseEndpoint::observe($this->connection);
        static::assertFalse($endpoint->standby);
        static::assertStringStartsWith($endpoint->platform === 'postgresql' ? '18.6 ' : '8.4.', $endpoint->version);
        fwrite(\STDERR, '[dal-reader] platform=' . $endpoint->platform . ' endpoint=' . $endpoint->serverId . "\n");
        $this->dropSchema();
        $this->createSchema();
        (new DatabaseFunctionInstaller())->install($this->connection);
        $this->seed();

        $container = new ContainerBuilder();
        $this->registry = new DefinitionInstanceRegistry($container, [], []);
        $this->helper = new EntityDefinitionQueryHelper($this->connection);
        $validator = Validation::createValidator();
        foreach ([IdFieldSerializer::class, FkFieldSerializer::class, BoolFieldSerializer::class, DateTimeFieldSerializer::class, FloatFieldSerializer::class, JsonFieldSerializer::class, PriceFieldSerializer::class] as $class) {
            $container->set($class, new $class($validator, $this->registry));
        }
        $container->set(StringFieldSerializer::class, new StringFieldSerializer($validator, $this->registry, new HtmlSanitizer()));
        $container->set(VersionFieldSerializer::class, new VersionFieldSerializer());
        $container->set(ReferenceVersionFieldSerializer::class, new ReferenceVersionFieldSerializer());
        foreach ([ManyToOneAssociationFieldSerializer::class, OneToManyAssociationFieldSerializer::class, ManyToManyAssociationFieldSerializer::class, OneToOneAssociationFieldSerializer::class, TranslationsAssociationFieldSerializer::class, TranslatedFieldSerializer::class] as $class) {
            $container->set($class, static::createStub($class));
        }
        foreach ([JsonFieldAccessorBuilder::class, PriceFieldAccessorBuilder::class] as $class) {
            $container->set($class, new $class($this->connection));
        }
        $customFields = static::createStub(CustomFieldService::class);
        $customFields->method('getCustomField')->willReturnCallback(static fn (string $name) => match ($name) {
            'score' => new FloatField($name, $name),
            'nested' => new JsonField($name, $name, [new FloatField('amount', 'amount')]),
            default => new JsonField($name, $name),
        });
        $container->set(CustomFieldsAccessorBuilder::class, new CustomFieldsAccessorBuilder($customFields, $this->connection));
        $container->set(CustomFieldsSerializer::class, new CustomFieldsSerializer($this->registry, $validator, $customFields));
        $container->set(DefaultFieldAccessorBuilder::class, new DefaultFieldAccessorBuilder($this->connection));
        $container->set(ManyToOneAssociationFieldResolver::class, new ManyToOneAssociationFieldResolver($this->helper, $this->connection));
        $container->set(OneToManyAssociationFieldResolver::class, new OneToManyAssociationFieldResolver($this->connection));
        $container->set(ManyToManyAssociationFieldResolver::class, new ManyToManyAssociationFieldResolver($this->connection));
        $container->set(TranslationFieldResolver::class, new TranslationFieldResolver($this->connection));
        foreach ([new ReaderProductDefinition(), new ReaderTranslationDefinition(), new ReaderTagDefinition(), new ReaderMappingDefinition(), new ReaderDetailDefinition(), new ReaderAutoDefinition(), new VersionDefinition(), new LanguageDefinition()] as $definition) {
            $this->registry->register($definition);
        }
        $product = $this->registry->get(ReaderProductDefinition::class);
        static::assertInstanceOf(ReaderProductDefinition::class, $product);
        $this->product = $product;
        $parser = new SqlQueryParser($this->helper, $this->connection);
        $builder = new CriteriaQueryBuilder($parser, $this->helper, new SearchTermInterpreter(new Tokenizer(new HtmlSanitizer(), 2), 2), new EntityScoreQueryBuilder(), new JoinGroupBuilder(), new CriteriaPartResolver($this->connection, $parser));
        $this->builder = $builder;
        $hydrator = new EntityHydrator($container);
        $container->set(EntityHydrator::class, $hydrator);
        $this->reader = new EntityReader($this->connection, $hydrator, $this->helper, $parser, $builder, new NullLogger(), new CriteriaFieldsResolver());
        $this->context = new Context(new SystemSource(), languageIdChain: [DatabaseContractScenario::ENGLISH_LANGUAGE]);
        $this->context->setConsiderInheritance(true);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->dropSchema();
            if ($this->ownsJsonCollationSchema) {
                $this->connection->executeStatement('DROP SCHEMA sw_dal_json_collation CASCADE');
            }
            $this->connection->close();
        }
    }

    public function testNestedJsonFiltersUseParentAndLanguageFallbackWithBoundPaths(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('CREATE SCHEMA sw_dal_json_collation');
            $this->ownsJsonCollationSchema = true;
            $this->connection->executeStatement('SET search_path TO sw_dal_json_collation, public');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        }
        foreach (['payload' => 'payload', 'custom_fields' => 'customFields'] as $column => $property) {
            $this->connection->executeStatement('UPDATE sw_dal_product SET ' . $column . ' = :payload WHERE id = :id', ['payload' => '{"score":10}', 'id' => Uuid::fromHexToBytes(self::id(0))], ['id' => ParameterType::BINARY]);
            $criteria = (new Criteria([self::id(1)]))->addFilter(new EqualsFilter($property . '.score', '10'));
            static::assertSame([self::id(1)], array_values($this->reader->read($this->product, $criteria, $this->context)->getIds()));
            $this->connection->executeStatement('UPDATE sw_dal_product SET ' . $column . ' = :payload WHERE id = :id', ['payload' => '{"score":null}', 'id' => Uuid::fromHexToBytes(self::id(1))], ['id' => ParameterType::BINARY]);
            static::assertSame([self::id(1)], array_values($this->reader->read($this->product, $criteria, $this->context)->getIds()));
        }
        $this->connection->executeStatement('UPDATE sw_dal_product_translation SET attributes = :payload WHERE sw_dal_product_id = :id AND language_id = :language', ['payload' => '{"nested":{"amount":12.5}}', 'id' => Uuid::fromHexToBytes(self::id(0)), 'language' => Uuid::fromHexToBytes(DatabaseContractScenario::ENGLISH_LANGUAGE)], ['id' => ParameterType::BINARY, 'language' => ParameterType::BINARY]);
        $criteria = (new Criteria([self::id(1)]))->addFilter(new RangeFilter('attributes.nested.amount', [RangeFilter::GT => 12]));
        static::assertSame([self::id(1)], array_values($this->reader->read($this->product, $criteria, $this->context)->getIds()));
        $this->context = new Context(new SystemSource(), languageIdChain: [DatabaseContractScenario::GERMAN_LANGUAGE, DatabaseContractScenario::ENGLISH_LANGUAGE]);
        $this->context->setConsiderInheritance(true);
        static::assertSame([self::id(1)], array_values($this->reader->read($this->product, $criteria, $this->context)->getIds()));
    }

    public function testDeprecatedMetadataHelpersUseLiteralSchemaNamesOnBothPlatforms(): void
    {
        Feature::silent('v6.8.0.0', function (): void {
            static::assertTrue(EntityDefinitionQueryHelper::tableExists($this->connection, 'sw_dal_product'));
            static::assertFalse(EntityDefinitionQueryHelper::tableExists($this->connection, 'swXdalXproduct'));
            static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'sw_dal_product', 'version_id'));
            static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'sw_dal_product', 'versionXid'));
            static::assertTrue(EntityDefinitionQueryHelper::columnIsNullable($this->connection, 'sw_dal_product', 'parent_id'));
            static::assertFalse(EntityDefinitionQueryHelper::columnIsNullable($this->connection, 'sw_dal_product', 'id'));
        });
    }

    public function testHydrationAndUnrestrictedManyToManyPreserveIdsTypesAndInputOrder(): void
    {
        $criteria = new Criteria([self::id(1), self::id(0)]);
        $criteria->addAssociation('tags');
        $entities = $this->reader->read($this->product, $criteria, $this->context);
        static::assertSame([self::id(1), self::id(0)], array_values($entities->getIds()));
        $parent = self::entity($entities, self::id(0));
        static::assertSame(Defaults::LIVE_VERSION, $parent->get('versionId'));
        static::assertTrue($parent->get('active'));
        static::assertInstanceOf(\DateTimeInterface::class, $parent->get('createdAt'));
        static::assertSame('2024-02-29 23:59:59.000', $parent->get('createdAt')->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $expectedPayload = ['position' => 0, 'unicode' => 'Zażółć gęślą jaźń 商品 0', 'nullable' => null, 'enabled' => true];
        $actualPayload = $parent->get('payload');
        ksort($expectedPayload);
        ksort($actualPayload);
        static::assertSame($expectedPayload, $actualPayload);
        static::assertInstanceOf(PriceCollection::class, $parent->get('prices'));
        $price = $parent->get('prices')->first();
        static::assertNotNull($price);
        static::assertSame(19.99, $price->getGross());
        $expected = self::tagIds();
        sort($expected);
        static::assertSame($expected, array_values(self::related($parent, 'tags')->getIds()));
    }

    public function testOneToOneAndNestedToOneUseTheCorrectVersionAndComplexAliases(): void
    {
        $criteria = new Criteria([self::id(0)]);
        $criteria->addAssociation('detail.product.detail');
        $parent = $this->reader->read($this->product, $criteria, $this->context)->first();
        static::assertNotNull($parent);
        static::assertSame(self::id(0), self::toOne($parent, 'detail')->get('productId'));
        static::assertSame('Live detail', self::toOne($parent, 'detail')->get('label'));
        static::assertSame(self::id(0), self::toOne(self::toOne($parent, 'detail'), 'product')->getUniqueIdentifier());
    }

    public function testParentAndLanguageFallbackRetainRawAndResolvedValues(): void
    {
        $this->context = new Context(new SystemSource(), languageIdChain: [DatabaseContractScenario::GERMAN_LANGUAGE, DatabaseContractScenario::ENGLISH_LANGUAGE], considerInheritance: true);
        $entities = $this->reader->read($this->product, new Criteria([self::id(0), self::id(1), self::id(2)]), $this->context);
        static::assertSame('Eltern', self::entity($entities, self::id(0))->get('name'));
        static::assertNull(self::entity($entities, self::id(1))->get('name'));
        static::assertSame('Eltern', self::entity($entities, self::id(1))->getTranslation('name'));
        static::assertSame('Deutsch 2', self::entity($entities, self::id(2))->get('name'));
        static::assertSame(self::entity($entities, self::id(0))->get('payload'), self::entity($entities, self::id(1))->get('payload'));
    }

    public function testDraftOverridesLiveAndMissingDraftFallsBackToLive(): void
    {
        $context = $this->context->createWithVersionId(DatabaseContractScenario::DRAFT_VERSION);
        $criteria = new Criteria([self::id(0), self::id(1)]);
        $criteria->addAssociation('detail');
        $entities = $this->reader->read($this->product, $criteria, $context);
        static::assertSame([self::id(0), self::id(1)], array_values($entities->getIds()));
        static::assertSame(DatabaseContractScenario::DRAFT_VERSION, self::entity($entities, self::id(0))->get('versionId'));
        static::assertSame('Draft product', self::entity($entities, self::id(0))->get('name'));
        static::assertSame('Draft detail', self::toOne(self::entity($entities, self::id(0)), 'detail')->get('label'));
        static::assertSame(Defaults::LIVE_VERSION, self::entity($entities, self::id(1))->get('versionId'));
    }

    public function testOneToManyLimitAndOffsetAreStablePerParentWithTiedSortValues(): void
    {
        $criteria = new Criteria([self::id(0), self::id(4)]);
        $criteria->getAssociation('children')->setLimit(1)->setOffset(1)->addSorting(new FieldSorting('active'));
        $entities = $this->reader->read($this->product, $criteria, $this->context);
        foreach ([0 => [1, 2, 3], 4 => [5, 6, 7]] as $parent => $children) {
            $expected = array_map(self::id(...), $children);
            sort($expected);
            static::assertSame([$expected[1]], array_values(self::related(self::entity($entities, self::id($parent)), 'children')->getIds()));
        }
    }

    public function testManyToManyLimitAndOffsetApplyAfterFilterAndSortingPerParent(): void
    {
        $criteria = new Criteria([self::id(0), self::id(4)]);
        $criteria->getAssociation('tags')->setLimit(1)->setOffset(1)->addSorting(new FieldSorting('name', FieldSorting::DESCENDING));
        $entities = $this->reader->read($this->product, $criteria, $this->context);
        foreach ($entities as $entity) {
            static::assertSame([self::tagIds()[1]], array_values(self::related($entity, 'tags')->getIds()));
        }
        $criteria = new Criteria([self::id(0), self::id(4)]);
        $criteria->getAssociation('tags')->setLimit(1)->addSorting(new FieldSorting('name', FieldSorting::DESCENDING))->addFilter(new EqualsFilter('name', 'new'));
        foreach ($this->reader->read($this->product, $criteria, $this->context) as $entity) {
            static::assertSame([self::tagIds()[0]], array_values(self::related($entity, 'tags')->getIds()));
        }
    }

    public function testUnboundedOneToManyAndOffsetOnlyReturnStableRemainingChildren(): void
    {
        foreach ([0, 1] as $offset) {
            $criteria = new Criteria([self::id(0), self::id(4)]);
            $criteria->getAssociation('children')->setOffset($offset);
            $entities = $this->reader->read($this->product, $criteria, $this->context);
            foreach ([0 => [1, 2, 3], 4 => [5, 6, 7]] as $parent => $children) {
                $expected = array_map(self::id(...), $children);
                sort($expected);
                static::assertSame(\array_slice($expected, $offset), array_values(self::related(self::entity($entities, self::id($parent)), 'children')->getIds()));
            }
        }
    }

    public function testManyToManyOffsetWithoutLimitUsesUuidTieBreaker(): void
    {
        $this->connection->executeStatement('UPDATE sw_dal_tag SET name = \'same\'');
        $criteria = new Criteria([self::id(0), self::id(4)]);
        $criteria->getAssociation('tags')->setOffset(1)->addSorting(new FieldSorting('name'));
        $expected = self::tagIds();
        sort($expected);
        foreach ($this->reader->read($this->product, $criteria, $this->context) as $entity) {
            static::assertSame(\array_slice($expected, 1), array_values(self::related($entity, 'tags')->getIds()));
        }
    }

    public function testInheritedManyToManyUsesTheParentMappingForBothReadStrategies(): void
    {
        foreach ([false, true] as $restricted) {
            $criteria = new Criteria([self::id(1)]);
            $criteria->addAssociation('tags');
            if ($restricted) {
                $criteria->getAssociation('tags')->setLimit(1)->addSorting(new FieldSorting('name'));
            }
            $child = $this->reader->read($this->product, $criteria, $this->context)->first();
            static::assertNotNull($child);
            static::assertCount($restricted ? 1 : 3, self::related($child, 'tags'));
            $this->context->setConsiderInheritance(false);
            $child = $this->reader->read($this->product, $criteria, $this->context)->first();
            static::assertNotNull($child);
            static::assertCount(0, self::related($child, 'tags'));
            $this->context->setConsiderInheritance(true);
        }
    }

    public function testDraftAndLiveRelationsUseTheSelectedChildAndOwnerVersions(): void
    {
        $product = DatabaseContractScenario::data()['products'][2];
        $product['versionId'] = DatabaseContractScenario::DRAFT_VERSION;
        $this->insertProduct($product);
        $this->connection->update('sw_dal_product', ['parent_id' => Uuid::fromHexToBytes(self::id(4)), 'parent_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['id' => Uuid::fromHexToBytes(self::id(2)), 'version_id' => Uuid::fromHexToBytes(DatabaseContractScenario::DRAFT_VERSION)], array_fill_keys(['parent_id', 'parent_version_id', 'id', 'version_id'], ParameterType::BINARY));
        $this->insertTranslation(self::id(2), DatabaseContractScenario::DRAFT_VERSION, DatabaseContractScenario::ENGLISH_LANGUAGE, 'Moved draft child');
        $this->connection->insert('sw_dal_mapping', ['sw_dal_product_id' => Uuid::fromHexToBytes(self::id(0)), 'sw_dal_product_version_id' => Uuid::fromHexToBytes(DatabaseContractScenario::DRAFT_VERSION), 'sw_dal_tag_id' => Uuid::fromHexToBytes(self::tagIds()[1])], array_fill_keys(['sw_dal_product_id', 'sw_dal_product_version_id', 'sw_dal_tag_id'], ParameterType::BINARY));
        $context = $this->context->createWithVersionId(DatabaseContractScenario::DRAFT_VERSION);
        foreach ([false, true] as $paginated) {
            $criteria = new Criteria([self::id(0), self::id(4)]);
            $criteria->addAssociation('children')->addAssociation('tags');
            if ($paginated) {
                $criteria->getAssociation('children')->setLimit(10);
                $criteria->getAssociation('tags')->setLimit(10);
            }
            $entities = $this->reader->read($this->product, $criteria, $context);
            $expectedChildren = [self::id(1), self::id(3)];
            sort($expectedChildren);
            static::assertSame($expectedChildren, array_values(self::related(self::entity($entities, self::id(0)), 'children')->getIds()));
            static::assertSame([self::tagIds()[1]], array_values(self::related(self::entity($entities, self::id(0)), 'tags')->getIds()));
            static::assertCount(4, self::related(self::entity($entities, self::id(4)), 'children'));
            static::assertCount(3, self::related(self::entity($entities, self::id(4)), 'tags'));
        }
    }

    public function testInverseManyToManyCollectsLogicalIdsAcrossMappingVersionsForAllCriteriaShapes(): void
    {
        $tagId = self::tagIds()[1];
        $this->connection->delete('sw_dal_mapping', ['sw_dal_product_id' => Uuid::fromHexToBytes(self::id(0)), 'sw_dal_product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'sw_dal_tag_id' => Uuid::fromHexToBytes($tagId)], array_fill_keys(['sw_dal_product_id', 'sw_dal_product_version_id', 'sw_dal_tag_id'], ParameterType::BINARY));
        $this->connection->insert('sw_dal_mapping', ['sw_dal_product_id' => Uuid::fromHexToBytes(self::id(0)), 'sw_dal_product_version_id' => Uuid::fromHexToBytes(DatabaseContractScenario::DRAFT_VERSION), 'sw_dal_tag_id' => Uuid::fromHexToBytes($tagId)], array_fill_keys(['sw_dal_product_id', 'sw_dal_product_version_id', 'sw_dal_tag_id'], ParameterType::BINARY));
        $tag = $this->registry->get(ReaderTagDefinition::class);
        foreach ([Defaults::LIVE_VERSION, DatabaseContractScenario::DRAFT_VERSION] as $version) {
            $context = $this->context->createWithVersionId($version);
            foreach (['unrestricted', 'sorted', 'limited', 'offset', 'filtered', 'scored'] as $shape) {
                $criteria = (new Criteria([$tagId]))->addAssociation('products');
                $products = $criteria->getAssociation('products');
                if ($shape !== 'unrestricted') {
                    $products->addSorting(new FieldSorting('id'));
                }
                if ($shape === 'limited') {
                    $products->setLimit(1);
                } elseif ($shape === 'offset') {
                    $products->setOffset(1)->setLimit(1);
                } elseif ($shape === 'filtered') {
                    $products->addFilter(new EqualsFilter('id', self::id(0)));
                } elseif ($shape === 'scored') {
                    $products->addQuery(new ScoreQuery(new EqualsFilter('id', self::id(0)), 1.5))->setLimit(1);
                }
                $entity = $this->reader->read($tag, $criteria, $context)->first();
                static::assertNotNull($entity);
                $related = self::related($entity, 'products');
                $expected = match ($shape) {
                    'filtered', 'scored' => [self::id(0)],
                    'limited' => [self::id(4)],
                    'offset' => [self::id(0)],
                    default => [self::id(4), self::id(0)],
                };
                static::assertSame($expected, array_values($related->getIds()), $version . ':' . $shape);
                foreach ($related as $product) {
                    static::assertSame($product->getUniqueIdentifier() === self::id(0) ? $version : Defaults::LIVE_VERSION, $product->get('versionId'));
                }
            }
        }
    }

    public function testSearcherGroupsVersionAwareRowsAndPreservesCompositeMappingKeys(): void
    {
        $searcher = new EntitySearcher($this->connection, $this->helper, $this->builder);
        $criteria = (new Criteria())->addSorting(new FieldSorting('tags.name'))->setLimit(1)->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $result = $searcher->search($this->product, $criteria, $this->context->createWithVersionId(DatabaseContractScenario::DRAFT_VERSION));
        static::assertCount(1, $result->getIds());
        static::assertSame(8, $result->getTotal());
        $mapping = $this->registry->get(ReaderMappingDefinition::class);
        $result = $searcher->search($mapping, new Criteria(), $this->context);
        static::assertCount(6, $result->getIds());
        $tags = self::tagIds();
        sort($tags);
        static::assertNotEmpty($tags);
        static::assertSame(['productId' => self::id(4), 'tagId' => $tags[0]], $result->getDataOfId(self::id(4) . '-' . $tags[0]));
    }

    public function testToManyFilterAndSortingGroupTheCompleteRootKey(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('tags.name', 'new'));
        $criteria->addSorting(new FieldSorting('tags.name'));
        $expected = array_map(self::id(...), range(0, 7));
        sort($expected);
        static::assertSame($expected, array_values($this->reader->read($this->product, $criteria, $this->context)->getIds()));
    }

    public function testIteratorsReturnEveryLiveIdOnceInStableOrder(): void
    {
        $iterator = (new IteratorFactory($this->connection, $this->registry))->createIterator($this->product, limit: 3, versionId: Defaults::LIVE_VERSION);
        static::assertSame(8, $iterator->fetchCount());
        $actual = [];
        while ($batch = $iterator->fetch()) {
            array_push($actual, ...array_values($batch));
        }
        $expected = array_map(self::id(...), range(0, 7));
        sort($expected);
        static::assertSame($expected, $actual);
    }

    public function testIteratorWithoutVersionFilterKeepsBothPhysicalVersions(): void
    {
        $iterator = (new IteratorFactory($this->connection, $this->registry))->createIterator($this->product, limit: 2);
        static::assertSame(9, $iterator->fetchCount());
        $actual = [];
        while ($batch = $iterator->fetch()) {
            array_push($actual, ...array_values($batch));
        }
        $expected = [...array_map(self::id(...), range(0, 7)), self::id(0)];
        sort($expected);
        static::assertSame($expected, $actual);
        static::assertSame(9, $iterator->fetchCount());
    }

    public function testAutoIncrementIteratorResumesItsCursorAndStaysExhausted(): void
    {
        $factory = new IteratorFactory($this->connection, $this->registry);
        $iterator = $factory->createIterator('sw_dal_auto', limit: 3);
        static::assertSame(8, $iterator->fetchCount());
        static::assertSame(array_map(self::id(...), [0, 1, 2]), array_values($iterator->fetch()));
        $iterator = $factory->createIterator('sw_dal_auto', lastId: $iterator->getOffset(), limit: 3);
        static::assertSame(array_map(self::id(...), [3, 4, 5]), array_values($iterator->fetch()));
        static::assertSame(array_map(self::id(...), [6, 7]), array_values($iterator->fetch()));
        static::assertSame([], $iterator->fetch());
        static::assertSame([], $iterator->fetch());
    }

    public function testForeignKeyResolverFindsCascadingChildrenWithoutWriting(): void
    {
        $affected = (new EntityForeignKeyResolver($this->connection, $this->helper))->getAffectedDeletes($this->product, [self::id(0)], $this->context);
        $expected = array_map(self::id(...), [1, 2, 3]);
        sort($expected);
        $children = $affected[ReaderProductDefinition::ENTITY_NAME];
        sort($children);
        static::assertSame($expected, $children);
    }

    public function testAssociationLimitsGroupCountSortingsAndDeduplicateNestedOneToManyRows(): void
    {
        foreach ([new CountSorting('tags.id'), new FieldSorting('tags.name')] as $sorting) {
            $criteria = new Criteria([self::id(0), self::id(4)]);
            $criteria->getAssociation('children')->setLimit(1)->setOffset(1)->addSorting($sorting);
            $entities = $this->reader->read($this->product, $criteria, $this->context);
            foreach ([0 => [1, 2, 3], 4 => [5, 6, 7]] as $parent => $children) {
                $expected = array_map(self::id(...), $children);
                sort($expected);
                static::assertSame([$expected[1]], array_values(self::related(self::entity($entities, self::id($parent)), 'children')->getIds()));
            }
        }
        $criteria = new Criteria([self::id(0), self::id(4)]);
        $criteria->getAssociation('tags')->setLimit(1)->setOffset(1)->addSorting(new CountSorting('products.id'));
        $expected = self::tagIds();
        sort($expected);
        foreach ($this->reader->read($this->product, $criteria, $this->context) as $entity) {
            static::assertSame([$expected[1]], array_values(self::related($entity, 'tags')->getIds()));
        }
    }

    public function testManyToManyDeduplicatesRelationsBeforePaginationWithNestedToManySorting(): void
    {
        $this->connection->executeStatement('UPDATE sw_dal_product SET created_at = :created WHERE id = :id', ['created' => '2024-03-01 00:00:00.000', 'id' => Uuid::fromHexToBytes(self::id(4))], ['id' => ParameterType::BINARY]);
        foreach ([FieldSorting::ASCENDING, FieldSorting::DESCENDING] as $direction) {
            $criteria = new Criteria([self::id(0), self::id(4)]);
            $criteria->getAssociation('tags')->setLimit(1)->setOffset(1)->addSorting(new FieldSorting('products.createdAt', $direction));
            $expected = self::tagIds();
            sort($expected);
            foreach ($this->reader->read($this->product, $criteria, $this->context) as $entity) {
                static::assertSame([$expected[1]], array_values(self::related($entity, 'tags')->getIds()));
            }
        }
    }

    public function testManyToManyScoreSortingPreservesTheScoreExpressionBeforePagination(): void
    {
        $criteria = new Criteria([self::id(0), self::id(4)]);
        $criteria->getAssociation('tags')->setLimit(1)->setOffset(1)
            ->addQuery(new ScoreQuery(new EqualsFilter('name', 'sale'), 100))
            ->addQuery(new ScoreQuery(new EqualsFilter('name', 'new'), 80));
        foreach ($this->reader->read($this->product, $criteria, $this->context) as $entity) {
            static::assertSame([self::tagIds()[0]], array_values(self::related($entity, 'tags')->getIds()));
        }
    }

    /**
     * @param EntityCollection<Entity> $entities
     */
    private static function entity(EntityCollection $entities, string $id): Entity
    {
        $entity = $entities->get($id);
        static::assertNotNull($entity);

        return $entity;
    }

    /**
     * @return EntityCollection<Entity>
     */
    private static function related(Entity $entity, string $field): EntityCollection
    {
        $related = $entity->get($field);
        static::assertInstanceOf(EntityCollection::class, $related);

        return $related;
    }

    private static function toOne(Entity $entity, string $field): Entity
    {
        $related = $entity->get($field);
        static::assertInstanceOf(Entity::class, $related);

        return $related;
    }

    private static function id(int $index): string
    {
        return md5('shopware-database-contract-product-' . $index);
    }

    /**
     * @return list<string>
     */
    private static function tagIds(): array
    {
        return array_map(static fn (string $name): string => md5('shopware-database-contract-tag-' . $name), ['new', 'sale', 'unicode']);
    }

    private function createSchema(): void
    {
        $schema = new Schema();
        $table = $schema->createTable(ReaderProductDefinition::ENTITY_NAME);
        foreach (['id', 'version_id', 'parent_id', 'parent_version_id'] as $column) {
            $table->addColumn($column, Types::BINARY, ['length' => 16, 'notnull' => !str_starts_with($column, 'parent')]);
        }
        $table->addColumn('tags', Types::BINARY, ['length' => 16, 'notnull' => false]);
        $table->addColumn('payload', Types::JSONB, ['notnull' => false]);
        $table->addColumn('custom_fields', Types::JSONB, ['notnull' => false]);
        $table->addColumn('prices', Types::JSONB);
        $table->addColumn('active', Types::BOOLEAN);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['columnDefinition' => $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL' : 'DATETIME(3) NOT NULL']);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setColumnNames(UnqualifiedName::unquoted('id'), UnqualifiedName::unquoted('version_id'))->create());
        $table = $schema->createTable('sw_dal_product_translation');
        foreach (['sw_dal_product_id', 'sw_dal_product_version_id', 'language_id'] as $column) {
            $table->addColumn($column, Types::BINARY, ['length' => 16]);
        }
        $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('attributes', Types::JSONB, ['notnull' => false]);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setColumnNames(UnqualifiedName::unquoted('sw_dal_product_id'), UnqualifiedName::unquoted('sw_dal_product_version_id'), UnqualifiedName::unquoted('language_id'))->create());
        $table = $schema->createTable('sw_dal_tag');
        $table->addColumn('id', Types::BINARY, ['length' => 16]);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setColumnNames(UnqualifiedName::unquoted('id'))->create());
        $table = $schema->createTable('sw_dal_mapping');
        foreach (['sw_dal_product_id', 'sw_dal_product_version_id', 'sw_dal_tag_id'] as $column) {
            $table->addColumn($column, Types::BINARY, ['length' => 16]);
        }
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setColumnNames(UnqualifiedName::unquoted('sw_dal_product_id'), UnqualifiedName::unquoted('sw_dal_product_version_id'), UnqualifiedName::unquoted('sw_dal_tag_id'))->create());
        $table = $schema->createTable('sw_dal_detail');
        foreach (['id', 'version_id', 'sw_dal_product_id', 'sw_dal_product_version_id'] as $column) {
            $table->addColumn($column, Types::BINARY, ['length' => 16]);
        }
        $table->addColumn('label', Types::STRING, ['length' => 255]);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setColumnNames(UnqualifiedName::unquoted('id'), UnqualifiedName::unquoted('version_id'))->create());
        $table = $schema->createTable('sw_dal_auto');
        $table->addColumn('id', Types::BINARY, ['length' => 16]);
        $table->addColumn('auto_increment', Types::INTEGER);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setColumnNames(UnqualifiedName::unquoted('id'))->create());
        $table->addUniqueIndex(['auto_increment']);
        $this->connection->createSchemaManager()->createSchemaObjects($schema);
    }

    private function seed(): void
    {
        $data = DatabaseContractScenario::data();
        foreach ($data['products'] as $product) {
            $this->insertProduct($product);
        }
        foreach ([6, 7] as $index) {
            $product = $data['products'][5];
            $product['id'] = self::id($index);
            $this->insertProduct($product);
        }
        foreach (range(0, 7) as $index) {
            $this->connection->insert('sw_dal_auto', ['id' => Uuid::fromHexToBytes(self::id($index)), 'auto_increment' => $index + 1], ['id' => ParameterType::BINARY, 'auto_increment' => ParameterType::INTEGER]);
            $parent = match ($index) {
                1, 2, 3 => 0, 5, 6, 7 => 4, default => null,
            };
            $this->connection->update('sw_dal_product', ['parent_id' => $parent === null ? null : Uuid::fromHexToBytes(self::id($parent)), 'parent_version_id' => $parent === null ? null : Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'tags' => Uuid::fromHexToBytes(self::id($parent ?? $index)), 'active' => true], ['id' => Uuid::fromHexToBytes(self::id($index)), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], ['parent_id' => ParameterType::BINARY, 'parent_version_id' => ParameterType::BINARY, 'tags' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN, 'id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY]);
        }
        $this->connection->update('sw_dal_product', ['payload' => null], ['id' => Uuid::fromHexToBytes(self::id(1))], ['id' => ParameterType::BINARY]);
        foreach ($data['translations'] as $translation) {
            $this->insertTranslation($translation['productId'], $translation['productVersionId'], $translation['languageId'], $translation['name']);
        }
        foreach ([6, 7] as $index) {
            $this->insertTranslation(self::id($index), Defaults::LIVE_VERSION, DatabaseContractScenario::ENGLISH_LANGUAGE, 'Product ' . $index);
        }
        $this->insertTranslation(self::id(0), Defaults::LIVE_VERSION, DatabaseContractScenario::GERMAN_LANGUAGE, 'Eltern');
        $this->insertTranslation(self::id(2), Defaults::LIVE_VERSION, DatabaseContractScenario::GERMAN_LANGUAGE, 'Deutsch 2');
        foreach ($data['tags'] as $tag) {
            $this->connection->insert('sw_dal_tag', ['id' => Uuid::fromHexToBytes($tag['id']), 'name' => $tag['name']], ['id' => ParameterType::BINARY]);
            foreach ([0, 4] as $index) {
                $this->connection->insert('sw_dal_mapping', ['sw_dal_product_id' => Uuid::fromHexToBytes(self::id($index)), 'sw_dal_product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'sw_dal_tag_id' => Uuid::fromHexToBytes($tag['id'])], array_fill_keys(['sw_dal_product_id', 'sw_dal_product_version_id', 'sw_dal_tag_id'], ParameterType::BINARY));
            }
        }
        foreach ([Defaults::LIVE_VERSION => 'Live detail', DatabaseContractScenario::DRAFT_VERSION => 'Draft detail'] as $version => $label) {
            $this->connection->insert('sw_dal_detail', ['id' => Uuid::fromHexToBytes(md5('detail')), 'version_id' => Uuid::fromHexToBytes($version), 'sw_dal_product_id' => Uuid::fromHexToBytes(self::id(0)), 'sw_dal_product_version_id' => Uuid::fromHexToBytes($version), 'label' => $label], array_fill_keys(['id', 'version_id', 'sw_dal_product_id', 'sw_dal_product_version_id'], ParameterType::BINARY));
        }
    }

    /**
     * @param array<string, mixed> $product
     */
    private function insertProduct(array $product): void
    {
        $price = $product['prices'][0];
        $this->connection->insert('sw_dal_product', ['id' => Uuid::fromHexToBytes($product['id']), 'version_id' => Uuid::fromHexToBytes($product['versionId']), 'parent_id' => null, 'parent_version_id' => null, 'tags' => Uuid::fromHexToBytes($product['id']), 'payload' => json_encode($product['payload'], \JSON_THROW_ON_ERROR), 'prices' => json_encode([['currencyId' => Defaults::CURRENCY, 'gross' => $price['gross'], 'net' => $price['net'], 'linked' => true]], \JSON_THROW_ON_ERROR), 'active' => $product['active'], 'created_at' => $product['createdAt']], ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY, 'tags' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN]);
    }

    private function insertTranslation(string $id, string $version, string $language, string $name): void
    {
        $this->connection->insert('sw_dal_product_translation', ['sw_dal_product_id' => Uuid::fromHexToBytes($id), 'sw_dal_product_version_id' => Uuid::fromHexToBytes($version), 'language_id' => Uuid::fromHexToBytes($language), 'name' => $name], array_fill_keys(['sw_dal_product_id', 'sw_dal_product_version_id', 'language_id'], ParameterType::BINARY));
    }

    private function dropSchema(): void
    {
        foreach (['sw_dal_auto', 'sw_dal_detail', 'sw_dal_mapping', 'sw_dal_tag', 'sw_dal_product_translation', 'sw_dal_product'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . EntityDefinitionQueryHelper::escape($table, $this->connection));
        }
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ReaderProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sw_dal_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isInheritanceAware(): bool
    {
        return true;
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(), new ParentFkField(self::class), new ReferenceVersionField(self::class, 'parent_version_id'), new ParentAssociationField(self::class),
            (new JsonField('payload', 'payload'))->addFlags(new Inherited()), (new CustomFields())->addFlags(new Inherited()), new PriceField('prices', 'prices'), new BoolField('active', 'active'), new DateTimeField('created_at', 'createdAt'),
            (new TranslatedField('attributes'))->addFlags(new Inherited()),
            (new TranslatedField('name'))->addFlags(new Inherited()), new TranslationsAssociationField(ReaderTranslationDefinition::class, 'sw_dal_product_id'), new ChildrenAssociationField(self::class),
            (new ManyToManyAssociationField('tags', ReaderTagDefinition::class, ReaderMappingDefinition::class, 'sw_dal_product_id', 'sw_dal_tag_id'))->addFlags(new CascadeDelete(), new Inherited()),
            new OneToOneAssociationField('detail', 'id', 'sw_dal_product_id', ReaderDetailDefinition::class, false),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ReaderTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'sw_dal_product_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return ReaderProductDefinition::class;
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([new StringField('name', 'name'), new CustomFields('attributes', 'attributes')]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ReaderTagDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_dal_tag';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey()), new StringField('name', 'name'), new ManyToManyAssociationField('products', ReaderProductDefinition::class, ReaderMappingDefinition::class, 'sw_dal_tag_id', 'sw_dal_product_id')]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ReaderMappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_dal_mapping';
    }

    public function isVersionAware(): bool
    {
        return true;
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new FkField('sw_dal_product_id', 'productId', ReaderProductDefinition::class))->addFlags(new PrimaryKey()), (new ReferenceVersionField(ReaderProductDefinition::class))->addFlags(new PrimaryKey()), (new FkField('sw_dal_tag_id', 'tagId', ReaderTagDefinition::class))->addFlags(new PrimaryKey())]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ReaderDetailDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_dal_detail';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey()), new VersionField(), new FkField('sw_dal_product_id', 'productId', ReaderProductDefinition::class), new ReferenceVersionField(ReaderProductDefinition::class), new StringField('label', 'label'), new ManyToOneAssociationField('product', 'sw_dal_product_id', ReaderProductDefinition::class, 'id', false)]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class ReaderAutoDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sw_dal_auto';
    }

    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey()), new AutoIncrementField()]);
    }
}

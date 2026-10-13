<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Contract;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Api\CurrencyIsoCodeFkResolver;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\Xml\CustomFields;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageValidator;
use Shopware\Core\System\Locale\Api\LocaleCodeFkResolver;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\Validation\SalesChannelValidator;
use Shopware\Core\System\Salutation\Api\SalutationKeyFkResolver;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\System\Snippet\SnippetCollection;
use Shopware\Core\System\Snippet\SnippetService;
use Shopware\Core\System\Snippet\Subscriber\CustomFieldSubscriber;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineTransitionValidator;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Shopware\Core\System\Tax\Api\TaxRateFkResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\SystemSqlFixture;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\UsageRelatedDefinition;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class CatalogSystemSqlContractTest extends TestCase
{
    private SystemSqlFixture $fixture;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->fixture = new SystemSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testSyncForeignKeyResolversReadBinaryIdentities(): void
    {
        $this->fixture->insert('currency', ['id' => SystemSqlContractTest::CHANNEL, 'iso_code' => 'EUR', 'factor' => '1']);
        $this->fixture->insert('locale', ['id' => SystemSqlContractTest::CHANNEL, 'code' => 'en-GB']);
        $this->fixture->insert('salutation', ['id' => SystemSqlContractTest::CHANNEL, 'salutation_key' => 'mr']);
        $this->fixture->insert('tax', ['id' => SystemSqlContractTest::CHANNEL, 'tax_rate' => 19]);
        foreach ([[new CurrencyIsoCodeFkResolver($this->connection), 'EUR'], [new LocaleCodeFkResolver($this->connection), 'en-GB'], [new SalutationKeyFkResolver($this->connection), 'mr'], [new TaxRateFkResolver($this->connection), 19]] as [$resolver, $value]) {
            $reference = new FkReference('/0', 'contract', 'id', $value, false);
            static::assertSame(SystemSqlContractTest::CHANNEL, $resolver->resolve([$reference])[0]->resolved);
        }
        $this->fixture->insert('tax', ['id' => SystemSqlContractTest::CUSTOMER, 'tax_rate' => 19]);
        static::assertNull((new TaxRateFkResolver($this->connection))->resolve([new FkReference('/0', 'tax', 'taxRate', 19, false)])[0]->resolved);
    }

    public function testSnippetSubscriberWritesUpdatesAndDeletesOnlyItsCustomField(): void
    {
        $this->fixture->insert('snippet_set', ['id' => SystemSqlContractTest::CHANNEL, 'iso' => 'en-GB']);
        $subscriber = new CustomFieldSubscriber($this->connection, new MockClock('2026-10-12'));
        $context = Context::createDefaultContext();
        $result = new EntityWriteResult(SystemSqlContractTest::CUSTOMER, ['name' => 'contract', 'config' => ['label' => ['en-GB' => 'First']]], 'custom_field', EntityWriteResult::OPERATION_INSERT);
        $subscriber->customFieldIsWritten(new EntityWrittenEvent('custom_field', [$result], $context));
        static::assertSame('First', $this->connection->fetchOne('SELECT value FROM snippet'));
        $result = new EntityWriteResult(SystemSqlContractTest::CUSTOMER, ['name' => 'contract', 'config' => ['label' => ['en-GB' => 'Updated']]], 'custom_field', EntityWriteResult::OPERATION_INSERT);
        $subscriber->customFieldIsWritten(new EntityWrittenEvent('custom_field', [$result], $context));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM snippet'));
        static::assertSame('Updated', $this->connection->fetchOne('SELECT value FROM snippet'));
        $this->fixture->insert('snippet', ['id' => SystemSqlContractTest::RANGE, 'snippet_set_id' => SystemSqlContractTest::CHANNEL, 'translation_key' => 'unrelated', 'value' => 'keep', 'custom_fields' => '{"custom_field_id":"unrelated"}']);
        $subscriber->customFieldIsDeleted(new EntityDeletedEvent('custom_field', [new EntityWriteResult(SystemSqlContractTest::CUSTOMER, [], 'custom_field', EntityWriteResult::OPERATION_DELETE)], $context));
        static::assertSame(['keep'], $this->connection->fetchFirstColumn('SELECT value FROM snippet'));
    }

    public function testSnippetSetLookupUsesDomainThenLocaleAndEnglishFallback(): void
    {
        $this->fixture->insert('snippet_set', ['id' => SystemSqlContractTest::CHANNEL, 'iso' => 'en-GB']);
        $this->fixture->insert('snippet_set', ['id' => SystemSqlContractTest::CUSTOMER, 'iso' => 'de-DE']);
        $this->fixture->insert('sales_channel_domain', ['sales_channel_id' => SystemSqlContractTest::CHANNEL, 'language_id' => SystemSqlContractTest::RANGE, 'snippet_set_id' => SystemSqlContractTest::CUSTOMER]);
        $dispatcher = new EventDispatcher();
        $service = new SnippetService($this->connection, new SnippetFileCollection(), StaticEntityRepository::of(SnippetCollection::class), StaticEntityRepository::of(SnippetSetCollection::class), new SnippetFilterFactory([]), new ExtensionDispatcher($dispatcher), $dispatcher, static::createStub(FilesystemOperator::class), new Filesystem());
        static::assertSame(SystemSqlContractTest::CUSTOMER, $service->findSnippetSetId(SystemSqlContractTest::CHANNEL, SystemSqlContractTest::RANGE, 'en-GB'));
        static::assertSame(SystemSqlContractTest::CUSTOMER, $service->findSnippetSetId(SystemSqlContractTest::CHANNEL, SystemSqlContractTest::CUSTOMER, 'de-DE'));
        static::assertSame(SystemSqlContractTest::CHANNEL, $service->findSnippetSetId(SystemSqlContractTest::CHANNEL, SystemSqlContractTest::CUSTOMER, 'fr-FR'));
        $this->fixture->insert('snippet', ['id' => SystemSqlContractTest::RANGE, 'snippet_set_id' => SystemSqlContractTest::CUSTOMER, 'translation_key' => 'contract.title', 'value' => 'Stored']);
        static::assertSame(['contract.title' => 'Stored'], $service->getStorefrontSnippets(new MessageCatalogue('de-DE'), SystemSqlContractTest::CUSTOMER));
    }

    public function testAppSystemConfigFacadeLoadsPrivilegesWithBinaryParameters(): void
    {
        $this->fixture->insert('acl_role', ['id' => SystemSqlContractTest::RANGE, 'privileges' => '["system_config:read"]']);
        $this->fixture->insert('app', ['id' => SystemSqlContractTest::CUSTOMER, 'name' => 'contract', 'active' => 1, 'acl_role_id' => SystemSqlContractTest::RANGE]);
        $service = static::createStub(SystemConfigService::class);
        $service->method('get')->willReturn('configured');
        $facade = new SystemConfigFacade($service, $this->connection, new ScriptAppInformation(SystemSqlContractTest::CUSTOMER, 'contract', '1', SystemSqlContractTest::RANGE), SystemSqlContractTest::CHANNEL);
        static::assertSame('configured', $facade->get('core.contract'));
        static::assertSame('configured', $facade->get('core.contract'));
    }

    public function testStateLoaderAndTransitionUpdateResolveStoredBinaryReferences(): void
    {
        $this->fixture->insert('state_machine', ['id' => SystemSqlContractTest::CHANNEL, 'technical_name' => 'order.state', 'initial_state_id' => SystemSqlContractTest::CUSTOMER]);
        $loader = new InitialStateIdLoader($this->connection, new ArrayAdapter());
        static::assertSame(SystemSqlContractTest::CUSTOMER, $loader->get('order.state'));
        $loader->reset();
        static::assertSame(SystemSqlContractTest::CUSTOMER, $loader->get('order.state'));
        $this->fixture->insert('state_machine_transition', ['id' => SystemSqlContractTest::CHANNEL, 'state_machine_id' => SystemSqlContractTest::CUSTOMER, 'from_state_id' => SystemSqlContractTest::RANGE, 'to_state_id' => SystemSqlContractTest::CHANNEL, 'action_name' => 'first']);
        $this->fixture->insert('state_machine_transition', ['id' => SystemSqlContractTest::CUSTOMER, 'state_machine_id' => SystemSqlContractTest::CUSTOMER, 'from_state_id' => SystemSqlContractTest::RANGE, 'to_state_id' => SystemSqlContractTest::CHANNEL, 'action_name' => 'second']);
        $registry = new StaticDefinitionInstanceRegistry([new StateMachineDefinition(), new StateMachineStateDefinition(), new StateMachineTransitionDefinition()], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [new UpdateCommand($registry->get(StateMachineTransitionDefinition::class), ['action_name' => 'first'], ['id' => Uuid::fromHexToBytes(SystemSqlContractTest::CUSTOMER)], static::createStub(EntityExistence::class), '/0/')]);
        Feature::silent('v6.8.0.0', fn () => (new StateMachineTransitionValidator($this->connection))->preValidate($event));
        static::assertCount(Feature::isActive('v6.8.0.0') ? 1 : 0, $event->getExceptions()->getExceptions());
    }

    public function testLanguageValidationReadsRootAndNestedLanguageViolations(): void
    {
        $this->fixture->insert('language', ['id' => SystemSqlContractTest::CUSTOMER, 'parent_id' => null, 'translation_code_id' => null]);
        $definition = new LanguageDefinition();
        new StaticDefinitionInstanceRegistry([$definition], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $command = new UpdateCommand($definition, ['parent_id' => null], ['id' => Uuid::fromHexToBytes(SystemSqlContractTest::CUSTOMER)], static::createStub(EntityExistence::class), '/0/');
        $event = new PostWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        (new LanguageValidator($this->connection))->postValidate($event);
        static::assertCount(1, $event->getExceptions()->getExceptions());
    }

    public function testSalesChannelValidatorLoadsItsStoredDefaultLanguageAndCurrency(): void
    {
        $this->fixture->insert('sales_channel', ['id' => SystemSqlContractTest::CHANNEL, 'language_id' => SystemSqlContractTest::CUSTOMER, 'currency_id' => SystemSqlContractTest::RANGE]);
        $this->fixture->insert('sales_channel_language', ['sales_channel_id' => SystemSqlContractTest::CHANNEL, 'language_id' => SystemSqlContractTest::CUSTOMER]);
        $this->fixture->insert('sales_channel_currency', ['sales_channel_id' => SystemSqlContractTest::CHANNEL, 'currency_id' => SystemSqlContractTest::RANGE]);
        $definition = new SalesChannelDefinition();
        new StaticDefinitionInstanceRegistry([$definition], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $command = new UpdateCommand($definition, ['language_id' => Uuid::fromHexToBytes(SystemSqlContractTest::RANGE)], ['id' => Uuid::fromHexToBytes(SystemSqlContractTest::CHANNEL)], static::createStub(EntityExistence::class), '/0/');
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        (new SalesChannelValidator($this->connection))->handleSalesChannelLanguageIds($event);
        static::assertCount(1, $event->getExceptions()->getExceptions());
    }

    public function testStoreApiEncodingFiltersBlockedGlobalAndEntityCustomFields(): void
    {
        $this->connection->insert('custom_field', ['name' => 'global_blocked', 'store_api_aware' => 0]);
        $this->fixture->insert('custom_field', ['id' => SystemSqlContractTest::RANGE, 'name' => 'entity_blocked', 'store_api_aware' => 0, 'set_id' => SystemSqlContractTest::CUSTOMER]);
        $this->fixture->insert('custom_field_set_relation', ['set_id' => SystemSqlContractTest::CUSTOMER, 'entity_name' => 'product']);
        $definition = new SystemApiDefinition();
        new StaticDefinitionInstanceRegistry([$definition], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $registry = static::createStub(DefinitionRegistryChain::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);
        $normalizer = static::createStub(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturn(['customFields' => ['global_blocked' => 'hidden', 'entity_blocked' => 'hidden', 'visible' => 'shown']]);
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));
        $encoder = new StructEncoder($registry, $normalizer, $this->connection);
        static::assertSame(['visible' => 'shown'], $encoder->encode($product, new ResponseFields())['customFields']);
        $encoder->reset();
        static::assertSame(['visible' => 'shown'], $encoder->encode($product, new ResponseFields())['customFields']);
    }

    public function testCustomFieldSetPersistenceReadsAppAndPluginOwnedBinaryIdentities(): void
    {
        $setRepository = StaticEntityRepository::of(CustomFieldSetCollection::class);
        $relations = StaticEntityRepository::of(CustomFieldSetRelationCollection::class);
        $fields = StaticEntityRepository::of(CustomFieldCollection::class);
        $persister = new CustomFieldSetPersister($setRepository, $this->connection, $relations, $fields);
        $this->fixture->insert('custom_field_set', ['id' => SystemSqlContractTest::RANGE, 'name' => 'owned', 'app_id' => SystemSqlContractTest::CHANNEL, 'extension_name' => 'App']);
        $this->fixture->insert('custom_field_set', ['id' => SystemSqlContractTest::CUSTOMER, 'name' => 'plugin', 'extension_name' => 'Plugin']);
        $persister->sync(CustomFields::fromArray([]), SystemSqlContractTest::CHANNEL, 'App', Context::createDefaultContext());
        static::assertSame([[['id' => SystemSqlContractTest::RANGE]]], $setRepository->deletes);
        $persister->sync(CustomFields::fromArray([]), null, 'Plugin', Context::createDefaultContext());
        static::assertCount(2, $setRepository->deletes);
        static::assertArrayHasKey(1, $setRepository->deletes);
        static::assertSame([['id' => SystemSqlContractTest::CUSTOMER]], $setRepository->deletes[1]);
    }

    public function testTagDuplicateEmptyAndAssignmentFiltersExecuteTheirPublicQueries(): void
    {
        $definition = new SystemTagDefinition();
        new StaticDefinitionInstanceRegistry([$definition, new SystemTagMappingDefinition(), new UsageRelatedDefinition()], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $this->fixture->insert('tag', ['id' => SystemSqlContractTest::CHANNEL, 'name' => 'duplicate']);
        $this->fixture->insert('tag', ['id' => SystemSqlContractTest::CUSTOMER, 'name' => 'duplicate']);
        $this->fixture->insert('tag', ['id' => SystemSqlContractTest::RANGE, 'name' => 'unique']);
        $this->fixture->insert('contract_tag_mapping', ['tag_id' => SystemSqlContractTest::CUSTOMER, 'usage_related_id' => SystemSqlContractTest::RANGE]);
        $builder = static::createStub(CriteriaQueryBuilder::class);
        $builder->method('build')->willReturnCallback(static fn (QueryBuilder $query) => $query->from('tag', 'tag'));
        $service = new FilterTagIdsService($definition, $this->connection, $builder);
        static::assertEqualsCanonicalizing([SystemSqlContractTest::CHANNEL, SystemSqlContractTest::CUSTOMER], array_values($service->filterIds(new Request(['duplicateFilter' => true]), new Criteria(), Context::createDefaultContext())->getIds()));
        static::assertEqualsCanonicalizing([SystemSqlContractTest::CHANNEL, SystemSqlContractTest::RANGE], array_values($service->filterIds(new Request(['emptyFilter' => true]), new Criteria(), Context::createDefaultContext())->getIds()));
        static::assertSame([SystemSqlContractTest::CUSTOMER], $service->filterIds(new Request(['assignmentFilter' => ['related']]), new Criteria(), Context::createDefaultContext())->getIds());
    }
}

/**
 * @internal
 */
#[Package('framework')]
class SystemTagDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'tag';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\ManyToManyAssociationField('related', UsageRelatedDefinition::class, SystemTagMappingDefinition::class, 'tag_id', 'usage_related_id'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class SystemTagMappingDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'contract_tag_mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\FkField('tag_id', 'tagId', SystemTagDefinition::class))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            (new Field\FkField('usage_related_id', 'usageRelatedId', UsageRelatedDefinition::class))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
        ]);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class SystemApiDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\JsonField('custom_fields', 'customFields'))->addFlags(new Field\Flag\ApiAware())]);
    }
}

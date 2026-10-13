<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\System\Consent\ConsentDefinitionRegistry;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope\System;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Log\ConsentChangedSubscriber;
use Shopware\Core\System\Consent\Log\DatabaseLog;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\Consent\Subscriber\SetupStagingEventSubscriber;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessageHandler;
use Shopware\Core\System\UsageData\EntitySync\EntityDispatcher;
use Shopware\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\ManyToManyAssociationService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\SystemSqlFixture;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\UsageRelatedDefinition;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentUsageSqlContractTest extends TestCase
{
    private SystemSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->fixture = new SystemSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-12 12:13:14.123 UTC');
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testConsentAcceptRevokeAndStagingResetKeepStateAndAudit(): void
    {
        $this->fixture->insert('user', ['id' => SystemSqlContractTest::CUSTOMER, 'username' => 'contract-user']);
        $repository = new ConsentRepository($this->connection, $this->clock);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ConsentChangedSubscriber(new DatabaseLog($this->connection, $this->clock)));
        $service = new ConsentService([new System()], new ConsentDefinitionRegistry([new BackendData()]), $repository, $dispatcher);
        $source = new AdminApiSource(SystemSqlContractTest::CUSTOMER);
        $source->setIsAdmin(true);
        $context = new Context($source);
        static::assertSame(ConsentStatus::DECLINED, $service->revokeConsent(BackendData::NAME, $context)->status);
        static::assertSame(ConsentStatus::DECLINED, $service->revokeConsent(BackendData::NAME, $context)->status);
        static::assertSame(ConsentStatus::ACCEPTED, $service->acceptConsent(BackendData::NAME, $context)->status);
        static::assertSame(ConsentStatus::REVOKED, $service->revokeConsent(BackendData::NAME, $context)->status);
        static::assertSame('contract-user', $service->getConsentState(BackendData::NAME, $context)->actor);
        static::assertCount(1, $repository->fetchAllConsentStates());
        static::assertGreaterThan(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM consent_log'));
        (new SetupStagingEventSubscriber($this->connection))->removeAllConsents(new SetupStagingEvent($context, new SymfonyStyle(new ArrayInput([]), new BufferedOutput())));
        static::assertSame([], $repository->fetchAllConsentStates());
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM consent_log'));
    }

    public function testConsentRevisionIsClearedOnDeclineAndRetainedOnAccept(): void
    {
        $this->fixture->insert('user', ['id' => SystemSqlContractTest::CUSTOMER, 'username' => 'contract-user']);
        $repository = new ConsentRepository($this->connection, $this->clock);
        $repository->updateConsentState(new BackendData(), 'system', ConsentStatus::ACCEPTED, SystemSqlContractTest::CUSTOMER, 'v2');
        static::assertSame('v2', $repository->fetchAllConsentStates()[0]->revision);
        $repository->updateConsentState(new BackendData(), 'system', ConsentStatus::REVOKED, SystemSqlContractTest::CUSTOMER, 'ignored');
        static::assertNull($repository->fetchAllConsentStates()[0]->revision);
        static::assertSame(ConsentStatus::REVOKED, $repository->fetchAllConsentStates()[0]->status);
    }

    public function testUsageIterationDispatchAndMappingSerializeBinaryIdsAndLiveVersions(): void
    {
        $definition = new UsageItemDefinition();
        new StaticDefinitionInstanceRegistry([$definition, new UsageMappingDefinition(), new UsageRelatedDefinition()], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $allow = static::createStub(UsageDataAllowListService::class);
        $allow->method('isEntityAllowed')->willReturn(true);
        $allow->method('getFieldsToSelectFromDefinition')->willReturn($definition->getFields()->filter(static fn (Field\Field $field) => !$field instanceof Field\VersionField));
        $service = new EntityDefinitionService([$definition], $allow);
        $binary = "\x00\xff\\'contract";
        foreach ([Defaults::LIVE_VERSION, SystemSqlContractTest::CHANNEL] as $version) {
            $this->connection->insert('usage_item', ['id' => Uuid::fromHexToBytes(SystemSqlContractTest::CUSTOMER), 'version_id' => Uuid::fromHexToBytes($version), 'name' => 'current', 'body' => $binary, 'created_at' => '2026-10-10 12:00:00', 'updated_at' => '2026-10-11 12:00:00'], ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY, 'body' => ParameterType::BINARY]);
            $this->fixture->insert('usage_mapping', ['usage_item_id' => SystemSqlContractTest::CUSTOMER, 'usage_item_version_id' => $version, 'usage_related_id' => $version === Defaults::LIVE_VERSION ? SystemSqlContractTest::RANGE : SystemSqlContractTest::CHANNEL]);
        }
        $mapping = new ManyToManyAssociationService($this->connection);
        $field = $definition->getField('related');
        static::assertInstanceOf(Field\ManyToManyAssociationField::class, $field);
        $ids = $mapping->getMappingIdsForAssociationFields([$field], [['id' => SystemSqlContractTest::CUSTOMER]], 'id');
        static::assertSame([SystemSqlContractTest::RANGE], $ids['related'][Uuid::fromHexToBytes(SystemSqlContractTest::CUSTOMER)]);
        $builder = new IterateEntitiesQueryBuilder($service, $this->connection, 10);
        $current = new \DateTimeImmutable('2026-10-12');
        static::assertSame([['id' => SystemSqlContractTest::CUSTOMER]], $builder->create('usage_item', Operation::CREATE, $current, null)->executeQuery()->fetchAllAssociative());
        static::assertSame([['id' => SystemSqlContractTest::CUSTOMER]], $builder->create('usage_item', Operation::UPDATE, $current, new \DateTimeImmutable('2026-10-10 23:00:00'))->executeQuery()->fetchAllAssociative());
        $message = new DispatchEntityMessage('usage_item', Operation::UPDATE, $current, [['id' => SystemSqlContractTest::CUSTOMER]], 'shop');
        $raw = (new DispatchEntitiesQueryBuilder($this->connection))->forEntity('usage_item')->withFields($definition->getFields())->withPrimaryKeys($message->primaryKeys)->checkLiveVersion($definition)->withCollectUntilConstraint($message, $current)->execute()->fetchAllAssociative();
        static::assertCount(1, $raw);
        static::assertSame(SystemSqlContractTest::CUSTOMER, Uuid::fromBytesToHex($raw[0]['id']));
        $consent = static::createStub(ConsentService::class);
        $consent->method('getConsentState')->willReturn(new ConsentState(BackendData::NAME, 'system', 'system', ConsentStatus::ACCEPTED, 'actor', '2026-10-12'));
        $shop = static::createStub(ShopIdProvider::class);
        $shop->method('getShopId')->willReturn('shop');
        $dispatcher = $this->createMock(EntityDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->willReturnCallback(static function (string $name, array $entities) use ($binary): void {
            static::assertSame('usage_item', $name);
            static::assertCount(1, $entities);
            static::assertSame(SystemSqlContractTest::CUSTOMER, $entities[0]['id']);
            static::assertSame(base64_encode($binary), $entities[0]['body']);
            static::assertSame([SystemSqlContractTest::RANGE], $entities[0]['related']);
        });
        (new DispatchEntityMessageHandler($service, $mapping, $allow, $this->connection, $dispatcher, $consent, $shop))($message);
    }

    public function testUsageDeletionDispatchRemovesOnlyItsSelectedRecords(): void
    {
        $definition = new UsageItemDefinition();
        new StaticDefinitionInstanceRegistry([$definition, new UsageMappingDefinition(), new UsageRelatedDefinition()], Validation::createValidator(), static::createStub(EntityWriteGatewayInterface::class));
        $allow = static::createStub(UsageDataAllowListService::class);
        $allow->method('isEntityAllowed')->willReturn(true);
        $service = new EntityDefinitionService([$definition], $allow);
        $this->fixture->insert('usage_data_entity_deletion', ['id' => SystemSqlContractTest::CUSTOMER, 'entity_name' => 'usage_item', 'entity_ids' => '{"id":"' . SystemSqlContractTest::RANGE . '"}', 'deleted_at' => '2026-10-11']);
        $this->fixture->insert('usage_data_entity_deletion', ['id' => SystemSqlContractTest::CHANNEL, 'entity_name' => 'usage_item', 'entity_ids' => '{}', 'deleted_at' => '2026-10-13']);
        $current = new \DateTimeImmutable('2026-10-12');
        $rows = (new IterateEntitiesQueryBuilder($service, $this->connection, 10))->create('usage_item', Operation::DELETE, $current, new \DateTimeImmutable('2026-10-10'))->executeQuery()->fetchAllAssociative();
        static::assertSame([['id' => SystemSqlContractTest::CUSTOMER]], $rows);
        $consent = static::createStub(ConsentService::class);
        $consent->method('getConsentState')->willReturn(new ConsentState(BackendData::NAME, 'system', 'system', ConsentStatus::ACCEPTED, 'actor', '2026-10-12'));
        $shop = static::createStub(ShopIdProvider::class);
        $shop->method('getShopId')->willReturn('shop');
        $dispatcher = $this->createMock(EntityDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->willReturnCallback(static function (string $name, array $entities): void {
            static::assertSame('usage_item', $name);
            static::assertSame([['id' => SystemSqlContractTest::RANGE]], $entities);
        });
        (new DispatchEntityMessageHandler($service, new ManyToManyAssociationService($this->connection), $allow, $this->connection, $dispatcher, $consent, $shop))(new DispatchEntityMessage('usage_item', Operation::DELETE, $current, $rows, 'shop'));
        static::assertSame([SystemSqlContractTest::CHANNEL], $this->connection->fetchFirstColumn('SELECT sw_uuid_hex(id) FROM usage_data_entity_deletion'));
    }
}

/**
 * @internal
 */
#[Package('data-services')]
class UsageItemDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'usage_item';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            new Field\VersionField(),
            new Field\StringField('name', 'name'),
            new Field\BlobField('body', 'body'),
            new Field\ManyToManyAssociationField('related', UsageRelatedDefinition::class, UsageMappingDefinition::class, 'usage_item_id', 'usage_related_id'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('data-services')]
class UsageMappingDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'usage_mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new Field\FkField('usage_item_id', 'usageItemId', UsageItemDefinition::class))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            (new Field\ReferenceVersionField(UsageItemDefinition::class, 'usage_item_version_id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
            (new Field\FkField('usage_related_id', 'usageRelatedId', UsageRelatedDefinition::class))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required()),
        ]);
    }
}

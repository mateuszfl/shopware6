<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppSecretResolver;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\FlowEventLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Integration\Core\Framework\App\Feature\StubFeatureConfig;
use Shopware\Tests\Integration\Core\Framework\App\Feature\StubFeatureDefinition;
use Shopware\Tests\Integration\Core\Framework\Contract\_fixtures\FrameworkSqlFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class AppPluginSqlContractTest extends TestCase
{
    private const APP = '0000000000000000000000000000ff01';
    private const ROLE = '0000000000000000000000000000ff02';
    private const WEBHOOK = '0000000000000000000000000000ff03';

    private FrameworkSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->fixture = new FrameworkSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-18 12:00:00 UTC');
        $this->fixture->insert('app', ['id' => self::APP, 'name' => 'contract-app', 'active' => 1, 'version' => '1.2.3', 'app_secret' => 'secret', 'acl_role_id' => self::ROLE, 'requested_privileges' => '["product:read"]']);
        $this->fixture->insert('acl_role', ['id' => self::ROLE, 'privileges' => '[]']);
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testFeatureSyncSaveRollbackKeepAndReattachLifecycle(): void
    {
        $storage = new AppFeatureStorage($this->connection, $this->clock, new AppFeatureDefinitionRegistry([new StubFeatureDefinition()]));
        $storage->syncForApp(self::APP, 'contract-app', [
            ['type' => 'stub_feature', 'name' => 'keep', 'payload' => ['name' => 'keep', 'value' => 'first']],
            ['type' => 'stub_feature', 'name' => 'remove', 'payload' => ['name' => 'remove', 'value' => 'stale']],
        ]);
        static::assertCount(2, $storage->forActiveApps(StubFeatureConfig::class));
        $before = $this->connection->fetchAssociative('SELECT sw_uuid_hex(id) AS id, created_at FROM app_feature WHERE name = :name', ['name' => 'keep']);
        $this->clock->sleep(60);
        $storage->syncForApp(self::APP, 'contract-app', [['type' => 'stub_feature', 'name' => 'keep', 'payload' => ['name' => 'keep', 'value' => 'updated']]]);
        static::assertSame($before, $this->connection->fetchAssociative('SELECT sw_uuid_hex(id) AS id, created_at FROM app_feature'));
        $storage->save(self::APP, new StubFeatureConfig('keep', 'saved'));
        static::assertSame('saved', $storage->forApp(self::APP, StubFeatureConfig::class)[0]->config->value);
        $this->connection->beginTransaction();
        $storage->deleteForApp(self::APP);
        $this->connection->rollBack();
        static::assertCount(1, $storage->forActiveApps(StubFeatureConfig::class));
        $this->connection->delete('app', ['id' => Uuid::fromHexToBytes(self::APP)], ['id' => ParameterType::BINARY]);
        static::assertSame([], $storage->forActiveApps(StubFeatureConfig::class));
        $newId = Uuid::randomHex();
        $this->fixture->insert('app', ['id' => $newId, 'name' => 'contract-app', 'active' => 1, 'version' => '2.0']);
        $storage->reattachKeptFeatures($newId, 'contract-app');
        static::assertSame('saved', $storage->forApp($newId, StubFeatureConfig::class)[0]->config->value);
        $storage->deleteForApp($newId);
        static::assertSame([], $storage->forActiveApps(StubFeatureConfig::class));
    }

    public function testAppSecretsPrivilegesAndWebhookMetadata(): void
    {
        static::assertSame('secret', (new AppSecretResolver($this->connection))->resolve('contract-app'));
        $privileges = new Privileges($this->connection, new EventDispatcher());
        $privileges->acceptAllForApps([self::APP], Context::createDefaultContext());
        static::assertSame([self::APP => ['product:read']], $privileges->getPrivileges([self::APP]));
        $lifecycle = new PermissionLifecycleService($this->connection, $privileges, $this->clock);
        $lifecycle->softDeleteRole(self::ROLE);
        static::assertNotNull($this->connection->fetchOne('SELECT deleted_at FROM acl_role'));
        $this->fixture->insert('webhook', ['id' => self::WEBHOOK, 'app_id' => self::APP, 'name' => 'hook', 'event_name' => 'order.written', 'url' => 'https://example.invalid', 'only_live_version' => 1, 'active' => 1]);
        $webhooks = (new WebhookLoader($this->connection))->getWebhooks();
        static::assertCount(1, $webhooks);
        static::assertSame(self::WEBHOOK, $webhooks[0]->id);
        static::assertSame('secret', (new WebhookSigningSecretResolver($this->connection, new DeletedAppsGateway($this->connection)))->resolve(new WebhookEventMessage(self::WEBHOOK, [], self::APP, self::WEBHOOK, '6.7', 'https://example.invalid', null, Defaults::LANGUAGE_SYSTEM, 'en-GB', appName: 'contract-app')));
        $deleted = new DeletedAppsGateway($this->connection);
        $deleted->insertSecretsForDeletedApp('contract-app', 'old', ['unconfirmed']);
        $deleted->insertSecretsForDeletedApp('contract-app', 'new', ['latest']);
        static::assertSame('new', $deleted->getDeletedAppSecret('contract-app'));
        static::assertSame(['latest'], $deleted->getDeletedAppUnconfirmedSecrets('contract-app'));
        $deleted->deleteSecretForApp('contract-app');
        static::assertNull($deleted->getDeletedAppSecret('contract-app'));
    }

    public function testDeactivatingAppOnlyDisablesItsOwnFlowEvents(): void
    {
        $this->fixture->insert('app_flow_event', ['id' => self::WEBHOOK, 'app_id' => self::APP, 'name' => 'contract.event']);
        $this->fixture->insert('flow', ['id' => self::WEBHOOK, 'event_name' => 'contract.event', 'active' => 1]);
        $this->fixture->insert('flow', ['id' => self::ROLE, 'event_name' => 'other.event', 'active' => 1]);
        $app = new AppEntity();
        $app->setId(self::APP);
        $handler = new FlowEventLifecycleHandler(StaticEntityRepository::of(AppFlowEventCollection::class), $this->connection);
        $handler->deactivate(new AppActivationContext($app, Context::createDefaultContext()));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT active FROM flow WHERE event_name = :name', ['name' => 'contract.event']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT active FROM flow WHERE event_name = :name', ['name' => 'other.event']));
    }

    public function testDatabaseTemplatesUseSnakeCaseBoundaryAndReset(): void
    {
        $this->fixture->insert('app_template', ['id' => self::WEBHOOK, 'app_id' => self::APP, 'path' => 'page.html.twig', 'template' => 'Hello Łódź', 'hash' => 'hash', 'active' => 1, 'updated_at' => '2026-10-18 11:00:00']);
        $loader = new EntityTemplateLoader($this->connection, 'prod');
        static::assertTrue($loader->exists('@contract-app/page.html.twig'));
        static::assertSame('Hello Łódź', $loader->getSourceContext('@contract-app/page.html.twig')->getCode());
        static::assertSame('@contract-app/page.html.twig_hash', $loader->getCacheKey('@contract-app/page.html.twig'));
        static::assertTrue($loader->isFresh('@contract-app/page.html.twig', $this->clock->now()->getTimestamp()));
        $this->connection->executeStatement('UPDATE app SET active = 0');
        $loader->reset();
        static::assertFalse($loader->exists('@contract-app/page.html.twig'));
    }

    public function testPluginMetadataKeepsInactiveUninstalledAndComposerFlags(): void
    {
        foreach ([['new', 1, null], ['installed', 1, '2026-10-18 10:00:00'], ['inactive', 0, '2026-10-18 11:00:00']] as [$name, $active, $installed]) {
            $this->fixture->insert('plugin', ['id' => Uuid::randomHex(), 'name' => $name, 'base_class' => 'MissingContractPlugin', 'path' => 'custom/plugins/contract', 'version' => '1.2.3', 'autoload' => '{"psr-4":{"Contract\\\\": ["src/"]}}', 'managed_by_composer' => 1, 'composer_name' => 'contract/plugin', 'active' => $active, 'installed_at' => $installed]);
        }
        $loader = new DbalKernelPluginLoader(new ClassLoader(), null, $this->connection);
        $loader->initializePlugins(\dirname(__DIR__, 5));
        $infos = $loader->getPluginInfos();
        static::assertSame(['new', 'installed', 'inactive'], array_column($infos, 'name'));
        static::assertSame([false, true, false], array_column($infos, 'active'));
        static::assertSame([true, true, true], array_column($infos, 'managedByComposer'));
        static::assertSame(['Contract\\' => ['src/']], $infos[0]['autoload']['psr-4']);
        static::assertSame('1.2.3', $infos[1]['version']);
    }
}

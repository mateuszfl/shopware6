<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Routing\DomainLoader;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\ProductListingReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Shopware\Storefront\Framework\Twig\NavigationInfo;
use Shopware\Storefront\Framework\Twig\TemplateDataExtension;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopware\Storefront\Theme\DataAbstractionLayer\ThemeIndexer;
use Shopware\Storefront\Theme\DataAbstractionLayer\ThemeIndexingMessage;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\Mail\MailThemeIdLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeCompilerInterface;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Shopware\Storefront\Theme\ThemeSalesChannel;
use Shopware\Storefront\Theme\ThemeSalesChannelCollection;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Storefront\Theme\_fixtures\ThemeDatabaseContractFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @phpstan-import-type ThemeRuntimeConfigArrayOverrides from ThemeRuntimeConfig
 */
#[Package('discovery')]
class ThemeDatabaseContractTest extends TestCase
{
    private Connection $connection;

    private ThemeDatabaseContractFixture $fixture;

    private ?Connection $maintenanceConnection = null;

    private ?string $isolatedDatabaseName = null;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->fixture = new ThemeDatabaseContractFixture($this->connection);
        $this->fixture->setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->fixture)) {
            $this->fixture->tearDown();
        }
        if (isset($this->connection)) {
            $this->connection->close();
        }
        if ($this->maintenanceConnection !== null && $this->isolatedDatabaseName !== null) {
            $this->maintenanceConnection->executeStatement('DROP DATABASE ' . $this->maintenanceConnection->getDatabasePlatform()->quoteSingleIdentifier($this->isolatedDatabaseName));
            $this->maintenanceConnection->close();
        }
    }

    public function testRuntimeConfigRoundTripsBothUniqueConflictsAndRollsBackFailedReplacement(): void
    {
        $storage = new ThemeRuntimeConfigStorage($this->connection);
        $themeIdX = Uuid::randomHex();
        $themeIdY = Uuid::randomHex();
        $nameA = 'contract-a-' . Uuid::randomHex();
        $nameB = 'contract-b-' . Uuid::randomHex();
        $configX = $this->createThemeRuntimeConfig(['themeId' => $themeIdX, 'technicalName' => $nameA]);
        $configY = $this->createThemeRuntimeConfig(['themeId' => $themeIdY, 'technicalName' => $nameB]);

        $storage->save($configX);
        $storage->save($configY);
        $storage->save($this->createThemeRuntimeConfig(['themeId' => $themeIdX, 'technicalName' => $nameB]));

        static::assertNull($storage->getById($themeIdY));
        static::assertNull($storage->getByName($nameA));
        $retrieved = $storage->getById($themeIdX);
        static::assertNotNull($retrieved);
        static::assertSame($nameB, $retrieved->technicalName);
        static::assertSame($themeIdX, $retrieved->themeId);
        static::assertSame($this->createThemeRuntimeConfig(['themeId' => $themeIdX, 'technicalName' => $nameB])->resolvedConfig, $retrieved->resolvedConfig);

        $failed = false;
        try {
            $storage->save($this->createThemeRuntimeConfig([
                'themeId' => Uuid::randomHex(),
                'technicalName' => $nameB,
                'resolvedConfig' => ['key' => 'failure'],
            ]));
        } catch (\Throwable) {
            $failed = true;
        }

        static::assertTrue($failed, 'The fixture constraint should reject the replacement row');
        $afterFailure = $storage->getByName($nameB);
        static::assertNotNull($afterFailure);
        static::assertSame($themeIdX, $afterFailure->themeId);
    }

    public function testThemeSalesChannelSelectionAndIndexerUsePortableSql(): void
    {
        $salesChannelId = Uuid::randomHex();
        $baseThemeId = Uuid::randomHex();
        $selectedThemeId = Uuid::randomHex();
        $copyThemeId = Uuid::randomHex();
        $this->connection->insert('sales_channel', [
            'id' => Uuid::fromHexToBytes($salesChannelId),
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
            'active' => true,
        ], [
            'id' => ParameterType::BINARY,
            'type_id' => ParameterType::BINARY,
            'active' => ParameterType::BOOLEAN,
            'maintenance' => ParameterType::BOOLEAN,
        ]);
        $this->insertTheme($baseThemeId, 'BaseTheme', null, ['configInheritance' => ['@Storefront']]);
        $this->insertTheme($selectedThemeId, 'SelectedTheme', $baseThemeId, ['configInheritance' => ['@BaseTheme']]);
        $this->insertTheme($copyThemeId, null, $selectedThemeId, null);
        $this->connection->insert('theme_sales_channel', [
            'theme_id' => Uuid::fromHexToBytes($copyThemeId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);

        static::assertSame(['SelectedTheme', 'BaseTheme'], (new DatabaseSalesChannelThemeLoader($this->connection))->load($salesChannelId));
        $provider = new DatabaseAvailableThemeProvider($this->connection);
        static::assertSame([$salesChannelId => $copyThemeId], $provider->load(Context::createDefaultContext(), true));

        $indexer = new ThemeIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            $this->connection,
            new EventDispatcher(),
        );
        $message = new ThemeIndexingMessage([$copyThemeId]);
        $indexer->handle($message);
        $indexer->handle($message);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM theme_child WHERE child_id = :childId AND parent_id = :parentId',
            ['childId' => Uuid::fromHexToBytes($copyThemeId), 'parentId' => Uuid::fromHexToBytes($selectedThemeId)],
            ['childId' => ParameterType::BINARY, 'parentId' => ParameterType::BINARY],
        ));
    }

    public function testRuntimeStorageSelectorsCoverCopiesChildrenNamesAndDelete(): void
    {
        $this->usePersistentMySqlDatabase();
        $storage = new ThemeRuntimeConfigStorage($this->connection);
        $parentId = Uuid::randomHex();
        $copyId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $grandchildId = Uuid::randomHex();
        $parentName = 'contract-parent-' . Uuid::randomHex();
        $childName = 'contract-child-' . Uuid::randomHex();
        $copyName = 'contract-copy-' . Uuid::randomHex();
        $this->insertTheme($parentId, $parentName, null, null);
        $this->insertTheme($copyId, null, $parentId, null);
        $this->insertTheme($childId, $childName, $parentId, null);
        $this->insertTheme($grandchildId, 'contract-grandchild-' . Uuid::randomHex(), $childId, null);
        foreach ([[$copyId, $parentId], [$childId, $parentId], [$grandchildId, $childId]] as [$descendantId, $ancestorId]) {
            $this->connection->insert('theme_child', [
                'child_id' => Uuid::fromHexToBytes($descendantId),
                'parent_id' => Uuid::fromHexToBytes($ancestorId),
            ], ['child_id' => ParameterType::BINARY, 'parent_id' => ParameterType::BINARY]);
        }
        $storage->save($this->createThemeRuntimeConfig(['themeId' => $parentId, 'technicalName' => $parentName]));
        $storage->save($this->createThemeRuntimeConfig(['themeId' => $copyId, 'technicalName' => $copyName]));

        static::assertSame([$copyId], $storage->getCopiesIds($parentId));
        static::assertEqualsCanonicalizing([$copyId, $childId, $grandchildId], array_values($storage->getChildThemeIds($parentId)));
        static::assertSame($parentName, $storage->getThemeTechnicalName($parentId));
        static::assertSame($parentId, $storage->getThemeIdByTechnicalName($parentName));

        $storage->deleteByTechnicalName($copyName);
        static::assertNull($storage->getByName($copyName));
    }

    public function testDomainLoaderReturnsSalesChannelDomainWithTheme(): void
    {
        $this->usePersistentMySqlDatabase();

        $salesChannelId = Uuid::randomHex();
        $themeId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();
        $domainId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $this->connection->insert('sales_channel', [
            'id' => Uuid::fromHexToBytes($salesChannelId),
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
            'active' => true,
            'maintenance' => false,
        ], [
            'id' => ParameterType::BINARY,
            'type_id' => ParameterType::BINARY,
            'active' => ParameterType::BOOLEAN,
            'maintenance' => ParameterType::BOOLEAN,
        ]);
        $this->insertTheme($themeId, 'DomainTheme', null, null);
        $this->connection->insert('theme_sales_channel', [
            'theme_id' => Uuid::fromHexToBytes($themeId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
        $this->connection->insert('snippet_set', ['id' => Uuid::fromHexToBytes($snippetSetId), 'iso' => 'en-GB'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::fromHexToBytes($domainId),
            'url' => 'https://example.test/en',
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'snippet_set_id' => Uuid::fromHexToBytes($snippetSetId),
            'currency_id' => Uuid::fromHexToBytes($currencyId),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ], [
            'id' => ParameterType::BINARY,
            'sales_channel_id' => ParameterType::BINARY,
            'snippet_set_id' => ParameterType::BINARY,
            'currency_id' => ParameterType::BINARY,
            'language_id' => ParameterType::BINARY,
        ]);

        $domains = (new DomainLoader($this->connection))->loadDomains();
        $domain = $domains->get('https://example.test/en/');
        static::assertNotNull($domain);
        static::assertSame($domainId, $domain->id);
        static::assertSame($salesChannelId, $domain->salesChannelId);
        static::assertSame('DomainTheme', $domain->themeName);
        static::assertSame('en-GB', $domain->locale);
    }

    public function testReadinessChecksAndMailThemeLoaderQueryTheirPublicDatabasePaths(): void
    {
        $this->usePersistentMySqlDatabase();

        $salesChannelId = Uuid::randomHex();
        $themeId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();
        $domainId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $cmsPageId = Uuid::randomHex();
        $this->connection->insert('sales_channel', [
            'id' => Uuid::fromHexToBytes($salesChannelId),
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
            'active' => true,
            'maintenance' => false,
            'navigation_category_id' => Uuid::fromHexToBytes($categoryId),
            'navigation_category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ], [
            'id' => ParameterType::BINARY,
            'type_id' => ParameterType::BINARY,
            'active' => ParameterType::BOOLEAN,
            'maintenance' => ParameterType::BOOLEAN,
            'navigation_category_id' => ParameterType::BINARY,
            'navigation_category_version_id' => ParameterType::BINARY,
        ]);
        $this->connection->insert('theme_sales_channel', [
            'theme_id' => Uuid::fromHexToBytes($themeId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
        $this->connection->insert('snippet_set', ['id' => Uuid::fromHexToBytes($snippetSetId), 'iso' => 'en-GB'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::fromHexToBytes($domainId),
            'url' => 'https://readiness.example.test/en',
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'snippet_set_id' => Uuid::fromHexToBytes($snippetSetId),
            'currency_id' => Uuid::fromHexToBytes($currencyId),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ], [
            'id' => ParameterType::BINARY,
            'sales_channel_id' => ParameterType::BINARY,
            'snippet_set_id' => ParameterType::BINARY,
            'currency_id' => ParameterType::BINARY,
            'language_id' => ParameterType::BINARY,
        ]);
        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($productId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'active' => true,
            'stock' => 5,
        ], ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN]);
        $this->connection->insert('product_visibility', [
            'product_id' => Uuid::fromHexToBytes($productId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['product_id' => ParameterType::BINARY, 'product_version_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
        $this->connection->insert('cms_page', [
            'id' => Uuid::fromHexToBytes($cmsPageId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'product_list',
        ], ['id' => ParameterType::BINARY, 'version_id' => ParameterType::BINARY]);
        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($categoryId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'parent_id' => null,
            'active' => true,
            'cms_page_id' => Uuid::fromHexToBytes($cmsPageId),
        ], [
            'id' => ParameterType::BINARY,
            'version_id' => ParameterType::BINARY,
            'parent_id' => ParameterType::BINARY,
            'active' => ParameterType::BOOLEAN,
            'cms_page_id' => ParameterType::BINARY,
        ]);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/health');
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('ok', Response::HTTP_OK));
        $util = new SalesChannelDomainUtil($router, new RequestStack(), $kernel, new NullLogger(), new MockClock());
        $provider = new SalesChannelDomainProvider($this->connection);

        static::assertCount(1, $provider->fetchSalesChannelDomains());
        $detailResult = (new ProductDetailReadinessCheck($util, $this->connection, $provider))->run();
        static::assertSame(Status::OK, $detailResult->status);
        static::assertCount(1, $detailResult->extra);
        $listingResult = (new ProductListingReadinessCheck($util, $this->connection, $provider))->run();
        static::assertSame(Status::OK, $listingResult->status);
        static::assertCount(1, $listingResult->extra);
        static::assertSame($themeId, (new MailThemeIdLoader($this->connection))->load($salesChannelId));
    }

    public function testUnusedThemeDirectoryDeleterKeepsDatabaseReferencedPaths(): void
    {
        $salesChannelId = Uuid::randomHex();
        $themeId = Uuid::randomHex();
        $this->connection->insert('theme_sales_channel', [
            'theme_id' => Uuid::fromHexToBytes($themeId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);

        $adapter = new InMemoryFilesystemAdapter();
        $filesystem = new Filesystem($adapter);
        $filesystem->createDirectory('theme/' . $themeId);
        $filesystem->createDirectory('theme/compiled-path');
        $filesystem->createDirectory('theme/unused-path');
        $pathBuilder = new class extends AbstractThemePathBuilder {
            public function getDecorated(): AbstractThemePathBuilder
            {
                throw new DecorationPatternException(self::class);
            }

            public function assemblePath(string $salesChannelId, string $themeId): string
            {
                return 'compiled-path';
            }

            public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string
            {
                return 'unused-path';
            }

            public function saveSeed(string $salesChannelId, string $themeId, string $seed): void
            {
            }
        };

        $deleter = new UnusedThemeDirectoryDeleter($this->connection, $filesystem, $pathBuilder, new MockClock());
        static::assertSame(1, $deleter->deleteUnusedDirectories());
        static::assertTrue($filesystem->directoryExists('theme/' . $themeId));
        static::assertTrue($filesystem->directoryExists('theme/compiled-path'));
        static::assertFalse($filesystem->directoryExists('theme/unused-path'));
    }

    public function testThemeConfigToolLooksUpThemeForSalesChannelName(): void
    {
        $salesChannelId = Uuid::randomHex();
        $themeId = Uuid::randomHex();
        $this->connection->insert('sales_channel', [
            'id' => Uuid::fromHexToBytes($salesChannelId),
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
            'active' => true,
        ], ['id' => ParameterType::BINARY, 'type_id' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN]);
        $this->connection->insert('sales_channel_translation', [
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'name' => 'Contract Storefront',
        ], ['sales_channel_id' => ParameterType::BINARY]);
        $this->connection->insert('theme_sales_channel', [
            'theme_id' => Uuid::fromHexToBytes($themeId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
        $themeService = static::createStub(ThemeService::class);
        $themeService->method('getPlainThemeConfiguration')->willReturn(['color' => 'blue']);

        $response = (new ThemeConfigTool($themeService, new McpContextProvider(new RequestStack()), $this->connection))(
            'Contract Storefront',
            'get',
        );
        $decoded = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($decoded['success']);
        static::assertSame($themeId, $decoded['data']['themeId']);
        static::assertSame(['color' => 'blue'], $decoded['data']['config']);
    }

    public function testThemeServiceMapsAssignedAndDependentSalesChannels(): void
    {
        $this->usePersistentMySqlDatabase();

        $themeId = Uuid::randomHex();
        $childThemeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $childSalesChannelId = Uuid::randomHex();
        $this->insertTheme($themeId, 'MappingTheme', null, null);
        $this->insertTheme($childThemeId, 'MappingChildTheme', $themeId, null);
        foreach ([[$themeId, $salesChannelId], [$childThemeId, $childSalesChannelId]] as [$assignedThemeId, $assignedSalesChannelId]) {
            $this->connection->insert('theme_sales_channel', [
                'theme_id' => Uuid::fromHexToBytes($assignedThemeId),
                'sales_channel_id' => Uuid::fromHexToBytes($assignedSalesChannelId),
            ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
        }

        $service = new ThemeService(
            static::createStub(StorefrontPluginRegistry::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(ThemeCompilerInterface::class),
            static::createStub(AbstractScssCompiler::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(AbstractConfigLoader::class),
            $this->connection,
            static::createStub(SystemConfigService::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(NotificationService::class),
            static::createStub(ThemeMergedConfigBuilder::class),
            static::createStub(ThemeRuntimeConfigService::class),
        );

        $mappings = $service->getThemeDependencyMapping($themeId)->getElements();
        static::assertCount(2, $mappings);
        $pairs = array_map(static fn (ThemeSalesChannel $mapping): array => [$mapping->getThemeId(), $mapping->getSalesChannelId()], $mappings);
        static::assertEqualsCanonicalizing([[$themeId, $salesChannelId], [$childThemeId, $childSalesChannelId]], array_values($pairs));
    }

    public function testThemeLifecycleHandlerMapsActiveAssignmentsForCompilation(): void
    {
        $this->usePersistentMySqlDatabase();
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $this->connection->insert('theme_sales_channel', [
            'theme_id' => Uuid::fromHexToBytes($themeId),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
        ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('compileTheme')
            ->with($salesChannelId, $themeId, static::isInstanceOf(Context::class));
        $themeService->expects($this->once())
            ->method('refreshThemeImportMap')
            ->with($salesChannelId, $themeId, static::isInstanceOf(Context::class));
        $handler = new ThemeLifecycleHandler(
            static::createStub(ThemeLifecycleService::class),
            $themeService,
            static::createStub(EntityRepository::class),
            static::createStub(StorefrontPluginRegistry::class),
            $this->connection,
        );
        $context = Context::createDefaultContext();

        $handler->recompileAllActiveThemes($context);
        $handler->refreshAllActiveThemeImportMaps($context);
    }

    public function testThemeLifecycleDeactivationReadsThemeDependenciesAndAssignments(): void
    {
        $this->usePersistentMySqlDatabase();
        $themeId = Uuid::randomHex();
        $childThemeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $this->insertTheme($themeId, 'LifecycleParent', null, null);
        $this->insertTheme($childThemeId, 'LifecycleChild', $themeId, null);
        foreach ([[$themeId, $salesChannelId], [$childThemeId, Uuid::randomHex()]] as [$assignedThemeId, $assignedSalesChannelId]) {
            $this->connection->insert('theme_sales_channel', [
                'theme_id' => Uuid::fromHexToBytes($assignedThemeId),
                'sales_channel_id' => Uuid::fromHexToBytes($assignedSalesChannelId),
            ], ['theme_id' => ParameterType::BINARY, 'sales_channel_id' => ParameterType::BINARY]);
            $this->connection->insert('sales_channel_translation', [
                'sales_channel_id' => Uuid::fromHexToBytes($assignedSalesChannelId),
                'name' => 'Lifecycle channel',
            ], ['sales_channel_id' => ParameterType::BINARY]);
        }

        $dependencies = new ThemeSalesChannelCollection();
        $dependencies->add(new ThemeSalesChannel($themeId, $salesChannelId));
        $themeService = static::createMock(ThemeService::class);
        $themeService->method('getThemeDependencyMapping')->willReturn($dependencies);
        $handler = new ThemeLifecycleHandler(
            static::createStub(ThemeLifecycleService::class),
            $themeService,
            static::createStub(EntityRepository::class),
            static::createStub(StorefrontPluginRegistry::class),
            $this->connection,
        );
        $configuration = new StorefrontPluginConfiguration('LifecycleParent');
        $configuration->setIsTheme(true);

        $this->expectException(ThemeAssignmentException::class);
        $handler->deactivateTheme($configuration, Context::createDefaultContext());
    }

    public function testThemeLifecycleServiceReadsThemeRowsThroughDialectHex(): void
    {
        $this->usePersistentMySqlDatabase();
        $themeId = Uuid::randomHex();
        $this->insertTheme($themeId, 'LifecycleReadTheme', null, null);

        $themeEntity = new ThemeEntity();
        $themeEntity->setId($themeId);
        $themeEntity->setTechnicalName('LifecycleReadTheme');
        $themeEntity->setName('Lifecycle Read Theme');
        $themeEntity->setAuthor('Shopware');
        $themeRepository = StaticEntityRepository::of(ThemeCollection::class, [new ThemeCollection([$themeEntity])]);

        $locale = static::createStub(LocaleEntity::class);
        $locale->method('getCode')->willReturn('en-GB');
        $language = static::createStub(LanguageEntity::class);
        $language->method('getTranslationCode')->willReturn($locale);
        $languageRepository = StaticEntityRepository::of(LanguageCollection::class, [new LanguageCollection([$language])]);

        $mediaFolderIds = static::createStub(IdSearchResult::class);
        $mediaFolderIds->method('firstId')->willReturn(null);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaFolderRepository->method('searchIds')->willReturn($mediaFolderIds);
        $themeChildRepository = static::createStub(EntityRepository::class);
        $themeChildIds = static::createStub(IdSearchResult::class);
        $themeChildRepository->method('searchIds')->willReturn($themeChildIds);
        $pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $pluginRegistry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());
        $runtimeConfigService = static::createStub(ThemeRuntimeConfigService::class);

        $service = new ThemeLifecycleService(
            $pluginRegistry,
            $themeRepository,
            static::createStub(EntityRepository::class),
            $mediaFolderRepository,
            static::createStub(EntityRepository::class),
            static::createStub(FileSaver::class),
            static::createStub(FileNameProvider::class),
            static::createStub(ThemeFilesystemResolver::class),
            $languageRepository,
            $themeChildRepository,
            $this->connection,
            static::createStub(AbstractStorefrontPluginConfigurationFactory::class),
            $runtimeConfigService,
        );
        $configuration = new StorefrontPluginConfiguration('LifecycleReadTheme');
        $configuration->setIsTheme(true);

        $service->refreshTheme($configuration, Context::createDefaultContext());

        static::assertCount(2, $themeRepository->upserts);
    }

    public function testTemplateGlobalsResolveLandingPageCategoryAndLanguageConfig(): void
    {
        $landingPageId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $this->connection->insert('category_translation', [
            'category_id' => Uuid::fromHexToBytes($categoryId),
            'link_type' => 'landing_page',
            'internal_link' => Uuid::fromHexToBytes($landingPageId),
        ], ['category_id' => ParameterType::BINARY, 'internal_link' => ParameterType::BINARY]);
        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($categoryId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'parent_id' => null,
            'active' => true,
            'cms_page_id' => null,
            'path' => '|' . $categoryId . '|',
        ], [
            'id' => ParameterType::BINARY,
            'version_id' => ParameterType::BINARY,
            'parent_id' => ParameterType::BINARY,
            'active' => ParameterType::BOOLEAN,
            'cms_page_id' => ParameterType::BINARY,
        ]);
        $this->connection->insert('product_search_config', [
            'language_id' => Uuid::fromHexToBytes($languageId),
            'min_search_length' => 4,
        ], ['language_id' => ParameterType::BINARY]);

        $salesChannel = static::createStub(SalesChannelEntity::class);
        $salesChannel->method('getNavigationCategoryId')->willReturn(Uuid::randomHex());
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn($languageId);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $request = Request::create('/');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set('landingPageId', $landingPageId);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $globals = (new TemplateDataExtension($requestStack, false, $this->connection))->getGlobals();

        static::assertSame(4, $globals['shopware']['minSearchLength']);
        $navigation = $globals['shopware']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigation);
        static::assertSame($categoryId, $navigation->id);
    }

    public function testAdministrationControllerResetsSearchTermsUsingTypedUuidQueries(): void
    {
        $localeId = Uuid::randomHex();
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $searchConfigId = Uuid::randomHex();
        $this->connection->insert('locale', [
            'id' => Uuid::fromHexToBytes($localeId),
            'code' => 'de-DE',
        ], ['id' => ParameterType::BINARY]);
        $this->connection->insert('language', [
            'id' => Uuid::fromHexToBytes($languageId),
            'translation_code_id' => Uuid::fromHexToBytes($localeId),
        ], ['id' => ParameterType::BINARY, 'translation_code_id' => ParameterType::BINARY]);
        $this->connection->insert('product_search_config', [
            'id' => Uuid::fromHexToBytes($searchConfigId),
            'language_id' => Uuid::fromHexToBytes($languageId),
            'min_search_length' => 3,
            'excluded_terms' => '[]',
        ], ['id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);

        $controller = new AdministrationController(
            static::createStub(TemplateFinderInterface::class),
            static::createStub(FirstRunWizardService::class),
            static::createStub(SnippetFinderInterface::class),
            [],
            static::createStub(KnownIpsCollectorInterface::class),
            $this->connection,
            static::createStub(EventDispatcherInterface::class),
            getcwd() . '/src/Core',
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(HtmlSanitizer::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(ParameterBagInterface::class),
            static::createStub(FilesystemOperator::class),
            '',
            static::createStub(EntityRepository::class),
            static::createStub(SymfonyBearerTokenValidator::class),
            '',
            static::createStub(CustomerEmailUniqueChecker::class),
        );

        $response = $controller->resetExcludedSearchTerm(Context::createDefaultContext());
        $storedTerms = $this->connection->fetchOne('SELECT excluded_terms FROM product_search_config WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($searchConfigId),
        ], ['id' => ParameterType::BINARY]);

        static::assertSame(200, $response->getStatusCode());
        static::assertNotSame('[]', $storedTerms);
    }

    /**
     * @param ThemeRuntimeConfigArrayOverrides $overrides
     */
    private function createThemeRuntimeConfig(array $overrides = []): ThemeRuntimeConfig
    {
        return ThemeRuntimeConfig::fromArray([
            'themeId' => Uuid::randomHex(),
            'technicalName' => 'database-contract-theme',
            'resolvedConfig' => ['key' => 'round-trip'],
            'viewInheritance' => ['base.html.twig'],
            'scriptFiles' => ['theme.js'],
            'iconSets' => ['default' => ['path' => 'icons', 'namespace' => 'contract']],
            'updatedAt' => new \DateTimeImmutable('2026-10-19T00:00:00+00:00'),
        ])->with($overrides);
    }

    /**
     * @param array<string, mixed>|null $baseConfig
     */
    private function insertTheme(string $id, ?string $technicalName, ?string $parentId, ?array $baseConfig): void
    {
        $this->connection->insert('theme', [
            'id' => Uuid::fromHexToBytes($id),
            'parent_theme_id' => $parentId !== null ? Uuid::fromHexToBytes($parentId) : null,
            'technical_name' => $technicalName,
            'base_config' => $baseConfig !== null ? json_encode($baseConfig, \JSON_THROW_ON_ERROR) : null,
        ], ['id' => ParameterType::BINARY, 'parent_theme_id' => ParameterType::BINARY]);
    }

    private function usePersistentMySqlDatabase(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $this->fixture->tearDown();
        $this->maintenanceConnection = $this->connection;
        $this->isolatedDatabaseName = 'sw018_contract_' . bin2hex(random_bytes(6));
        $quote = $this->maintenanceConnection->getDatabasePlatform()->quoteSingleIdentifier($this->isolatedDatabaseName);
        $this->maintenanceConnection->executeStatement('CREATE DATABASE ' . $quote);
        $params = $this->maintenanceConnection->getParams();
        $params['dbname'] = $this->isolatedDatabaseName;
        $this->connection = DriverManager::getConnection($params);
        $this->fixture = new ThemeDatabaseContractFixture($this->connection);
        $this->fixture->setUp(true);
    }
}

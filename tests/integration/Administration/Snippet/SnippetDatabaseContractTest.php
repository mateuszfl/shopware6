<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Snippet;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Administration\Framework\App\ActiveAdminAppLoader;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('discovery')]
class SnippetDatabaseContractTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $schemaPrefix = $postgres ? 'pg_temp.' : '';
        $drop = $postgres ? 'DROP TABLE IF EXISTS ' : 'DROP TEMPORARY TABLE IF EXISTS ';
        foreach (['app_administration_snippet', 'app', 'acl_role', 'locale'] as $table) {
            $this->connection->executeStatement($drop . $schemaPrefix . $table);
        }

        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement("CREATE TEMPORARY TABLE locale (id $binary PRIMARY KEY, code VARCHAR(16) NOT NULL)");
        $this->connection->executeStatement("CREATE TEMPORARY TABLE acl_role (id $binary PRIMARY KEY, privileges TEXT NULL)");
        $this->connection->executeStatement("CREATE TEMPORARY TABLE app (
            id $binary PRIMARY KEY, active BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL,
            integration_id $binary NULL, base_app_url VARCHAR(255) NULL, version VARCHAR(32) NOT NULL,
            source_type VARCHAR(32) NOT NULL, acl_role_id $binary NULL
        )");
        $this->connection->executeStatement("CREATE TEMPORARY TABLE app_administration_snippet (
            id $binary PRIMARY KEY,
            app_id $binary NOT NULL,
            locale_id $binary NOT NULL,
            value TEXT NOT NULL
        )");
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
            $schemaPrefix = $postgres ? 'pg_temp.' : '';
            $drop = $postgres ? 'DROP TABLE IF EXISTS ' : 'DROP TEMPORARY TABLE IF EXISTS ';
            foreach (['app_administration_snippet', 'app', 'acl_role', 'locale'] as $table) {
                $this->connection->executeStatement($drop . $schemaPrefix . $table);
            }
            $this->connection->close();
        }
    }

    public function testPersistedAdminSnippetsRoundTripAndInactiveAppsAreExcluded(): void
    {
        $localeId = random_bytes(16);
        $activeAppId = random_bytes(16);
        $inactiveAppId = random_bytes(16);
        $this->connection->insert('locale', ['id' => $localeId, 'code' => 'en-GB'], ['id' => ParameterType::BINARY]);
        $app = static fn (string $id, bool $active, string $name): array => [
            'id' => $id,
            'active' => $active,
            'name' => $name,
            'integration_id' => random_bytes(16),
            'base_app_url' => 'https://example.test/' . $name,
            'version' => '1.0.0',
            'source_type' => 'local',
            'acl_role_id' => null,
        ];
        $this->connection->insert('app', $app($activeAppId, true, 'active'), [
            'id' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN, 'integration_id' => ParameterType::BINARY,
        ]);
        $this->connection->insert('app', $app($inactiveAppId, false, 'inactive'), [
            'id' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN, 'integration_id' => ParameterType::BINARY,
        ]);
        $activeSnippet = ['contractSnippet' => ['label' => 'round-trip']];
        $inactiveSnippet = ['inactiveContractSnippet' => ['label' => 'hidden']];
        foreach ([[$activeAppId, $activeSnippet], [$inactiveAppId, $inactiveSnippet]] as [$appId, $snippet]) {
            $this->connection->insert('app_administration_snippet', [
                'id' => random_bytes(16),
                'app_id' => $appId,
                'locale_id' => $localeId,
                'value' => json_encode($snippet, \JSON_THROW_ON_ERROR),
            ], ['id' => ParameterType::BINARY, 'app_id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        }

        $finder = $this->createSnippetFinder();
        static::assertSame('round-trip', $finder->findSnippets('en-GB')['contractSnippet']['label']);
        static::assertArrayNotHasKey('inactiveContractSnippet', $finder->findSnippets('en-GB'));

        $apps = (new ActiveAdminAppLoader($this->connection))->getActiveAdminApps();
        static::assertCount(1, $apps);
        static::assertSame('https://example.test/active', $apps[0]['baseUrl']);
        static::assertSame('local', $apps[0]['sourceType']);

        $this->connection->update('app', ['active' => true], ['id' => $inactiveAppId], ['id' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN]);
        static::assertSame('hidden', $finder->findSnippets('en-GB')['inactiveContractSnippet']['label']);
    }

    private function createSnippetFinder(): SnippetFinder
    {
        $pluginLoader = new class(new ClassLoader()) extends KernelPluginLoader {
            protected function loadPluginInfos(): void
            {
            }
        };
        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);
        $kernel->method('getBundles')->willReturn([]);

        $translationLoader = static::createStub(AbstractTranslationLoader::class);
        $translationLoader->method('getLocalePath')->willReturn('/no-translation-files');
        $htmlSanitizer = static::createStub(HtmlSanitizer::class);
        $htmlSanitizer->method('sanitize')->willReturnArgument(0);
        $translationConfig = new TranslationConfig(
            new Uri('https://example.test/translations'),
            [],
            [],
            new LanguageCollection(),
            new PluginMappingCollection(),
            new Uri('https://example.test/metadata'),
            [],
        );

        return new SnippetFinder(
            $kernel,
            $this->connection,
            new Filesystem(new InMemoryFilesystemAdapter()),
            $translationConfig,
            $translationLoader,
            $htmlSanitizer,
            new NullLogger(),
            false,
        );
    }
}

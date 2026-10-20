<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;
use Shopware\Core\Service\Subscriber\ServiceWriteProtectionSubscriber;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class SharedRuntimeSqlContractTest extends TestCase
{
    private Connection $connection;

    private string $quotedUserTable;

    /**
     * @var list<string>
     */
    private array $ownedTables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->quotedUserTable = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('user');
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['user']), 'The isolated contract database must not contain a user table.');
        $this->connection->executeStatement('CREATE TABLE ' . $this->quotedUserTable . ' (id INTEGER PRIMARY KEY, store_token VARCHAR(128) NULL)');
        $this->ownedTables[] = 'user';
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            foreach (array_reverse($this->ownedTables) as $table) {
                $this->connection->executeStatement('DROP TABLE ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table));
            }

            $this->connection->close();
        }
    }

    public function testShopwareAccountRequirementRunsReservedIdentifierQueryOnBothEngines(): void
    {
        $requirement = new ShopwareAccountRequirement($this->connection);
        static::assertFalse($requirement->isSatisfied());

        $this->connection->executeStatement('INSERT INTO ' . $this->quotedUserTable . ' (id, store_token) VALUES (1, ?)', ['fixture-token']);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testSearchConfigLoaderExecutesSharedSelectFragmentOnBothEngines(): void
    {
        $isPostgreSql = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $binary = $isPostgreSql ? 'BYTEA' : 'BINARY(16)';
        $text = $isPostgreSql ? 'TEXT' : 'LONGTEXT';
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['product_search_config', 'product_search_config_field']), 'The isolated contract database must not contain search config tables.');
        $this->connection->executeStatement('CREATE TABLE product_search_config (id ' . $binary . ' PRIMARY KEY, language_id ' . $binary . ' NOT NULL, and_logic VARCHAR(10) NOT NULL, excluded_terms ' . $text . ' NOT NULL, min_search_length INTEGER NOT NULL)');
        $this->ownedTables[] = 'product_search_config';
        $this->connection->executeStatement('CREATE TABLE product_search_config_field (id ' . $binary . ' PRIMARY KEY, product_search_config_id ' . $binary . ' NOT NULL, field VARCHAR(255) NOT NULL, tokenize SMALLINT NOT NULL, ranking DOUBLE PRECISION NOT NULL, use_exact_subfield SMALLINT NOT NULL, searchable SMALLINT NOT NULL)');
        $this->ownedTables[] = 'product_search_config_field';

        $languageId = Context::createDefaultContext()->getLanguageIdChain()[0];
        $configId = random_bytes(16);
        $this->connection->insert('product_search_config', [
            'id' => $configId,
            'language_id' => hex2bin($languageId),
            'and_logic' => 'OR',
            'excluded_terms' => '["fixture"]',
            'min_search_length' => 2,
        ], ['id' => ParameterType::BINARY, 'language_id' => ParameterType::BINARY]);
        $this->connection->insert('product_search_config_field', [
            'id' => random_bytes(16),
            'product_search_config_id' => $configId,
            'field' => 'name',
            'tokenize' => 1,
            'ranking' => 1.5,
            'use_exact_subfield' => 0,
            'searchable' => 1,
        ], ['id' => ParameterType::BINARY, 'product_search_config_id' => ParameterType::BINARY]);

        static::assertSame([[
            'and_logic' => 'OR',
            'excluded_terms' => ['fixture'],
            'min_search_length' => 2,
            'field' => 'name',
            'tokenize' => 1,
            'ranking' => 1.5,
            'use_exact_subfield' => 0,
        ]], (new SearchConfigLoader($this->connection))->load(Context::createDefaultContext()));
    }

    public function testServiceWriteProtectionExecutesPlatformQuotedQueryOnBothEngines(): void
    {
        $quotedApp = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('app');
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['app']), 'The isolated contract database must not contain an app table.');
        $binary = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'BYTEA' : 'BINARY(16)';
        $id = random_bytes(16);
        $this->connection->executeStatement('CREATE TABLE ' . $quotedApp . ' (id ' . $binary . ' PRIMARY KEY, self_managed SMALLINT NOT NULL)');
        $this->ownedTables[] = 'app';

        $this->connection->executeStatement('INSERT INTO ' . $quotedApp . ' (id, self_managed) VALUES (?, 1)', [$id], [ParameterType::BINARY]);
        $definition = new AppDefinition();
        new StaticDefinitionInstanceRegistry([$definition], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGatewayInterface::class));
        $command = new UpdateCommand($definition, ['active' => false], ['id' => $id], EntityExistence::createEmpty(), '/0/');
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()))),
            [$command],
        );
        (new ServiceWriteProtectionSubscriber($this->connection))->checkWrite($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());

        $regularAppId = random_bytes(16);
        $this->connection->executeStatement('INSERT INTO ' . $quotedApp . ' (id, self_managed) VALUES (?, 0)', [$regularAppId], [ParameterType::BINARY]);
        $regularAppCommand = new UpdateCommand($definition, ['active' => false], ['id' => $regularAppId], EntityExistence::createEmpty(), '/1/');
        $regularAppEvent = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()))),
            [$regularAppCommand],
        );
        (new ServiceWriteProtectionSubscriber($this->connection))->checkWrite($regularAppEvent);

        static::assertCount(0, $regularAppEvent->getExceptions()->getExceptions());
    }
}

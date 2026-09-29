<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractFixture;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractGateway;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractScenario;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition\ContractLanguageDefinition;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition\ContractProductDefinition;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition\ContractProductTagDefinition;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition\ContractProductTranslationDefinition;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition\ContractTagDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseContractTest extends TestCase
{
    private static bool $platformLogged = false;

    private Connection $connection;

    private DatabaseContractFixture $fixture;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $endpoint = DatabaseEndpoint::observe($this->connection);

        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            static::assertStringStartsWith('18.6 ', $endpoint->version);
        } else {
            static::assertStringStartsWith('8.4.', $endpoint->version);
        }

        if (!self::$platformLogged) {
            fwrite(\STDERR, \sprintf(
                "[database-contract] platform=%s version=%s endpoint=%s planned-tests=5\n",
                $endpoint->platform,
                $endpoint->version,
                $endpoint->serverId,
            ));
            self::$platformLogged = true;
        }

        $this->fixture = new DatabaseContractFixture($this->connection);
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
    }

    public function testFixtureSchemaIsExplicitAndRebuildable(): void
    {
        $tables = array_map(
            static fn ($table): string => $table->getUnqualifiedName()->getValue(),
            $this->connection->createSchemaManager()->introspectTableNames(),
        );

        static::assertContains('sw_contract_language', $tables);
        static::assertContains('sw_contract_product', $tables);
        static::assertContains('sw_contract_product_translation', $tables);
        static::assertContains('sw_contract_tag', $tables);
        static::assertContains('sw_contract_product_tag', $tables);

        $this->fixture->setUp();
        static::assertSame(0, (new DatabaseContractGateway($this->connection))->countProducts());
    }

    public function testDalDefinitionsDescribeTheSharedFixture(): void
    {
        $container = new ContainerBuilder();
        $registry = new DefinitionInstanceRegistry($container, [], []);
        foreach ([
            new ContractLanguageDefinition(),
            new ContractTagDefinition(),
            new ContractProductDefinition(),
            new ContractProductTranslationDefinition(),
            new ContractProductTagDefinition(),
        ] as $definition) {
            $registry->register($definition);
        }

        $product = $registry->getByEntityName(ContractProductDefinition::ENTITY_NAME);
        static::assertTrue($product->isVersionAware());
        static::assertInstanceOf(VersionField::class, $product->getField('versionId'));
        static::assertInstanceOf(PriceField::class, $product->getField('prices'));
        static::assertInstanceOf(ManyToManyAssociationField::class, $product->getField('tags'));
        static::assertSame(
            ContractProductTranslationDefinition::ENTITY_NAME,
            $registry->getByEntityName(ContractProductTranslationDefinition::ENTITY_NAME)->getEntityName(),
        );
    }

    public function testEmptyAndBatchRemainderUseTheSameGatewayContract(): void
    {
        $gateway = new DatabaseContractGateway($this->connection);
        $empty = ['languages' => [], 'products' => [], 'translations' => [], 'tags' => [], 'productTags' => []];

        static::assertSame(0, $gateway->write($empty));
        static::assertSame([], $gateway->read(DatabaseContractScenario::ENGLISH_LANGUAGE));

        $productBatches = $gateway->write(DatabaseContractScenario::data());

        static::assertSame(3, $productBatches, 'Seven products must use two full batches and one remainder batch');
        DatabaseContractScenario::assertState($gateway->read(DatabaseContractScenario::ENGLISH_LANGUAGE));
    }

    public function testDuplicateInBatchRollsBackAllRows(): void
    {
        $gateway = new DatabaseContractGateway($this->connection);
        $data = DatabaseContractScenario::data();
        $data['products'][] = $data['products'][0];

        try {
            $gateway->write($data);
            static::fail('A duplicate versioned product must fail the SQL batch');
        } catch (UniqueConstraintViolationException) {
            static::assertSame(0, $gateway->countProducts());
        }
    }

    public function testNullTranslationAndMissingRelationsStayDistinct(): void
    {
        $gateway = new DatabaseContractGateway($this->connection);
        $gateway->write(DatabaseContractScenario::data());

        $state = $gateway->read(DatabaseContractScenario::ENGLISH_LANGUAGE);
        $withNullPayload = array_values(array_filter($state, static fn (array $row): bool => $row['payload'] === null));
        $withoutTags = array_values(array_filter($state, static fn (array $row): bool => $row['tags'] === []));

        static::assertCount(1, $withNullPayload);
        static::assertCount(5, $withoutTags);
        static::assertNull($withNullPayload[0]['description']);
    }
}

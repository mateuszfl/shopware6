<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\FixtureLoader;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class FixtureLoaderDatabaseContractTest extends TestCase
{
    private const SYSTEM_LOCALE_ID = 'b4b45f58088d41289490db956ca19af7';

    private const SPANISH_LOCALE_ID = '7912f4de72aa43d792bcebae4eb45c5c';

    private Connection $connection;

    private bool $createdHexFunction = false;

    /**
     * @var list<string>
     */
    private array $ownedTables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $schema = new DatabaseSchema($this->connection);
        $existing = array_map(static fn ($table) => $table->getUnqualifiedName()->getValue(), $schema->introspectCurrentSchemaTableNames());
        static::assertSame([], array_values(array_intersect(['language', 'locale'], $existing)), 'Fixture table names must be unused in the current schema.');

        $this->createHexFunctionIfNeeded();
        $idType = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement('CREATE TABLE language (id ' . $idType . ' PRIMARY KEY, locale_id ' . $idType . ' NOT NULL)');
        $this->ownedTables[] = 'language';
        $this->connection->executeStatement('CREATE TABLE locale (id ' . $idType . ' PRIMARY KEY, code VARCHAR(16) NOT NULL)');
        $this->ownedTables[] = 'locale';

        $systemLocaleId = Uuid::fromHexToBytes(self::SYSTEM_LOCALE_ID);
        $spanishLocaleId = Uuid::fromHexToBytes(self::SPANISH_LOCALE_ID);
        $this->connection->insert('language', [
            'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'locale_id' => $systemLocaleId,
        ], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);
        $this->connection->insert('locale', ['id' => $systemLocaleId, 'code' => 'en-GB'], ['id' => ParameterType::BINARY, 'code' => ParameterType::STRING]);
        $this->connection->insert('locale', ['id' => $spanishLocaleId, 'code' => 'es-ES'], ['id' => ParameterType::BINARY, 'code' => ParameterType::STRING]);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }

        foreach (array_reverse($this->ownedTables) as $table) {
            $this->connection->executeStatement('DROP TABLE ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table));
        }

        if ($this->createdHexFunction) {
            if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $schema = (string) $this->connection->fetchOne('SELECT current_schema()');
                $this->connection->executeStatement('DROP FUNCTION ' . $this->connection->quoteSingleIdentifier($schema) . '.sw_uuid_hex(bytea)');
            } else {
                $this->connection->executeStatement('DROP FUNCTION sw_uuid_hex');
            }
        }

        $this->connection->close();
    }

    public function testLoadLooksUpLocaleIdsOnBothSupportedPlatforms(): void
    {
        $writer = static::createMock(EntityWriterInterface::class);
        $writtenId = null;
        $writer->expects($this->once())->method('sync')->willReturnCallback(function (array $operations, WriteContext $context) use (&$writtenId): WriteResult {
            static::assertCount(1, $operations);
            static::assertInstanceOf(SyncOperation::class, $operations[0]);
            static::assertSame('product', $operations[0]->getEntity());
            static::assertSame('upsert', $operations[0]->getAction());
            $writtenId = $operations[0]->getPayload()[0]['id'];
            static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $writtenId);

            return new WriteResult([]);
        });

        $container = new ContainerBuilder();
        $container->set(Connection::class, $this->connection);
        $container->set(EntityWriter::class, $writer);
        $indexer = static::createMock(EntityIndexerRegistry::class);
        $indexer->expects($this->once())->method('index')->with(false);
        $container->set(EntityIndexerRegistry::class, $indexer);

        $ids = (new FixtureLoader($container))->load('{"product":[{"id":"{fixture-id}"}]}');

        static::assertSame(self::SYSTEM_LOCALE_ID, $ids->get('locale'));
        static::assertSame(self::SPANISH_LOCALE_ID, $ids->get('es-locale'));
        static::assertSame($writtenId, $ids->get('fixture-id'));
    }

    private function createHexFunctionIfNeeded(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $schema = (string) $this->connection->fetchOne('SELECT current_schema()');
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM pg_catalog.pg_proc p INNER JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = ? AND p.proname = ? AND p.proargtypes = ?::oidvector',
                [$schema, 'sw_uuid_hex', '17'],
            ) > 0;
        } else {
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = \'FUNCTION\'',
                ['sw_uuid_hex'],
            ) > 0;
        }

        (new DatabaseFunctionInstaller())->install($this->connection);
        $this->createdHexFunction = !$exists;
    }
}

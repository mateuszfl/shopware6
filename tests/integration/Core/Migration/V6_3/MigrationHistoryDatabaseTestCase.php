<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Migration\V6_3\Migration1536232600Language;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('framework')]
abstract class MigrationHistoryDatabaseTestCase extends TestCase
{
    protected Connection $postgres;

    protected Connection $mysql;

    /**
     * @var list<Connection>
     */
    private array $ownedDatabases = [];

    protected function setUp(): void
    {
        if (!getenv('DATABASE_CONTRACT_URL') || !getenv('DATABASE_CONTRACT_URL_MYSQL')) {
            static::markTestSkipped('Set DATABASE_CONTRACT_URL and DATABASE_CONTRACT_URL_MYSQL to dedicated empty contract databases.');
        }

        $this->postgres = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->mysql = ContractConnectionFactory::create('DATABASE_CONTRACT_URL_MYSQL');
        static::assertInstanceOf(PostgreSQLPlatform::class, $this->postgres->getDatabasePlatform());
        static::assertNotInstanceOf(PostgreSQLPlatform::class, $this->mysql->getDatabasePlatform());

        foreach ([$this->postgres, $this->mysql] as $connection) {
            static::assertSame([], $this->tableNames($connection), 'Use a dedicated empty database.');
            $schema = $connection === $this->postgres ? 'current_schema()' : 'DATABASE()';
            static::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM information_schema.views WHERE table_schema = $schema"), 'Use a dedicated empty database.');
        }

        foreach ([$this->postgres, $this->mysql] as $connection) {
            $this->ownedDatabases[] = $connection;
            static::assertTrue((new SetupDatabaseAdapter())->initializeShopwareDb($connection));
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->ownedDatabases as $connection) {
            if ($connection === $this->mysql) {
                $connection->executeStatement('SET SESSION FOREIGN_KEY_CHECKS = 0');
            }
            try {
                foreach ($this->tableNames($connection) as $table) {
                    $connection->executeStatement('DROP TABLE ' . $connection->quoteSingleIdentifier($table) . ($connection === $this->postgres ? ' CASCADE' : ''));
                }
            } finally {
                try {
                    if ($connection === $this->mysql) {
                        $connection->executeStatement('SET SESSION FOREIGN_KEY_CHECKS = 1');
                    }
                } finally {
                    $connection->close();
                }
            }
        }
    }

    protected function collection(Connection $connection): MigrationCollection
    {
        $file = (new \ReflectionClass(Migration1536232600Language::class))->getFileName();
        static::assertIsString($file);
        $source = new MigrationSource('core.V6_3', [\dirname($file) => 'Shopware\\Core\\Migration\\V6_3']);
        $logger = new NullLogger();

        return new MigrationCollection($source, new MigrationRuntime($connection, $logger), $connection, $logger);
    }

    /**
     * @return list<non-empty-string>
     */
    protected function tableNames(Connection $connection): array
    {
        return array_map(
            static fn (OptionallyQualifiedName $table): string => $table->getUnqualifiedName()->getValue(),
            (new DatabaseSchema($connection))->introspectCurrentSchemaTableNames()
        );
    }
}

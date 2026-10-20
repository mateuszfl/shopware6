<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\DbState;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('framework')]
class DbStateDatabaseContractTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $tables = ['sw_db_state_added', 'sw_db_state_deleted', 'sw_db_state_counted', 'version_commit', 'version_commit_data'];

    private ?string $otherSchema = null;

    /**
     * @var list<string>
     */
    private array $ownedTables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $schema = new DatabaseSchema($this->connection);
        $existing = array_map(static fn ($table) => $table->getUnqualifiedName()->getValue(), $schema->introspectCurrentSchemaTableNames());
        static::assertSame([], array_values(array_intersect($this->tables, $existing)), 'Contract table names must be unused in the current schema.');

        foreach (\array_slice($this->tables, 1) as $table) {
            $this->createTable($table);
        }

        foreach (\array_slice($this->tables, 1) as $table) {
            $this->connection->insert($table, ['id' => 1]);
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->otherSchema = 'sw_db_state_' . bin2hex(random_bytes(4));
            $quotedSchema = $this->connection->quoteSingleIdentifier($this->otherSchema);
            $this->connection->executeStatement('CREATE SCHEMA ' . $quotedSchema);
            $this->connection->executeStatement('CREATE TABLE ' . $quotedSchema . '."sw_db_state_counted" (id INTEGER PRIMARY KEY)');
            $this->connection->executeStatement('INSERT INTO ' . $quotedSchema . '."sw_db_state_counted" VALUES (1), (2)');
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }

        if ($this->otherSchema !== null) {
            $this->connection->executeStatement('DROP SCHEMA ' . $this->connection->quoteSingleIdentifier($this->otherSchema) . ' CASCADE');
        }

        foreach ($this->ownedTables as $table) {
            $this->connection->executeStatement('DROP TABLE ' . $this->connection->quoteSingleIdentifier($table));
        }

        $this->connection->close();
    }

    public function testDiffTracksTableAndRowChangesAndIgnoresVersionTables(): void
    {
        $state = new DbState($this->connection);
        $state->rememberCurrentDbState();

        $this->createTable('sw_db_state_added');
        $this->connection->insert('sw_db_state_added', ['id' => 1]);
        $this->connection->executeStatement('DROP TABLE ' . $this->connection->quoteSingleIdentifier('sw_db_state_deleted'));
        $this->ownedTables = array_values(array_diff($this->ownedTables, ['sw_db_state_deleted']));
        $this->connection->insert('sw_db_state_counted', ['id' => 2]);
        $this->connection->insert('version_commit', ['id' => 2]);
        $this->connection->insert('version_commit_data', ['id' => 2]);

        static::assertSame(
            [
                'added' => ['sw_db_state_added'],
                'deleted' => ['sw_db_state_deleted'],
                'changed' => ['sw_db_state_counted' => 1],
            ],
            $state->getDiff(),
        );
    }

    public function testPostgreSqlSearchPathScopesUnqualifiedTablesToCurrentSchema(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $table = 'sw_db_state_scope_' . bin2hex(random_bytes(4));
        $schema = 'sw_db_state_scope_' . bin2hex(random_bytes(4));
        $quotedTable = $this->connection->quoteSingleIdentifier($table);
        $quotedSchema = $this->connection->quoteSingleIdentifier($schema);
        $publicNames = array_map(static fn ($name) => $name->getUnqualifiedName()->getValue(), (new DatabaseSchema($this->connection))->introspectCurrentSchemaTableNames());
        static::assertNotContains($table, $publicNames);

        $this->connection->executeStatement('CREATE TABLE public.' . $quotedTable . ' (id INTEGER PRIMARY KEY)');
        $this->connection->executeStatement('CREATE SCHEMA ' . $quotedSchema);
        try {
            $this->connection->executeStatement('CREATE TABLE ' . $quotedSchema . '.' . $quotedTable . ' (id INTEGER PRIMARY KEY)');
            $this->connection->executeStatement('INSERT INTO ' . $quotedSchema . '.' . $quotedTable . ' VALUES (1)');
            $this->connection->executeStatement('INSERT INTO public.' . $quotedTable . ' VALUES (1), (2)');
            $this->connection->executeStatement('SET search_path TO ' . $quotedSchema . ', public');

            $state = new DbState($this->connection);
            $state->rememberCurrentDbState();

            static::assertSame(1, $state->tableCounts[$table]);
            static::assertCount(1, array_filter(array_keys($state->tableCounts), static fn (string $name): bool => $name === $table));
        } finally {
            $this->connection->executeStatement('SET search_path TO public');
            $this->connection->executeStatement('DROP SCHEMA ' . $quotedSchema . ' CASCADE');
            $this->connection->executeStatement('DROP TABLE public.' . $quotedTable);
        }
    }

    private function createTable(string $table): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE ' . $this->connection->quoteSingleIdentifier($table) . ' (id INTEGER PRIMARY KEY)',
        );
        $this->ownedTables[] = $table;
    }
}

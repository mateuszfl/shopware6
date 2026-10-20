<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('framework')]
class SqlFixtureDatabaseContractTest extends TestCase
{
    private const DAL_FIXTURE_TABLES = [
        'attribute_entity',
        'attribute_entity_currency',
        'attribute_entity_translation',
        'attribute_entity_agg',
        'attribute_entity_with_hydrator',
        'attribute_entity_order',
    ];

    private const FIXTURE_TABLES = [
        'attribute_entity',
        'attribute_entity_currency',
        'attribute_entity_translation',
        'attribute_entity_agg',
        'attribute_entity_with_hydrator',
        'attribute_entity_order',
        'salutation',
        'salutation_translation',
        'newsletter_recipient',
    ];

    private Connection $connection;

    private ?string $schema = null;

    /**
     * @var list<string>
     */
    private array $ownedTables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->schema = 'sw_sql_fixture_' . bin2hex(random_bytes(5));
            $quotedSchema = $this->connection->quoteSingleIdentifier($this->schema);
            $this->connection->executeStatement('CREATE SCHEMA ' . $quotedSchema);
            $this->connection->executeStatement('SET search_path TO ' . $quotedSchema . ', public');
        }

        $tables = [...self::FIXTURE_TABLES, 'currency', 'state_machine_state', 'language', 'order'];
        $existing = array_map(
            static fn ($table) => $table->getUnqualifiedName()->getValue(),
            (new DatabaseSchema($this->connection))->introspectCurrentSchemaTableNames(),
        );
        static::assertSame([], array_values(array_intersect($tables, $existing)), 'Fixture contract table names must be unused in its schema.');

        $binary = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'BYTEA' : 'BINARY(16)';
        $this->createTable('currency', 'id ' . $binary . ' PRIMARY KEY');
        $this->createTable('state_machine_state', 'id ' . $binary . ' PRIMARY KEY');
        $this->createTable('language', 'id ' . $binary . ' PRIMARY KEY, locale_id ' . $binary . ' NOT NULL');
        $this->createTable('order', 'id ' . $binary . ' NOT NULL, version_id ' . $binary . ' NOT NULL, PRIMARY KEY (id, version_id)');

        $systemLocaleId = Uuid::fromHexToBytes('b4b45f58088d41289490db956ca19af7');
        $this->connection->insert('language', [
            'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'locale_id' => $systemLocaleId,
        ], ['id' => ParameterType::BINARY, 'locale_id' => ParameterType::BINARY]);

        $this->createNewsletterTables($binary);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }

        if ($this->schema !== null) {
            $this->connection->executeStatement('SET search_path TO public');
            $this->connection->executeStatement('DROP SCHEMA ' . $this->connection->quoteSingleIdentifier($this->schema) . ' CASCADE');
        } else {
            foreach (array_reverse($this->ownedTables) as $table) {
                $this->connection->executeStatement('DROP TABLE ' . $this->connection->quoteSingleIdentifier($table));
            }
        }

        $this->connection->close();
    }

    public function testNewsletterFixturesKeepTheirRowsAndStatusesOnBothPlatforms(): void
    {
        foreach (['salutation', 'recipient'] as $fixture) {
            $sql = file_get_contents(__DIR__ . '/../../Content/Newsletter/fixtures/' . $fixture . ($this->schema !== null ? '.postgresql' : '') . '.sql');
            static::assertIsString($sql);
            $this->connection->executeStatement(str_replace(':now', '2026-01-15 10:00:00.500', $sql));
        }

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM salutation'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM salutation_translation'));
        static::assertSame(8, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM newsletter_recipient'));
        static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE status = ?', ['optOut']));
        static::assertSame(5, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE status = ?', ['notSet']));
    }

    public function testAttributeDalFixtureCreatesConstraintsAndSupportsWrites(): void
    {
        foreach (self::DAL_FIXTURE_TABLES as $table) {
            $this->ownedTables[] = $table;
        }
        $sql = file_get_contents(__DIR__ . '/../DataAbstractionLayer/fixture/schema' . ($this->schema !== null ? '.postgresql' : '') . '.sql');
        static::assertIsString($sql);
        $this->connection->executeStatement($sql);

        static::assertNotEmpty($this->connection->createSchemaManager()->introspectTableForeignKeyConstraints(OptionallyQualifiedName::unquoted('attribute_entity')));

        $entityId = Uuid::fromHexToBytes(Uuid::randomHex());
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $this->connection->insert('attribute_entity', [
            'id' => $entityId,
            'string' => 'fixture',
            'html_string' => 'fixture',
            'created_at' => '2026-01-15 10:00:00.000',
        ], ['id' => ParameterType::BINARY]);
        $this->connection->insert('attribute_entity_translation', [
            'trans_string' => 'fixture translation',
            'created_at' => '2026-01-15 10:00:00.000',
            'attribute_entity_id' => $entityId,
            'language_id' => $languageId,
        ], [
            'attribute_entity_id' => ParameterType::BINARY,
            'language_id' => ParameterType::BINARY,
        ]);

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM attribute_entity WHERE id = ?', [$entityId], [ParameterType::BINARY]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM attribute_entity_translation WHERE attribute_entity_id = ?', [$entityId], [ParameterType::BINARY]));
        $autoIncrement = (int) $this->connection->fetchOne('SELECT auto_increment FROM attribute_entity WHERE id = ?', [$entityId], [ParameterType::BINARY]);
        static::assertGreaterThan(0, $autoIncrement);

        static::expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('attribute_entity', [
            'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'string' => 'duplicate',
            'html_string' => 'duplicate',
            'created_at' => '2026-01-15 10:00:00.000',
            'auto_increment' => $autoIncrement,
        ], ['id' => ParameterType::BINARY]);
    }

    private function createTable(string $table, string $columns): void
    {
        $this->connection->executeStatement('CREATE TABLE ' . $this->connection->quoteSingleIdentifier($table) . ' (' . $columns . ')');
        $this->ownedTables[] = $table;
    }

    private function createNewsletterTables(string $binary): void
    {
        $timestamp = $this->schema !== null ? 'TIMESTAMP(3)' : 'DATETIME(3)';
        $json = $this->schema !== null ? 'JSONB' : 'JSON';
        $this->createTable('salutation', 'id ' . $binary . ' PRIMARY KEY, salutation_key VARCHAR(255), created_at ' . $timestamp . ', updated_at ' . $timestamp . ' NULL');
        $this->createTable('salutation_translation', 'salutation_id ' . $binary . ', language_id ' . $binary . ', display_name VARCHAR(255), letter_name VARCHAR(255), created_at ' . $timestamp . ', updated_at ' . $timestamp . ' NULL');
        $this->createTable('newsletter_recipient', 'id ' . $binary . ' PRIMARY KEY, email VARCHAR(255), first_name VARCHAR(255), last_name VARCHAR(255), zip_code VARCHAR(255), city VARCHAR(255), street VARCHAR(255), status VARCHAR(32), hash VARCHAR(255), salutation_id ' . $binary . ', language_id ' . $binary . ', sales_channel_id ' . $binary . ', custom_fields ' . $json . ' NULL, confirmed_at ' . $timestamp . ' NULL, created_at ' . $timestamp . ', updated_at ' . $timestamp . ' NULL');
    }
}

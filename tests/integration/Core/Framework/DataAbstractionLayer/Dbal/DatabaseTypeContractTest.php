<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Isolated fixture tests for the PostgreSQL/MySQL physical type contract.
 * Run with --no-configuration --bootstrap vendor/autoload.php and an explicit
 * TYPE_CONTRACT_DATABASE_URL pointing to a disposable *_test database.
 *
 * @internal
 */
#[Package('framework')]
class DatabaseTypeContractTest extends TestCase
{
    private Connection $connection;

    private bool $postgres;

    protected function setUp(): void
    {
        $url = getenv('TYPE_CONTRACT_DATABASE_URL');
        static::assertIsString($url, 'TYPE_CONTRACT_DATABASE_URL must name a disposable test database');
        $database = parse_url($url, \PHP_URL_PATH);
        static::assertIsString($database);
        static::assertStringEndsWith('_test', $database);

        $this->connection = DriverManager::getConnection((new DsnParser([
            'postgresql' => 'pdo_pgsql',
            'mysql' => 'pdo_mysql',
        ]))->parse($url));
        $this->postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        if ($this->postgres) {
            static::assertStringStartsWith('18.6 ', (string) $this->connection->fetchOne('SHOW server_version'));
            $this->connection->executeStatement('SET TIME ZONE \'UTC\'');
        } else {
            static::assertStringStartsWith('8.4.', (string) $this->connection->fetchOne('SELECT VERSION()'));
            $this->connection->executeStatement('SET time_zone = \'+00:00\'');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_type_child');
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_type_parent');
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_type_scalar');
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_type_text');

            if ($this->postgres) {
                $this->connection->executeStatement('DROP COLLATION IF EXISTS sw_type_ci');
            }

            $this->connection->close();
        }
    }

    public function testBinaryUuidVersionForeignKeyAndBlobRoundTrip(): void
    {
        $binary = $this->postgres ? 'BYTEA' : 'BINARY(16)';
        $blob = $this->postgres ? 'BYTEA' : 'LONGBLOB';
        $lengthCheck = $this->postgres ? 'CHECK (octet_length(id) = 16), CHECK (octet_length(version_id) = 16),' : '';
        $fkLengthCheck = $this->postgres ? 'CHECK (octet_length(id) = 16), CHECK (octet_length(parent_id) = 16), CHECK (octet_length(parent_version_id) = 16),' : '';

        $this->connection->executeStatement("CREATE TABLE sw_type_parent (id $binary NOT NULL, version_id $binary NOT NULL, $lengthCheck PRIMARY KEY (id, version_id))");
        $this->connection->executeStatement("CREATE TABLE sw_type_child (id $binary NOT NULL, parent_id $binary NOT NULL, parent_version_id $binary NOT NULL, payload $blob NOT NULL, $fkLengthCheck PRIMARY KEY (id), FOREIGN KEY (parent_id, parent_version_id) REFERENCES sw_type_parent (id, version_id))");

        $id = hex2bin('000102030405060708090a0b0c0d0e0f');
        $version = hex2bin('f0e0d0c0b0a090807060504030201000');
        $child = hex2bin('00112233445566778899aabbccddeeff');
        static::assertIsString($id);
        static::assertIsString($version);
        static::assertIsString($child);
        $payload = "\x00\xff\x80cart\x00\x01";

        $this->connection->executeStatement('INSERT INTO sw_type_parent (id, version_id) VALUES (?, ?)', [$id, $version], [ParameterType::BINARY, ParameterType::BINARY]);
        $this->connection->executeStatement('INSERT INTO sw_type_child (id, parent_id, parent_version_id, payload) VALUES (?, ?, ?, ?)', [$child, $id, $version, $payload], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]);

        $row = $this->connection->fetchAssociative('SELECT id, parent_id, parent_version_id, payload FROM sw_type_child WHERE parent_id IN (?)', [[$id, $child]], [ArrayParameterType::BINARY]);
        static::assertIsArray($row);
        $parentHex = Uuid::fromBytesToHex($row['parent_id']);
        static::assertSame($child, self::bytes($row['id']));
        static::assertSame($id, Uuid::fromHexToBytes($parentHex));
        static::assertSame($version, self::bytes($row['parent_version_id']));
        static::assertSame($payload, (new BlobFieldSerializer())->decode(new BlobField('payload', 'payload'), $row['payload']));
        static::assertSame('000102030405060708090a0b0c0d0e0f', $parentHex);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_child WHERE parent_id IN (?)', [[]], [ArrayParameterType::BINARY]));

        try {
            $this->connection->executeStatement('INSERT INTO sw_type_child (id, parent_id, parent_version_id, payload) VALUES (?, ?, ?, ?)', [Uuid::randomBytes(), $id, $child, $payload], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]);
            static::fail('The foreign key must include version_id');
        } catch (ForeignKeyConstraintViolationException) {
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_child'));
        }

        if ($this->postgres) {
            $this->expectException(DriverException::class);
            $this->connection->executeStatement('INSERT INTO sw_type_parent (id, version_id) VALUES (?, ?)', ['short', $version], [ParameterType::BINARY, ParameterType::BINARY]);
        }
    }

    public function testBooleanDatesNumbersAndIdentityRoundTrip(): void
    {
        $identity = $this->postgres ? 'BIGINT GENERATED BY DEFAULT AS IDENTITY' : 'BIGINT UNSIGNED AUTO_INCREMENT';
        $bool = $this->postgres ? 'SMALLINT DEFAULT 0 CHECK (enabled IN (0, 1))' : 'TINYINT(1) DEFAULT 0';
        $date = $this->postgres ? 'TIMESTAMP(3) WITHOUT TIME ZONE' : 'DATETIME(3)';
        $history = $this->postgres ? 'TIMESTAMP(6) WITHOUT TIME ZONE' : 'TIMESTAMP(6)';
        $money = $this->postgres ? 'NUMERIC(20, 6)' : 'DECIMAL(20, 6)';
        $float = $this->postgres ? 'DOUBLE PRECISION' : 'DOUBLE';
        $unsigned = $this->postgres ? 'BIGINT CHECK (unsigned_count BETWEEN 0 AND 4294967295)' : 'INT UNSIGNED';
        $this->connection->executeStatement("CREATE TABLE sw_type_scalar (sequence_id $identity PRIMARY KEY, enabled $bool NOT NULL, created_at $date NOT NULL, migration_at $history NOT NULL, amount $money NOT NULL, ratio $float NOT NULL, unsigned_count $unsigned NOT NULL)");

        $this->connection->executeStatement('INSERT INTO sw_type_scalar (enabled, created_at, migration_at, amount, ratio, unsigned_count) VALUES (?, ?, ?, ?, ?, ?)', [1, '2024-02-29 23:59:59.123', '2024-02-29 23:59:59.123456', '123456789.123456', 0.125, '4294967295'], [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        $row = $this->connection->fetchAssociative('SELECT * FROM sw_type_scalar');
        static::assertIsArray($row);
        static::assertSame(1, (int) $row['sequence_id']);
        static::assertSame(1, (int) $row['enabled']);
        static::assertSame('2024-02-29 23:59:59.123', $row['created_at']);
        static::assertSame('2024-02-29 23:59:59.123456', $row['migration_at']);
        static::assertSame('123456789.123456', $row['amount']);
        static::assertEqualsWithDelta(0.125, (float) $row['ratio'], 0.0000001);
        static::assertSame(4294967295, (int) $row['unsigned_count']);

        $this->connection->executeStatement('INSERT INTO sw_type_scalar (enabled, created_at, migration_at, amount, ratio, unsigned_count) VALUES (?, ?, ?, ?, ?, ?)', [0, '2024-02-29 23:59:59.124', '2024-02-29 23:59:59.123457', '0.000001', 0.0, 0], [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT MAX(sequence_id) FROM sw_type_scalar'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT enabled FROM sw_type_scalar WHERE sequence_id = 2'));

        $invalidBoolean = [2, '2024-02-29 23:59:59.125', '2024-02-29 23:59:59.123458', '0.000001', 0.0, 0];
        if ($this->postgres) {
            try {
                $this->connection->executeStatement('INSERT INTO sw_type_scalar (enabled, created_at, migration_at, amount, ratio, unsigned_count) VALUES (?, ?, ?, ?, ?, ?)', $invalidBoolean);
                static::fail('PostgreSQL boolean representation accepts only 0 and 1');
            } catch (DriverException) {
                static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_scalar'));
            }
        } else {
            $this->connection->executeStatement('INSERT INTO sw_type_scalar (enabled, created_at, migration_at, amount, ratio, unsigned_count) VALUES (?, ?, ?, ?, ?, ?)', $invalidBoolean);
            static::assertSame(2, (int) $this->connection->fetchOne('SELECT enabled FROM sw_type_scalar WHERE sequence_id = 3'));
        }

        try {
            $this->connection->executeStatement('INSERT INTO sw_type_scalar (enabled, created_at, migration_at, amount, ratio, unsigned_count) VALUES (?, ?, ?, ?, ?, ?)', [1, '2024-02-29 23:59:59.126', '2024-02-29 23:59:59.123459', '0.000001', 0.0, -1]);
            static::fail('Unsigned columns must reject negative values');
        } catch (DriverException) {
            static::assertSame($this->postgres ? 2 : 3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_scalar'));
        }
    }

    public function testJsonValueTypesAndDuplicateKeyCanonicalization(): void
    {
        $json = $this->postgres ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE sw_type_scalar (document $json NOT NULL)");
        $input = '{"b":true,"a":null,"n":12345678901234567890,"a":"last","items":[1,2.5,"x"],"nullValue":null}';
        $this->connection->executeStatement('INSERT INTO sw_type_scalar (document) VALUES (?)', [$input], [ParameterType::STRING]);
        $raw = $this->connection->fetchOne('SELECT document FROM sw_type_scalar');
        static::assertIsString($raw);
        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        static::assertSame('last', $decoded['a']);
        static::assertTrue($decoded['b']);
        static::assertSame('12345678901234567890', $decoded['n']);
        static::assertSame([1, 2.5, 'x'], $decoded['items']);
        static::assertArrayHasKey('nullValue', $decoded);
        static::assertNull($decoded['nullValue']);
        static::assertCount(5, $decoded);

        $publicDecoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsFloat($publicDecoded['n']);

        if ($this->postgres) {
            static::assertSame('number', $this->connection->fetchOne('SELECT jsonb_typeof(\'1e400\'::jsonb)'));
        } else {
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT JSON_VALID(\'1e400\')'));
        }
    }

    public function testTextEqualityLikeUniqueAndNullOrdering(): void
    {
        if ($this->postgres) {
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
            $this->connection->executeStatement('CREATE TABLE sw_type_text (value TEXT COLLATE sw_type_ci UNIQUE)');
        } else {
            $this->connection->executeStatement('CREATE TABLE sw_type_text (value VARCHAR(255) COLLATE utf8mb4_unicode_ci UNIQUE)');
        }

        $this->connection->executeStatement('INSERT INTO sw_type_text (value) VALUES (?), (?), (?)', ['café', 'Straße', null]);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_text WHERE value = ?', ['CAFE']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_text WHERE value LIKE ?', ['CAF%']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_text WHERE value = ?', ['strasse']));
        static::assertSame([null, 'café', 'Straße'], $this->connection->fetchFirstColumn('SELECT value FROM sw_type_text ORDER BY CASE WHEN value IS NULL THEN 0 ELSE 1 END, value'));
        static::assertSame($this->postgres ? ['café', 'Straße', null] : [null, 'café', 'Straße'], $this->connection->fetchFirstColumn('SELECT value FROM sw_type_text ORDER BY value'));

        try {
            $this->connection->executeStatement('INSERT INTO sw_type_text (value) VALUES (?)', ['cafe']);
            static::fail('Case/accent equivalent text must violate unique constraint');
        } catch (UniqueConstraintViolationException) {
            static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_type_text'));
        }
    }

    private static function bytes(mixed $value): string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        static::assertIsString($value);

        return $value;
    }
}

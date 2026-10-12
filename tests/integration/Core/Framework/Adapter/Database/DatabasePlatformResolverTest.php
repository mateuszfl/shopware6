<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialectException;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\MySQLDialect;
use Shopware\Core\Framework\Adapter\Database\PostgreSQLDialect;
use Shopware\Core\Framework\Log\Package;

/**
 * Run with --no-configuration --bootstrap vendor/autoload.php and both
 * DIALECT_MYSQL_URL and DIALECT_POSTGRES_URL pointing at disposable *_test databases.
 *
 * @internal
 */
#[Package('framework')]
class DatabasePlatformResolverTest extends TestCase
{
    public function testTwoConnectionsKeepTheirOwnDialectAndBindValues(): void
    {
        $mysql = self::connection('DIALECT_MYSQL_URL');
        $postgres = self::connection('DIALECT_POSTGRES_URL');

        (new DatabaseFunctionInstaller())->install($mysql);
        (new DatabaseFunctionInstaller())->install($postgres);
        try {
            $mysqlDialect = (new DatabasePlatformResolver($mysql))->dialect();
            $postgresDialect = (new DatabasePlatformResolver($postgres))->dialect();
            static::assertInstanceOf(MySQLDialect::class, $mysqlDialect);
            static::assertInstanceOf(PostgreSQLDialect::class, $postgresDialect);
            static::assertSame('`value.with.dot`', $mysqlDialect->quoteIdentifier('value.with.dot'));
            static::assertSame('"value.with.dot"', $postgresDialect->quoteIdentifier('value.with.dot'));
            static::assertSame('`table`.`column`', $mysqlDialect->quoteQualifiedIdentifier(['table', 'column']));
            static::assertSame('"table"."column"', $postgresDialect->quoteQualifiedIdentifier(['table', 'column']));
            static::assertSame('sw_uuid_hex(`source`.`value.with.dot`)', $mysqlDialect->hex(['source', 'value.with.dot']));
            static::assertSame('sw_uuid_hex("source"."value.with.dot")', $postgresDialect->hex(['source', 'value.with.dot']));
            static::assertSame('`value.with.dot`', (new DatabasePlatformResolver($mysql))->dialect()->quoteIdentifier('value.with.dot'));
            static::assertFalse((new DatabasePlatformResolver($mysql))->capabilities()->supportsConflictTarget);
            static::assertTrue((new DatabasePlatformResolver($postgres))->capabilities()->supportsConflictTarget);

            foreach ([[$mysql, $mysqlDialect], [$postgres, $postgresDialect]] as [$connection, $dialect]) {
                $json = $dialect->jsonObject(['a\'b' => 'value.with.dot']);
                static::assertSame(['a\'b'], $json->parameters);
                static::assertSame([ParameterType::STRING], $json->types);
                $result = $connection->fetchOne('SELECT ' . $json->sql . ' FROM (SELECT ? AS ' . $dialect->quoteIdentifier('value.with.dot') . ') source', [...$json->parameters, 'safe'], [...$json->types, ParameterType::STRING]);
                static::assertIsString($result);
                static::assertSame(['a\'b' => 'safe'], json_decode($result, true, 512, \JSON_THROW_ON_ERROR));

                $hex = $dialect->hex('value.with.dot');
                $binary = "\x00\xff\x10";
                $binaryPlaceholder = $dialect instanceof PostgreSQLDialect ? '?::bytea' : '?';
                $encoded = $connection->fetchOne('SELECT ' . $hex . ' FROM (SELECT ' . $binaryPlaceholder . ' AS ' . $dialect->quoteIdentifier('value.with.dot') . ') source', [$binary], [ParameterType::BINARY]);
                static::assertSame('00ff10', $encoded);
                static::assertSame($binary, self::bytes($connection->fetchOne('SELECT ' . $dialect->fromHex('?'), ['00ff10'], [ParameterType::STRING])));

                $date = $dialect->dateAddSeconds('value.with.dot', 60);
                static::assertSame([60], $date->parameters);
                $datePlaceholder = $dialect instanceof PostgreSQLDialect ? '?::timestamp' : '?';
                static::assertSame('2024-01-01 00:01:00', $connection->fetchOne('SELECT ' . $date->sql . ' FROM (SELECT ' . $datePlaceholder . ' AS ' . $dialect->quoteIdentifier('value.with.dot') . ') source', [...$date->parameters, '2024-01-01 00:00:00'], [...$date->types, ParameterType::STRING]));

                $key = 'key.with"quote\' OR 1=1 --';
                $path = $dialect->jsonExtract('value.with.dot', [$key]);
                $document = json_encode([$key => 'yes'], \JSON_THROW_ON_ERROR);
                $extracted = $connection->fetchOne('SELECT ' . $path->sql . ' FROM (SELECT ? AS ' . $dialect->quoteIdentifier('value.with.dot') . ') source', [...$path->parameters, $document], [...$path->types, ParameterType::STRING]);
                static::assertSame('"yes"', $extracted);

                $aggregate = $dialect->stringAggregate('value.with.dot', 'ordering', '|');
                $rows = '(SELECT ? AS ' . $dialect->quoteIdentifier('value.with.dot') . ', 2 AS ordering UNION ALL SELECT ?, 1) source';
                static::assertSame('first|second', $connection->fetchOne('SELECT ' . $aggregate->sql . ' FROM ' . $rows, [...$aggregate->parameters, 'second', 'first'], [...$aggregate->types, ParameterType::STRING, ParameterType::STRING]));
            }
        } finally {
            $mysql->close();
            $postgres->close();
        }
    }

    public function testUpsertAndJoinedGatewayQueries(): void
    {
        foreach (['DIALECT_MYSQL_URL', 'DIALECT_POSTGRES_URL'] as $urlName) {
            $connection = self::connection($urlName);
            $dialect = (new DatabasePlatformResolver($connection))->dialect();

            try {
                $connection->executeStatement('CREATE TEMPORARY TABLE sw_dialect_target (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
                $connection->executeStatement('CREATE TEMPORARY TABLE sw_dialect_joined (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
                $connection->insert('sw_dialect_joined', ['id' => 1, 'value' => 'join']);

                foreach (['first', 'second'] as $value) {
                    $statement = $dialect->upsert('sw_dialect_target', ['id' => 1, 'value' => $value], ['id'], ['value']);
                    static::assertSame([ParameterType::INTEGER, ParameterType::STRING], $statement->types);
                    $connection->executeStatement($statement->sql, $statement->parameters, $statement->types);
                }

                static::assertSame('second', $connection->fetchOne('SELECT value FROM sw_dialect_target WHERE id = 1'));

                $condition = 'target.id = joined.id AND joined.value = ?';
                $update = $dialect->queryShape()->updateJoined('sw_dialect_target', 'sw_dialect_joined', $condition, ['value' => 'updated'], ['join']);
                $connection->executeStatement($update->sql, $update->parameters, $update->types);
                static::assertSame('updated', $connection->fetchOne('SELECT value FROM sw_dialect_target WHERE id = 1'));

                $delete = $dialect->queryShape()->deleteJoined('sw_dialect_target', 'sw_dialect_joined', $condition, ['join']);
                $connection->executeStatement($delete->sql, $delete->parameters, $delete->types);
                static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM sw_dialect_target'));
            } finally {
                $connection->close();
            }
        }
    }

    public function testUnsupportedDriverIsRejectedBeforeConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->expectExceptionObject(DatabaseDialectException::unsupportedDriver('pdo_sqlite'));
        (new DatabasePlatformResolver($connection))->dialect();
    }

    public function testUnsupportedPlatformIsRejectedForSupportedDriver(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'wrapperClass' => UnsupportedPlatformConnection::class]);
        $this->expectExceptionObject(DatabaseDialectException::unsupportedPlatform(SQLitePlatform::class));
        (new DatabasePlatformResolver($connection))->dialect();
    }

    public function testMariaDbDialectCanBeResolvedBeforeConnecting(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '10.11.0-MariaDB']);
        $resolver = new DatabasePlatformResolver($connection);

        static::assertFalse($connection->isConnected());
        static::assertInstanceOf(MySQLDialect::class, $resolver->dialect());
        static::assertFalse($connection->isConnected());
    }

    public function testIdentifiersCannotContainSqlSyntax(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $dialect = (new DatabasePlatformResolver($connection))->dialect();

        $this->expectExceptionObject(DatabaseDialectException::invalidIdentifier('name; DROP TABLE users'));
        $dialect->quoteIdentifier('name; DROP TABLE users');
    }

    private static function connection(string $environmentName): Connection
    {
        $url = getenv($environmentName);
        static::assertIsString($url, $environmentName . ' must point at a disposable test database');
        $database = parse_url($url, \PHP_URL_PATH);
        static::assertIsString($database);
        static::assertStringEndsWith('_test', $database);

        return DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($url));
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

/**
 * @internal
 */
class UnsupportedPlatformConnection extends Connection
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new SQLitePlatform();
    }
}

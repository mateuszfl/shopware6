<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialectException;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Requires DATABASE_FUNCTION_INSTALL_URL with a maintenance account on a disposable test database.
 *
 * @internal
 */
#[Package('framework')]
class DatabaseFunctionInstallerTest extends TestCase
{
    private Connection $connection;

    private string $schema;

    private string $originalSchema;

    private bool $postgres;

    protected function setUp(): void
    {
        $url = getenv('DATABASE_FUNCTION_INSTALL_URL');
        if (!\is_string($url) || $url === '') {
            throw new \RuntimeException('DATABASE_FUNCTION_INSTALL_URL is required for the database function maintenance contract');
        }
        $parameters = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $this->connection = DriverManager::getConnection($parameters);
        $database = $this->connection->getDatabase();
        static::assertIsString($database);
        static::assertStringEndsWith('_test', $database);
        $this->postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $this->schema = 'sw_function_' . Uuid::randomHex() . '"quoted';
        $this->originalSchema = $this->postgres ? (string) $this->connection->fetchOne('SELECT current_schema()') : $database;
        $quoted = $this->connection->quoteSingleIdentifier($this->schema);
        $this->connection->executeStatement($this->postgres ? 'CREATE SCHEMA ' . $quoted : 'CREATE DATABASE ' . $quoted);
        $this->connection->executeStatement($this->postgres ? 'SET search_path TO ' . $quoted : 'USE ' . $quoted);
    }

    protected function tearDown(): void
    {
        if (!isset($this->schema)) {
            return;
        }
        $quoted = $this->connection->quoteSingleIdentifier($this->originalSchema);
        $this->connection->executeStatement($this->postgres ? 'SET search_path TO ' . $quoted : 'USE ' . $quoted);
        $quoted = $this->connection->quoteSingleIdentifier($this->schema);
        $this->connection->executeStatement($this->postgres ? 'DROP SCHEMA ' . $quoted . ' CASCADE' : 'DROP DATABASE ' . $quoted);
        $this->connection->close();
    }

    public function testInstallPreservesNullAndBinaryValuesAndCanBeRepeated(): void
    {
        $installer = new DatabaseFunctionInstaller();
        $installer->install($this->connection);
        $installer->install($this->connection);

        foreach (['', "\x00\xff\x80", str_repeat("\x00", 16), str_repeat("\xff", 16), Uuid::randomBytes()] as $bytes) {
            static::assertSame(bin2hex($bytes), $this->connection->fetchOne('SELECT sw_uuid_hex(?)', [$bytes], [ParameterType::BINARY]));
        }
        static::assertNull($this->connection->fetchOne('SELECT sw_uuid_hex(NULL)'));
        $this->connection->close();
        static::assertSame('00ff', $this->connection->fetchOne('SELECT ' . $this->connection->quoteSingleIdentifier($this->schema) . '.sw_uuid_hex(?)', ["\x00\xff"], [ParameterType::BINARY]));
    }

    public function testConflictingFunctionIsRejectedWithoutReplacement(): void
    {
        $sql = $this->postgres
            ? 'CREATE FUNCTION sw_uuid_hex(value bytea) RETURNS text LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE RETURN \'wrong\'::text'
            : 'CREATE FUNCTION sw_uuid_hex(value VARBINARY(16)) RETURNS VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DETERMINISTIC NO SQL SQL SECURITY INVOKER RETURN \'wrong\'';
        $this->connection->executeStatement($sql);

        try {
            (new DatabaseFunctionInstaller())->install($this->connection);
            static::fail('An incompatible function must not be accepted');
        } catch (DatabaseDialectException $exception) {
            static::assertSame('The installed sw_uuid_hex function does not satisfy the lowercase binary UUID contract', $exception->getMessage());
        }
        static::assertSame('wrong', $this->connection->fetchOne('SELECT sw_uuid_hex(?)', [Uuid::randomBytes()], [ParameterType::BINARY]));
    }

    public function testExistingFunctionCanBeVerifiedInAReadOnlyTransaction(): void
    {
        $installer = new DatabaseFunctionInstaller();
        $installer->install($this->connection);
        if (!$this->postgres) {
            $this->connection->executeStatement('SET TRANSACTION READ ONLY');
        }
        $this->connection->beginTransaction();
        try {
            if ($this->postgres) {
                $this->connection->executeStatement('SET TRANSACTION READ ONLY');
            }
            $installer->install($this->connection);
            static::assertSame('ff', $this->connection->fetchOne('SELECT sw_uuid_hex(?)', ["\xff"], [ParameterType::BINARY]));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testIncompatibleSecurityMetadataIsRejected(): void
    {
        $sql = $this->postgres
            ? 'CREATE FUNCTION sw_uuid_hex(value bytea) RETURNS text LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE SECURITY DEFINER RETURN pg_catalog.encode(value, \'hex\')'
            : 'CREATE FUNCTION sw_uuid_hex(value VARBINARY(16)) RETURNS VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DETERMINISTIC NO SQL SQL SECURITY DEFINER RETURN LOWER(HEX(value))';
        $this->connection->executeStatement($sql);

        $this->expectExceptionObject(DatabaseDialectException::functionInstallationFailed('The installed sw_uuid_hex function has incompatible metadata'));
        (new DatabaseFunctionInstaller())->install($this->connection);
    }

    public function testRuntimeAccountCanUseInstalledFunctionWithoutCreatingRoutines(): void
    {
        (new DatabaseFunctionInstaller())->install($this->connection);
        $account = 'sw_function_' . substr(Uuid::randomHex(), 0, 16);
        $password = Uuid::randomHex();
        $quotedSchema = $this->connection->quoteSingleIdentifier($this->schema);
        $runtime = null;
        if ($this->postgres) {
            $quotedAccount = $this->connection->quoteSingleIdentifier($account);
            $this->connection->executeStatement('CREATE ROLE ' . $quotedAccount);
        } else {
            $quotedAccount = $this->connection->quote($account) . '@\'%\'';
            $this->connection->executeStatement('CREATE USER ' . $quotedAccount . ' IDENTIFIED BY ' . $this->connection->quote($password));
        }
        try {
            if ($this->postgres) {
                $this->connection->executeStatement('GRANT USAGE ON SCHEMA ' . $quotedSchema . ' TO ' . $quotedAccount);
                $this->connection->executeStatement('SET ROLE ' . $quotedAccount);
                $runtime = $this->connection;
                static::assertFalse($runtime->fetchOne('SELECT has_schema_privilege(current_user, ?, \'CREATE\')', [$this->schema]));
            } else {
                $this->connection->executeStatement('GRANT SELECT, EXECUTE ON ' . $quotedSchema . '.* TO ' . $quotedAccount);
                $runtime = DriverManager::getConnection(array_replace($this->connection->getParams(), ['user' => $account, 'password' => $password, 'dbname' => $this->schema]));
                $grants = implode(' ', $runtime->fetchFirstColumn('SHOW GRANTS'));
                static::assertStringNotContainsString('CREATE ROUTINE', $grants);
            }
            static::assertSame('00ff80', $runtime->fetchOne('SELECT sw_uuid_hex(?)', ["\x00\xff\x80"], [ParameterType::BINARY]));
            (new DatabaseFunctionInstaller())->install($runtime);
        } finally {
            if ($this->postgres) {
                $this->connection->executeStatement('RESET ROLE');
                $this->connection->executeStatement('REVOKE USAGE ON SCHEMA ' . $quotedSchema . ' FROM ' . $quotedAccount);
                $this->connection->executeStatement('DROP ROLE ' . $quotedAccount);
            } else {
                $runtime?->close();
                $this->connection->executeStatement('DROP USER ' . $quotedAccount);
            }
        }
    }
}

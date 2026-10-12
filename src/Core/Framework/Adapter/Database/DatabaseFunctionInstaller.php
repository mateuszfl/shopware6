<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;

/**
 * Maintenance prerequisite, never called by connection creation or request handling.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Database\DatabaseFunctionInstallerTest
 */
#[Package('framework')]
final class DatabaseFunctionInstaller
{
    public function install(Connection $connection): void
    {
        $dialect = (new DatabasePlatformResolver($connection))->dialect();
        if ($dialect->capabilities()->supportsJsonb) {
            $schema = $connection->fetchOne('SELECT current_schema()');
            if (!\is_string($schema)) {
                throw DatabaseDialectException::functionInstallationFailed('A writable application schema is required to install database functions');
            }
            $name = $connection->quoteSingleIdentifier($schema) . '.sw_uuid_hex';
            $metadata = $connection->fetchAssociative(
                'SELECT p.prokind, p.prorettype, p.provolatile, p.proisstrict, p.proparallel, p.prosecdef FROM pg_catalog.pg_proc p INNER JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = :schema AND p.proname = :name AND p.proargtypes = \'17\'::oidvector',
                ['schema' => $schema, 'name' => 'sw_uuid_hex'],
            );
            if ($metadata === false) {
                $connection->executeStatement(\sprintf(<<<'SQL'
                    CREATE FUNCTION %s(value bytea) RETURNS text
                    LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE SECURITY INVOKER
                    RETURN pg_catalog.encode(value, 'hex')
                    SQL, $name));
            } elseif ($metadata['prokind'] !== 'f' || (int) $metadata['prorettype'] !== 25
                || $metadata['provolatile'] !== 'i' || !$metadata['proisstrict']
                || $metadata['proparallel'] !== 's' || $metadata['prosecdef']) {
                throw DatabaseDialectException::functionInstallationFailed('The installed sw_uuid_hex function has incompatible metadata');
            }
        } else {
            $schema = $connection->getDatabase();
            if ($schema === null) {
                throw DatabaseDialectException::functionInstallationFailed('An application database is required to install database functions');
            }
            $name = $connection->quoteSingleIdentifier($schema) . '.sw_uuid_hex';
            $metadata = $connection->fetchAssociative(
                'SELECT IS_DETERMINISTIC, SQL_DATA_ACCESS, SECURITY_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :schema AND ROUTINE_NAME = :name AND ROUTINE_TYPE = \'FUNCTION\'',
                ['schema' => $schema, 'name' => 'sw_uuid_hex'],
            );
            if ($metadata === false) {
                $connection->executeStatement(\sprintf(<<<'SQL'
                    CREATE FUNCTION %s(value VARBINARY(16)) RETURNS VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin
                    DETERMINISTIC NO SQL SQL SECURITY INVOKER
                    RETURN LOWER(HEX(value))
                    SQL, $name));
            } else {
                $parameters = $connection->fetchAllAssociative(
                    'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = :schema AND SPECIFIC_NAME = :name AND ORDINAL_POSITION > 0 ORDER BY ORDINAL_POSITION',
                    ['schema' => $schema, 'name' => 'sw_uuid_hex'],
                );
                if ($metadata['IS_DETERMINISTIC'] !== 'YES' || $metadata['SQL_DATA_ACCESS'] !== 'NO SQL'
                    || $metadata['SECURITY_TYPE'] !== 'INVOKER' || $metadata['DATA_TYPE'] !== 'varchar'
                    || (int) $metadata['CHARACTER_MAXIMUM_LENGTH'] !== 32 || $metadata['CHARACTER_SET_NAME'] !== 'ascii'
                    || $metadata['COLLATION_NAME'] !== 'ascii_bin' || \count($parameters) !== 1
                    || $parameters[0]['DATA_TYPE'] !== 'varbinary' || (int) $parameters[0]['CHARACTER_MAXIMUM_LENGTH'] !== 16) {
                    throw DatabaseDialectException::functionInstallationFailed('The installed sw_uuid_hex function has incompatible metadata');
                }
            }
        }

        // Existing functions must satisfy the same contract; never silently accept a conflicting routine.
        foreach (['', "\x00\xff\x80", str_repeat("\x00", 16), str_repeat("\xff", 16), "\x00\xff\x80" . str_repeat("\x12", 13)] as $bytes) {
            $hex = $connection->fetchOne('SELECT ' . $name . '(?)', [$bytes], [ParameterType::BINARY]);
            if ($hex !== bin2hex($bytes)) {
                throw DatabaseDialectException::functionInstallationFailed('The installed sw_uuid_hex function does not satisfy the lowercase binary UUID contract');
            }
        }
        if ($connection->fetchOne('SELECT ' . $name . '(NULL)') !== null) {
            throw DatabaseDialectException::functionInstallationFailed('The installed sw_uuid_hex function does not preserve NULL');
        }
    }
}

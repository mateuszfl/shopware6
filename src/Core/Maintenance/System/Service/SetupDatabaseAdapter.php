<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\MaintenanceException;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Maintenance\System\Service\SetupDatabaseAdapterTest
 */
#[Package('framework')]
class SetupDatabaseAdapter
{
    public function dropDatabase(Connection $connection, string $database): void
    {
        $connection->executeStatement('DROP DATABASE IF EXISTS ' . $connection->quoteSingleIdentifier($database));
    }

    public function createDatabase(Connection $connection, string $database): void
    {
        $postgres = $connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        if ($postgres && \in_array($database, $this->getExistingDatabases($connection, []), true)) {
            return;
        }

        $sql = 'CREATE DATABASE ' . ($postgres ? '' : 'IF NOT EXISTS ') . $connection->quoteSingleIdentifier($database);
        if (!$postgres) {
            $sql .= ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        $connection->executeStatement($sql);
    }

    public function initializeShopwareDb(Connection $connection, ?string $database = null): bool
    {
        if ($database !== null && $database !== $connection->getDatabase()) {
            $target = $this->connectToDatabase($connection, $database);
            try {
                return $this->initializeShopwareDb($target);
            } finally {
                $target->close();
            }
        }

        if (!$hasShopwareTables = $this->hasShopwareTables($connection)) {
            foreach ((new DatabaseSchema($connection))->prerequisites() as $query) {
                $connection->executeStatement($query);
            }
            $connection->executeStatement($this->getBaseSchema($connection));
        }

        (new DatabaseFunctionInstaller())->install($connection);

        return !$hasShopwareTables;
    }

    public function hasShopwareTables(Connection $connection, ?string $database = null): bool
    {
        if ($database !== null && $database !== $connection->getDatabase()) {
            $target = $this->connectToDatabase($connection, $database);
            try {
                return $this->hasShopwareTables($target);
            } finally {
                $target->close();
            }
        }

        return $connection->createSchemaManager()->tablesExist(['migration']);
    }

    public function getTableCount(Connection $connection, string $database): int
    {
        $target = $this->connectToDatabase($connection, $database);
        try {
            return \count($target->createSchemaManager()->introspectTableNames());
        } finally {
            $target->close();
        }
    }

    /**
     * @param list<string> $ignoredSchemas
     *
     * @return array<string>
     */
    public function getExistingDatabases(Connection $connection, array $ignoredSchemas): array
    {
        // DBAL's MySQL metadata provider requires a selected database even when listing databases.
        // The maintenance connection deliberately has none, so read the same metadata directly.
        $databases = $connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? array_map(static fn (UnqualifiedName $database): string => $database->getIdentifier()->getValue(), $connection->createSchemaManager()->introspectDatabaseNames())
            : $connection->fetchFirstColumn('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');

        return array_values(array_diff($databases, $ignoredSchemas));
    }

    private function connectToDatabase(Connection $connection, string $database): Connection
    {
        $parameters = $connection->getParams();
        $parameters['dbname'] = $database;

        return DriverManager::getConnection($parameters, $connection->getConfiguration());
    }

    private function getBaseSchema(Connection $connection): string
    {
        $kernelClass = new \ReflectionClass(Kernel::class);
        $directory = \dirname((string) $kernelClass->getFileName());

        $path = $directory . ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? '/schema.postgresql.sql' : '/schema.sql');
        if (!is_file($path) || !is_readable($path)) {
            throw MaintenanceException::couldNotReadFile($path);
        }

        return (string) file_get_contents($path);
    }
}

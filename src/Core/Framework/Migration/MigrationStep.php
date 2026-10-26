<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Deprecation\BCChange\ExceptionChange;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;

#[Package('framework')]
abstract class MigrationStep
{
    use AddColumnTrait;

    final public const INSTALL_ENVIRONMENT_VARIABLE = 'SHOPWARE_INSTALL';

    private const MAX_INT_32_BIT = 2147483647;

    /**
     * get creation timestamp
     */
    abstract public function getCreationTimestamp(): int;

    /**
     * update non-destructive changes
     */
    abstract public function update(Connection $connection): void;

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
    }

    public function getPlausibleCreationTimestamp(): int
    {
        $creationTime = $this->getCreationTimestamp();

        if ($creationTime < 1 || $creationTime >= self::MAX_INT_32_BIT) {
            throw MigrationException::implausibleCreationTimestamp($creationTime, $this);
        }

        return $creationTime;
    }

    public function removeTrigger(Connection $connection, string $name): void
    {
        try {
            if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $tables = $connection->fetchFirstColumn('SELECT quote_ident(n.nspname) || \'.\' || quote_ident(c.relname) FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE t.tgname = ? AND NOT t.tgisinternal AND n.nspname = current_schema()', [$name]);
                foreach ($tables as $table) {
                    $connection->executeStatement('DROP TRIGGER IF EXISTS ' . $connection->getDatabasePlatform()->quoteSingleIdentifier($name) . ' ON ' . $table);
                }
            } else {
                $connection->executeStatement('DROP TRIGGER IF EXISTS ' . $connection->getDatabasePlatform()->quoteSingleIdentifier($name));
            }
        } catch (Exception) {
        }
    }

    public function isInstallation(): bool
    {
        return (bool) EnvironmentHelper::getVariable(self::INSTALL_ENVIRONMENT_VARIABLE, false);
    }

    /**
     * Create a PostgreSQL trigger function used by a migration trigger. The function body is a
     * trusted migration definition, just like the SQL passed to createTrigger().
     */
    protected function createTriggerFunction(Connection $connection, string $name, string $body): void
    {
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            throw MigrationException::invalidArgument('Trigger functions are only supported on PostgreSQL.');
        }

        if ((int) EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', false) === 0) {
            return;
        }

        $quotedName = $connection->getDatabasePlatform()->quoteSingleIdentifier($name);
        $delimiter = '$shopware_' . bin2hex(random_bytes(8)) . '$';
        $connection->transactional(static function (Connection $connection) use ($quotedName, $delimiter, $body): void {
            $connection->executeStatement('CREATE FUNCTION ' . $quotedName . '() RETURNS trigger LANGUAGE plpgsql AS ' . $delimiter . $body . $delimiter);
            $connection->executeStatement('COMMENT ON FUNCTION ' . $quotedName . '() IS \'shopware:migration-trigger\'');
        });
    }

    /**
     * Drop only functions created by createTriggerFunction().
     */
    protected function dropTriggerFunctionIfExists(Connection $connection, string $name): bool
    {
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return false;
        }

        $owned = $connection->fetchOne('SELECT 1 FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE p.proname = ? AND p.pronargs = 0 AND n.nspname = current_schema() AND obj_description(p.oid, \'pg_proc\') = \'shopware:migration-trigger\'', [$name]);
        if ($owned === false) {
            return false;
        }

        $connection->executeStatement('DROP FUNCTION ' . $connection->getDatabasePlatform()->quoteSingleIdentifier($name) . '()');

        return true;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function createTrigger(Connection $connection, string $query, array $params = []): void
    {
        $blueGreenDeployment = EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', false);
        if ((int) $blueGreenDeployment === 0) {
            return;
        }

        $connection->executeStatement($query, $params);
    }

    /**
     * @param list<string> $indexerToRun
     */
    protected function registerIndexer(Connection $connection, string $name, array $indexerToRun = []): void
    {
        IndexerQueuer::registerIndexer($connection, $name, $indexerToRun);
    }

    /**
     * @param non-empty-string $table
     */
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [], description: 'Will no longer throw TableNotFoundException for a missing table but return false.')]
    protected function indexExists(Connection $connection, string $table, string $index): bool
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform || Feature::isActive('v6.8.0.0')) {
            return TableHelper::indexExists($connection, $table, $index);
        }

        return (bool) $connection->fetchOne(
            'SHOW INDEXES FROM `' . $table . '` WHERE `key_name` LIKE :index',
            ['index' => $index]
        );
    }

    /**
     * @see NonStandardFkGuard
     *
     * @internal Temporary workaround, will be removed once MySQL fixes bug #118151; safe to call
     *           from extension migrations in the meantime.
     */
    protected function executeDdlStatement(Connection $connection, string $sql): void
    {
        NonStandardFkGuard::executeDdl($connection, $sql);
    }

    protected function dropTableIfExists(Connection $connection, string $table): void
    {
        $sql = $connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? 'DROP TABLE IF EXISTS ' . $connection->getDatabasePlatform()->quoteSingleIdentifier($table)
            : \sprintf('DROP TABLE IF EXISTS `%s`', $table);
        $connection->executeStatement($sql);
    }

    /**
     * @param non-empty-string $table
     *
     * @return bool - Returns true when the column has really been deleted
     */
    protected function dropColumnIfExists(Connection $connection, string $table, string $columnName): bool
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if (!TableHelper::columnExists($connection, $table, $columnName)) {
                return false;
            }

            $quote = $connection->getDatabasePlatform()->quoteSingleIdentifier(...);
            NonStandardFkGuard::executeDdl($connection, 'ALTER TABLE ' . $quote($table) . ' DROP COLUMN ' . $quote($columnName));

            return true;
        }

        try {
            NonStandardFkGuard::executeDdl($connection, \sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $columnName));
        } catch (\Throwable $e) {
            if ($e instanceof TableNotFoundException) {
                return false;
            }

            // column does not exist
            if (str_contains($e->getMessage(), 'SQLSTATE[42000]')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param non-empty-string $table
     *
     * @return bool - Returns true when the foreign key has really been deleted
     */
    protected function dropForeignKeyIfExists(Connection $connection, string $table, string $foreignKeyName): bool
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if (!TableHelper::foreignKeyExists($connection, $table, $foreignKeyName)) {
                return false;
            }

            $quote = $connection->getDatabasePlatform()->quoteSingleIdentifier(...);
            NonStandardFkGuard::executeDdl($connection, 'ALTER TABLE ' . $quote($table) . ' DROP CONSTRAINT ' . $quote($foreignKeyName));

            return true;
        }

        $sql = \sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $foreignKeyName);

        try {
            NonStandardFkGuard::executeDdl($connection, $sql);
        } catch (\Throwable $e) {
            if ($e instanceof TableNotFoundException) {
                return false;
            }

            // fk does not exist
            if (str_contains($e->getMessage(), 'SQLSTATE[42000]')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param non-empty-string $table
     *
     * @return bool - Returns true when the index has really been deleted
     */
    protected function dropIndexIfExists(Connection $connection, string $table, string $indexName): bool
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if (!TableHelper::indexExists($connection, $table, $indexName)) {
                return false;
            }

            $schema = $connection->fetchOne('SELECT current_schema()');
            if (!\is_string($schema) || $schema === '') {
                return false;
            }
            $platform = $connection->getDatabasePlatform();
            NonStandardFkGuard::executeDdl($connection, 'DROP INDEX ' . $platform->quoteSingleIdentifier($schema) . '.' . $platform->quoteSingleIdentifier($indexName));

            return true;
        }

        $sql = \sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $indexName);

        try {
            NonStandardFkGuard::executeDdl($connection, $sql);
        } catch (\Throwable $e) {
            if ($e instanceof TableNotFoundException) {
                return false;
            }

            // index does not exist
            if (str_contains($e->getMessage(), 'SQLSTATE[42000]')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param array<string, array<string>> $privileges
     *
     * @throws ConnectionException
     * @throws Exception
     * @throws \JsonException
     */
    protected function addAdditionalPrivileges(Connection $connection, array $privileges): void
    {
        $roles = $connection->iterateAssociative('SELECT * from acl_role');

        try {
            $connection->beginTransaction();

            foreach ($roles as $role) {
                if (\is_resource($role['id'])) {
                    $binaryId = stream_get_contents($role['id']);
                    if ($binaryId === false) {
                        throw MigrationException::migrationError('Unable to read ACL role identifier while updating privileges.');
                    }
                    $role['id'] = $binaryId;
                }

                $currentPrivileges = \json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);
                $newPrivileges = $this->fixRolePrivileges($privileges, $currentPrivileges);

                if ($currentPrivileges === $newPrivileges) {
                    continue;
                }

                $role['privileges'] = \json_encode($newPrivileges, \JSON_THROW_ON_ERROR);
                $role['updated_at'] = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT);

                $connection->update('acl_role', $role, ['id' => $role['id']], ['id' => ParameterType::BINARY]);
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * @param array<string, array<string>> $privilegeChange
     * @param array<string> $rolePrivileges
     *
     * @return array<string>
     */
    private function fixRolePrivileges(array $privilegeChange, array $rolePrivileges): array
    {
        $rolePrivilegesToBeAdded = [];
        foreach ($privilegeChange as $existingPrivilege => $newPrivileges) {
            if (\in_array($existingPrivilege, $rolePrivileges, true)) {
                $rolePrivilegesToBeAdded[] = $newPrivileges;
            }
        }
        $rolePrivileges = \array_merge($rolePrivileges, ...$rolePrivilegesToBeAdded);

        return \array_values(\array_unique($rolePrivileges));
    }
}

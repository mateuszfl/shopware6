<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class MigrationRuntime
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly MigrationVariantRegistry $variants = new MigrationVariantRegistry(),
    ) {
    }

    /**
     * @return \Generator<class-string<MigrationStep>>
     */
    public function migrate(MigrationSource $source, ?int $until = null, ?int $limit = null): \Generator
    {
        $migrations = $this->getExecutableMigrations($source, $until, $limit);

        yield from $this->execute($migrations, false);
    }

    /**
     * @return \Generator<class-string<MigrationStep>>
     */
    public function migrateDestructive(MigrationSource $source, ?int $until = null, ?int $limit = null): \Generator
    {
        yield from $this->execute($this->getExecutableDestructiveMigrations($source, $until, $limit), true);
    }

    /**
     * @internal
     */
    public function isVariant(string $class): bool
    {
        return $this->variants->isVariant($class);
    }

    /**
     * @return array<class-string<MigrationStep>>
     */
    public function getExecutableMigrations(MigrationSource $source, ?int $until = null, ?int $limit = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($source, $until, $limit)
            ->andWhere($this->quote('update') . ' IS NULL')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /**
     * @return array<class-string<MigrationStep>>
     */
    public function getExecutableDestructiveMigrations(MigrationSource $source, ?int $until = null, ?int $limit = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($source, $until, $limit)
            ->andWhere($this->quote('update') . ' IS NOT NULL')
            ->andWhere($this->quote('update_destructive') . ' IS NULL')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    public function getTotalMigrationCount(MigrationSource $source, ?int $until = null, ?int $limit = null): int
    {
        return \count($this->getExecutableMigrationsBaseQuery($source, $until, $limit)
            ->executeQuery()
            ->fetchFirstColumn());
    }

    protected function setDefaultStorageEngine(): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('SET default_storage_engine=InnoDB');
        }
    }

    /**
     * @param array<class-string<MigrationStep>> $migrations
     *
     * @return \Generator<class-string<MigrationStep>>
     */
    private function execute(array $migrations, bool $destructive): \Generator
    {
        $postgresql = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        // A failed step must be rolled back before its error can be persisted. Do not own a caller's transaction.
        if ($postgresql && $this->connection->isTransactionActive()) {
            throw MigrationException::logicError('PostgreSQL migrations require a connection without an active transaction.');
        }

        $steps = [];
        foreach ($migrations as $logicalClass) {
            if ($postgresql) {
                $timestamp = (int) $this->connection->fetchOne(
                    'SELECT creation_timestamp FROM migration WHERE class = ?',
                    [$logicalClass]
                );
                $steps[$logicalClass] = $this->variants->resolve($logicalClass, $timestamp);
            } elseif (class_exists($logicalClass)) {
                $steps[$logicalClass] = new $logicalClass();
            } else {
                $this->logger->notice(\sprintf('Migration "%s" does not exist. Ignoring it', $logicalClass));
            }
        }

        $this->setDefaultStorageEngine();
        foreach ($steps as $logicalClass => $migration) {
            try {
                if ($postgresql) {
                    $this->connection->beginTransaction();
                }
                if ($destructive) {
                    $migration->updateDestructive($this->connection);
                } else {
                    $migration->update($this->connection);
                }
                if ($postgresql && $this->connection->getTransactionNestingLevel() !== 1) {
                    throw MigrationException::logicError('Migration changed its transaction boundary: ' . $logicalClass);
                }
                $this->setExecuted($logicalClass, $destructive);
                if ($postgresql) {
                    $this->connection->commit();
                }
            } catch (\Throwable $e) {
                if ($postgresql) {
                    while ($this->connection->isTransactionActive()) {
                        $this->connection->rollBack();
                    }
                }
                $this->logError($logicalClass, $migration, $e->getMessage());
                if (!$destructive && $e instanceof \Exception) {
                    $this->enrichException($e);
                }

                throw $e;
            }

            yield $logicalClass;
        }
    }

    private function getExecutableMigrationsBaseQuery(MigrationSource $source, ?int $until = null, ?int $limit = null): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->select($this->quote('class'))
            ->from('migration');

        if ($until !== null) {
            $query->andWhere('creation_timestamp <= :timestamp');
            $query->setParameter('timestamp', $until);
        }

        if ($limit > 0) {
            $query->setMaxResults($limit);
        }

        // Match one class level, including Unicode names, without engine-specific regex encodings.
        $namespaces = [];
        $sourceOrder = [];
        $index = 0;
        $column = $this->quote('class');
        foreach ($source->getNamespaceGroups() as $rank => $group) {
            $matches = [];
            foreach ($group as $namespace) {
                $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $namespace . '\\');
                $match = '(' . $column . ' LIKE :namespace' . $index . ' ESCAPE \'!\' AND '
                    . $column . ' NOT LIKE :nested' . $index . ' ESCAPE \'!\')';
                $namespaces[] = $match;
                $matches[] = $match;
                $query->setParameter('namespace' . $index, $prefix . '%');
                $query->setParameter('nested' . $index, $prefix . '%\\%');
                ++$index;
            }
            $sourceOrder[] = 'WHEN (' . implode(' OR ', $matches) . ') THEN ' . $rank;
        }
        $query->andWhere($namespaces === [] ? '1 = 0' : '(' . implode(' OR ', $namespaces) . ')');
        if ($sourceOrder !== []) {
            $query->orderBy('CASE ' . implode(' ', $sourceOrder) . ' END', 'ASC');
        }
        $query->addOrderBy('creation_timestamp', 'ASC')->addOrderBy($column, 'ASC');

        return $query;
    }

    /**
     * @param class-string<MigrationStep> $logicalClass
     */
    private function logError(string $logicalClass, MigrationStep $migration, string $message): void
    {
        $this->connection->update('migration', [
            $this->quote('message') => mb_convert_encoding($message, 'UTF-8', 'UTF-8'),
        ], [$this->quote('class') => $logicalClass]);

        $platform = $this->connection->getDatabasePlatform()::class;
        $this->logger->error('Migration: "' . $logicalClass . '" (' . $migration::class . ', ' . $platform . ') failed: "' . $message . '"');
    }

    /**
     * @param class-string<MigrationStep> $logicalClass
     */
    private function setExecuted(string $logicalClass, bool $destructive): void
    {
        $column = $destructive ? 'update_destructive' : 'update';
        $now = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? '(clock_timestamp() AT TIME ZONE \'UTC\')' : 'NOW(6)';
        $this->connection->executeStatement(
            'UPDATE migration SET ' . $this->quote('message') . ' = NULL, ' . $this->quote($column) . ' = ' . $now
            . ' WHERE ' . $this->quote('class') . ' = :class',
            ['class' => $logicalClass]
        );
    }

    private function quote(string $identifier): string
    {
        return $this->connection->getDatabasePlatform()->quoteSingleIdentifier($identifier);
    }

    private function enrichException(\Exception $e): void
    {
        if ($e->getCode() !== 0) {
            return;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*(1452).*a foreign key constraint/', $e->getMessage())) {
            $matches = [];
            preg_match(
                '/TABLE.*?`(.*?)`.*? (REFERENCES|constraint).*?`(.*?)`/',
                (string) preg_replace(["/\r|\n/", '/ +/'], ['', ' '], $e->getMessage()),
                $matches
            );

            if (isset($matches[1], $matches[2], $matches[3]) && $matches[2] === 'REFERENCES') {
                throw MigrationException::migrationError(
                    'The migration failed due to inconsistent data. You can try to check the table `' . $matches[1]
                    . '` for entries that do not match the entries in table `' . $matches[3] . '`.',
                    $e
                );
            }

            if (isset($matches[1])) {
                throw MigrationException::migrationError(
                    'The migration failed due to inconsistent data. You can try to check the table `' . $matches[1]
                    . '` for entries that do not match the entries in the table referenced in the foreign key.',
                    $e
                );
            }
        }
    }
}

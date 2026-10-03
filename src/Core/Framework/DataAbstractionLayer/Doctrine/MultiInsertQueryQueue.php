<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type DataRow array{data: array<string, mixed>, types: array<string, ParameterType>|null}
 */
#[Package('framework')]
class MultiInsertQueryQueue
{
    /**
     * @var array<string, list<DataRow>>
     */
    private array $inserts = [];

    /**
     * @var array<string, list<string>>
     */
    private array $updateFieldsOnDuplicateKey = [];

    /**
     * Explicit unique targets; replacement callers declare that this is the complete conflict contract.
     *
     * @var array<string, list<string>>
     */
    private array $conflictTargets = [];

    /**
     * @var int<1, max>
     */
    private readonly int $chunkSize;

    public function __construct(
        private readonly Connection $connection,
        int $chunkSize = 250,
        private readonly bool $ignoreErrors = false,
        private readonly bool $useReplace = false
    ) {
        if ($chunkSize < 1) {
            throw DataAbstractionLayerException::invalidChunkSize($chunkSize);
        }
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, ParameterType>|null $types
     */
    public function addInsert(string $table, array $data, ?array $types = null): void
    {
        $this->inserts[$table][] = [
            'data' => $data,
            'types' => $types,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, ParameterType>|null $types
     */
    public function addInserts(string $table, array $rows, ?array $types = null): void
    {
        foreach ($rows as $row) {
            $this->addInsert($table, $row, $types);
        }
    }

    public function execute(): void
    {
        if ($this->inserts === []) {
            return;
        }

        $queries = $this->prepareQueries();
        RetryableTransaction::retryable($this->connection, function () use ($queries): void {
            foreach ($queries as $query) {
                $this->connection->executeStatement($query['query'], $query['values'], $query['types']);
            }
        });
        unset($queries);

        $executed = $this->inserts;
        $this->inserts = [];
        RetryableTransaction::onRollback($this->connection, function () use ($executed): void {
            foreach ($executed as $table => $rows) {
                $this->inserts[$table] = array_merge($rows, $this->inserts[$table] ?? []);
            }
        });
    }

    /**
     * You can add fields which should be updated with the new values on duplicate keys
     */
    public function addUpdateFieldOnDuplicateKey(string $table, string $updateField): void
    {
        $this->updateFieldsOnDuplicateKey[$table][] = $updateField;
    }

    /**
     * @param list<string> $columns
     */
    public function setConflictTarget(string $table, array $columns): void
    {
        if ($columns === []) {
            throw DataAbstractionLayerException::invalidWriteOperation('a conflict target cannot be empty');
        }
        $this->conflictTargets[$table] = $columns;
    }

    /**
     * @return list<array{query: string, values: list<mixed>, types: list<ParameterType>}>
     */
    private function prepareQueries(): array
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $postgres = $dialect->capabilities()->supportsConflictTarget;
        $queries = [];

        foreach ($this->inserts as $table => $rows) {
            $columns = $this->prepareColumns($rows);
            $escapedColumns = implode(', ', array_map($dialect->quoteIdentifier(...), $columns));
            $escapedTable = $dialect->quoteIdentifier($table);
            $target = $this->conflictTargets[$table] ?? [];
            $updates = array_values(array_intersect($this->updateFieldsOnDuplicateKey[$table] ?? [], $columns));
            $prefix = 'INSERT INTO';
            if (!$postgres) {
                $prefix = $this->useReplace ? 'REPLACE INTO' : ($this->ignoreErrors ? 'INSERT IGNORE INTO' : 'INSERT INTO');
            }
            $clause = $dialect->insertConflictClause($target, $updates, $this->ignoreErrors && !$this->useReplace);

            // PostgreSQL cannot update one row twice in one ON CONFLICT statement. Execute in input order,
            // so repeated keys retain MySQL's last-row-wins behavior, including DEFAULT and NULL.
            $chunkSize = $postgres && ($updates !== [] || $this->useReplace) ? 1 : $this->chunkSize;
            if ($postgres && $this->useReplace) {
                if ($target === []) {
                    throw DataAbstractionLayerException::invalidWriteOperation('PostgreSQL replacement needs an audited explicit conflict target for ' . $table);
                }
                $this->validateReplacementTarget($table, $target);
                // Serialize delete+insert with ordinary writers, including replacement of an absent key.
                $queries[] = ['query' => 'LOCK TABLE ' . $escapedTable . ' IN SHARE ROW EXCLUSIVE MODE', 'values' => [], 'types' => []];
            }

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                if ($postgres && $this->useReplace) {
                    $row = $chunk[0];
                    $conditions = [];
                    $values = [];
                    $types = [];
                    foreach ($target as $column) {
                        if (!isset($row['data'][$column])) {
                            throw DataAbstractionLayerException::invalidWriteOperation('replacement conflict columns must have non-null explicit values');
                        }
                        $conditions[] = $dialect->quoteIdentifier($column) . ' = ?';
                        $values[] = $row['data'][$column];
                        $types[] = $row['types'][$column] ?? ParameterType::STRING;
                    }
                    $queries[] = ['query' => 'DELETE FROM ' . $escapedTable . ' WHERE ' . implode(' AND ', $conditions), 'values' => $values, 'types' => $types];
                }
                $data = $this->prepareValues($columns, $chunk);
                $queries[] = [
                    'query' => $prefix . ' ' . $escapedTable . ' (' . $escapedColumns . ') VALUES ' . implode(', ', $data['placeholders']) . $clause . ';',
                    'values' => $data['values'],
                    'types' => $data['types'],
                ];
            }
        }

        return $queries;
    }

    /**
     * A replacement target is complete only when every unique index identifies the same row.
     * Secondary unique keys, partial indexes and expression indexes need a caller-specific strategy.
     *
     * @param list<string> $target
     */
    private function validateReplacementTarget(string $table, array $target): void
    {
        if ($table === '') {
            throw DataAbstractionLayerException::invalidWriteOperation('replacement table cannot be empty');
        }
        $guard = (new DatabasePlatformResolver($this->connection))->dialect()->unsupportedReplacementConflicts($table);
        if ((int) $this->connection->fetchOne($guard->sql, $guard->parameters, $guard->types) > 0) {
            throw DataAbstractionLayerException::invalidWriteOperation('replacement has an unaudited partial or expression conflict on ' . $table);
        }
        $manager = $this->connection->createSchemaManager();
        $name = OptionallyQualifiedName::quoted($table);
        $unique = false;
        $primary = $manager->introspectTablePrimaryKeyConstraint($name);
        if ($primary !== null) {
            $columns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $primary->getColumnNames());
            if (array_diff($target, $columns) !== [] || array_diff($columns, $target) !== []) {
                throw DataAbstractionLayerException::invalidWriteOperation('replacement has an unaudited primary conflict on ' . $table);
            }
            $unique = true;
        }
        foreach ($manager->introspectTableIndexes($name) as $index) {
            if ($index->getType() !== IndexType::UNIQUE) {
                continue;
            }
            $columns = array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns());
            if (array_diff($target, $columns) !== [] || array_diff($columns, $target) !== [] || $index->getPredicate() !== null
            ) {
                throw DataAbstractionLayerException::invalidWriteOperation('replacement has an unaudited secondary, partial or expression conflict on ' . $table);
            }
            $unique = true;
        }
        if (!$unique) {
            throw DataAbstractionLayerException::invalidWriteOperation('replacement target is not a unique key on ' . $table);
        }
    }

    /**
     * @param list<DataRow> $rows
     *
     * @return list<string>
     */
    private function prepareColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row['data']) as $column) {
                $columns[$column] = 1;
            }
        }

        return array_keys($columns);
    }

    /**
     * @param list<string> $columns
     * @param list<DataRow> $rows
     *
     * @return array{placeholders: list<string>, values: list<mixed>, types: list<ParameterType>}
     */
    private function prepareValues(array $columns, array $rows): array
    {
        $placeholders = [];
        $values = [];
        $types = [];

        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                if (!\array_key_exists($column, $row['data'])) { // to use default values if the column is not set
                    $rowPlaceholders[] = 'DEFAULT';
                    continue;
                }
                if ($row['data'][$column] === null) { // to insert nulls if the value is null
                    $rowPlaceholders[] = 'NULL';
                    continue;
                }

                $rowPlaceholders[] = '?';
                $values[] = $row['data'][$column];
                $types[] = $row['types'][$column] ?? ParameterType::STRING;
            }
            $placeholders[] = '(' . implode(',', $rowPlaceholders) . ')';
        }

        return [
            'placeholders' => $placeholders,
            'values' => $values,
            'types' => $types,
        ];
    }
}

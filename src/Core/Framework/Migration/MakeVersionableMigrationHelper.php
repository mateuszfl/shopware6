<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type RelationData array{TABLE_NAME: string, COLUMN_NAME: string, CONSTRAINT_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string}
 * @phpstan-type ForeignKeyData array{TABLE_NAME: string, COLUMN_NAME: list<string>, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: list<string>}
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class MakeVersionableMigrationHelper
{
    private const DROP_FOREIGN_KEY = 'ALTER TABLE `%s` DROP FOREIGN KEY `%s`';
    private const DROP_KEY = 'ALTER TABLE `%s` DROP KEY `%s`';
    private const ADD_FOREIGN_KEY = 'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s, `%s`) REFERENCES `%s` (%s, `%s`) ON DELETE %s ON UPDATE CASCADE';
    private const ADD_NEW_COLUMN_WITH_DEFAULT = 'ALTER TABLE `%s` ADD `%s` binary(16) NOT NULL DEFAULT 0x%s AFTER `%s`';
    private const ADD_NEW_COLUMN_NULLABLE = 'ALTER TABLE `%s` ADD `%s` binary(16) NULL AFTER `%s`';
    private const MODIFY_PRIMARY_KEY_IN_MAIN = 'ALTER TABLE `%s` DROP PRIMARY KEY, ADD `%s` binary(16) NOT NULL DEFAULT 0x%s AFTER `%s`, ADD PRIMARY KEY (`%s`, `%s`)';
    private const MODIFY_PRIMARY_KEY_IN_RELATION = 'ALTER TABLE `%s` DROP PRIMARY KEY, ADD PRIMARY KEY (%s, `%s`)';
    private const ADD_KEY = 'ALTER TABLE `%s` ADD UNIQUE INDEX `fk.%s.%s` (%s)';
    private const FIND_RELATIONSHIPS_QUERY = <<<EOD
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
	REFERENCED_TABLE_SCHEMA = '%s'
    AND REFERENCED_TABLE_NAME = '%s';
EOD;

    /**
     * @var AbstractSchemaManager<AbstractPlatform>
     */
    private readonly AbstractSchemaManager $schemaManager;

    public function __construct(
        private readonly Connection $connection
    ) {
        $this->schemaManager = $connection->createSchemaManager();
    }

    /**
     * @return array<string, ForeignKeyData>
     */
    public function getRelationData(string $tableName, string $keyColumn): array
    {
        $data = $this->fetchRelationData($tableName);

        return $this->hydrateForeignKeyData($data, $keyColumn);
    }

    /**
     * @param array<string, ForeignKeyData> $keyStructures
     * @param non-empty-string $tableName
     *
     * @return array<string>
     */
    public function createSql(array $keyStructures, string $tableName, string $newColumnName, string $defaultValue): array
    {
        return array_filter(array_merge(
            $this->createDropKeysPlaybookEntries($keyStructures),
            [$this->createModifyPrimaryKeyQuery($tableName, $newColumnName, $defaultValue)],
            $this->createAddKeysPlaybookEntries($keyStructures, $newColumnName, $tableName),
            $this->createAddColumnsAndKeysPlaybookEntries($newColumnName, $keyStructures, $defaultValue)
        ));
    }

    /**
     * @param array<string, ForeignKeyData> $keyStructures
     *
     * @return list<string>
     */
    private function createDropKeysPlaybookEntries(array $keyStructures): array
    {
        $playbook = [];
        foreach ($keyStructures as $constraintName => $keyStructure) {
            $tableName = $this->ensureTableName($keyStructure);

            $indexes = $this->schemaManager->introspectTableIndexesByUnquotedName($tableName);

            $playbook[] = $this->isPostgreSQL()
                ? 'ALTER TABLE ' . $this->quote($tableName) . ' DROP CONSTRAINT ' . $this->quote($constraintName)
                : \sprintf(self::DROP_FOREIGN_KEY, $tableName, $constraintName);

            foreach ($indexes as $index) {
                $indexName = $index->getObjectName()->getIdentifier()->getValue();
                if (strtolower($constraintName) === $indexName) {
                    $playbook[] = $this->isPostgreSQL()
                        ? 'DROP INDEX ' . $this->quote($constraintName)
                        : \sprintf(self::DROP_KEY, $tableName, $constraintName);
                }
            }
        }

        return $playbook;
    }

    /**
     * @param array<string, ForeignKeyData> $keyStructures
     *
     * @return list<string|null>
     */
    private function createAddColumnsAndKeysPlaybookEntries(string $newColumnName, array $keyStructures, string $default): array
    {
        $playbook = [];
        $duplicateColumnNamePrevention = [];

        foreach ($keyStructures as $constraintName => $keyStructure) {
            $tableName = $this->ensureTableName($keyStructure);
            $foreignKeyColumnName = $keyStructure['REFERENCED_TABLE_NAME'] . '_' . $newColumnName;

            if (isset($duplicateColumnNamePrevention[$tableName])) {
                $foreignKeyColumnName .= '_' . $duplicateColumnNamePrevention[$tableName];
            }

            $foreignKey = $this->findForeignKeyDefinition($keyStructure);

            array_push($playbook, ...$this->determineAddColumnSql($foreignKey, $keyStructure, $foreignKeyColumnName, $default));
            $playbook[] = $this->determineModifyPrimaryKeySql($keyStructure, $foreignKeyColumnName);
            $playbook[] = $this->getAddForeignKeySql($keyStructure, $constraintName, $foreignKeyColumnName, $newColumnName, $foreignKey);

            if (isset($duplicateColumnNamePrevention[$tableName])) {
                ++$duplicateColumnNamePrevention[$tableName];
            } else {
                $duplicateColumnNamePrevention[$tableName] = 1;
            }
        }

        return $playbook;
    }

    /**
     * @param array<string, ForeignKeyData> $keyStructures
     *
     * @return list<string>
     */
    private function createAddKeysPlaybookEntries(array $keyStructures, string $newColumnName, string $tableName): array
    {
        $playbook = [];
        foreach ($keyStructures as $keyStructure) {
            if ((is_countable($keyStructure['REFERENCED_COLUMN_NAME']) ? \count($keyStructure['REFERENCED_COLUMN_NAME']) : 0) < 2) {
                continue;
            }

            $keyColumns = $keyStructure['REFERENCED_COLUMN_NAME'];
            $keyColumns[] = $newColumnName;
            $uniqueName = implode('_', $keyColumns);

            $playbook[$uniqueName] = $this->isPostgreSQL()
                ? 'CREATE UNIQUE INDEX ' . $this->quote('fk.' . $tableName . '.' . $uniqueName) . ' ON ' . $this->quote($tableName) . ' (' . $this->implodeColumns($keyColumns) . ')'
                : \sprintf(self::ADD_KEY, $tableName, $tableName, $uniqueName, $this->implodeColumns($keyColumns));
        }

        return array_values($playbook);
    }

    /**
     * @param array<string> $columns
     */
    private function implodeColumns(array $columns): string
    {
        return implode(',', array_map(fn (string $column): string => $this->quote($column), $columns));
    }

    /**
     * @param list<string> $localFieldNames
     * @param list<string> $foreignFieldNames
     */
    private function isEqualForeignKey(ForeignKeyConstraint $constraint, array $localFieldNames, string $foreignTable, array $foreignFieldNames): bool
    {
        if ($constraint->getReferencedTableName()->getUnqualifiedName()->getValue() !== $foreignTable) {
            return false;
        }

        $referencingColumns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $constraint->getReferencingColumnNames());
        if ($referencingColumns !== $localFieldNames) {
            return false;
        }

        $referencedColumns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $constraint->getReferencedColumnNames());

        return $referencedColumns === $foreignFieldNames;
    }

    /**
     * @param list<RelationData> $data
     *
     * @return array<string, ForeignKeyData>
     */
    private function hydrateForeignKeyData(array $data, string $keyColumnName): array
    {
        $hydratedData = $this->mapHydrateForeignKeyData($data);

        return $this->filterHydrateForeignKeyData($hydratedData, $keyColumnName);
    }

    /**
     * @param list<RelationData> $data
     *
     * @return array<string, ForeignKeyData>
     */
    private function mapHydrateForeignKeyData(array $data): array
    {
        $hydratedData = [];

        foreach ($data as $entry) {
            $constraintName = $entry['CONSTRAINT_NAME'];

            if (!isset($hydratedData[$constraintName])) {
                $hydratedData[$constraintName] = [
                    'TABLE_NAME' => $entry['TABLE_NAME'],
                    'COLUMN_NAME' => [$entry['COLUMN_NAME']],
                    'REFERENCED_TABLE_NAME' => $entry['REFERENCED_TABLE_NAME'],
                    'REFERENCED_COLUMN_NAME' => [$entry['REFERENCED_COLUMN_NAME']],
                ];

                continue;
            }

            $hydratedData[$constraintName]['COLUMN_NAME'][] = $entry['COLUMN_NAME'];
            $hydratedData[$constraintName]['REFERENCED_COLUMN_NAME'][] = $entry['REFERENCED_COLUMN_NAME'];
        }

        return $hydratedData;
    }

    /**
     * @param array<string, ForeignKeyData> $hydratedData
     *
     * @return array<string, ForeignKeyData>
     */
    private function filterHydrateForeignKeyData(array $hydratedData, string $keyColumnName): array
    {
        return array_filter($hydratedData, static fn (array $entry): bool => \in_array($keyColumnName, $entry['REFERENCED_COLUMN_NAME'], true));
    }

    /**
     * @return list<RelationData>
     */
    private function fetchRelationData(string $tableName): array
    {
        if ($this->isPostgreSQL()) {
            $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT source.relname AS "TABLE_NAME", source_column.attname AS "COLUMN_NAME", constraint_definition.conname AS "CONSTRAINT_NAME",
       target.relname AS "REFERENCED_TABLE_NAME", target_column.attname AS "REFERENCED_COLUMN_NAME"
FROM pg_constraint constraint_definition
JOIN pg_class source ON source.oid = constraint_definition.conrelid
JOIN pg_namespace source_schema ON source_schema.oid = source.relnamespace
JOIN pg_class target ON target.oid = constraint_definition.confrelid
JOIN LATERAL unnest(constraint_definition.conkey) WITH ORDINALITY source_key(attnum, position) ON TRUE
JOIN LATERAL unnest(constraint_definition.confkey) WITH ORDINALITY target_key(attnum, position) ON target_key.position = source_key.position
JOIN pg_attribute source_column ON source_column.attrelid = source.oid AND source_column.attnum = source_key.attnum
JOIN pg_attribute target_column ON target_column.attrelid = target.oid AND target_column.attnum = target_key.attnum
WHERE constraint_definition.contype = 'f'
  AND constraint_definition.confrelid = to_regclass(format('%I.%I', current_schema(), CAST(:referencedTable AS text)))
  AND source_schema.nspname = current_schema()
ORDER BY source.relname, constraint_definition.conname, source_key.position
SQL, ['referencedTable' => $tableName]);

            return $this->hydrateRelationRows($rows);
        }

        $databaseName = $this->connection->fetchOne('SELECT DATABASE()') ?: '';
        $query = \sprintf(self::FIND_RELATIONSHIPS_QUERY, $databaseName, $tableName);

        return $this->hydrateRelationRows($this->connection->fetchAllAssociative($query));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<RelationData>
     */
    private function hydrateRelationRows(array $rows): array
    {
        $relations = [];
        foreach ($rows as $row) {
            $values = [];
            foreach (['TABLE_NAME', 'COLUMN_NAME', 'CONSTRAINT_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME'] as $key) {
                if (!isset($row[$key]) || !\is_string($row[$key]) || $row[$key] === '') {
                    throw MigrationException::logicError('Foreign key metadata is missing a required name.');
                }
                $values[$key] = $row[$key];
            }

            $relations[] = [
                'TABLE_NAME' => $values['TABLE_NAME'],
                'COLUMN_NAME' => $values['COLUMN_NAME'],
                'CONSTRAINT_NAME' => $values['CONSTRAINT_NAME'],
                'REFERENCED_TABLE_NAME' => $values['REFERENCED_TABLE_NAME'],
                'REFERENCED_COLUMN_NAME' => $values['REFERENCED_COLUMN_NAME'],
            ];
        }

        return $relations;
    }

    /**
     * @param non-empty-string $tableName
     */
    private function createModifyPrimaryKeyQuery(string $tableName, string $newColumnName, string $defaultValue): string
    {
        $primaryKeyColumns = $this->getPrimaryKeyColumns($tableName);
        if (\count($primaryKeyColumns) !== 1) {
            throw MigrationException::multiColumnPrimaryKey();
        }
        $primaryKeyColumn = array_first($primaryKeyColumns);
        if (!$primaryKeyColumn instanceof UnqualifiedName) {
            throw MigrationException::noPrimaryKey();
        }
        $primaryKeyColumnName = $primaryKeyColumn->getIdentifier()->getValue();

        if ($this->isPostgreSQL()) {
            $primaryKeyName = $this->getPostgreSQLPrimaryKeyName($tableName);
            if ($primaryKeyName === null) {
                throw MigrationException::noPrimaryKey();
            }

            return 'ALTER TABLE ' . $this->quote($tableName) . ' DROP CONSTRAINT ' . $this->quote($primaryKeyName)
                . ', ADD COLUMN ' . $this->quote($newColumnName) . ' BYTEA NOT NULL DEFAULT decode(\'' . $defaultValue . '\', \'hex\') CHECK (octet_length(' . $this->quote($newColumnName) . ') = 16)'
                . ', ADD PRIMARY KEY (' . $this->implodeColumns([$primaryKeyColumnName, $newColumnName]) . ')';
        }

        return \sprintf(self::MODIFY_PRIMARY_KEY_IN_MAIN, $tableName, $newColumnName, $defaultValue, $primaryKeyColumnName, $primaryKeyColumnName, $newColumnName);
    }

    /**
     * @param ForeignKeyData $keyStructure
     */
    private function findForeignKeyDefinition(array $keyStructure): ForeignKeyConstraint
    {
        $tableName = $this->ensureTableName($keyStructure);
        $foreignKeys = $this->schemaManager->introspectTableForeignKeyConstraintsByUnquotedName($tableName);
        $returnedForeignKey = null;

        foreach ($foreignKeys as $foreignKey) {
            if ($this->isEqualForeignKey($foreignKey, $keyStructure['COLUMN_NAME'], $keyStructure['REFERENCED_TABLE_NAME'], $keyStructure['REFERENCED_COLUMN_NAME'])) {
                $returnedForeignKey = $foreignKey;
                break;
            }
        }

        if ($returnedForeignKey === null) {
            throw MigrationException::logicError('Unable to find a foreign key that was previously selected');
        }

        return $returnedForeignKey;
    }

    /**
     * @param ForeignKeyData $keyStructure
     *
     * @return list<string>
     */
    private function determineAddColumnSql(ForeignKeyConstraint $fk, array $keyStructure, string $foreignKeyColumnName, string $default): array
    {
        $tableName = $this->ensureTableName($keyStructure);
        $columnName = array_last($keyStructure['COLUMN_NAME']);
        if (!\is_string($columnName)) {
            throw MigrationException::logicError('Column name is needed');
        }

        if ($fk->getOnDeleteAction()->value === 'SET NULL') {
            if ($this->isPostgreSQL()) {
                $column = $this->quote($foreignKeyColumnName);
                $table = $this->quote($tableName);
                $referencesPresent = array_map(fn (string $referencingColumn): string => $this->quote($referencingColumn) . ' IS NOT NULL', $keyStructure['COLUMN_NAME']);

                return [
                    'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' BYTEA NULL CHECK (' . $column . ' IS NULL OR octet_length(' . $column . ') = 16)',
                    'UPDATE ' . $table . ' SET ' . $column . ' = decode(\'' . $default . '\', \'hex\') WHERE ' . implode(' AND ', $referencesPresent),
                ];
            }

            $addColumnSql = \sprintf(
                self::ADD_NEW_COLUMN_NULLABLE,
                $tableName,
                $foreignKeyColumnName,
                $columnName
            );
        } else {
            if ($this->isPostgreSQL()) {
                $column = $this->quote($foreignKeyColumnName);

                return ['ALTER TABLE ' . $this->quote($tableName) . ' ADD COLUMN ' . $column . ' BYTEA NOT NULL DEFAULT decode(\'' . $default . '\', \'hex\') CHECK (octet_length(' . $column . ') = 16)'];
            }

            $addColumnSql = \sprintf(
                self::ADD_NEW_COLUMN_WITH_DEFAULT,
                $tableName,
                $foreignKeyColumnName,
                $default,
                $columnName
            );
        }

        return [$addColumnSql];
    }

    /**
     * @param ForeignKeyData $keyStructure
     */
    private function getAddForeignKeySql(
        array $keyStructure,
        string $constraintName,
        string $foreignKeyColumnName,
        string $newColumnName,
        ForeignKeyConstraint $fk
    ): string {
        $tableName = $this->ensureTableName($keyStructure);

        if ($this->isPostgreSQL()) {
            $columns = array_merge($keyStructure['COLUMN_NAME'], [$foreignKeyColumnName]);
            $referencedColumns = array_merge($keyStructure['REFERENCED_COLUMN_NAME'], [$newColumnName]);

            return 'ALTER TABLE ' . $this->quote($tableName) . ' ADD CONSTRAINT ' . $this->quote($constraintName)
                . ' FOREIGN KEY (' . $this->implodeColumns($columns) . ') REFERENCES ' . $this->quote($keyStructure['REFERENCED_TABLE_NAME'])
                . ' (' . $this->implodeColumns($referencedColumns) . ') ON DELETE ' . ($fk->getOnDeleteAction()->value ?? 'RESTRICT') . ' ON UPDATE CASCADE';
        }

        return \sprintf(
            self::ADD_FOREIGN_KEY,
            $tableName,
            $constraintName,
            $this->implodeColumns($keyStructure['COLUMN_NAME']),
            $foreignKeyColumnName,
            $keyStructure['REFERENCED_TABLE_NAME'],
            $this->implodeColumns($keyStructure['REFERENCED_COLUMN_NAME']),
            $newColumnName,
            $fk->getOnDeleteAction()->value ?? 'RESTRICT'
        );
    }

    /**
     * @param ForeignKeyData $keyStructure
     */
    private function determineModifyPrimaryKeySql(array $keyStructure, string $foreignKeyColumnName): ?string
    {
        $tableName = $this->ensureTableName($keyStructure);
        $indexedColumns = $this->getPrimaryKeyColumns($tableName);
        $indexedColumns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $indexedColumns);

        if (array_intersect($indexedColumns, $keyStructure['COLUMN_NAME']) !== []) {
            if ($this->isPostgreSQL()) {
                $primaryKeyName = $this->getPostgreSQLPrimaryKeyName($tableName);
                if ($primaryKeyName === null) {
                    throw MigrationException::noPrimaryKey();
                }

                return 'ALTER TABLE ' . $this->quote($tableName) . ' DROP CONSTRAINT ' . $this->quote($primaryKeyName)
                    . ', ADD PRIMARY KEY (' . $this->implodeColumns(array_merge($indexedColumns, [$foreignKeyColumnName])) . ')';
            }

            return \sprintf(
                self::MODIFY_PRIMARY_KEY_IN_RELATION,
                $tableName,
                $this->implodeColumns($indexedColumns),
                $foreignKeyColumnName
            );
        }

        return null;
    }

    /**
     * @param ForeignKeyData $keyStructure
     *
     * @return non-empty-string
     */
    private function ensureTableName(array $keyStructure): string
    {
        $tableName = $keyStructure['TABLE_NAME'];
        if (!\is_string($tableName) || $tableName === '') {
            throw MigrationException::logicError('Table name not given or empty');
        }

        return $tableName;
    }

    private function isPostgreSQL(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    private function quote(string $identifier): string
    {
        return $this->connection->getDatabasePlatform()->quoteSingleIdentifier($identifier);
    }

    private function getPostgreSQLPrimaryKeyName(string $tableName): ?string
    {
        $name = $this->connection->fetchOne(
            'SELECT conname FROM pg_constraint WHERE conrelid = to_regclass(format(\'%I.%I\', current_schema(), CAST(? AS text))) AND contype = \'p\'',
            [$tableName],
        );

        return \is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param non-empty-string $tableName
     *
     * @return non-empty-list<UnqualifiedName>
     */
    private function getPrimaryKeyColumns(string $tableName): array
    {
        $primaryKey = $this->schemaManager->introspectTablePrimaryKeyConstraint(OptionallyQualifiedName::unquoted($tableName));
        if ($primaryKey === null) {
            throw MigrationException::noPrimaryKey();
        }

        return $primaryKey->getColumnNames();
    }
}

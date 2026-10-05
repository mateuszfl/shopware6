<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Complements DBAL where PostgreSQL introspection omits checks, timestamp precision
 * and generated expressions. SQL expressions are trusted schema definitions.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Database\DatabaseSchemaTest
 */
#[Package('framework')]
final class DatabaseSchema
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isPostgreSQL(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    /**
     * @return non-empty-string
     */
    public static function generatedName(string $name, bool $postgres): string
    {
        if ($name === '') {
            throw DatabaseDialectException::invalidIdentifier($name);
        }
        if (!$postgres) {
            return substr($name, 0, 64);
        }

        if (\strlen($name) <= 63) {
            return $name;
        }

        return mb_strcut($name, 0, 46, 'UTF-8') . '_' . substr(Hasher::hash($name, 'sha256'), 0, 16);
    }

    /**
     * @return list<string>
     */
    public function prerequisites(): array
    {
        if (!$this->isPostgreSQL()) {
            return [];
        }

        $schema = (string) $this->connection->fetchOne('SELECT current_schema()');
        $this->validateIdentifier($schema);
        $collation = $this->connection->fetchAssociative('SELECT c.collprovider, c.collisdeterministic, c.colllocale FROM pg_catalog.pg_collation c JOIN pg_catalog.pg_namespace n ON n.oid = c.collnamespace WHERE n.nspname = ? AND c.collname = ?', [$schema, 'sw_type_ci']);
        if ($collation !== false) {
            if ($collation['collprovider'] !== 'i' || filter_var($collation['collisdeterministic'], \FILTER_VALIDATE_BOOL) || $collation['colllocale'] !== 'und-u-ks-level1') {
                throw DatabaseDialectException::invalidOperation('sw_type_ci must use nondeterministic ICU und-u-ks-level1 collation');
            }

            return [];
        }

        return ['CREATE COLLATION ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($schema) . '.sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)'];
    }

    public function prepareTable(Table $table, bool $customColumnsOnly = false): Table
    {
        if (!$this->isPostgreSQL()) {
            return $table;
        }
        if ($table->getOptions()['shopware_prepared'] ?? false) {
            return $table;
        }

        $name = $table->getObjectName()->getUnqualifiedName()->getValue();
        $this->validateIdentifier($name);
        $editor = $table->edit()->setQuotedName($name, $table->getObjectName()->getQualifier()?->getValue());
        $options = $table->getOptions();
        unset($options['charset'], $options['collate']);
        /** @var array<string, string> $checks */
        $checks = $options['shopware_checks'] ?? [];
        /** @var array<string, array{expression: string, stored: bool}> $generated */
        $generated = $options['shopware_generated'] ?? [];

        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columnName = $column->getObjectName()->getIdentifier()->getValue();
            $this->validateIdentifier($columnName);
            $column = $column->edit()->setQuotedName($columnName)->create();
            if (!$customColumnsOnly || $column->getComment() === 'custom-entity-element') {
                $type = Type::lookupName($column->getType());
                $quoted = $this->connection->getDatabasePlatform()->quoteSingleIdentifier($columnName);
                if ($type === Types::BINARY && $column->getLength() === 16) {
                    $checks[self::generatedName('chk_sw_' . $name . '_' . $columnName . '_uuid', true)] = 'octet_length(' . $quoted . ') = 16';
                    $column->setType(Type::getType(Types::BLOB))->setLength(null)->setFixed(false);
                } elseif ($type === Types::BOOLEAN) {
                    $checks[self::generatedName('chk_sw_' . $name . '_' . $columnName . '_bool', true)] = $quoted . ' = ANY (ARRAY[0, 1])';
                    $column->setType(Type::getType(Types::SMALLINT));
                    if ($column->getDefault() !== null) {
                        if (!\in_array($column->getDefault(), [0, 1, false, true, '0', '1'], true)) {
                            throw DatabaseDialectException::invalidOperation('Boolean schema defaults must be 0 or 1');
                        }
                        $column->setDefault((int) $column->getDefault());
                    }
                } elseif ($type === Types::JSON) {
                    $column->setType(Type::getType(Types::JSONB));
                } elseif ($type === Types::INTEGER && $column->getUnsigned()) {
                    $checks[self::generatedName('chk_sw_' . $name . '_' . $columnName . '_unsigned', true)] = '(' . $quoted . ' >= 0) AND (' . $quoted . ' <= \'4294967295\'::bigint)';
                    $column->setType(Type::getType(Types::BIGINT))->setUnsigned(false);
                }

                if ($type === Types::DATETIME_MUTABLE || $type === Types::DATETIME_IMMUTABLE) {
                    $column->setPrecision($column->getPrecision() ?? 3);
                    $column->setColumnDefinition($this->timestampDeclaration($column));
                }

                if (($type === Types::STRING || $type === Types::TEXT) && $column->getCollation() === null) {
                    $column->setPlatformOption('collation', 'sw_type_ci');
                }
            }

            if (isset($generated[$columnName])) {
                $definition = $generated[$columnName];
                $declaration = $column->getType()->getSQLDeclaration($column->toArray(), $this->connection->getDatabasePlatform());
                $column->setDefault(null)->setColumnDefinition($declaration . ' GENERATED ALWAYS AS (' . $definition['expression'] . ') ' . ($definition['stored'] ? 'STORED' : 'VIRTUAL') . ($column->getNotnull() ? ' NOT NULL' : ''));
            }
            $columns[] = $column;
        }

        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            /** @phpstan-ignore method.deprecated (DBAL 4 still exposes primary keys as indexes) */
            if ($index->isPrimary()) {
                continue;
            }
            $indexName = $index->getObjectName()->getIdentifier()->getValue();
            if (!$customColumnsOnly && !($options['shopware_introspected'] ?? false)) {
                $indexName = self::generatedName($name . '__' . $indexName, true);
            }
            $indexedColumns = array_map(static fn (IndexedColumn $column): IndexedColumn => new IndexedColumn(UnqualifiedName::quoted($column->getColumnName()->getIdentifier()->getValue()), $column->getLength()), $index->getIndexedColumns());
            \assert($indexedColumns !== []);
            $indexes[] = $index->edit()->setQuotedName($indexName)->setColumns(...$indexedColumns)->create();
        }
        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $foreignKey) {
            $fkName = $foreignKey->getObjectName()?->getIdentifier()->getValue();
            if ($fkName === null) {
                throw DatabaseDialectException::invalidOperation('Schema foreign keys must be named');
            }
            $referencing = array_map(static fn (UnqualifiedName $column): UnqualifiedName => UnqualifiedName::quoted($column->getIdentifier()->getValue()), $foreignKey->getReferencingColumnNames());
            $referenced = array_map(static fn (UnqualifiedName $column): UnqualifiedName => UnqualifiedName::quoted($column->getIdentifier()->getValue()), $foreignKey->getReferencedColumnNames());
            \assert($referencing !== [] && $referenced !== []);
            $referenceTable = $foreignKey->getReferencedTableName();
            $foreignKeys[] = $foreignKey->edit()->setQuotedName(self::generatedName($fkName, true))
                ->setReferencingColumnNames(...$referencing)
                ->setReferencedColumnNames(...$referenced)
                ->setReferencedTableName(OptionallyQualifiedName::quoted($referenceTable->getUnqualifiedName()->getValue(), $referenceTable->getQualifier()?->getValue()))->create();
        }
        $primary = $table->getPrimaryKeyConstraint();
        if ($primary !== null) {
            $primaryColumns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $primary->getColumnNames());
            \assert($primaryColumns !== []);
            $editor->setPrimaryKeyConstraint($primary->edit()->setQuotedColumnNames(...$primaryColumns)->create());
        }
        $uniqueConstraints = [];
        foreach ($table->getUniqueConstraints() as $unique) {
            $uniqueName = $unique->getObjectName()?->getIdentifier()->getValue();
            if ($uniqueName === null) {
                throw DatabaseDialectException::invalidOperation('Schema unique constraints must be named');
            }
            $uniqueColumns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $unique->getColumnNames());
            \assert($uniqueColumns !== []);
            $uniqueConstraints[] = $unique->edit()->setQuotedName(self::generatedName($name . '__' . $uniqueName, true))->setQuotedColumnNames(...$uniqueColumns)->create();
        }
        $options['shopware_checks'] = $checks;
        $options['shopware_prepared'] = true;
        $options['shopware_manage_checks'] = !$customColumnsOnly;

        return $editor->setColumns(...$columns)->setIndexes(...$indexes)->setUniqueConstraints(...$uniqueConstraints)->setForeignKeyConstraints(...$foreignKeys)->setOptions($options)->create();
    }

    /**
     * @return list<string>
     */
    public function createTableQueries(Table $table): array
    {
        return array_merge($this->connection->getDatabasePlatform()->getCreateTableSQL($table), $this->checkQueries($table, []));
    }

    /**
     * @return list<string>
     */
    public function alterTableQueries(Table $old, Table $new): array
    {
        $old = clone $old;
        $new = clone $new;
        $new = $this->comparisonTable($new);
        [$before, $after] = $this->catalogAlterQueries($old, $new);
        $before = array_merge($before, $this->dropChangedUniqueConstraints($old, $new));
        $manager = $this->connection->createSchemaManager();

        return array_merge($before, $this->connection->getDatabasePlatform()->getAlterTableSQL($manager->createComparator()->compareTables($old, $new)), $after);
    }

    /**
     * @return list<string>
     */
    public function alterSchemaQueries(Schema $old, Schema $new): array
    {
        $old = clone $old;
        $new = clone $new;
        foreach ($new->getTables() as $table) {
            foreach ($table->getUniqueConstraints() as $unique) {
                $name = $unique->getObjectName();
                if ($name === null) {
                    throw DatabaseDialectException::invalidOperation('Schema unique constraints must be named');
                }
                $columns = array_map(fn (UnqualifiedName $column): string => $column->toSQL($this->connection->getDatabasePlatform()), $unique->getColumnNames());
                $table->dropUniqueConstraint($name->toString());
                $table->addUniqueIndex($columns, $name->toSQL($this->connection->getDatabasePlatform()));
            }
        }
        if (!$this->isPostgreSQL()) {
            $manager = $this->connection->createSchemaManager();

            return $this->connection->getDatabasePlatform()->getAlterSchemaSQL($manager->createComparator()->compareSchemas($old, $new));
        }
        $queries = [];
        // Drop referencing constraints before tables, while retaining PostgreSQL's integrity checks.
        foreach ($old->getTables() as $table) {
            foreach ($table->getForeignKeys() as $fk) {
                $name = $fk->getObjectName()?->getIdentifier()->getValue();
                if ($name !== null && (!$new->hasTable($table->getObjectName()->toString()) || !$new->getTable($table->getObjectName()->toString())->hasForeignKey($name))) {
                    $queries[] = $this->connection->getDatabasePlatform()->getDropForeignKeySQL($this->connection->getDatabasePlatform()->quoteSingleIdentifier($name), $table->getObjectName()->toSQL($this->connection->getDatabasePlatform()));
                    $table->dropForeignKey($name);
                }
            }
        }
        $after = [];
        foreach ($new->getTables() as $table) {
            if ($old->hasTable($table->getObjectName()->toString())) {
                [$beforeTable, $afterTable] = $this->catalogAlterQueries($old->getTable($table->getObjectName()->toString()), $table);
                $queries = array_merge($queries, $beforeTable, $this->dropChangedUniqueConstraints($old->getTable($table->getObjectName()->toString()), $table));
                $after = array_merge($after, $afterTable);
            } else {
                $after = array_merge($after, $this->checkQueries($table, []));
            }
        }
        // DBAL orders table creation and removal, including cycles and foreign keys.
        $manager = $this->connection->createSchemaManager();
        $queries = array_merge($queries, $this->connection->getDatabasePlatform()->getAlterSchemaSQL($manager->createComparator()->compareSchemas($old, $new)), $after);

        return $queries;
    }

    /**
     * @return array<string, array{physical_type: string, generated: string, expression: string}>
     */
    public function columnMetadata(Table $table): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT a.attname, format_type(a.atttypid, a.atttypmod) AS physical_type,
                   a.attgenerated AS generated, COALESCE(pg_get_expr(d.adbin, d.adrelid), '') AS expression
            FROM pg_catalog.pg_attribute a
            LEFT JOIN pg_catalog.pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            WHERE a.attrelid = to_regclass(?) AND a.attnum > 0 AND NOT a.attisdropped
            SQL, [$table->getObjectName()->toSQL($this->connection->getDatabasePlatform())]);
        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row['attname']] = ['physical_type' => $row['physical_type'], 'generated' => $row['generated'], 'expression' => $row['expression']];
        }

        return $metadata;
    }

    /**
     * @param non-empty-string $name
     */
    public function introspectTable(string $name): Table
    {
        $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName($name);
        if (!$this->isPostgreSQL()) {
            return $table;
        }
        $checks = $this->connection->fetchAllKeyValue('SELECT conname, pg_get_expr(conbin, conrelid) FROM pg_catalog.pg_constraint WHERE conrelid = to_regclass(?) AND contype = \'c\' AND convalidated AND conenforced', [$table->getObjectName()->toSQL($this->connection->getDatabasePlatform())]);
        $tableName = $table->getObjectName()->getUnqualifiedName()->getValue();
        $metadata = $this->columnMetadata($table);
        $generated = [];
        foreach ($table->getColumns() as $column) {
            $columnName = $column->getObjectName()->getIdentifier()->getValue();
            $checkName = self::generatedName('chk_sw_' . $tableName . '_' . $columnName . '_uuid', true);
            if (isset($checks[$checkName]) && $this->expressionKey($checks[$checkName]) === $this->expressionKey('octet_length(' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($columnName) . ') = 16')) {
                $column->setType(Type::getType(Types::BINARY))->setLength(16)->setFixed(true);
            }
            $checkName = self::generatedName('chk_sw_' . $tableName . '_' . $columnName . '_bool', true);
            if (isset($checks[$checkName]) && $this->expressionKey($checks[$checkName]) === $this->expressionKey($this->connection->getDatabasePlatform()->quoteSingleIdentifier($columnName) . ' = ANY (ARRAY[0, 1])')) {
                $column->setType(Type::getType(Types::BOOLEAN));
            }
            $checkName = self::generatedName('chk_sw_' . $tableName . '_' . $columnName . '_unsigned', true);
            $unsignedCheck = '(' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($columnName) . ' >= 0) AND (' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($columnName) . ' <= \'4294967295\'::bigint)';
            if (isset($checks[$checkName]) && $this->expressionKey($checks[$checkName]) === $this->expressionKey($unsignedCheck)) {
                $column->setType(Type::getType(Types::INTEGER))->setUnsigned(true);
            }
            if ($metadata[$columnName]['physical_type'] === 'jsonb') {
                $column->setType(Type::getType(Types::JSONB));
            }
            if (preg_match('/^timestamp\((\d+)\) without time zone$/', $metadata[$columnName]['physical_type'], $precision) === 1) {
                $column->setPrecision((int) $precision[1]);
            }
            if ($metadata[$columnName]['generated'] !== '') {
                $generated[$columnName] = ['expression' => $metadata[$columnName]['expression'], 'stored' => $metadata[$columnName]['generated'] === 's'];
                $column->setDefault(null);
            }
        }
        $table->addOption('shopware_checks', $checks);
        $table->addOption('shopware_generated', $generated);
        $table->addOption('shopware_introspected', true);

        return $table;
    }

    /**
     * @return array{list<string>, list<string>}
     */
    private function catalogAlterQueries(Table $old, Table $new): array
    {
        $extra = [];
        $checks = [];
        if ($this->isPostgreSQL()) {
            $metadata = $this->columnMetadata($old);
            foreach ($metadata as $name => $row) {
                if ($row['physical_type'] === 'jsonb') {
                    $old->getColumn($name)->setType(Type::getType(Types::JSONB));
                    if (($new->getOptions()['shopware_manage_checks'] ?? true) === false && $new->hasColumn($name)) {
                        $new->getColumn($name)->setType(Type::getType(Types::JSONB));
                    }
                }
                if ($row['generated'] !== '') {
                    $old->getColumn($name)->setDefault(null);
                }
            }
            /** @var array<string, array{expression: string, stored: bool}> $generated */
            $generated = $new->getOptions()['shopware_generated'] ?? [];
            foreach ($new->getColumns() as $column) {
                $name = $column->getObjectName()->getIdentifier()->getValue();
                if (!isset($metadata[$name])) {
                    continue;
                }
                $row = $metadata[$name];
                $alter = 'ALTER TABLE ' . $new->getObjectName()->toSQL($this->connection->getDatabasePlatform()) . ' ALTER COLUMN ' . $column->getObjectName()->toSQL($this->connection->getDatabasePlatform());
                if ($column->getColumnDefinition() !== null && str_starts_with($column->getColumnDefinition(), 'TIMESTAMP(') && $row['physical_type'] !== 'timestamp(' . $column->getPrecision() . ') without time zone') {
                    $extra[] = $alter . ' TYPE TIMESTAMP(' . $column->getPrecision() . ') WITHOUT TIME ZONE';
                }
                if (isset($generated[$name])) {
                    if ($row['generated'] !== ($generated[$name]['stored'] ? 's' : 'v')) {
                        throw DatabaseDialectException::invalidOperation('Changing generated storage or converting a regular column requires an explicit migration: ' . $name);
                    }
                    if (!$this->expressionsEqual($old, $generated[$name]['expression'], $row['expression'])) {
                        $extra[] = $alter . ' SET EXPRESSION AS (' . $generated[$name]['expression'] . ')';
                    }
                } elseif ($row['generated'] !== '') {
                    // A DAL definition does not model generated columns: preserve unrelated generated metadata.
                    $column->setDefault(null);
                }
            }
            $checks = $this->connection->fetchAllKeyValue('SELECT conname, CASE WHEN convalidated AND conenforced THEN pg_get_expr(conbin, conrelid) ELSE \'\' END FROM pg_catalog.pg_constraint WHERE conrelid = to_regclass(?) AND contype = \'c\'', [$old->getObjectName()->toSQL($this->connection->getDatabasePlatform())]);
        }

        $checkQueries = $this->checkQueries($new, $checks);
        $before = array_values(array_filter($checkQueries, static fn (string $sql): bool => str_contains($sql, ' DROP CONSTRAINT ')));
        $after = array_values(array_filter($checkQueries, static fn (string $sql): bool => !str_contains($sql, ' DROP CONSTRAINT ')));

        return [$before, array_merge($extra, $after)];
    }

    /**
     * @param array<string, string> $existing
     *
     * @return list<string>
     */
    private function checkQueries(Table $table, array $existing): array
    {
        if (!$this->isPostgreSQL()) {
            return [];
        }
        /** @var array<string, string> $checks */
        $checks = $table->getOptions()['shopware_checks'] ?? [];
        $queries = [];
        $alter = 'ALTER TABLE ' . $table->getObjectName()->toSQL($this->connection->getDatabasePlatform());
        if ($table->getOptions()['shopware_manage_checks'] ?? true) {
            foreach ($existing as $name => $expression) {
                if (str_starts_with($name, 'chk_sw_') && !isset($checks[$name])) {
                    $queries[] = $alter . ' DROP CONSTRAINT ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($name);
                }
            }
        }
        foreach ($checks as $name => $expression) {
            $name = self::generatedName($name, true);
            if (isset($existing[$name]) && $this->expressionsEqual($table, $existing[$name], $expression)) {
                continue;
            }
            if (isset($existing[$name])) {
                $queries[] = $alter . ' DROP CONSTRAINT ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($name);
            }
            $queries[] = $alter . ' ADD CONSTRAINT ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($name) . ' CHECK (' . $expression . ')';
        }

        return $queries;
    }

    /**
     * @return non-empty-string
     */
    private function timestampDeclaration(Column $column): string
    {
        return 'TIMESTAMP(' . $column->getPrecision() . ') WITHOUT TIME ZONE'
            . $this->connection->getDatabasePlatform()->getDefaultValueDeclarationSQL($column->toArray())
            . ($column->getNotnull() ? ' NOT NULL' : '');
    }

    private function comparisonTable(Table $table): Table
    {
        if ($table->getUniqueConstraints() === []) {
            return $table;
        }
        $editor = $table->edit()->setUniqueConstraints();
        foreach ($table->getUniqueConstraints() as $unique) {
            $name = $unique->getObjectName();
            if ($name === null) {
                throw DatabaseDialectException::invalidOperation('Schema unique constraints must be named');
            }
            $columns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $unique->getColumnNames());
            $editor->addIndex(Index::editor()->setName($name)->setQuotedColumnNames(...$columns)->setType(IndexType::UNIQUE)->create());
        }

        return $editor->create();
    }

    /**
     * @return list<string>
     */
    private function dropChangedUniqueConstraints(Table $old, Table $new): array
    {
        if (!$this->isPostgreSQL()) {
            return [];
        }
        $constraints = $this->connection->fetchFirstColumn('SELECT conname FROM pg_catalog.pg_constraint WHERE conrelid = to_regclass(?) AND contype = \'u\'', [$old->getObjectName()->toSQL($this->connection->getDatabasePlatform())]);
        $diff = $this->connection->createSchemaManager()->createComparator((new ComparatorConfig())->withReportModifiedIndexes(false))->compareTables($old, $new);
        $queries = [];
        foreach ($diff->getDroppedIndexes() as $index) {
            $name = $index->getObjectName()->getIdentifier()->getValue();
            if (\in_array($name, $constraints, true)) {
                $queries[] = 'ALTER TABLE ' . $old->getObjectName()->toSQL($this->connection->getDatabasePlatform()) . ' DROP CONSTRAINT ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($name);
                $old->dropIndex($name);
            }
        }

        return $queries;
    }

    private function validateIdentifier(string $identifier): void
    {
        if ($identifier === '' || \strlen($identifier) > 63 || str_contains($identifier, "\0")) {
            throw DatabaseDialectException::invalidOperation('PostgreSQL schema identifiers must contain 1 to 63 bytes: ' . $identifier);
        }
    }

    private function expressionKey(string $expression): string
    {
        // Ignore whitespace outside literals and redundant outer parentheses only.
        // Preserve grouping: (a + b) * c must differ from a + b * c.
        preg_match_all('/\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"|[^\s\'"()]+|[()]/', $expression, $matches);
        $tokens = $matches[0];
        while (($tokens[0] ?? null) === '(' && end($tokens) === ')') {
            $depth = 0;
            foreach ($tokens as $index => $token) {
                $depth += $token === '(' ? 1 : ($token === ')' ? -1 : 0);
                if ($depth === 0 && $index < \count($tokens) - 1) {
                    break 2;
                }
            }
            array_shift($tokens);
            array_pop($tokens);
        }

        return implode('', array_map(static fn (string $token): string => preg_match('/^"([a-z_][a-z_0-9]*)"$/', $token, $identifier) === 1 ? $identifier[1] : $token, $tokens));
    }

    private function expressionsEqual(Table $table, string $first, string $second): bool
    {
        if ($this->expressionKey($first) === $this->expressionKey($second)) {
            return true;
        }
        // PostgreSQL adds grouping and casts when deparsing expressions. Let its parser
        // compare those forms without executing data reads or removing meaningful grouping.
        try {
            return $this->connection->transactional(function () use ($table, $first, $second): bool {
                $outputs = [];
                foreach ([$first, $second] as $expression) {
                    $plan = $this->connection->fetchOne('EXPLAIN (VERBOSE, FORMAT JSON, COSTS OFF) SELECT (' . $expression . ') FROM ' . $table->getObjectName()->toSQL($this->connection->getDatabasePlatform()) . ' LIMIT 0');
                    $decoded = json_decode($plan, true, 512, \JSON_THROW_ON_ERROR);
                    $outputs[] = $this->expressionKey($decoded[0]['Plan']['Output'][0]);
                }

                return $outputs[0] === $outputs[1];
            });
        } catch (InvalidFieldNameException) {
            // A replacement expression can reference a column added by the pending diff.
            return false;
        }
    }
}

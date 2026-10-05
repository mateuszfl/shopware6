<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Table;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class MigrationQueryGenerator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schemaBuilder
    ) {
    }

    /**
     * Generates the SQL queries for the given entity definition based on the current database schema.
     * If the definition was updated, it will generate the queries to update the schema.
     * If the definition was created, it will generate the queries to create the schema.
     *
     * @return list<string>
     */
    public function generateQueries(EntityDefinition $entityDefinition): array
    {
        $schema = new DatabaseSchema($this->connection);
        if (TableHelper::tableExists($this->connection, $entityDefinition->getEntityName())) {
            return array_merge($schema->prerequisites(), $this->getAlterTableQueries($entityDefinition));
        }

        return array_merge($schema->prerequisites(), $this->getCreateTableQueries($entityDefinition));
    }

    /**
     * @return list<string>
     */
    private function getAlterTableQueries(EntityDefinition $definition): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $originalTableSchema = $schemaManager->introspectTableByUnquotedName($definition->getEntityName());

        // Ordinary indexes are not modeled by definitions; unique keys enforce schema integrity.
        $this->dropIndexes($originalTableSchema);

        $schema = new DatabaseSchema($this->connection);
        $tableSchema = $schema->prepareTable($this->schemaBuilder->buildSchemaOfDefinition($definition));

        $this->dropIndexes($tableSchema);

        // Definitions do not model index declarations. Preserve existing unique keys.
        foreach ($originalTableSchema->getIndexes() as $index) {
            if ($index->getType() !== IndexType::UNIQUE) {
                continue;
            }
            $columns = array_map(static fn (IndexedColumn $column): array => [$column->getColumnName()->getIdentifier()->getValue(), $column->getLength()], $index->getIndexedColumns());
            foreach ($tableSchema->getIndexes() as $generated) {
                if ($generated->getType() !== IndexType::UNIQUE || $index->getPredicate() !== null) {
                    continue;
                }
                $generatedColumns = array_map(static fn (IndexedColumn $column): array => [$column->getColumnName()->getIdentifier()->getValue(), $column->getLength()], $generated->getIndexedColumns());
                if ($generatedColumns === $columns && $generated->getObjectName()->toString() !== $index->getObjectName()->toString()) {
                    $tableSchema = $tableSchema->edit()->renameIndex($generated->getObjectName(), $index->getObjectName())->create();

                    break;
                }
            }
            if (!$tableSchema->hasIndex($index->getObjectName()->toString())) {
                $tableSchema = $tableSchema->edit()->addIndex($index)->create();
            }
        }

        return $schema->alterTableQueries($originalTableSchema, $tableSchema);
    }

    /**
     * @return list<string>
     */
    private function getCreateTableQueries(EntityDefinition $definition): array
    {
        $schema = new DatabaseSchema($this->connection);
        $tableSchema = $schema->prepareTable($this->schemaBuilder->buildSchemaOfDefinition($definition));

        $this->dropIndexes($tableSchema);

        return $schema->createTableQueries($tableSchema);
    }

    private function dropIndexes(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            /** @phpstan-ignore method.deprecated (if can be removed with DBAL 5.0 as primaries won't be inlcuded anymore) */
            if ($index->isPrimary() || $index->getType() === IndexType::UNIQUE) {
                continue;
            }

            $table->dropIndex($index->getObjectName()->toString());
        }
    }
}

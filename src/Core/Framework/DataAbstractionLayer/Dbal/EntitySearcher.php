<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\EntitySearcherTest
 *
 * @internal
 */
#[Package('framework')]
class EntitySearcher implements EntitySearcherInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityDefinitionQueryHelper $queryHelper,
        private readonly CriteriaQueryBuilder $criteriaQueryBuilder
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if ($criteria->getLimit() === 0) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        $table = $definition->getEntityName();

        $query = new QueryBuilder($this->connection);

        $fields = [];
        foreach ($definition->getFields() as $field) {
            if (!$field instanceof StorageAware || $field instanceof ReferenceVersionField || $field instanceof VersionField) {
                continue;
            }
            if ($field instanceof NumberRangeField) {
                $fields[$field->getStorageName()] = $field;

                continue;
            }
            if ($field instanceof AutoIncrementField) {
                $fields[$field->getStorageName()] = $field;

                continue;
            }
            if ($field->is(PrimaryKey::class)) {
                $fields[$field->getStorageName()] = $field;
            }
        }

        foreach ($fields as $field) {
            $query->addSelect(
                EntityDefinitionQueryHelper::escape($table, $this->connection) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName(), $this->connection)
            );
        }

        $query = $this->criteriaQueryBuilder->build($query, $definition, $criteria, $context);

        if ($criteria->getIds() !== []) {
            $this->queryHelper->addIdCondition($criteria, $definition, $query);
        }

        if ($criteria->getGroupFields() !== []) {
            $query = $this->buildRankedQuery($query, $definition, $criteria, $context, $table, $fields);
        } else {
            $this->queryHelper->addGroupBy($definition, $criteria, $context, $query, $table);
            foreach ($definition->getPrimaryKeys() as $primary) {
                if ($primary instanceof StorageAware) {
                    $query->addOrderBy($this->quote($table) . '.' . $this->quote($primary->getStorageName()));
                }
            }
        }

        // add pagination
        if ($criteria->getOffset() !== null) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() !== null) {
            $query->setMaxResults($criteria->getLimit());
        }

        $this->addTotalCountMode($criteria, $query);

        if ($criteria->getTitle()) {
            $query->setTitle($criteria->getTitle() . '::search-ids');
        }

        // execute and fetch ids
        $rows = $query->executeQuery()->fetchAllAssociative();

        $total = $this->getTotalCount($criteria, $query, $rows);

        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $rows = \array_slice($rows, 0, $criteria->getLimit());
        }

        $converted = [];

        foreach ($rows as $row) {
            $pk = [];
            $data = [];

            foreach ($row as $storageName => $value) {
                $field = $fields[$storageName] ?? null;

                if (!$field) {
                    $data[$storageName] = $value;

                    continue;
                }

                if (\is_resource($value)) {
                    $bytes = stream_get_contents($value);
                    if ($bytes === false) {
                        throw DataAbstractionLayerException::unreadableHydrationValue($storageName);
                    }
                    $value = $bytes;
                }
                $value = $field->getSerializer()->decode($field, $value);

                $data[$field->getPropertyName()] = $value;

                if (!$field->is(PrimaryKey::class)) {
                    continue;
                }

                $pk[$field->getPropertyName()] = $value;
            }

            $arrayKey = implode('-', $pk);

            if (\count($pk) === 1) {
                $pk = array_shift($pk);
            }

            $converted[$arrayKey] = [
                'primaryKey' => $pk,
                'data' => $data,
            ];
        }

        if ($criteria->useIdSorting()) {
            $converted = $this->sortByIdArray($criteria->getIds(), $converted);
        }

        return new IdSearchResult($total, $converted, $criteria, $context);
    }

    /**
     * Select one entity per requested group, ordered by score, the complete sorting tuple and primary key.
     * Aggregate joins per physical entity first, so additional joined rows cannot consume a group slot.
     *
     * @param array<string, Field> $fields keyed by storage name
     */
    private function buildRankedQuery(QueryBuilder $query, EntityDefinition $definition, Criteria $criteria, Context $context, string $table, array $fields): QueryBuilder
    {
        $primaryOrder = [];
        foreach ($definition->getPrimaryKeys() as $primary) {
            if (!$primary instanceof StorageAware) {
                continue;
            }
            $storageName = $primary->getStorageName();
            $query->addGroupBy($this->quote($table) . '.' . $this->quote($storageName));
            if (!isset($fields[$storageName])) {
                $query->addSelect($this->quote($table) . '.' . $this->quote($storageName));
            }
            $primaryOrder[] = 'inner_q.' . $this->quote($storageName) . ' ASC';
        }

        $partitionColumns = [];
        foreach (array_values($criteria->getGroupFields()) as $i => $grouping) {
            $accessor = $this->queryHelper->getFieldAccessor($grouping->getField(), $definition, $table, $context, $query);
            $alias = '_group_' . $i;
            $query->addSelect($accessor . ' as ' . $this->quote($alias));
            $query->addGroupBy($this->quote($alias));
            $partitionColumns[] = 'inner_q.' . $this->quote($alias);
        }

        $outer = new QueryBuilder($this->connection);
        $rankingOrder = $query->hasState(Criteria::SCORE_FIELD) ? ['inner_q._score DESC'] : [];
        foreach ($query->getOrderByPairs() as $i => [$expression, $direction]) {
            $alias = Criteria::SCORE_FIELD;
            if ($expression !== Criteria::SCORE_FIELD) {
                $alias = '_sort_' . $i;
                $query->addSelect($expression . ' as ' . $this->quote($alias));
            }
            $rankingOrder[] = 'inner_q.' . $this->quote($alias) . ' ' . $direction;
            $outer->addOrderBy('ranked.' . $this->quote($alias), $direction);
        }
        $rankingOrder = [...$rankingOrder, ...$primaryOrder];
        foreach ($primaryOrder as $order) {
            $outer->addOrderBy(str_replace('inner_q.', 'ranked.', substr($order, 0, -4)));
        }
        $query->resetOrderBy();

        $columns = array_keys($fields);
        if ($query->hasState(Criteria::SCORE_FIELD)) {
            $columns[] = Criteria::SCORE_FIELD;
        }
        foreach ($columns as $column) {
            $outer->addSelect('ranked.' . $this->quote($column));
        }
        $outer->from(\sprintf(
            '(SELECT inner_q.*, ROW_NUMBER() OVER(PARTITION BY %s ORDER BY %s) as _rn FROM (%s) inner_q)',
            implode(', ', $partitionColumns),
            implode(', ', $rankingOrder),
            $query->getSQL()
        ), 'ranked')->andWhere('ranked._rn = 1');
        $outer->setParameters($query->getParameters(), $query->getParameterTypes());

        return $outer;
    }

    private function quote(string $identifier): string
    {
        return EntityDefinitionQueryHelper::escape($identifier, $this->connection);
    }

    private function addTotalCountMode(Criteria $criteria, QueryBuilder $query): void
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            return;
        }

        $query->setMaxResults($criteria->getNextPagesLimit());
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    private function getTotalCount(Criteria $criteria, QueryBuilder $query, array $data): int
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_EXACT) {
            return \count($data);
        }

        $offset = $criteria->getOffset() ?? 0;
        $isPartialPage = $criteria->getLimit() === null || \count($data) < $criteria->getLimit();
        // A partial page is the last page, so the fetched rows already determine the exact total and no separate
        // COUNT(*) query is needed. An empty page with an offset does not: the total could be anything up to the
        // offset, so fall through to the count query for that case.
        if ($isPartialPage && ($data !== [] || $offset === 0)) {
            return $offset + \count($data);
        }

        $query->resetOrderBy();
        $query->setMaxResults(null);
        $query->setFirstResult(0);

        $total = new QueryBuilder($this->connection);
        $total->select('COUNT(*)')
            ->from(\sprintf('(%s) total', $query->getSQL()))
            ->setParameters($query->getParameters(), $query->getParameterTypes());

        return (int) $total->executeQuery()->fetchOne();
    }

    /**
     * @param array<string>|array<array<string, string>> $ids
     * @param array<string, array{primaryKey: string|array<string, string>, data: array<string, mixed>}> $data
     *
     * @return array<string, array{primaryKey: string|array<string, string>, data: array<string, mixed>}>
     */
    private function sortByIdArray(array $ids, array $data): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', $id);
            }

            if (\array_key_exists($id, $data)) {
                $sorted[$id] = $data[$id];
            }
        }

        return $sorted;
    }
}

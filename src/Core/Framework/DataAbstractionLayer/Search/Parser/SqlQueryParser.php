<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class SqlQueryParser
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinitionQueryHelper $queryHelper,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param list<ScoreQuery> $queries
     */
    public function parseRanking(
        array $queries,
        EntityDefinition $definition,
        string $root,
        Context $context
    ): ParseResult {
        $result = new ParseResult();

        foreach ($queries as $query) {
            $parsed = $this->parse($query->getQuery(), $definition, $context, $root);
            $score = $this->getKey();
            $result->addParameter($score, (string) $query->getScore(), ParameterType::STRING);

            foreach ($parsed->getWheres() as $where) {
                if ($query->getScoreField()) {
                    $field = $this->queryHelper->getFieldAccessor(
                        $query->getScoreField(),
                        $definition,
                        $root,
                        $context,
                        $result
                    );

                    $result->addWhere(
                        \sprintf('CASE WHEN %s THEN CAST(:%s AS DECIMAL(30, 10)) * %s ELSE 0 END', $where, $score, $field)
                    );

                    continue;
                }

                $result->addWhere(
                    \sprintf('CASE WHEN %s THEN CAST(:%s AS DECIMAL(30, 10)) ELSE 0 END', $where, $score)
                );
            }

            foreach ($parsed->getParameters() as $key => $parameter) {
                $result->addParameter($key, $parameter, $parsed->getType($key));
            }
        }

        return $result;
    }

    public function parse(
        Filter $query,
        EntityDefinition $definition,
        Context $context,
        ?string $root = null,
        bool $negated = false
    ): ParseResult {
        if ($root === null) {
            $root = $definition->getEntityName();
        }

        if ($query instanceof SingleFieldFilter && $query->getResolved()) {
            $result = new ParseResult();
            $result->addWhere($query->getResolved());

            return $result;
        }

        return match (true) {
            $query instanceof EqualsFilter => $this->parseEqualsFilter($query, $definition, $root, $context, $negated),
            $query instanceof EqualsAnyFilter => $this->parseEqualsAnyFilter($query, $definition, $root, $context, $negated),
            $query instanceof ContainsFilter => $this->parseContainsFilter($query, $definition, $root, $context),
            $query instanceof PrefixFilter => $this->parsePrefixFilter($query, $definition, $root, $context),
            $query instanceof SuffixFilter => $this->parseSuffixFilter($query, $definition, $root, $context),
            $query instanceof RangeFilter => $this->parseRangeFilter($query, $definition, $root, $context),
            $query instanceof NotFilter => $this->parseNotFilter($query, $definition, $root, $context),
            $query instanceof MultiFilter => $this->parseMultiFilter($query, $definition, $root, $context, $negated),
            default => throw DataAbstractionLayerException::unsupportedQueryFilter($query::class),
        };
    }

    private function parseRangeFilter(
        RangeFilter $query,
        EntityDefinition $definition,
        string $root,
        Context $context
    ): ParseResult {
        $result = new ParseResult();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context, $result);
        $definitionField = $this->queryHelper->getField($query->getField(), $definition, $root);
        $binary = $definitionField instanceof IdField || $definitionField instanceof FkField;
        $where = [];
        $bounds = [
            $query->hasParameter(RangeFilter::GT) ? RangeFilter::GT : RangeFilter::GTE => $query->hasParameter(RangeFilter::GT) ? '>' : '>=',
            $query->hasParameter(RangeFilter::LT) ? RangeFilter::LT : RangeFilter::LTE => $query->hasParameter(RangeFilter::LT) ? '<' : '<=',
        ];
        foreach ($bounds as $bound => $operator) {
            if (!$query->hasParameter($bound)) {
                continue;
            }
            $key = $this->getKey();
            $value = $query->getParameter($bound);
            if ($binary && \is_string($value) && Uuid::isValid($value)) {
                $value = Uuid::fromHexToBytes($value);
            }
            $where[] = $field . ' ' . $operator . ' :' . $key;
            $result->addParameter($key, $value, $binary ? ParameterType::BINARY : ParameterType::STRING);
        }

        $where = '(' . implode(' AND ', $where) . ')';
        $result->addWhere($where);

        return $result;
    }

    private function parseContainsFilter(ContainsFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $result = new ParseResult();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context, $result);
        $result->addWhere($field . ' LIKE :' . $key);

        $escaped = addcslashes((string) $query->getValue(), '\\_%');
        $result->addParameter($key, '%' . $escaped . '%');

        return $result;
    }

    private function parsePrefixFilter(PrefixFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $result = new ParseResult();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context, $result);
        $result->addWhere($field . ' LIKE :' . $key);

        $escaped = addcslashes($query->getValue(), '\\_%');
        $result->addParameter($key, $escaped . '%');

        return $result;
    }

    private function parseSuffixFilter(SuffixFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $result = new ParseResult();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context, $result);
        $result->addWhere($field . ' LIKE :' . $key);

        $escaped = addcslashes($query->getValue(), '\\_%');
        $result->addParameter($key, '%' . $escaped);

        return $result;
    }

    private function parseEqualsAnyFilter(EqualsAnyFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
    {
        $key = $this->getKey();
        $result = new ParseResult();

        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context, $result);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        if ($field instanceof ListField) {
            $where = [];

            foreach ($query->getValue() as $value) {
                $key = $this->getKey();
                $where[] = (new DatabasePlatformResolver($this->connection))->dialect()->jsonListContains($select, ':' . $key);
                $result->addParameter($key, json_encode([$value], \JSON_THROW_ON_ERROR));
            }

            if ($where === []) {
                $result->addWhere('1 = 0');

                return $result;
            }

            $result->addWhere('(' . implode(' OR ', $where) . ')');

            return $result;
        }

        $hasNulls = false;
        $value = [];
        foreach ($query->getValue() as $v) {
            if ($v === null) {
                $hasNulls = true;
                continue;
            }
            $value[] = $v;
        }
        if ($field instanceof IdField || $field instanceof FkField) {
            $value = array_filter(array_map(static fn (bool|float|int|string $id): string => Uuid::fromHexToBytes((string) $id), $value));
        }

        $where = [];
        if ($value !== []) {
            $result->addParameter($key, $value, $field instanceof IdField || $field instanceof FkField ? ArrayParameterType::BINARY : ArrayParameterType::STRING);

            $inFilter = $select . ' IN (:' . $key . ')';
            if ($negated && !$hasNulls) {
                $where[] = '(' . $select . ' IS NOT NULL AND ' . $inFilter . ')';
            } else {
                $where[] = $inFilter;
            }
        }

        if ($hasNulls) {
            $where[] = $select . ' IS NULL';
        }

        if ($where === []) {
            $result->addWhere('1 = 0');

            return $result;
        }

        $result->addWhere('(' . implode(' OR ', $where) . ')');

        return $result;
    }

    private function parseEqualsFilter(EqualsFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
    {
        $key = $this->getKey();
        $result = new ParseResult();

        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context, $result);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        if ($field instanceof ListField) {
            if ($query->getValue() === null) {
                $result->addWhere($select . ' IS NULL');

                return $result;
            }
            $result->addWhere((new DatabasePlatformResolver($this->connection))->dialect()->jsonListContains($select, ':' . $key));
            $result->addParameter($key, json_encode([$query->getValue()], \JSON_THROW_ON_ERROR));

            return $result;
        }

        if ($negated || $query->getValue() === null) {
            $result->addWhere((new DatabasePlatformResolver($this->connection))->dialect()->nullSafeEquals($select, ':' . $key));
        } else {
            $result->addWhere($select . ' = :' . $key);
        }

        $value = $query->getValue();
        if ($value === null) {
            $result->addParameter($key, null);

            return $result;
        }

        if ($field instanceof IdField || $field instanceof FkField) {
            $value = Uuid::fromHexToBytes((string) $value);
        }

        $result->addParameter($key, $value, $field instanceof IdField || $field instanceof FkField ? ParameterType::BINARY : ParameterType::STRING);

        return $result;
    }

    private function parseMultiFilter(MultiFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
    {
        $result = $this->iterateNested($query, $definition, $root, $context, $negated);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if ($wheres !== []) {
            $result->addWhere('(' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function parseNotFilter(NotFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $result = $this->iterateNested($query, $definition, $root, $context, true);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';

        if ($wheres !== []) {
            $result->addWhere('NOT (' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function iterateNested(MultiFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
    {
        $result = new ParseResult();
        foreach ($query->getQueries() as $multiFilter) {
            $result = $result->merge(
                $this->parse($multiFilter, $definition, $context, $root, $negated)
            );
        }

        return $result;
    }

    private function getKey(): string
    {
        return 'param_' . Uuid::randomHex();
    }
}

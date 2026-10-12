<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Database\DatabasePlatformResolverTest
 */
#[Package('framework')]
final class PostgreSQLDialect extends AbstractDatabaseDialect
{
    public function unsupportedReplacementConflicts(string $table): SqlFragment
    {
        // DBAL's column-based index introspection omits expression-only indexes.
        return new SqlFragment(
            'SELECT COUNT(*) FROM pg_catalog.pg_index WHERE indrelid = CAST(? AS regclass) AND indisunique AND (indexprs IS NOT NULL OR indpred IS NOT NULL)',
            [$this->quoteIdentifier($table)],
            [ParameterType::STRING],
        );
    }

    public function capabilities(): DatabaseCapabilities
    {
        return new DatabaseCapabilities(true, true, true, true);
    }

    public function hex(string|array $column): string
    {
        return $this->hexExpression($this->quoteColumn($column));
    }

    public function hexExpression(string $expression): string
    {
        return 'sw_uuid_hex(' . $expression . ')';
    }

    public function binaryText(string $expression): string
    {
        return 'CAST(' . $expression . ' AS text) COLLATE "C"';
    }

    public function dateBucket(string $expression, string $interval, ?string $timeZone = null): SqlFragment
    {
        if (!\in_array($interval, ['minute', 'hour', 'day', 'week', 'month', 'quarter', 'year'], true)) {
            throw DatabaseDialectException::invalidOperation('unsupported date bucket interval');
        }
        if ($timeZone !== null) {
            $expression = '(' . $expression . ' AT TIME ZONE \'UTC\' AT TIME ZONE ?)';
        }

        return (new SqlFragment(
            "date_trunc('$interval', $expression)",
            $timeZone === null ? [] : [$timeZone],
            $timeZone === null ? [] : [ParameterType::STRING],
        ))->named();
    }

    public function fromHex(string $placeholder): string
    {
        if ($placeholder !== '?') {
            throw DatabaseDialectException::invalidOperation('hex input must be a bound placeholder');
        }

        return 'decode(?, \'hex\')';
    }

    public function jsonExtract(string|array $column, array $path): SqlFragment
    {
        if ($path === []) {
            return new SqlFragment($this->quoteColumn($column));
        }

        return new SqlFragment(
            'jsonb_extract_path(' . $this->quoteColumn($column) . '::jsonb, ' . implode(', ', array_fill(0, \count($path), '?::text')) . ')',
            $path,
            array_fill(0, \count($path), ParameterType::STRING),
        );
    }

    public function jsonObject(array $fields): SqlFragment
    {
        $expressions = [];
        foreach ($fields as $column) {
            $expressions[] = '?::text';
            $expressions[] = $this->quoteColumn($column);
        }

        return new SqlFragment('jsonb_build_object(' . implode(', ', $expressions) . ')', array_keys($fields), array_fill(0, \count($fields), ParameterType::STRING));
    }

    public function jsonExtractPath(string $expression, JsonPath $path): SqlFragment
    {
        $hasIndex = array_any($path->steps, static fn (array $step): bool => $step['kind'] === 'index');
        if (!$path->hasWildcard() && !$hasIndex) {
            return (new SqlFragment(
                'jsonb_path_query_first(' . $expression . '::jsonb, ?::jsonpath, \'{}\'::jsonb, true)',
                ['strict ' . $path->expression()],
                [ParameterType::STRING]
            ))->named();
        }

        // Evaluate each step separately. A missing member in one wildcard element
        // must not stop matching later elements; [0] alone permits scalar autowrapping.
        $from = '(SELECT ' . $expression . '::jsonb AS value) AS sw_path_root';
        $previous = 'sw_path_root';
        $selectors = [];
        $ordering = [];
        foreach ($path->steps as $index => $step) {
            $alias = 'sw_path_' . $index;
            $selector = match ($step['kind']) {
                'member' => 'strict $.' . json_encode($step['value'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                'wildcard' => 'strict $.*',
                default => ($step['value'] === '*' ? 'strict' : 'lax') . ' $[' . $step['value'] . ']',
            };
            $from .= ' CROSS JOIN LATERAL jsonb_path_query(' . $previous . '.value, ?::jsonpath, \'{}\'::jsonb, true) WITH ORDINALITY AS ' . $alias . '(value, ordinality)';
            $selectors[] = $selector;
            $ordering[] = $alias . '.ordinality';
            $previous = $alias;
        }
        $select = $path->hasWildcard()
            ? 'jsonb_agg(' . $previous . '.value ORDER BY ' . implode(', ', $ordering) . ')'
            : $previous . '.value';
        $sql = '(SELECT ' . $select . ' FROM ' . $from . ($path->hasWildcard() ? '' : ' LIMIT 1') . ')';

        return (new SqlFragment($sql, $selectors, array_fill(0, \count($selectors), ParameterType::STRING)))->named();
    }

    public function jsonScalar(string $expression, string $type = 'text'): string
    {
        $text = '(' . $expression . ' #>> \'{}\')';
        // Match MySQL's numeric prefix conversion, including false/non-numeric -> 0.
        $number = 'COALESCE(substring(' . $text . ' FROM \'^[[:space:]]*([+-]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+)(?:[eE][+-]?[0-9]+)?)\')::numeric, 0)';
        $scalar = match ($type) {
            'number' => $number,
            'bool' => 'CASE WHEN ' . $text . ' != \'true\' AND ' . $number . ' = 0 THEN 0 ELSE 1 END',
            'datetime' => 'CASE WHEN ' . $text . ' ~ \'^[0-9]{4}([-/]?[0-9]{2}){2}([ T]|$)\' AND pg_input_is_valid(' . $text . ', \'timestamp without time zone\') THEN CAST(' . $text . ' AS timestamp(3) without time zone) ELSE NULL END',
            'date' => 'CASE WHEN ' . $text . ' ~ \'^[0-9]{4}([-/]?[0-9]{2}){2}([ T]|$)\' AND pg_input_is_valid(' . $text . ', \'date\') THEN CAST(' . $text . ' AS date) ELSE NULL END',
            'json' => $expression,
            default => $text . ' COLLATE "sw_type_ci"',
        };

        return 'CASE WHEN jsonb_typeof(' . $expression . ') != \'null\' THEN ' . $scalar . ' ELSE NULL END';
    }

    public function jsonListContains(string $expression, string $placeholder): string
    {
        // JSON_CONTAINS descends through arrays, unlike jsonb structural containment.
        // ListField filters bind a singleton scalar candidate; object values are not list members.
        $target = $expression . '::jsonb';
        $nested = '(WITH RECURSIVE sw_list_values(value) AS (SELECT ' . $target
            . ' UNION ALL SELECT element.value FROM sw_list_values CROSS JOIN LATERAL jsonb_array_elements('
            . 'CASE WHEN jsonb_typeof(sw_list_values.value) = \'array\' THEN sw_list_values.value ELSE \'[]\'::jsonb END'
            . ') AS element(value)) SELECT EXISTS (SELECT 1 FROM sw_list_values WHERE value = (' . $placeholder . '::jsonb -> 0)))';

        return 'CASE WHEN jsonb_typeof(' . $target . ') = \'array\' THEN ' . $nested . ' ELSE (' . $target . ' @> ' . $placeholder . '::jsonb) END';
    }

    public function nullSafeEquals(string $expression, string $placeholder): string
    {
        return $expression . ' IS NOT DISTINCT FROM ' . $placeholder;
    }

    public function jsonOverlaps(string $left, string $right): string
    {
        // Evaluate operands once; unlike containment, overlap never descends into nested arrays.
        return '(SELECT CASE WHEN l IS NULL OR r IS NULL THEN NULL '
            . 'WHEN jsonb_typeof(l) = \'object\' AND jsonb_typeof(r) = \'object\' THEN EXISTS ('
            . 'SELECT 1 FROM jsonb_each(l) a JOIN jsonb_each(r) b ON a.key = b.key AND a.value = b.value) '
            . 'ELSE EXISTS (SELECT 1 FROM '
            . 'jsonb_array_elements(CASE WHEN jsonb_typeof(l) = \'array\' THEN l ELSE jsonb_build_array(l) END) a '
            . 'JOIN '
            . 'jsonb_array_elements(CASE WHEN jsonb_typeof(r) = \'array\' THEN r ELSE jsonb_build_array(r) END) b '
            . 'ON a.value = b.value) END FROM (SELECT ' . $left . '::jsonb AS l, ' . $right . '::jsonb AS r) sw_overlap)';
    }

    public function jsonObjectExpressions(array $fields, bool $aggregate = false): SqlFragment
    {
        $parts = [];
        foreach ($fields as $expression) {
            $parts[] = '?::text';
            $parts[] = $expression;
        }
        $sql = 'jsonb_build_object(' . implode(', ', $parts) . ')';
        if ($aggregate) {
            $sql = 'jsonb_agg(DISTINCT ' . $sql . ')';
        }

        return (new SqlFragment($sql, array_map(strval(...), array_keys($fields)), array_fill(0, \count($fields), ParameterType::STRING)))->named();
    }

    public function stringAggregate(string|array $column, string|array $orderColumn, string $separator = ','): SqlFragment
    {
        return new SqlFragment('string_agg(' . $this->quoteColumn($column) . '::text, ?::text ORDER BY ' . $this->quoteColumn($orderColumn) . ')', [$separator], [ParameterType::STRING]);
    }

    public function hexAggregate(string|array $column, string $separator = '||'): SqlFragment
    {
        return new SqlFragment('string_agg(' . $this->hex($column) . ', ?::text ORDER BY ' . $this->quoteColumn($column) . ')', [$separator], [ParameterType::STRING]);
    }

    public function insertConflictClause(array $conflictColumns, array $updateColumns, bool $ignoreDuplicates = false): string
    {
        if ($updateColumns !== []) {
            if ($conflictColumns === []) {
                throw DatabaseDialectException::invalidOperation('a PostgreSQL update on conflict requires an explicit conflict target');
            }

            return $this->upsertClause($conflictColumns, $updateColumns);
        }

        if (!$ignoreDuplicates) {
            return '';
        }

        return $conflictColumns === [] ? ' ON CONFLICT DO NOTHING' : $this->upsertClause($conflictColumns, []);
    }

    public function jsonSetMembers(string $column, array $members): SqlFragment
    {
        $column = $this->quoteIdentifier($column);
        if ($members === []) {
            return new SqlFragment($column);
        }

        $expression = "COALESCE($column, '{}'::jsonb)";
        $parameters = [];
        foreach ($members as $member => $value) {
            $expression = 'jsonb_set(' . $expression . ', ARRAY[?]::text[], ?::jsonb, true)';
            $parameters[] = (string) $member;
            $parameters[] = json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE);
        }

        return new SqlFragment(
            "CASE WHEN $column IS NULL OR jsonb_typeof($column) = 'object' THEN $expression ELSE $column END",
            $parameters,
            array_fill(0, \count($parameters), ParameterType::STRING),
        );
    }

    public function queryShape(): DatabaseQueryShape
    {
        return new PostgreSQLQueryShape($this);
    }

    /**
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    protected function upsertClause(array $conflictColumns, array $updateColumns): string
    {
        $target = implode(', ', array_map($this->quoteIdentifier(...), $conflictColumns));
        if ($updateColumns === []) {
            return ' ON CONFLICT (' . $target . ') DO NOTHING';
        }

        $assignments = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = EXCLUDED.' . $this->quoteIdentifier($column), $updateColumns);

        return ' ON CONFLICT (' . $target . ') DO UPDATE SET ' . implode(', ', $assignments);
    }
}

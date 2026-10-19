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
final class MySQLDialect extends AbstractDatabaseDialect
{
    public function unsupportedReplacementConflicts(string $table): SqlFragment
    {
        // Native REPLACE resolves all unique indexes, including functional indexes.
        return new SqlFragment('SELECT 0');
    }

    public function random(): string
    {
        return 'RAND()';
    }

    public function capabilities(): DatabaseCapabilities
    {
        return new DatabaseCapabilities(false, false, false, false);
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
        return 'CAST(CAST(' . $expression . ' AS CHAR CHARACTER SET utf8mb4) AS BINARY)';
    }

    public function dateBucket(string $expression, string $interval, ?string $timeZone = null): SqlFragment
    {
        if ($timeZone !== null) {
            $expression = 'CONVERT_TZ(' . $expression . ', \'+00:00\', ?)';
        }
        $sql = match ($interval) {
            'minute' => "DATE_FORMAT($expression, '%Y-%m-%d %H:%i:00')",
            'hour' => "DATE_FORMAT($expression, '%Y-%m-%d %H:00:00')",
            'day' => "DATE_FORMAT($expression, '%Y-%m-%d 00:00:00')",
            'week' => "DATE_SUB(DATE($expression), INTERVAL WEEKDAY($expression) DAY)",
            'month' => "DATE_FORMAT($expression, '%Y-%m-01 00:00:00')",
            'quarter' => "DATE_ADD(MAKEDATE(YEAR($expression), 1), INTERVAL (QUARTER($expression) - 1) * 3 MONTH)",
            'year' => "DATE_FORMAT($expression, '%Y-01-01 00:00:00')",
            default => throw DatabaseDialectException::invalidOperation('unsupported date bucket interval'),
        };
        $parameters = $timeZone === null ? [] : array_fill(0, substr_count($sql, '?'), $timeZone);

        return (new SqlFragment($sql, $parameters, array_fill(0, \count($parameters), ParameterType::STRING)))->named();
    }

    public function fromHex(string $placeholder): string
    {
        if ($placeholder !== '?') {
            throw DatabaseDialectException::invalidOperation('hex input must be a bound placeholder');
        }

        return 'UNHEX(?)';
    }

    public function jsonExtract(string|array $column, array $path): SqlFragment
    {
        $parts = array_map(static fn (string $part): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $part) . '"', $path);

        return new SqlFragment('JSON_EXTRACT(' . $this->quoteColumn($column) . ', ?)', ['$' . ($parts === [] ? '' : '.' . implode('.', $parts))], [ParameterType::STRING]);
    }

    public function jsonObject(array $fields): SqlFragment
    {
        $expressions = [];
        foreach ($fields as $column) {
            $expressions[] = '?';
            $expressions[] = $this->quoteColumn($column);
        }

        return new SqlFragment('JSON_OBJECT(' . implode(', ', $expressions) . ')', array_keys($fields), array_fill(0, \count($fields), ParameterType::STRING));
    }

    public function jsonExtractPath(string $expression, JsonPath $path): SqlFragment
    {
        return (new SqlFragment('JSON_EXTRACT(' . $expression . ', ?)', [$path->expression()], [ParameterType::STRING]))->named();
    }

    public function jsonScalar(string $expression, string $type = 'text'): string
    {
        $text = 'JSON_UNQUOTE(' . $expression . ')';
        $scalar = match ($type) {
            'number' => '(' . $text . ' + 0.0)',
            'bool' => 'CASE WHEN ' . $text . ' != \'true\' AND ' . $text . ' = 0 THEN 0 ELSE 1 END',
            'datetime' => 'CAST(' . $text . ' AS datetime(3))',
            'date' => 'CAST(' . $text . ' AS DATE)',
            'json' => $expression,
            default => 'CONVERT(' . $text . ' USING utf8mb4) COLLATE utf8mb4_unicode_ci',
        };

        return 'CASE WHEN JSON_TYPE(' . $expression . ') != \'NULL\' THEN ' . $scalar . ' ELSE NULL END';
    }

    public function jsonListContains(string $expression, string $placeholder): string
    {
        return 'JSON_CONTAINS(' . $expression . ', ' . $placeholder . ')';
    }

    public function nullSafeEquals(string $expression, string $placeholder): string
    {
        return $expression . ' <=> ' . $placeholder;
    }

    public function jsonOverlaps(string $left, string $right): string
    {
        return 'JSON_OVERLAPS(' . $left . ', ' . $right . ')';
    }

    public function jsonObjectExpressions(array $fields, bool $aggregate = false): SqlFragment
    {
        $parts = [];
        foreach ($fields as $expression) {
            $parts[] = '?';
            $parts[] = $expression;
        }
        $sql = 'JSON_OBJECT(' . implode(', ', $parts) . ')';
        if ($aggregate) {
            $sql = 'CONCAT(\'[\', GROUP_CONCAT(DISTINCT ' . $sql . '), \']\')';
        }

        return (new SqlFragment($sql, array_map(strval(...), array_keys($fields)), array_fill(0, \count($fields), ParameterType::STRING)))->named();
    }

    public function stringAggregate(string|array $column, string|array $orderColumn, string $separator = ','): SqlFragment
    {
        return new SqlFragment('GROUP_CONCAT(' . $this->quoteColumn($column) . ' ORDER BY ' . $this->quoteColumn($orderColumn) . ' SEPARATOR ?)', [$separator], [ParameterType::STRING]);
    }

    public function hexAggregate(string|array $column, string $separator = '||'): SqlFragment
    {
        return new SqlFragment('GROUP_CONCAT(' . $this->hex($column) . ' ORDER BY ' . $this->quoteColumn($column) . ' SEPARATOR ?)', [$separator], [ParameterType::STRING]);
    }

    public function insertConflictClause(array $conflictColumns, array $updateColumns, bool $ignoreDuplicates = false): string
    {
        return $updateColumns === [] ? '' : $this->upsertClause($conflictColumns, $updateColumns);
    }

    public function jsonSetMembers(string $column, array $members): SqlFragment
    {
        $column = $this->quoteIdentifier($column);
        if ($members === []) {
            return new SqlFragment($column);
        }

        $arguments = [];
        $parameters = [];
        foreach ($members as $member => $value) {
            $arguments[] = '?, JSON_EXTRACT(?, \'$\')';
            $parameters[] = '$.' . json_encode((string) $member, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
            $parameters[] = json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE);
        }

        return new SqlFragment(
            "JSON_SET(COALESCE($column, '{}'), " . implode(', ', $arguments) . ')',
            $parameters,
            array_fill(0, \count($parameters), ParameterType::STRING),
        );
    }

    public function queryShape(): DatabaseQueryShape
    {
        return new MySQLQueryShape($this);
    }

    /**
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    protected function upsertClause(array $conflictColumns, array $updateColumns): string
    {
        if ($updateColumns === []) {
            // MySQL has no targeted DO NOTHING; a no-op update still reports duplicate-key handling.
            $column = $this->quoteIdentifier($conflictColumns[0]);

            return ' ON DUPLICATE KEY UPDATE ' . $column . ' = ' . $column;
        }

        $assignments = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = VALUES(' . $this->quoteIdentifier($column) . ')', $updateColumns);

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
    }
}

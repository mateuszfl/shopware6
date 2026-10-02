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
    public function capabilities(): DatabaseCapabilities
    {
        return new DatabaseCapabilities(true, true, true, true);
    }

    public function hex(string|array $column): string
    {
        return 'encode(' . $this->quoteColumn($column) . ', \'hex\')';
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

    public function stringAggregate(string|array $column, string|array $orderColumn, string $separator = ','): SqlFragment
    {
        return new SqlFragment('string_agg(' . $this->quoteColumn($column) . '::text, ?::text ORDER BY ' . $this->quoteColumn($orderColumn) . ')', [$separator], [ParameterType::STRING]);
    }

    public function hexAggregate(string|array $column, string $separator = '||'): SqlFragment
    {
        return new SqlFragment('string_agg(' . $this->hex($column) . ', ?::text ORDER BY ' . $this->quoteColumn($column) . ')', [$separator], [ParameterType::STRING]);
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

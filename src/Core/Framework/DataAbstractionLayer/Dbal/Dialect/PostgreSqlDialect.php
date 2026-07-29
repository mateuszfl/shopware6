<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * PostgreSQL dialect.
 *
 * Renders the same DAL SQL fragments as {@see MySqlDialect} using PostgreSQL syntax. This is the
 * foundation for running Shopware on PostgreSQL; the full port (schema, migrations, the ~600 raw
 * SQL call sites and the ONLY_FULL_GROUP_BY dependent queries) is delivered in later phases.
 *
 * @internal
 */
#[Package('framework')]
class PostgreSqlDialect extends AbstractSqlDialect
{
    public function supports(AbstractPlatform $platform): bool
    {
        return $platform instanceof PostgreSQLPlatform;
    }

    public function getName(): string
    {
        return 'postgresql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '"')) {
            throw DataAbstractionLayerException::invalidIdentifier($identifier);
        }

        return '"' . $identifier . '"';
    }

    public function toHex(string $expression): string
    {
        // encode() over a bytea already yields lower-case hex, matching LOWER(HEX()) on MySQL.
        return \sprintf("encode(%s, 'hex')", $expression);
    }

    public function fromHex(string $expression): string
    {
        return \sprintf("decode(%s, 'hex')", $expression);
    }

    public function jsonExtract(string $column, string $path): string
    {
        // #>> takes a text[] path and returns the value as text. MySQL passes a '$.a.b' style path,
        // so callers must translate it; jsonExtractTyped() below performs that translation.
        return \sprintf('(%s #>> %s)', $column, $this->jsonPathToPgArray($path));
    }

    public function jsonExtractTyped(string $column, string $path, JsonExtractType $type): string
    {
        // #>> already returns SQL NULL for a JSON null, so the MySQL IF(JSON_TYPE...) guard is unnecessary.
        $value = $this->jsonExtract($column, $path);

        return match ($type) {
            JsonExtractType::INT => \sprintf('(%s)::bigint', $value),
            JsonExtractType::FLOAT => \sprintf('(%s)::double precision', $value),
            JsonExtractType::BOOL => \sprintf('(%s)::boolean', $value),
            JsonExtractType::DATETIME => \sprintf('(%s)::timestamp(3)', $value),
            JsonExtractType::DATE => \sprintf('(%s)::date', $value),
            JsonExtractType::STRING => $value,
        };
    }

    public function ifNull(string $expression, string $default): string
    {
        return \sprintf('COALESCE(%s, %s)', $expression, $default);
    }

    public function groupConcat(string $expression, string $separator = ',', bool $distinct = false): string
    {
        return \sprintf(
            'string_agg(%s%s, %s)',
            $distinct ? 'DISTINCT ' : '',
            $expression,
            $this->quoteString($separator)
        );
    }

    public function jsonObject(array $keyExpressionMap): string
    {
        return \sprintf('json_build_object(%s)', implode(', ', $this->flattenJsonObjectArgs($keyExpressionMap)));
    }

    public function jsonObjectAgg(array $keyExpressionMap): string
    {
        return \sprintf("COALESCE(json_agg(DISTINCT %s)::text, '[]')", $this->jsonObject($keyExpressionMap));
    }

    public function insertIgnoreKeyword(): string
    {
        return 'INSERT';
    }

    public function insertIgnoreSuffix(): string
    {
        return ' ON CONFLICT DO NOTHING';
    }

    public function buildUpsertSuffix(array $updateColumns, array $conflictColumns = []): string
    {
        if ($updateColumns === []) {
            return '';
        }

        if ($conflictColumns === []) {
            throw DataAbstractionLayerException::missingConflictColumns($this->getName());
        }

        $target = implode(', ', array_map($this->quoteIdentifier(...), $conflictColumns));

        $parts = [];
        foreach ($updateColumns as $column) {
            $quoted = $this->quoteIdentifier($column);
            $parts[] = \sprintf('%s = EXCLUDED.%s', $quoted, $quoted);
        }

        return \sprintf(' ON CONFLICT (%s) DO UPDATE SET %s', $target, implode(', ', $parts));
    }

    public function supportsReplaceInto(): bool
    {
        return false;
    }

    public function likeEscapeClause(): string
    {
        return " ESCAPE '\\'";
    }

    public function nullSafeEquals(string $left, string $right): string
    {
        return \sprintf('%s IS NOT DISTINCT FROM %s', $left, $right);
    }

    public function dateHistogramFormat(string $accessor, string $interval): string
    {
        return match ($interval) {
            DateHistogramAggregation::PER_MINUTE => \sprintf("to_char(%s, 'YYYY-MM-DD HH24:MI')", $accessor),
            DateHistogramAggregation::PER_HOUR => \sprintf("to_char(%s, 'YYYY-MM-DD HH24')", $accessor),
            DateHistogramAggregation::PER_DAY => \sprintf("to_char(%s, 'YYYY-MM-DD')", $accessor),
            DateHistogramAggregation::PER_WEEK => \sprintf("to_char(%s, 'IYYY-IW')", $accessor),
            DateHistogramAggregation::PER_MONTH => \sprintf("to_char(%s, 'YYYY-MM')", $accessor),
            DateHistogramAggregation::PER_QUARTER => \sprintf("(to_char(%s, 'YYYY') || '-' || EXTRACT(QUARTER FROM %s)::int)", $accessor, $accessor),
            DateHistogramAggregation::PER_YEAR => \sprintf("to_char(%s, 'YYYY')", $accessor),
            default => \sprintf("to_char(%s, 'YYYY-MM-DD HH24:MI:SS')", $accessor),
        };
    }

    /**
     * Translate a MySQL-style JSON path expression (e.g. the literal `'$.a.b'`, possibly wrapped
     * in quotes by Connection::quote()) into a PostgreSQL text[] path literal (e.g. `'{a,b}'`).
     */
    private function jsonPathToPgArray(string $path): string
    {
        $trimmed = trim($path, "'\"");
        $trimmed = ltrim($trimmed, '$');
        $trimmed = ltrim($trimmed, '.');

        $segments = $trimmed === '' ? [] : explode('.', $trimmed);
        $segments = array_map(static fn (string $segment): string => trim($segment, '"'), $segments);

        return $this->quoteString('{' . implode(',', $segments) . '}');
    }

    /**
     * @param array<string, string> $keyExpressionMap
     *
     * @return list<string>
     */
    private function flattenJsonObjectArgs(array $keyExpressionMap): array
    {
        $args = [];
        foreach ($keyExpressionMap as $key => $expression) {
            $args[] = $this->quoteString($key);
            $args[] = $expression;
        }

        return $args;
    }

    private function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

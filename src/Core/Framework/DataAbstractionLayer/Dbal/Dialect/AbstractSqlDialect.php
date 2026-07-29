<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Shopware\Core\Framework\Log\Package;

/**
 * Abstraction over the SQL-dialect specific fragments the DAL emits.
 *
 * Shopware historically assembles raw SQL strings with MySQL/MariaDB specific syntax
 * (backtick quoting, JSON_EXTRACT/JSON_UNQUOTE, LOWER(HEX())/UNHEX(), GROUP_CONCAT,
 * IFNULL, ON DUPLICATE KEY UPDATE, <=>, DATE_FORMAT/QUARTER, ...). This class centralises
 * those fragments so a second engine (PostgreSQL) can provide alternative renderings while
 * the MySQL implementation stays byte-for-byte identical to the previous hard-coded output.
 *
 * All methods are pure string builders (no I/O). This keeps them trivially unit-testable and
 * safe to call from static contexts (see {@see SqlDialectProvider}).
 *
 * A class (not an interface) is used on purpose: it lets us add fragment methods in later
 * phases with a sensible shared default, without breaking third-party subclasses.
 *
 * @internal This is an internal building block. Method set will grow across porting phases.
 */
#[Package('framework')]
abstract class AbstractSqlDialect
{
    /**
     * Whether this dialect handles the given (DBAL negotiated) platform.
     */
    abstract public function supports(AbstractPlatform $platform): bool;

    /**
     * Short, stable identifier of the dialect, e.g. "mysql" or "postgresql".
     */
    abstract public function getName(): string;

    /**
     * Quote an identifier (table/column/alias name).
     *
     * MySQL: `foo` ; PostgreSQL: "foo".
     */
    abstract public function quoteIdentifier(string $identifier): string;

    /**
     * Render a lower-case hexadecimal representation of a binary column/expression.
     *
     * MySQL: LOWER(HEX(expr)) ; PostgreSQL: encode(expr, 'hex').
     */
    abstract public function toHex(string $expression): string;

    /**
     * Turn a hexadecimal string expression (literal or placeholder) into its binary value.
     *
     * MySQL: UNHEX(expr) ; PostgreSQL: decode(expr, 'hex').
     */
    abstract public function fromHex(string $expression): string;

    /**
     * Extract the raw JSON value at $path (a JSON path like `$.foo.bar`) from a JSON column expression.
     */
    abstract public function jsonExtract(string $column, string $path): string;

    /**
     * Extract a value from a JSON column and coerce it to the given target type, converting
     * a JSON null into a SQL NULL. This is the typed accessor used by the field accessor builders.
     */
    abstract public function jsonExtractTyped(string $column, string $path, JsonExtractType $type): string;

    /**
     * Null coalescing between two expressions.
     *
     * MySQL: IFNULL(a, b) (kept for byte-for-byte parity) ; PostgreSQL: COALESCE(a, b).
     */
    abstract public function ifNull(string $expression, string $default): string;

    /**
     * Aggregate a set of values into a single delimited string.
     *
     * MySQL: GROUP_CONCAT([DISTINCT ]expr SEPARATOR 'sep') ; PostgreSQL: string_agg([DISTINCT ]expr, 'sep').
     */
    abstract public function groupConcat(string $expression, string $separator = ',', bool $distinct = false): string;

    /**
     * Build a JSON object from a map of json-key => sql-expression.
     *
     * MySQL: JSON_OBJECT('k1', e1, 'k2', e2) ; PostgreSQL: json_build_object('k1', e1, 'k2', e2).
     *
     * @param array<string, string> $keyExpressionMap
     */
    abstract public function jsonObject(array $keyExpressionMap): string;

    /**
     * Aggregate JSON objects (built from the given map) into a JSON array string.
     *
     * MySQL: CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT(...)), ']')
     * PostgreSQL: COALESCE(json_agg(DISTINCT json_build_object(...))::text, '[]')
     *
     * @param array<string, string> $keyExpressionMap
     */
    abstract public function jsonObjectAgg(array $keyExpressionMap): string;

    /**
     * Keyword variant used to build an "insert or ignore" statement.
     *
     * MySQL: "INSERT IGNORE" ; PostgreSQL: "INSERT" (the ignore is expressed via {@see insertIgnoreSuffix()}).
     */
    abstract public function insertIgnoreKeyword(): string;

    /**
     * Suffix appended to an INSERT to make it ignore duplicate-key conflicts.
     *
     * MySQL: "" (handled by {@see insertIgnoreKeyword()}) ; PostgreSQL: " ON CONFLICT DO NOTHING".
     */
    abstract public function insertIgnoreSuffix(): string;

    /**
     * Suffix appended to an INSERT to turn it into an upsert.
     *
     * MySQL: " ON DUPLICATE KEY UPDATE col = VALUES(col), ..." (conflict keys ignored)
     * PostgreSQL: " ON CONFLICT (keys) DO UPDATE SET col = EXCLUDED.col, ..." (conflict keys required)
     *
     * @param array<string> $updateColumns unquoted column names to update on conflict
     * @param array<string> $conflictColumns unquoted column names forming the conflict target (PostgreSQL only)
     */
    abstract public function buildUpsertSuffix(array $updateColumns, array $conflictColumns = []): string;

    /**
     * Whether the engine supports the MySQL-style REPLACE INTO statement.
     */
    abstract public function supportsReplaceInto(): bool;

    /**
     * Trailing clause required to make a LIKE use backslash as escape character, matching the
     * PHP-side addcslashes($value, '\_%') escaping the DAL performs.
     *
     * MySQL: "" (backslash is the default escape char) ; PostgreSQL: " ESCAPE '\\'".
     */
    abstract public function likeEscapeClause(): string;

    /**
     * Null-safe equality comparison.
     *
     * MySQL: left <=> right ; PostgreSQL: left IS NOT DISTINCT FROM right.
     */
    abstract public function nullSafeEquals(string $left, string $right): string;

    /**
     * Render the SQL expression grouping a datetime accessor by the given date-histogram interval.
     *
     * $interval is one of the DateHistogramAggregation::PER_* constant values
     * (minute|hour|day|week|month|quarter|year).
     */
    abstract public function dateHistogramFormat(string $accessor, string $interval): string;
}

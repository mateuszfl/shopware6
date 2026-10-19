<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;

/**
 * SQL fragments for low-level Core gateways. Column and table names are identifiers;
 * caller-provided values are always returned as bound parameters.
 *
 * @internal
 */
#[Package('framework')]
interface DatabaseDialect
{
    public function capabilities(): DatabaseCapabilities;

    public function random(): string;

    /**
     * Query counting conflicts that cannot use the audited delete/insert replacement strategy.
     */
    public function unsupportedReplacementConflicts(string $table): SqlFragment;

    public function quoteIdentifier(string $identifier): string;

    /**
     * @param list<string> $parts
     */
    public function quoteQualifiedIdentifier(array $parts): string;

    /**
     * @param string|list<string> $column
     */
    public function hex(string|array $column): string;

    /**
     * Encode a trusted DAL accessor expression as a lowercase hexadecimal UUID.
     */
    public function hexExpression(string $expression): string;

    /**
     * Text representation with binary collation for deterministic spellings within a collated bucket.
     */
    public function binaryText(string $expression): string;

    public function fromHex(string $placeholder): string;

    /**
     * @param string|list<string> $column
     * @param list<string> $path
     */
    public function jsonExtract(string|array $column, array $path): SqlFragment;

    public function jsonExtractPath(string $expression, JsonPath $path): SqlFragment;

    public function jsonScalar(string $expression, string $type = 'text'): string;

    /**
     * The placeholder binds a JSON array containing exactly one scalar or JSON null.
     * Descend through nested target arrays, preserving scalar types and ignoring object values.
     */
    public function jsonListContains(string $expression, string $placeholder): string;

    /**
     * Compare immediate array elements, object key/value pairs or scalars without coercion or recursive flattening.
     */
    public function jsonOverlaps(string $left, string $right): string;

    public function nullSafeEquals(string $expression, string $placeholder): string;

    /**
     * @param array<int|string, string> $fields Trusted SQL expressions keyed by JSON member
     */
    public function jsonObjectExpressions(array $fields, bool $aggregate = false, bool $distinct = true): SqlFragment;

    /**
     * @param array<string, string|list<string>> $fields Map of JSON keys to column names
     */
    public function jsonObject(array $fields): SqlFragment;

    /**
     * @param string|list<string> $column
     * @param string|list<string> $orderColumn
     */
    public function stringAggregate(string|array $column, string|array $orderColumn, string $separator = ','): SqlFragment;

    /**
     * @param string|list<string> $column
     */
    public function hexAggregate(string|array $column, string $separator = '||'): SqlFragment;

    /**
     * @param string|list<string> ...$columns
     */
    public function concat(string|array ...$columns): string;

    /**
     * @param string|list<string> $column
     */
    public function dateAddSeconds(string|array $column, int $seconds): SqlFragment;

    /**
     * UTC storage timestamp to the start of a calendar bucket in the requested time zone.
     *
     * @param 'minute'|'hour'|'day'|'week'|'month'|'quarter'|'year' $interval
     */
    public function dateBucket(string $expression, string $interval, ?string $timeZone = null): SqlFragment;

    /**
     * @param array<string, mixed> $values Column => value
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     * @param array<string, ParameterType> $types
     */
    public function upsert(string $table, array $values, array $conflictColumns, array $updateColumns, array $types = []): SqlFragment;

    /**
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    public function insertConflictClause(array $conflictColumns, array $updateColumns, bool $ignoreDuplicates = false): string;

    /**
     * Atomically replace literal top-level members, retaining other members and scalar/array roots.
     *
     * @param array<string, mixed> $members
     */
    public function jsonSetMembers(string $column, array $members): SqlFragment;

    public function queryShape(): DatabaseQueryShape;
}

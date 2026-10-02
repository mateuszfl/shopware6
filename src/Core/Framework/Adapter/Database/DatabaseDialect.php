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

    public function quoteIdentifier(string $identifier): string;

    /**
     * @param list<string> $parts
     */
    public function quoteQualifiedIdentifier(array $parts): string;

    /**
     * @param string|list<string> $column
     */
    public function hex(string|array $column): string;

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

    public function nullSafeEquals(string $expression, string $placeholder): string;

    /**
     * @param array<int|string, string> $fields Trusted SQL expressions keyed by JSON member
     */
    public function jsonObjectExpressions(array $fields, bool $aggregate = false): SqlFragment;

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
     * @param array<string, mixed> $values Column => value
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     * @param array<string, ParameterType> $types
     */
    public function upsert(string $table, array $values, array $conflictColumns, array $updateColumns, array $types = []): SqlFragment;

    public function queryShape(): DatabaseQueryShape;
}

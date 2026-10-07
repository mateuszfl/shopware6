<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * Trusted expressions for low-level index producers. Values remain bound by callers.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\IndexingContractTest
 */
#[Package('framework')]
final class IndexingSql
{
    public static function hex(Connection $connection, string $expression): string
    {
        return (new DatabasePlatformResolver($connection))->dialect()->hexExpression($expression);
    }

    public static function hash(Connection $connection, string $expression): string
    {
        return (new DatabasePlatformResolver($connection))->capabilities()->supportsJsonb
            ? "encode(sha256(convert_to($expression, 'UTF8')), 'hex')"
            : "SHA2($expression, 256)";
    }

    /**
     * @param list<string> $expressions
     */
    public static function concat(Connection $connection, array $expressions): string
    {
        return (new DatabasePlatformResolver($connection))->capabilities()->supportsJsonb
            ? '(' . implode(' || ', $expressions) . ')'
            : 'CONCAT(' . implode(', ', $expressions) . ')';
    }

    public static function aggregate(Connection $connection, string $expression, string $separator, string $order): string
    {
        $separator = $connection->quote($separator);

        return (new DatabasePlatformResolver($connection))->capabilities()->supportsJsonb
            ? "STRING_AGG($expression, $separator ORDER BY $order)"
            : "GROUP_CONCAT($expression ORDER BY $order SEPARATOR $separator)";
    }

    public static function idArray(Connection $connection, string $expression): string
    {
        $hex = self::hex($connection, $expression);

        return (new DatabasePlatformResolver($connection))->capabilities()->supportsJsonb
            ? "JSONB_AGG(DISTINCT $hex ORDER BY $hex)"
            : "CONCAT('[', GROUP_CONCAT(DISTINCT JSON_QUOTE($hex) ORDER BY $hex), ']')";
    }

    public static function objectMap(Connection $connection, string $key, string $value): string
    {
        return (new DatabasePlatformResolver($connection))->capabilities()->supportsJsonb
            ? "JSONB_OBJECT_AGG($key, $value ORDER BY $key)"
            : "CONCAT('{', GROUP_CONCAT(CONCAT(JSON_QUOTE($key), ':', JSON_QUOTE($value)) ORDER BY $key), '}')";
    }

    /**
     * @param array<string, string> $assignments Column => trusted expression
     */
    public static function joinedUpdate(Connection $connection, string $table, string $alias, string $joined, array $assignments, string $condition): string
    {
        $dialect = (new DatabasePlatformResolver($connection))->dialect();
        $sets = [];
        foreach ($assignments as $column => $expression) {
            $sets[] = ($dialect->capabilities()->supportsUpdateFrom ? '' : $alias . '.') . $dialect->quoteIdentifier($column) . ' = ' . $expression;
        }
        $target = $dialect->quoteIdentifier($table) . ' ' . $alias;

        return $dialect->capabilities()->supportsUpdateFrom
            ? 'UPDATE ' . $target . ' SET ' . implode(', ', $sets) . ' FROM ' . $joined . ' WHERE ' . $condition
            : 'UPDATE ' . $target . ' INNER JOIN ' . $joined . ' ON ' . $condition . ' SET ' . implode(', ', $sets);
    }

    public static function insertIgnorePrefix(Connection $connection): string
    {
        return (new DatabasePlatformResolver($connection))->capabilities()->supportsConflictTarget ? 'INSERT INTO' : 'INSERT IGNORE INTO';
    }
}

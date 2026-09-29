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
final class PostgreSQLQueryShape implements DatabaseQueryShape
{
    public function __construct(private readonly DatabaseDialect $dialect)
    {
    }

    public function updateJoined(string $table, string $joinedTable, string $condition, array $assignments, array $conditionParameters = []): SqlFragment
    {
        if ($assignments === []) {
            throw DatabaseDialectException::invalidOperation('joined update needs assignments');
        }

        $sets = [];
        foreach ($assignments as $column => $value) {
            $sets[] = $this->dialect->quoteIdentifier($column) . ' = ?';
        }

        return new SqlFragment(
            'UPDATE ' . $this->dialect->quoteIdentifier($table) . ' target SET ' . implode(', ', $sets) . ' FROM ' . $this->dialect->quoteIdentifier($joinedTable) . ' joined WHERE ' . $condition,
            [...array_values($assignments), ...$conditionParameters],
            [...array_map(self::type(...), array_values($assignments)), ...array_fill(0, \count($conditionParameters), ParameterType::STRING)],
        );
    }

    public function deleteJoined(string $table, string $joinedTable, string $condition, array $conditionParameters = []): SqlFragment
    {
        return new SqlFragment(
            'DELETE FROM ' . $this->dialect->quoteIdentifier($table) . ' target USING ' . $this->dialect->quoteIdentifier($joinedTable) . ' joined WHERE ' . $condition,
            $conditionParameters,
            array_fill(0, \count($conditionParameters), ParameterType::STRING),
        );
    }

    private static function type(mixed $value): ParameterType
    {
        return match (true) {
            $value === null => ParameterType::NULL,
            \is_bool($value) => ParameterType::BOOLEAN,
            \is_int($value) => ParameterType::INTEGER,
            default => ParameterType::STRING,
        };
    }
}

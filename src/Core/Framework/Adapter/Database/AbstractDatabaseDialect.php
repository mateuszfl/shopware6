<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Database\DatabasePlatformResolverTest
 */
#[Package('framework')]
abstract class AbstractDatabaseDialect implements DatabaseDialect
{
    public function __construct(protected readonly AbstractPlatform $platform)
    {
    }

    public function quoteIdentifier(string $identifier): string
    {
        self::validateIdentifier($identifier);

        // Unlike DBAL quoteIdentifier(), this treats dots in an alias as literal characters.
        return $this->platform->quoteSingleIdentifier($identifier);
    }

    public function quoteQualifiedIdentifier(array $parts): string
    {
        if ($parts === []) {
            throw DatabaseDialectException::invalidOperation('an identifier must have at least one part');
        }

        return implode('.', array_map($this->quoteIdentifier(...), $parts));
    }

    public function concat(string|array ...$columns): string
    {
        if ($columns === []) {
            throw DatabaseDialectException::invalidOperation('concatenation needs at least one column');
        }

        return $this->platform->getConcatExpression(...array_map($this->quoteColumn(...), $columns));
    }

    public function dateAddSeconds(string|array $column, int $seconds): SqlFragment
    {
        return new SqlFragment(
            $this->platform->getDateAddSecondsExpression($this->quoteColumn($column), '?'),
            [$seconds],
            [ParameterType::INTEGER],
        );
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     * @param array<string, ParameterType> $types
     */
    public function upsert(string $table, array $values, array $conflictColumns, array $updateColumns, array $types = []): SqlFragment
    {
        if ($values === [] || $conflictColumns === []) {
            throw DatabaseDialectException::invalidOperation('upsert needs values and a conflict target');
        }

        foreach (array_merge($conflictColumns, $updateColumns, array_keys($types)) as $column) {
            if (!\array_key_exists($column, $values)) {
                throw DatabaseDialectException::invalidOperation('upsert column is missing from values: ' . $column);
            }
        }

        $columns = array_keys($values);
        $quoted = array_map($this->quoteIdentifier(...), $columns);
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', array_fill(0, \count($values), '?')) . ')';

        $parameterTypes = [];
        foreach ($values as $column => $value) {
            $parameterTypes[] = $types[$column] ?? match (true) {
                $value === null => ParameterType::NULL,
                \is_bool($value) => ParameterType::BOOLEAN,
                \is_int($value) => ParameterType::INTEGER,
                default => ParameterType::STRING,
            };
        }

        return new SqlFragment($sql . $this->upsertClause($conflictColumns, $updateColumns), array_values($values), $parameterTypes);
    }

    /**
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    abstract protected function upsertClause(array $conflictColumns, array $updateColumns): string;

    /**
     * @param string|list<string> $column
     */
    protected function quoteColumn(string|array $column): string
    {
        return \is_array($column) ? $this->quoteQualifiedIdentifier($column) : $this->quoteIdentifier($column);
    }

    private static function validateIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/D', $identifier) !== 1) {
            throw DatabaseDialectException::invalidIdentifier($identifier);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\SQL\Parser\Visitor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * SQL and its bind values must travel together. Identifiers are quoted by the dialect before construction.
 *
 * @internal
 */
#[Package('framework')]
final class SqlFragment
{
    /**
     * @param array<int<0, max>|string, mixed> $parameters
     * @param array<int<0, max>|string, ParameterType> $types
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $parameters = [],
        public readonly array $types = [],
    ) {
    }

    /**
     * Adapter-owned templates only. Identical expressions reuse parameter names within a query.
     */
    public function named(): self
    {
        if ($this->parameters === [] || !array_is_list($this->parameters)) {
            return $this;
        }

        $prefix = 'accessor_' . Hasher::hash($this->sql . serialize($this->parameters) . serialize($this->types), 'sha256') . '_';
        $visitor = new class($prefix) implements Visitor {
            public string $sql = '';

            public int $index = 0;

            public function __construct(private readonly string $prefix)
            {
            }

            public function acceptPositionalParameter(string $sql): void
            {
                $this->sql .= ':' . $this->prefix . $this->index++;
            }

            public function acceptNamedParameter(string $sql): void
            {
                $this->sql .= $sql;
            }

            public function acceptOther(string $sql): void
            {
                $this->sql .= $sql;
            }
        };
        (new Parser(true))->parse($this->sql, $visitor);
        if ($visitor->index !== \count($this->parameters)) {
            throw DatabaseDialectException::invalidOperation('fragment placeholder count does not match its parameters');
        }

        $parameters = [];
        $types = [];
        foreach ($this->parameters as $index => $value) {
            $key = $prefix . $index;
            $parameters[$key] = $value;
            $types[$key] = $this->types[$index] ?? ParameterType::STRING;
        }

        return new self($visitor->sql, $parameters, $types);
    }

    /**
     * Compatibility for the string-only accessor API. Query builders use bound fragments.
     */
    public function inline(Connection $connection): string
    {
        $fragment = $this->named();
        $replacements = [];
        foreach ($fragment->parameters as $key => $value) {
            $replacements[':' . $key] = match (true) {
                $value === null => 'NULL',
                \is_bool($value) => $value ? '1' : '0',
                \is_int($value) => (string) $value,
                default => $connection->getDatabasePlatform() instanceof PostgreSQLPlatform
                    ? 'convert_from(decode(\'' . bin2hex((string) $value) . '\', \'hex\'), \'UTF8\')'
                    : 'CONVERT(X\'' . bin2hex((string) $value) . '\' USING utf8mb4)',
            };
        }

        return strtr($fragment->sql, $replacements);
    }
}

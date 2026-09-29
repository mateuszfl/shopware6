<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Framework\Log\Package;

/**
 * One resolver per DBAL connection. It can be created before kernel boot and never
 * stores a process-wide platform selection.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Database\DatabasePlatformResolverTest
 */
#[Package('framework')]
final class DatabasePlatformResolver
{
    private ?DatabaseDialect $dialect = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function dialect(): DatabaseDialect
    {
        if ($this->dialect !== null) {
            return $this->dialect;
        }

        $driver = $this->connection->getParams()['driver'] ?? null;
        if (!\in_array($driver, ['pdo_mysql', 'mysqli', 'pdo_pgsql', 'pgsql'], true)) {
            throw DatabaseDialectException::unsupportedDriver((string) $driver);
        }

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform && \in_array($driver, ['pdo_pgsql', 'pgsql'], true)) {
            return $this->dialect = new PostgreSQLDialect($platform);
        }

        if ($platform instanceof AbstractMySQLPlatform && \in_array($driver, ['pdo_mysql', 'mysqli'], true)) {
            return $this->dialect = new MySQLDialect($platform);
        }

        throw DatabaseDialectException::unsupportedPlatform($platform::class);
    }

    public function capabilities(): DatabaseCapabilities
    {
        return $this->dialect()->capabilities();
    }

    public function queryShape(): DatabaseQueryShape
    {
        return $this->dialect()->queryShape();
    }
}

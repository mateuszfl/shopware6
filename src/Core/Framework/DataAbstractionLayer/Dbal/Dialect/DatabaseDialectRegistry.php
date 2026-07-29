<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves the {@see AbstractSqlDialect} matching a given connection.
 *
 * The source of truth is the platform DBAL actually negotiated for the connection
 * ({@see Connection::getDatabasePlatform()}), not the configured DSN scheme, so the dialect the
 * DAL uses can never disagree with the real connection.
 *
 * @internal
 */
#[Package('framework')]
class DatabaseDialectRegistry
{
    /**
     * @param iterable<AbstractSqlDialect> $dialects
     */
    public function __construct(private readonly iterable $dialects)
    {
    }

    public function get(Connection $connection): AbstractSqlDialect
    {
        $platform = $connection->getDatabasePlatform();

        foreach ($this->dialects as $dialect) {
            if ($dialect->supports($platform)) {
                return $dialect;
            }
        }

        throw DataAbstractionLayerException::unsupportedDatabasePlatform($platform::class);
    }
}

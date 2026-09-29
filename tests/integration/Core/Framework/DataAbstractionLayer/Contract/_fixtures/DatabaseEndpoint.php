<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * @internal
 */
final readonly class DatabaseEndpoint
{
    public function __construct(
        public string $platform,
        public string $version,
        public string $serverId,
        public int $backendId,
        public bool $standby,
    ) {
    }

    public static function observe(Connection $connection): self
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $row = $connection->fetchAssociative(<<<'SQL'
                SELECT current_setting('server_version') AS version,
                       COALESCE(inet_server_addr()::text, 'local') || ':' || inet_server_port()::text AS server_id,
                       pg_backend_pid() AS backend_id,
                       pg_is_in_recovery() AS standby
                SQL);
            if (!\is_array($row)) {
                throw new \RuntimeException('Failed to observe the PostgreSQL endpoint');
            }

            return new self(
                'postgresql',
                (string) $row['version'],
                (string) $row['server_id'],
                (int) $row['backend_id'],
                filter_var($row['standby'], \FILTER_VALIDATE_BOOL),
            );
        }

        $row = $connection->fetchAssociative(<<<'SQL'
            SELECT VERSION() AS version,
                   CONCAT(@@server_uuid, '@', @@hostname, ':', @@port) AS server_id,
                   CONNECTION_ID() AS backend_id
            SQL);
        if (!\is_array($row)) {
            throw new \RuntimeException('Failed to observe the MySQL endpoint');
        }

        return new self('mysql', (string) $row['version'], (string) $row['server_id'], (int) $row['backend_id'], false);
    }
}

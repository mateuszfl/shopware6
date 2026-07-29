<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * Driver-aware entry point for building the application's DBAL connection.
 *
 * It inspects the DATABASE_URL scheme to decide which engine to build. The MySQL/MariaDB path is
 * delegated verbatim to {@see MySQLFactory}, so existing behaviour is unchanged. PostgreSQL DSNs
 * are built here. This is the seam that lets Shopware target more than one database engine.
 *
 * @phpstan-import-type Params from DriverManager
 *
 * @internal
 */
#[Package('framework')]
class ConnectionFactory
{
    private const POSTGRES_SCHEMES = ['postgres', 'postgresql', 'pgsql'];

    /**
     * @param array<Middleware> $middlewares
     */
    public static function create(array $middlewares = []): Connection
    {
        $url = (string) EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'));

        if (self::isPostgres($url)) {
            return self::createPostgres($url, $middlewares);
        }

        // Default and MySQL/MariaDB: keep the exact, battle-tested MySQL bootstrap.
        return MySQLFactory::create($middlewares);
    }

    private static function isPostgres(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));

        return \in_array($scheme, self::POSTGRES_SCHEMES, true);
    }

    /**
     * @param array<Middleware> $middlewares
     */
    private static function createPostgres(string $url, array $middlewares): Connection
    {
        $config = (new Configuration())->setMiddlewares($middlewares);

        $dsnParser = new DsnParser([
            'postgres' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
            'pgsql' => 'pdo_pgsql',
        ]);
        $dsnParameters = $dsnParser->parse($url);

        /** @var Params $parameters */
        $parameters = array_merge([
            'charset' => 'UTF8',
            'driver' => 'pdo_pgsql',
        ], $dsnParameters);

        $parameters['driverOptions'] = [
            \PDO::ATTR_TIMEOUT => 5,
        ] + ($dsnParameters['driverOptions'] ?? []);

        // NOTE: PostgreSQL session initialisation (e.g. "SET TIME ZONE 'UTC'") has no direct
        // equivalent to MySQL's ATTR_INIT_COMMAND and is applied through a driver middleware in a
        // later porting phase. TLS is configured through the DSN (sslmode/sslrootcert/...).

        return DriverManager::getConnection($parameters, $config);
    }
}

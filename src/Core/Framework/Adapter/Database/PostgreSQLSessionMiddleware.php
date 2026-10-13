<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter as NativePostgreSQLExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies PostgreSQL session settings on each physical connection, including reconnects.
 *
 * @internal
 */
#[Package('framework')]
final class PostgreSQLSessionMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function getExceptionConverter(): ExceptionConverter
            {
                $converter = parent::getExceptionConverter();

                return $converter instanceof NativePostgreSQLExceptionConverter ? new PostgreSQLExceptionConverter($converter) : $converter;
            }

            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);

                if (($params['driver'] ?? null) === 'pdo_pgsql') {
                    $connection->exec('SET TIME ZONE \'UTC\'');
                }

                return $connection;
            }
        };
    }
}

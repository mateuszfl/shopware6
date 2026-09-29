<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Pdo\Mysql;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @phpstan-import-type Params from DriverManager
 * @phpstan-import-type OverrideParams from DriverManager
 *
 * @internal
 */
#[Package('framework')]
class DatabaseConnectionFactory
{
    public const PLACEHOLDER_DATABASE_URL = 'mysql://_placeholder.test';

    public const POSTGRESQL_PLACEHOLDER_DATABASE_URL = 'postgresql://_placeholder.test';

    /**
     * Returns true, when bin/ci is used and Shopware is called in a CI/CD environment where the Database is not available to warmup caches
     */
    public static function hasNoDatabaseAvailable(): bool
    {
        return \in_array((string) EnvironmentHelper::getVariable('DATABASE_URL', ''), [self::PLACEHOLDER_DATABASE_URL, self::POSTGRESQL_PLACEHOLDER_DATABASE_URL], true);
    }

    /**
     * @param array<Middleware> $middlewares
     */
    public static function create(array $middlewares = []): Connection
    {
        $config = (new Configuration())
            ->setMiddlewares([new PostgreSQLSessionMiddleware(), ...$middlewares]);

        $url = (string) EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'));
        if ($url === '') {
            $url = 'mysql://root:shopware@127.0.0.1:3306/shopware';
        }

        $dsnParser = new DsnParser(['mysql' => 'pdo_mysql', 'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql']);
        $dsnParameters = self::parseDsn($dsnParser, $url);
        $driver = $dsnParameters['driver'] ?? 'pdo_mysql';
        if (!\in_array($driver, ['pdo_mysql', 'pdo_pgsql'], true)) {
            throw DatabaseDialectException::unsupportedDriver((string) $driver);
        }

        $parameters = array_merge([
            'charset' => $driver === 'pdo_mysql' ? 'utf8mb4' : 'utf8',
            'driver' => $driver,
        ], $dsnParameters); // adding parameters that are not in the DSN

        // Merge driverOptions separately using + to preserve PDO constant keys
        $parameters['driverOptions'] = [
            \PDO::ATTR_STRINGIFY_FETCHES => true,
        ] + ($dsnParameters['driverOptions'] ?? []);

        if ($driver === 'pdo_mysql') {
            $parameters = self::configureMySQL($parameters);
        } else {
            $parameters = self::configurePostgreSQL($parameters);
        }

        $replicaUrl = (string) EnvironmentHelper::getVariable('DATABASE_REPLICA_0_URL');
        if ($replicaUrl !== '') {
            if (!isset($parameters['wrapperClass'])) {
                $parameters['wrapperClass'] = PrimaryReadReplicaConnection::class;
            }

            // DBAL keeps both physical connections so the resetter can return
            // the next unit of work to a replica after a primary operation.
            if (!\array_key_exists('keepReplica', $parameters)) {
                $parameters['keepReplica'] = true;
            } else {
                $parameters['keepReplica'] = (bool) self::castValue($parameters['keepReplica']);
            }

            $parameters['primary'] = array_merge([
                'charset' => $parameters['charset'] ?? ($driver === 'pdo_mysql' ? 'utf8mb4' : 'utf8'),
            ], $dsnParameters);
            unset($parameters['primary']['primary'], $parameters['primary']['replica'], $parameters['primary']['keepReplica']);
            $parameters['primary']['driverOptions'] = ($parameters['driverOptions'] ?? []) + ($dsnParameters['driverOptions'] ?? []);
            $parameters['primary'] = self::copyConnectionOptions($parameters, $parameters['primary']);

            $parameters['replica'] = [];

            for ($i = 0; $replicaUrl = (string) EnvironmentHelper::getVariable('DATABASE_REPLICA_' . $i . '_URL'); ++$i) {
                $replicaParams = self::parseDsn($dsnParser, $replicaUrl);
                if (($replicaParams['driver'] ?? null) !== $driver) {
                    throw DatabaseDialectException::replicaDriverMismatch();
                }
                unset($replicaParams['primary'], $replicaParams['replica'], $replicaParams['keepReplica']);

                $parameters['replica'][$i] = array_merge([
                    'charset' => $parameters['charset'] ?? ($driver === 'pdo_mysql' ? 'utf8mb4' : 'utf8'),
                ], $replicaParams);
                $parameters['replica'][$i]['driverOptions'] = ($parameters['driverOptions'] ?? []) + ($replicaParams['driverOptions'] ?? []);
                $parameters['replica'][$i] = self::copyConnectionOptions($parameters, $parameters['replica'][$i]);
            }
        }

        return DriverManager::getConnection($parameters, $config);
    }

    /**
     * @phpstan-param Params $parameters
     *
     * @phpstan-return Params
     */
    private static function configureMySQL(array $parameters): array
    {
        $parameters['driverOptions'][\PDO::ATTR_TIMEOUT] = 5;

        $initCommands = [
            'SET @@session.time_zone = \'+00:00\'',
            'SET @@group_concat_max_len = CAST(IF(@@group_concat_max_len > 320000, @@group_concat_max_len, 320000) AS UNSIGNED)',
            'SET sql_mode=(SELECT REPLACE(@@sql_mode,\'ONLY_FULL_GROUP_BY\',\'\'))',
        ];

        $parameters['driverOptions'][Mysql::ATTR_INIT_COMMAND] = \implode(';', $initCommands);

        if ($sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_CA] = $sslCa;
        }

        if ($sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_CERT] = $sslCert;
        }

        if ($sslCertKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_KEY] = $sslCertKey;
        }

        if (EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT') && \defined('\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        if (EnvironmentHelper::getVariable('DATABASE_PERSISTENT_CONNECTION')) {
            $parameters['driverOptions'][\PDO::ATTR_PERSISTENT] = true;
        }

        if (EnvironmentHelper::getVariable('DATABASE_PROTOCOL_COMPRESSION')) {
            $parameters['driverOptions'][Mysql::ATTR_COMPRESS] = true;
        }

        return $parameters;
    }

    /**
     * @phpstan-param Params $parameters
     *
     * @phpstan-return Params
     */
    private static function configurePostgreSQL(array $parameters): array
    {
        $parameters['driverOptions'][\PDO::ATTR_TIMEOUT] ??= 5;

        $sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA');
        if (\is_string($sslCa) && $sslCa !== '') {
            $parameters['sslrootcert'] = $sslCa;
            $parameters['sslmode'] ??= 'verify-full';
        }
        $sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT');
        if (\is_string($sslCert) && $sslCert !== '') {
            $parameters['sslcert'] = $sslCert;
        }
        $sslKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY');
        if (\is_string($sslKey) && $sslKey !== '') {
            $parameters['sslkey'] = $sslKey;
        }
        if (EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT')) {
            $parameters['sslmode'] = 'require';
        }
        if (EnvironmentHelper::getVariable('DATABASE_PERSISTENT_CONNECTION')) {
            $parameters['driverOptions'][\PDO::ATTR_PERSISTENT] = true;
        }

        return $parameters;
    }

    /**
     * @phpstan-param Params $source
     * @phpstan-param OverrideParams $target
     *
     * @phpstan-return OverrideParams
     */
    private static function copyConnectionOptions(array $source, array $target): array
    {
        foreach (['sslmode', 'sslrootcert', 'sslcert', 'sslkey'] as $option) {
            if (isset($source[$option]) && !isset($target[$option])) {
                $target[$option] = $source[$option];
            }
        }

        return $target;
    }

    /**
     * @return Params
     */
    private static function parseDsn(DsnParser $dsnParser, string $url): array
    {
        $dsnParameters = $dsnParser->parse($url);

        $dsnParameters['driverOptions'] = array_map(static function (mixed $value): mixed {
            return self::castValue($value);
        }, $dsnParameters['driverOptions'] ?? []);

        return $dsnParameters;
    }

    private static function castValue(mixed $value): mixed
    {
        if (is_iterable($value)) {
            foreach ($value as &$item) {
                $item = self::castValue($item);
            }

            return $value;
        }

        if (!\is_string($value)) {
            return $value;
        }

        return XmlUtils::phpize($value);
    }
}

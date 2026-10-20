<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * @internal
 */
final class ContractConnectionFactory
{
    public static function create(string $environmentVariable): Connection
    {
        $url = getenv($environmentVariable);
        if (!\is_string($url) || $url === '') {
            throw new \RuntimeException($environmentVariable . ' must point at a disposable *_test database');
        }

        $database = parse_url($url, \PHP_URL_PATH);
        if (!\is_string($database) || !str_ends_with($database, '_test')) {
            throw new \RuntimeException($environmentVariable . ' must point at a disposable *_test database');
        }

        $params = (new DsnParser([
            'mysql' => 'pdo_mysql',
            'postgres' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
        ]))->parse($url);
        $queryLog = getenv('DATABASE_CONTRACT_QUERY_LOG');
        if (\is_string($queryLog) && $queryLog !== '') {
            $configuration = new Configuration();
            $configuration->setMiddlewares([new Middleware(new DatabaseContractQueryLogger($queryLog))]);

            return DriverManager::getConnection($params, $configuration);
        }

        return DriverManager::getConnection($params);
    }
}

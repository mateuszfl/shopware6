<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class MySQLIncrementer extends AbstractIncrementer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public function increment(string $cluster, string $key): void
    {
        $payload = [
            'pool' => $this->poolName,
            'cluster' => $cluster,
            'key' => $key,
            'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $keyColumn = $dialect->quoteIdentifier('key');
        $conflict = $dialect->capabilities()->supportsConflictTarget
            ? 'ON CONFLICT (pool, cluster, ' . $keyColumn . ') DO UPDATE SET count = increment.count + 1, updated_at = EXCLUDED.created_at'
            : 'ON DUPLICATE KEY UPDATE count = count + 1, updated_at = :createdAt';
        $this->connection->executeStatement(
            'INSERT INTO increment (pool, cluster, ' . $keyColumn . ', count, created_at) VALUES (:pool, :cluster, :key, 1, :createdAt) ' . $conflict,
            $payload,
        );
    }

    public function decrement(string $cluster, string $key): void
    {
        $payload = [
            'pool' => $this->poolName,
            'cluster' => $cluster,
            'key' => $key,
            'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->executeStatement(
            '
            UPDATE increment
            SET count = count - 1, updated_at = :updatedAt
            WHERE pool = :pool AND cluster = :cluster AND ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('key') . ' = :key AND count > 0;
        ',
            $payload,
        );
    }

    public function reset(string $cluster, ?string $key = null): void
    {
        $query = $this->connection->createQueryBuilder()
            ->update('increment')
            ->set('count', ':count')
            ->set('updated_at', ':updatedAt')
            ->where('pool = :pool')
            ->andWhere('cluster = :cluster')
            ->setParameter('updatedAt', $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->setParameter('cluster', $cluster)
            ->setParameter('count', 0)
            ->setParameter('pool', $this->poolName);

        if ($key !== null) {
            $query->andWhere($this->connection->getDatabasePlatform()->quoteSingleIdentifier('key') . ' = :key')
                ->setParameter('key', $key);
        }

        RetryableQuery::retryable($this->connection, static function () use ($query): void {
            $query->executeStatement();
        });
    }

    public function delete(string $cluster, array $keys = []): void
    {
        $query = $this->connection->createQueryBuilder()
            ->delete('increment')
            ->where('pool = :pool')
            ->andWhere('cluster = :cluster')
            ->setParameter('pool', $this->poolName)
            ->setParameter('cluster', $cluster);

        if ($keys !== []) {
            $query->andWhere($this->connection->getDatabasePlatform()->quoteSingleIdentifier('key') . ' IN (:keys)')
                ->setParameter('keys', $keys, ArrayParameterType::STRING);
        }

        RetryableQuery::retryable($this->connection, static function () use ($query): void {
            $query->executeStatement();
        });
    }

    public function list(string $cluster, int $limit = 5, int $offset = 0): array
    {
        $sql = 'SELECT ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('key') . ' as array_key, pool, cluster, ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('key') . ', count
            FROM increment  WHERE cluster = :cluster AND pool = :pool
            ORDER BY count DESC, CASE WHEN updated_at IS NULL THEN 0 ELSE 1 END DESC, updated_at DESC, ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('key') . ' ASC';

        $payload = [
            'pool' => $this->poolName,
            'cluster' => $cluster,
        ];

        $types = [];

        if ($limit > -1) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $payload['limit'] = $limit;
            $payload['offset'] = $offset;
            $types = [
                'offset' => ParameterType::INTEGER,
                'limit' => ParameterType::INTEGER,
            ];
        }

        /** @var array<string, array{count: string, key: string, cluster: string, pool: string}> $result */
        $result = $this->connection->fetchAllAssociativeIndexed($sql, $payload, $types);

        foreach ($result as $key => $row) {
            $result[$key]['count'] = max(0, (int) $row['count']);
        }

        return $result;
    }
}

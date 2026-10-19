<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Outbox\StreamLockServiceTest
 */
#[Package('framework')]
class StreamLockService
{
    public const ORPHAN_GRACE_SECONDS = 60;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Atomically claims the next partition with at least one due delivery.
     * Returns null when nothing is claimable (either no streams, no due rows,
     * or all candidate rows are locked by other workers).
     *
     * @param non-empty-list<WebhookEventLogDefinition::STATUS_QUEUED|WebhookEventLogDefinition::STATUS_PENDING_RETRY> $statuses
     */
    public function claimNext(string $workerId, int $leaseSeconds, array $statuses): ?StreamLease
    {
        return $this->connection->transactional(function () use ($workerId, $leaseSeconds, $statuses): ?StreamLease {
            $now = $this->clock->now();
            $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $staleCutoff = $now->modify(\sprintf('-%d seconds', $leaseSeconds))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            // Read candidates without locks: InnoDB may lock every filesort input before
            // applying LIMIT. Claim by the unique partition key, rechecking all conditions.
            $selection = <<<'SQL'
                SELECT s.partition_key FROM webhook_stream s
                WHERE (s.locked_by IS NULL OR s.lock_expires_at <= :now)
                  AND EXISTS (
                      SELECT 1 FROM webhook_delivery d
                      JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
                      WHERE d.partition_key = s.partition_key
                        AND el.delivery_status NOT IN (:successStatus, :failedStatus)
                        AND (
                            (d.delivery_status IN (:statuses) AND (d.next_retry_at IS NULL OR d.next_retry_at <= :now))
                            OR (d.delivery_status = :running AND d.last_attempt_at <= :staleCutoff)
                        )
                  )
                SQL;
            $parameters = [
                'now' => $nowFormatted,
                'statuses' => $statuses,
                'running' => WebhookEventLogDefinition::STATUS_RUNNING,
                'staleCutoff' => $staleCutoff,
                'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
            ];
            $types = ['statuses' => ArrayParameterType::STRING];
            $partitionKey = null;
            $offset = 0;
            do {
                $candidates = $this->connection->fetchFirstColumn(
                    $selection . ' ORDER BY CASE WHEN s.last_claimed_at IS NULL THEN 0 ELSE 1 END, s.last_claimed_at, s.partition_key LIMIT 100 OFFSET :offset',
                    [...$parameters, 'offset' => $offset],
                    [...$types, 'offset' => ParameterType::INTEGER],
                );
                foreach ($candidates as $candidate) {
                    $candidate = \is_resource($candidate) ? stream_get_contents($candidate) : $candidate;
                    $claimed = $this->connection->fetchOne(
                        $selection . ' AND s.partition_key = :candidate FOR UPDATE SKIP LOCKED',
                        [...$parameters, 'candidate' => $candidate],
                        [...$types, 'candidate' => ParameterType::BINARY],
                    );
                    if ($claimed !== false) {
                        $partitionKey = $candidate;
                        break 2;
                    }
                }
                $offset += 100;
            } while (\count($candidates) === 100);

            if ($partitionKey === null) {
                return null;
            }

            $expiresAt = $now->modify(\sprintf('+%d seconds', $leaseSeconds));

            $this->connection->executeStatement(
                'UPDATE webhook_stream SET locked_by = :workerId, lock_expires_at = :expiresAt, last_claimed_at = :now WHERE partition_key = :pk',
                [
                    'workerId' => $workerId,
                    'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'now' => $nowFormatted,
                    'pk' => $partitionKey,
                ],
                ['pk' => ParameterType::BINARY],
            );

            return new StreamLease(
                partitionKey: $partitionKey,
                workerId: $workerId,
                acquiredAt: \DateTimeImmutable::createFromInterface($now),
                expiresAt: \DateTimeImmutable::createFromInterface($expiresAt),
            );
        });
    }

    /**
     * Refreshes the lease expiration. Returns the renewed lease (with updated expiresAt)
     * on success, or null if the lease was stolen (another worker's locked_by is on the row now).
     */
    public function heartbeat(StreamLease $lease, int $leaseSeconds): ?StreamLease
    {
        $expiresAt = \DateTimeImmutable::createFromInterface(
            $this->clock->now()->modify(\sprintf('+%d seconds', $leaseSeconds))
        );

        $affected = (int) $this->connection->executeStatement(
            'UPDATE webhook_stream SET lock_expires_at = :expiresAt WHERE partition_key = :pk AND locked_by = :workerId',
            [
                'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'pk' => $lease->partitionKey,
                'workerId' => $lease->workerId,
            ],
            ['pk' => ParameterType::BINARY],
        );

        if ($affected === 0) {
            return null;
        }

        return new StreamLease(
            partitionKey: $lease->partitionKey,
            workerId: $lease->workerId,
            acquiredAt: $lease->acquiredAt,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Verifies that the worker still owns the lease without extending it.
     */
    public function verifyOwnership(StreamLease $lease): ?StreamLease
    {
        $expiresAt = $this->connection->fetchOne(
            'SELECT lock_expires_at
             FROM webhook_stream
             WHERE partition_key = :pk
               AND locked_by = :workerId
               AND lock_expires_at > :now',
            [
                'pk' => $lease->partitionKey,
                'workerId' => $lease->workerId,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['pk' => ParameterType::BINARY],
        );

        if ($expiresAt === false) {
            return null;
        }

        return new StreamLease(
            partitionKey: $lease->partitionKey,
            workerId: $lease->workerId,
            acquiredAt: $lease->acquiredAt,
            expiresAt: new \DateTimeImmutable((string) $expiresAt),
        );
    }

    /**
     * Releases the lease by clearing locked_by / lock_expires_at.
     * No-op if the lease was already stolen.
     */
    public function release(StreamLease $lease): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = NULL, lock_expires_at = NULL WHERE partition_key = :pk AND locked_by = :workerId',
            ['pk' => $lease->partitionKey, 'workerId' => $lease->workerId],
            ['pk' => ParameterType::BINARY],
        );
    }

    /**
     * Deletes webhook_stream rows that have no corresponding webhook_delivery rows,
     * are unlocked or past their lease, and are older than {@see self::ORPHAN_GRACE_SECONDS}.
     *
     * Delivery writes refresh `created_at`, restarting the stream's cleanup grace window.
     */
    public function deleteOrphanedStreams(int $batchSize): int
    {
        $batchSize = max(1, $batchSize);
        $now = $this->clock->now();

        return $this->connection->transactional(function () use ($now, $batchSize): int {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT sw_uuid_hex(id) FROM webhook_stream
                 WHERE NOT EXISTS (SELECT 1 FROM webhook_delivery d WHERE d.partition_key = webhook_stream.partition_key)
                   AND (locked_by IS NULL OR lock_expires_at <= :now)
                   AND created_at < :cutoff
                 ORDER BY id LIMIT :limit FOR UPDATE SKIP LOCKED',
                [
                    'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'cutoff' => $now->modify(\sprintf('-%d seconds', self::ORPHAN_GRACE_SECONDS))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'limit' => $batchSize,
                ],
                ['limit' => ParameterType::INTEGER],
            );
            if ($ids === []) {
                return 0;
            }

            return (int) $this->connection->executeStatement(
                'DELETE FROM webhook_stream WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY],
            );
        });
    }
}

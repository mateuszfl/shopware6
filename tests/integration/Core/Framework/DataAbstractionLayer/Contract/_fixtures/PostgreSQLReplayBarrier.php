<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use Doctrine\DBAL\Connection;

/**
 * @internal
 */
final class PostgreSQLReplayBarrier
{
    public function __construct(
        private readonly Connection $primary,
        private readonly Connection $standbyControl,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    public function wait(): string
    {
        $lsn = $this->primary->fetchOne('SELECT pg_current_wal_lsn()::text');
        if (!\is_string($lsn) || $lsn === '') {
            throw new \RuntimeException('The primary did not return a WAL LSN');
        }

        $deadline = microtime(true) + $this->timeoutSeconds;
        do {
            $replayed = $this->standbyControl->fetchOne(
                'SELECT COALESCE(pg_last_wal_replay_lsn() >= ?::pg_lsn, false)',
                [$lsn],
            );
            if (filter_var($replayed, \FILTER_VALIDATE_BOOL)) {
                return $lsn;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $state = $this->standbyControl->fetchAssociative(<<<'SQL'
            SELECT pg_last_wal_receive_lsn()::text AS received,
                   pg_last_wal_replay_lsn()::text AS replayed,
                   pg_get_wal_replay_pause_state() AS pause_state
            SQL);

        throw new \RuntimeException(\sprintf(
            'Standby did not replay WAL through %s within %d seconds: %s',
            $lsn,
            $this->timeoutSeconds,
            json_encode($state, \JSON_THROW_ON_ERROR),
        ));
    }
}

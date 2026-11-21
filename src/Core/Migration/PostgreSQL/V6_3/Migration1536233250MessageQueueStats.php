<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536233250MessageQueueStats extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233250;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS message_queue_stats (
                id BYTEA NOT NULL PRIMARY KEY CONSTRAINT chk_sw_message_queue_stats_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                size INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                CONSTRAINT "message_queue_stats__uniq.message_queue_stats.name" UNIQUE (name),
                CONSTRAINT "check.message_queue_stats.size" CHECK (size >= 0)
            )
            SQL);
    }
}

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
class Migration1536233320DeadMessage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233320;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dead_message (
                id BYTEA NOT NULL CONSTRAINT chk_sw_dead_message_id_uuid CHECK (octet_length(id) = 16),
                original_message_class TEXT COLLATE sw_type_ci NOT NULL,
                serialized_original_message BYTEA NOT NULL,
                handler_class TEXT COLLATE sw_type_ci NOT NULL,
                encrypted SMALLINT DEFAULT 0 NOT NULL CONSTRAINT chk_sw_dead_message_encrypted_bool CHECK (encrypted IN (0, 1)),
                error_count INTEGER NOT NULL,
                next_execution_time TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                exception TEXT COLLATE sw_type_ci NOT NULL,
                exception_message TEXT COLLATE sw_type_ci NOT NULL,
                exception_file TEXT COLLATE sw_type_ci NOT NULL,
                exception_line INTEGER NOT NULL,
                scheduled_task_id BYTEA NULL CONSTRAINT chk_sw_dead_message_scheduled_task_id_uuid CHECK (octet_length(scheduled_task_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE,
                PRIMARY KEY (id),
                CONSTRAINT "check.dead_message.error_count" CHECK (error_count >= 1),
                CONSTRAINT "fk.dead_message.scheduled_task_id" FOREIGN KEY (scheduled_task_id)
                    REFERENCES scheduled_task (id) ON DELETE SET NULL
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "dead_message__fk.dead_message.scheduled_task_id" ON dead_message (scheduled_task_id)');
    }
}

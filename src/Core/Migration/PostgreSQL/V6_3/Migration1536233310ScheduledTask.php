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
class Migration1536233310ScheduledTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233310;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS scheduled_task (
                id BYTEA NOT NULL CONSTRAINT chk_sw_scheduled_task_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                scheduled_task_class VARCHAR(512) COLLATE sw_type_ci NOT NULL,
                run_interval INTEGER NOT NULL,
                status VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                last_execution_time TIMESTAMP(3) WITHOUT TIME ZONE,
                next_execution_time TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "check.scheduled_task.run_interval" CHECK (run_interval >= 1),
                CONSTRAINT "scheduled_task__uniq.scheduled_task.scheduled_task_class" UNIQUE (scheduled_task_class)
            )
            SQL);
    }
}

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
class Migration1536233370EventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233370;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS event_action (
                id BYTEA NOT NULL PRIMARY KEY CONSTRAINT chk_sw_event_action_id_uuid CHECK (octet_length(id) = 16),
                event_name VARCHAR(500) COLLATE sw_type_ci NOT NULL,
                action_name VARCHAR(500) COLLATE sw_type_ci NOT NULL,
                config JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "event_action__idx.event_action.event_name" ON event_action (event_name)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "event_action__idx.event_action.action_name" ON event_action (action_name)');
    }
}

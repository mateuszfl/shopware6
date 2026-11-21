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
class Migration1536233260StateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233260;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS state_machine_history (
                id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_history_id_uuid CHECK (octet_length(id) = 16),
                state_machine_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_history_state_machine_id_uuid CHECK (octet_length(state_machine_id) = 16),
                entity_name VARCHAR(100) COLLATE sw_type_ci NOT NULL,
                entity_id JSONB NOT NULL,
                from_state_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_history_from_state_id_uuid CHECK (octet_length(from_state_id) = 16),
                to_state_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_history_to_state_id_uuid CHECK (octet_length(to_state_id) = 16),
                action_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                user_id BYTEA NULL CONSTRAINT chk_sw_state_machine_history_user_id_uuid CHECK (octet_length(user_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.state_machine_history.state_machine_id" FOREIGN KEY (state_machine_id)
                    REFERENCES state_machine (id) ON DELETE NO ACTION ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_history.from_state_id" FOREIGN KEY (from_state_id)
                    REFERENCES state_machine_state (id) ON DELETE NO ACTION ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_history.to_state_id" FOREIGN KEY (to_state_id)
                    REFERENCES state_machine_state (id) ON DELETE NO ACTION ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_history.user_id" FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE NO ACTION ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_history__fk.state_machine_histor_a5acdbc4ae792ad2" ON state_machine_history (state_machine_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_history__fk.state_machine_history.from_state_id" ON state_machine_history (from_state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_history__fk.state_machine_history.to_state_id" ON state_machine_history (to_state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_history__fk.state_machine_history.user_id" ON state_machine_history (user_id)');
    }
}

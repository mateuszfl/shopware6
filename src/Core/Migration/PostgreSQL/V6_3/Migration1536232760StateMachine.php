<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536232760StateMachine extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232760;
    }

    public function update(Connection $connection): void
    {
        $this->createMachineTables($connection);
        $this->createStateTables($connection);
        $this->createTransitionTable($connection);
        $this->connectInitialState($connection);
    }

    private function createMachineTables(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS state_machine (
                id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_id_uuid CHECK (octet_length(id) = 16),
                technical_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                initial_state_id BYTEA NULL CONSTRAINT chk_sw_state_machine_initial_state_id_uuid CHECK (octet_length(initial_state_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "state_machine__state_machine__uniq.state_machine.technical_name" UNIQUE (technical_name)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS state_machine_translation (
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                state_machine_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_translation_state_machine_id_uuid CHECK (octet_length(state_machine_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (language_id,state_machine_id),
                CONSTRAINT "fk.state_machine_translation.state_machine_id" FOREIGN KEY (state_machine_id)
                    REFERENCES state_machine (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_translation__idx.state_machine_t_3f904899bdfe93d4" ON state_machine_translation (language_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_translation__idx.state_machine_t_30d7064e7b42e429" ON state_machine_translation (state_machine_id)');
    }

    private function createStateTables(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS state_machine_state (
                id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_state_id_uuid CHECK (octet_length(id) = 16),
                technical_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                state_machine_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_state_state_machine_id_uuid CHECK (octet_length(state_machine_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT state_machine_state__state_machine_state__uniq_cd8f5f01ec4f7a2d UNIQUE (technical_name,state_machine_id),
                CONSTRAINT "fk.state_machine_state.state_machine_id" FOREIGN KEY (state_machine_id)
                    REFERENCES state_machine (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_state__idx.state_machine_state.state_machine_id" ON state_machine_state (state_machine_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS state_machine_state_translation (
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_state_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                state_machine_state_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_state_translation_state_m_16ccad1832bdbce1 CHECK (octet_length(state_machine_state_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (language_id,state_machine_state_id),
                CONSTRAINT "fk.state_machine_state_translation.state_machine_state_id" FOREIGN KEY (state_machine_state_id)
                    REFERENCES state_machine_state (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_state_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_state_translation__idx.language" ON state_machine_state_translation (language_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_state_translation__idx.state_machine" ON state_machine_state_translation (state_machine_state_id)');
    }

    private function createTransitionTable(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS state_machine_transition (
                id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_transition_id_uuid CHECK (octet_length(id) = 16),
                action_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                state_machine_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_transition_state_machine_id_uuid CHECK (octet_length(state_machine_id) = 16),
                from_state_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_transition_from_state_id_uuid CHECK (octet_length(from_state_id) = 16),
                to_state_id BYTEA NOT NULL CONSTRAINT chk_sw_state_machine_transition_to_state_id_uuid CHECK (octet_length(to_state_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT state_machine_transition__state_machine_transi_be4177dd3f344ab9 UNIQUE (action_name,state_machine_id,from_state_id,to_state_id),
                CONSTRAINT "fk.state_machine_transition.state_machine_id" FOREIGN KEY (state_machine_id)
                    REFERENCES state_machine (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_transition.to_state_id" FOREIGN KEY (to_state_id)
                    REFERENCES state_machine_state (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.state_machine_transition.from_state_id" FOREIGN KEY (from_state_id)
                    REFERENCES state_machine_state (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_transition__idx.state_machine_tr_51076693981ba71c" ON state_machine_transition (state_machine_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_transition__idx.state_machine_tr_95d5ceaaecb5bca9" ON state_machine_transition (from_state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine_transition__idx.state_machine_tr_5bcaad0baf12b0f5" ON state_machine_transition (to_state_id)');
    }

    private function connectInitialState(Connection $connection): void
    {
        if (!TableHelper::foreignKeyExists($connection, 'state_machine', 'fk.state_machine.initial_state_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE state_machine ADD CONSTRAINT "fk.state_machine.initial_state_id" FOREIGN KEY (initial_state_id)
                    REFERENCES state_machine_state (id) ON DELETE SET NULL ON UPDATE CASCADE
                SQL);
        }

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "state_machine__fk.state_machine.initial_state_id" ON state_machine (initial_state_id)');
    }
}

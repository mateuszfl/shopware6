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
class Migration1536233380UserRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233380;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_recovery (
                id BYTEA NOT NULL CONSTRAINT chk_sw_user_recovery_id_uuid CHECK (octet_length(id) = 16),
                user_id BYTEA NOT NULL CONSTRAINT chk_sw_user_recovery_user_id_uuid CHECK (octet_length(user_id) = 16),
                hash VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "user_recovery__uniq.user_recovery.user_id" UNIQUE (user_id),
                CONSTRAINT "fk.user_recovery.user_id" FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);
    }
}

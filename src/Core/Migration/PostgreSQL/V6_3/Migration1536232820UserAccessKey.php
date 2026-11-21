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
class Migration1536232820UserAccessKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232820;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_access_key (
                id BYTEA NOT NULL CONSTRAINT chk_sw_user_access_key_id_uuid CHECK (octet_length(id) = 16),
                user_id BYTEA NOT NULL CONSTRAINT chk_sw_user_access_key_user_id_uuid CHECK (octet_length(user_id) = 16),
                write_access SMALLINT NOT NULL CONSTRAINT chk_sw_user_access_key_write_access_bool CHECK (write_access IN (0, 1)),
                access_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                secret_access_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                last_usage_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.user_access_key.user_id" FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "user_access_key__idx.user_access_key.user_id_" ON user_access_key (user_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "user_access_key__idx.user_access_key.access_key" ON user_access_key (access_key)');
    }
}

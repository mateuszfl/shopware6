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
class Migration1536232710Integration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232710;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS integration (
                id BYTEA NOT NULL CONSTRAINT chk_sw_integration_id_uuid CHECK (octet_length(id) = 16),
                write_access SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_integration_write_access_bool CHECK (write_access IN (0, 1)),
                access_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                secret_access_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                label VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                last_usage_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "integration__idx.access_key" ON integration (access_key)');
    }
}

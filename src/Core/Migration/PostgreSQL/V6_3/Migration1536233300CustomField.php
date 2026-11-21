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
class Migration1536233300CustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233300;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS custom_field (
                id BYTEA NOT NULL PRIMARY KEY CONSTRAINT chk_sw_custom_field_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                config JSONB NULL,
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_custom_field_active_bool CHECK (active IN (0, 1)),
                set_id BYTEA NULL CONSTRAINT chk_sw_custom_field_set_id_uuid CHECK (octet_length(set_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                CONSTRAINT "custom_field__uniq.custom_field.name" UNIQUE (name),
                CONSTRAINT "fk.custom_field.set_id" FOREIGN KEY (set_id)
                    REFERENCES custom_field_set (id) ON UPDATE CASCADE ON DELETE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "custom_field__fk.custom_field.set_id" ON custom_field (set_id)');
    }
}

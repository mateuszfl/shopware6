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
class Migration1536232840MediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232840;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_folder (
                id BYTEA NOT NULL CONSTRAINT chk_sw_media_folder_id_uuid CHECK (octet_length(id) = 16),
                parent_id BYTEA NULL CONSTRAINT chk_sw_media_folder_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                default_folder_id BYTEA NULL CONSTRAINT chk_sw_media_folder_default_folder_id_uuid CHECK (octet_length(default_folder_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                child_count BIGINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_media_folder_child_count_unsigned CHECK (child_count >= 0 AND child_count <= '4294967295'::bigint),
                media_folder_configuration_id BYTEA NULL CONSTRAINT chk_sw_media_folder_media_folder_configuration_id_uuid CHECK (octet_length(media_folder_configuration_id) = 16),
                use_parent_configuration SMALLINT NULL DEFAULT 1 CONSTRAINT chk_sw_media_folder_use_parent_configuration_bool CHECK (use_parent_configuration IN (0, 1)),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "media_folder__uniq.media_folder.default_folder_id" UNIQUE (default_folder_id),
                CONSTRAINT "fk.media_folder.parent_id" FOREIGN KEY (parent_id)
                    REFERENCES media_folder (id) ON DELETE CASCADE,
                CONSTRAINT "fk.media_folder.default_folder_id" FOREIGN KEY (default_folder_id)
                    REFERENCES media_default_folder (id) ON DELETE SET NULL
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media_folder__fk.media_folder.parent_id" ON media_folder (parent_id)');
    }
}

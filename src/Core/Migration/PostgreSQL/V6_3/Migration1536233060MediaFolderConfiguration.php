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
class Migration1536233060MediaFolderConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233060;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_folder_configuration (
                id BYTEA CONSTRAINT chk_sw_media_folder_configuration_id_uuid CHECK (octet_length(id) = 16),
                create_thumbnails SMALLINT DEFAULT 1 CONSTRAINT chk_sw_media_folder_configuration_create_thumbnails_bool CHECK (create_thumbnails IN (0, 1)),
                thumbnail_quality INTEGER DEFAULT 80,
                media_thumbnail_sizes_ro BYTEA NULL,
                keep_aspect_ratio SMALLINT DEFAULT 1 CONSTRAINT chk_sw_media_folder_configuration_keep_aspect_ratio_bool CHECK (keep_aspect_ratio IN (0, 1)),
                private SMALLINT DEFAULT 0 CONSTRAINT chk_sw_media_folder_configuration_private_bool CHECK (private IN (0, 1)),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
    }
}

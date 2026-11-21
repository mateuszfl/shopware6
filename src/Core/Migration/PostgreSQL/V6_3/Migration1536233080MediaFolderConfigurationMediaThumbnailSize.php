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
class Migration1536233080MediaFolderConfigurationMediaThumbnailSize extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233080;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_folder_configuration_media_thumbnail_size (
                media_folder_configuration_id BYTEA NOT NULL CONSTRAINT chk_sw_media_folder_configuration_media_thumbn_9d28e3e661cbdafd CHECK (octet_length(media_folder_configuration_id) = 16),
                media_thumbnail_size_id BYTEA NOT NULL CONSTRAINT chk_sw_media_folder_configuration_media_thumbn_e34e9b11d3655430 CHECK (octet_length(media_thumbnail_size_id) = 16),
                PRIMARY KEY (media_folder_configuration_id, media_thumbnail_size_id),
                CONSTRAINT "fk.media_folder_configuration_media_thumbnail_size.conf_id" FOREIGN KEY (media_folder_configuration_id)
                    REFERENCES media_folder_configuration (id) ON DELETE CASCADE,
                CONSTRAINT "fk.media_folder_configuration_media_thumbnail_size.size_id" FOREIGN KEY (media_thumbnail_size_id)
                    REFERENCES media_thumbnail_size (id) ON DELETE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media_folder_configuration_media_thumbnail_siz_72dcc4133dd4ae4f" ON media_folder_configuration_media_thumbnail_size (media_thumbnail_size_id)');
    }
}

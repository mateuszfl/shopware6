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
class Migration1536233090MediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233090;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_thumbnail (
                id BYTEA NOT NULL CONSTRAINT chk_sw_media_thumbnail_id_uuid CHECK (octet_length(id) = 16),
                media_id BYTEA NOT NULL CONSTRAINT chk_sw_media_thumbnail_media_id_uuid CHECK (octet_length(media_id) = 16),
                width BIGINT NOT NULL CONSTRAINT chk_sw_media_thumbnail_width_unsigned CHECK (width >= 0 AND width <= '4294967295'::bigint),
                height BIGINT NOT NULL CONSTRAINT chk_sw_media_thumbnail_height_unsigned CHECK (height >= 0 AND height <= '4294967295'::bigint),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.media_thumbnail.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media_thumbnail__fk.media_thumbnail.media_id" ON media_thumbnail (media_id)');
    }
}

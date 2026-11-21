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
class Migration1536232900CmsBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232900;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_block (
                id BYTEA NOT NULL CONSTRAINT chk_sw_cms_block_id_uuid CHECK (octet_length(id) = 16),
                cms_page_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_block_cms_page_id_uuid CHECK (octet_length(cms_page_id) = 16),
                "position" INTEGER NOT NULL,
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                locked SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_cms_block_locked_bool CHECK (locked IN (0, 1)),
                sizing_mode VARCHAR(255) COLLATE sw_type_ci NULL,
                margin_top VARCHAR(255) COLLATE sw_type_ci NULL,
                margin_bottom VARCHAR(255) COLLATE sw_type_ci NULL,
                margin_left VARCHAR(255) COLLATE sw_type_ci NULL,
                margin_right VARCHAR(255) COLLATE sw_type_ci NULL,
                background_color VARCHAR(255) COLLATE sw_type_ci NULL,
                background_media_id BYTEA NULL CONSTRAINT chk_sw_cms_block_background_media_id_uuid CHECK (octet_length(background_media_id) = 16),
                background_media_mode VARCHAR(255) COLLATE sw_type_ci NULL,
                css_class VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.cms_block.cms_page_id" FOREIGN KEY (cms_page_id)
                    REFERENCES cms_page (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cms_block.background_media_id" FOREIGN KEY (background_media_id)
                    REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_block__fk.cms_block.cms_page_id" ON cms_block (cms_page_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_block__fk.cms_block.background_media_id" ON cms_block (background_media_id)');
    }
}

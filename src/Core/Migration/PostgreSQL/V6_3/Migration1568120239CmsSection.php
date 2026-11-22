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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1568120239CmsSection extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568120239;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_section (
                id BYTEA NOT NULL CONSTRAINT chk_sw_cms_section_id_uuid CHECK (octet_length(id) = 16),
                cms_page_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_section_cms_page_id_uuid CHECK (octet_length(cms_page_id) = 16),
                "position" INTEGER NOT NULL,
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL DEFAULT 'default',
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                locked SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_cms_section_locked_bool CHECK (locked IN (0, 1)),
                sizing_mode VARCHAR(255) COLLATE sw_type_ci NOT NULL DEFAULT 'boxed',
                mobile_behavior VARCHAR(255) COLLATE sw_type_ci NOT NULL DEFAULT 'wrap',
                background_color VARCHAR(255) COLLATE sw_type_ci NULL,
                background_media_id BYTEA NULL CONSTRAINT chk_sw_cms_section_background_media_id_uuid CHECK (octet_length(background_media_id) = 16),
                background_media_mode VARCHAR(255) COLLATE sw_type_ci NULL,
                css_class VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.cms_section.cms_page_id" FOREIGN KEY (cms_page_id)
                    REFERENCES cms_page (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cms_section.background_media_id" FOREIGN KEY (background_media_id)
                    REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_section__fk.cms_section.cms_page_id" ON cms_section (cms_page_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_section__fk.cms_section.background_media_id" ON cms_section (background_media_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

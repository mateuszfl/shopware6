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
class Migration1536232890CmsPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232890;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_page (
                id BYTEA NOT NULL CONSTRAINT chk_sw_cms_page_id_uuid CHECK (octet_length(id) = 16),
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                entity VARCHAR(255) COLLATE sw_type_ci NULL,
                preview_media_id BYTEA NULL CONSTRAINT chk_sw_cms_page_preview_media_id_uuid CHECK (octet_length(preview_media_id) = 16),
                locked SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_cms_page_locked_bool CHECK (locked IN (0, 1)),
                config JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.cms_page.preview_media_id" FOREIGN KEY (preview_media_id)
                    REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_page__fk.cms_page.preview_media_id" ON cms_page (preview_media_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_page_translation (
                cms_page_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_page_translation_cms_page_id_uuid CHECK (octet_length(cms_page_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_page_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (cms_page_id, language_id),
                CONSTRAINT "fk.cms_page_translation.cms_page_id" FOREIGN KEY (cms_page_id)
                    REFERENCES cms_page (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cms_page_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_page_translation__fk.cms_page_translation.language_id" ON cms_page_translation (language_id)');
    }
}

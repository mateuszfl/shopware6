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
class Migration1536232930Navigation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232930;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS navigation (
                id BYTEA NOT NULL CONSTRAINT chk_sw_navigation_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_navigation_version_id_uuid CHECK (octet_length(version_id) = 16),
                parent_id BYTEA NULL CONSTRAINT chk_sw_navigation_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                parent_version_id BYTEA NULL CONSTRAINT chk_sw_navigation_parent_version_id_uuid CHECK (octet_length(parent_version_id) = 16),
                category_id BYTEA NULL CONSTRAINT chk_sw_navigation_category_id_uuid CHECK (octet_length(category_id) = 16),
                category_version_id BYTEA NULL CONSTRAINT chk_sw_navigation_category_version_id_uuid CHECK (octet_length(category_version_id) = 16),
                path TEXT COLLATE sw_type_ci,
                level BIGINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_navigation_level_unsigned CHECK (level >= 0 AND level <= '4294967295'::bigint),
                child_count BIGINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_navigation_child_count_unsigned CHECK (child_count >= 0 AND child_count <= '4294967295'::bigint),
                cms_page_id BYTEA NULL CONSTRAINT chk_sw_navigation_cms_page_id_uuid CHECK (octet_length(cms_page_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.navigation.parent_id" FOREIGN KEY (parent_id, parent_version_id)
                    REFERENCES navigation (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.navigation.cms_page_id" FOREIGN KEY (cms_page_id)
                    REFERENCES cms_page (id) ON DELETE NO ACTION ON UPDATE CASCADE,
                CONSTRAINT "fk.navigation.category_id" FOREIGN KEY (category_id, category_version_id)
                    REFERENCES category (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "navigation__idx.navigation.level" ON navigation (level)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "navigation__fk.navigation.parent_id" ON navigation (parent_id, parent_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "navigation__fk.navigation.cms_page_id" ON navigation (cms_page_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "navigation__fk.navigation.category_id" ON navigation (category_id, category_version_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS navigation_translation (
                navigation_id BYTEA NOT NULL CONSTRAINT chk_sw_navigation_translation_navigation_id_uuid CHECK (octet_length(navigation_id) = 16),
                navigation_version_id BYTEA NOT NULL CONSTRAINT chk_sw_navigation_translation_navigation_version_id_uuid CHECK (octet_length(navigation_version_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_navigation_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                slot_config JSONB,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE,
                PRIMARY KEY (navigation_id, navigation_version_id, language_id),
                CONSTRAINT "fk.navigation_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.navigation_translation.navigation_id" FOREIGN KEY (navigation_id, navigation_version_id)
                    REFERENCES navigation (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "navigation_translation__fk.navigation_translation.language_id" ON navigation_translation (language_id)');
    }
}

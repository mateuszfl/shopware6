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
class Migration1536232880Category extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232880;
    }

    public function update(Connection $connection): void
    {
        // Shift the signed sequence into the full unsigned range, starting at 1.
        $connection->executeStatement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS category_auto_increment_seq AS BIGINT
                MINVALUE -9223372036854775808 MAXVALUE 9223372036854775806
                START WITH -9223372036854775808
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS category (
                id BYTEA NOT NULL CONSTRAINT chk_sw_category_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_category_version_id_uuid CHECK (octet_length(version_id) = 16),
                auto_increment NUMERIC(20, 0) DEFAULT (nextval('category_auto_increment_seq')::numeric + 9223372036854775809) NOT NULL
                    CONSTRAINT chk_sw_category_auto_increment_unsigned CHECK (auto_increment BETWEEN 0 AND 18446744073709551615),
                parent_id BYTEA NULL CONSTRAINT chk_sw_category_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                parent_version_id BYTEA NULL CONSTRAINT chk_sw_category_parent_version_id_uuid CHECK (octet_length(parent_version_id) = 16),
                media_id BYTEA NULL CONSTRAINT chk_sw_category_media_id_uuid CHECK (octet_length(media_id) = 16),
                path TEXT COLLATE sw_type_ci,
                after_category_id BYTEA CONSTRAINT chk_sw_category_after_category_id_uuid CHECK (octet_length(after_category_id) = 16),
                after_category_version_id BYTEA CONSTRAINT chk_sw_category_after_category_version_id_uuid CHECK (octet_length(after_category_version_id) = 16),
                level BIGINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_category_level_unsigned CHECK (level >= 0 AND level <= '4294967295'::bigint),
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_category_active_bool CHECK (active IN (0, 1)),
                child_count BIGINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_category_child_count_unsigned CHECK (child_count >= 0 AND child_count <= '4294967295'::bigint),
                display_nested_products SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_category_display_nested_products_bool CHECK (display_nested_products IN (0, 1)),
                visible SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_category_visible_bool CHECK (visible IN (0, 1)),
                type VARCHAR(32) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.category.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT "fk.category.parent_id" FOREIGN KEY (parent_id, parent_version_id)
                    REFERENCES category (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.category.after_category_id" FOREIGN KEY (after_category_id, after_category_version_id)
                    REFERENCES category (id, version_id) ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('ALTER SEQUENCE category_auto_increment_seq OWNED BY category.auto_increment');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category__idx.level" ON category (level)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category__idx.auto_increment" ON category (auto_increment)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category__fk.category.media_id" ON category (media_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category__fk.category.parent_id" ON category (parent_id, parent_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category__fk.category.after_category_id" ON category (after_category_id, after_category_version_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS category_translation (
                category_id BYTEA NOT NULL CONSTRAINT chk_sw_category_translation_category_id_uuid CHECK (octet_length(category_id) = 16),
                category_version_id BYTEA NOT NULL CONSTRAINT chk_sw_category_translation_category_version_id_uuid CHECK (octet_length(category_version_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_category_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                external_link TEXT COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (category_id, category_version_id, language_id),
                CONSTRAINT "fk.category_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.category_translation.category_id" FOREIGN KEY (category_id, category_version_id)
                    REFERENCES category (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category_translation__fk.category_translation.language_id" ON category_translation (language_id)');
    }
}

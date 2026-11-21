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
class Migration1536232910CmsSlot extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232910;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_slot (
                id BYTEA NOT NULL CONSTRAINT chk_sw_cms_slot_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_slot_version_id_uuid CHECK (octet_length(version_id) = 16),
                cms_block_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_slot_cms_block_id_uuid CHECK (octet_length(cms_block_id) = 16),
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                slot VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                locked SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_cms_slot_locked_bool CHECK (locked IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.cms_slot.cms_block_id" FOREIGN KEY (cms_block_id)
                    REFERENCES cms_block (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_slot__fk.cms_slot.cms_block_id" ON cms_slot (cms_block_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_slot_translation (
                cms_slot_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_slot_translation_cms_slot_id_uuid CHECK (octet_length(cms_slot_id) = 16),
                cms_slot_version_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_slot_translation_cms_slot_version_id_uuid CHECK (octet_length(cms_slot_version_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_cms_slot_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                config JSONB NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (cms_slot_id, cms_slot_version_id, language_id),
                CONSTRAINT "fk.cms_slot_translation.cms_slot_id" FOREIGN KEY (cms_slot_id, cms_slot_version_id)
                    REFERENCES cms_slot (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cms_slot_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_slot_translation__fk.cms_slot_translation.language_id" ON cms_slot_translation (language_id)');
    }
}

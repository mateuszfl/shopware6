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
class Migration1536233100PropertyGroupOption extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233100;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS property_group_option (
                id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_option_id_uuid CHECK (octet_length(id) = 16),
                property_group_id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_option_property_group_id_uuid CHECK (octet_length(property_group_id) = 16),
                color_hex_code VARCHAR(20) COLLATE sw_type_ci NULL,
                media_id BYTEA NULL CONSTRAINT chk_sw_property_group_option_media_id_uuid CHECK (octet_length(media_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.property_group_option.property_group_id" FOREIGN KEY (property_group_id)
                    REFERENCES property_group (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.property_group_option.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "property_group_option__fk.property_group_optio_e5a8d82b54ad506c" ON property_group_option (property_group_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "property_group_option__fk.property_group_option.media_id" ON property_group_option (media_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS property_group_option_translation (
                property_group_option_id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_option_translation_prope_a7b51da21084f334 CHECK (octet_length(property_group_option_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_option_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                "position" INTEGER NOT NULL DEFAULT 1,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (property_group_option_id, language_id),
                CONSTRAINT "fk.property_group_option_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.property_group_option_translation.prop_group_option_id" FOREIGN KEY (property_group_option_id)
                    REFERENCES property_group_option (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "property_group_option_translation__fk.property_583a05facbccaabb" ON property_group_option_translation (language_id)');
    }
}

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
class Migration1536232630PropertyGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232630;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS property_group (
                id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_id_uuid CHECK (octet_length(id) = 16),
                sorting_type VARCHAR(50) COLLATE sw_type_ci NOT NULL DEFAULT 'alphanumeric',
                display_type VARCHAR(50) COLLATE sw_type_ci NOT NULL DEFAULT 'text',
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS property_group_translation (
                property_group_id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_translation_property_group_id_uuid CHECK (octet_length(property_group_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_property_group_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (property_group_id, language_id),
                CONSTRAINT "fk.property_group_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.property_group_translation.property_group_id" FOREIGN KEY (property_group_id) REFERENCES property_group (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "property_group_translation__fk.property_group_translation.language_id" ON property_group_translation (language_id)');
    }
}

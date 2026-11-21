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
class Migration1536232720Country extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232720;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS country (
                id BYTEA NOT NULL CONSTRAINT chk_sw_country_id_uuid CHECK (octet_length(id) = 16),
                iso VARCHAR(255) COLLATE sw_type_ci NULL,
                "position" INTEGER NOT NULL DEFAULT 1,
                tax_free SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_country_tax_free_bool CHECK (tax_free IN (0, 1)),
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_country_active_bool CHECK (active IN (0, 1)),
                iso3 VARCHAR(45) COLLATE sw_type_ci NULL,
                display_state_in_registration SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_country_display_state_in_registration_bool CHECK (display_state_in_registration IN (0, 1)),
                force_state_in_registration SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_country_force_state_in_registration_bool CHECK (force_state_in_registration IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS country_translation (
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_country_translation_country_id_uuid CHECK (octet_length(country_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_country_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (country_id, language_id),
                CONSTRAINT "fk.country_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.country_translation.country_id" FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "country_translation__fk.country_translation.language_id" ON country_translation (language_id)');
    }
}

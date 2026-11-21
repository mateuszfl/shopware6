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
class Migration1536232730CountryState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232730;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS country_state (
                id BYTEA NOT NULL CONSTRAINT chk_sw_country_state_id_uuid CHECK (octet_length(id) = 16),
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_country_state_country_id_uuid CHECK (octet_length(country_id) = 16),
                short_code VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                "position" INTEGER NOT NULL DEFAULT 1,
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_country_state_active_bool CHECK (active IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.country_state.country_id" FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "country_state__fk.country_state.country_id" ON country_state (country_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS country_state_translation (
                country_state_id BYTEA NOT NULL CONSTRAINT chk_sw_country_state_translation_country_state_id_uuid CHECK (octet_length(country_state_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_country_state_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (country_state_id, language_id),
                CONSTRAINT "fk.country_state_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.country_state_translation.country_state_id" FOREIGN KEY (country_state_id) REFERENCES country_state (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "country_state_translation__fk.country_state_translation.language_id" ON country_state_translation (language_id)');
    }
}

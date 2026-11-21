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
class Migration1536232670Unit extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232670;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS unit (
                id BYTEA NOT NULL CONSTRAINT chk_sw_unit_id_uuid CHECK (octet_length(id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS unit_translation (
                unit_id BYTEA NOT NULL CONSTRAINT chk_sw_unit_translation_unit_id_uuid CHECK (octet_length(unit_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_unit_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                short_code VARCHAR(255) COLLATE sw_type_ci NULL,
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (unit_id,language_id),
                CONSTRAINT "fk.unit_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.unit_translation.unit_id" FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "unit_translation__fk.unit_translation.language_id" ON unit_translation (language_id)');
    }
}

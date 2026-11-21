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
class Migration1536232950Salutation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232950;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS salutation (
                id BYTEA NOT NULL CONSTRAINT chk_sw_salutation_id_uuid CHECK (octet_length(id) = 16),
                salutation_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "salutation__salutation__uniq.salutation_key" UNIQUE (salutation_key)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS salutation_translation (
                salutation_id BYTEA NOT NULL CONSTRAINT chk_sw_salutation_translation_salutation_id_uuid CHECK (octet_length(salutation_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_salutation_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                display_name VARCHAR(255) COLLATE sw_type_ci NULL,
                letter_name VARCHAR(255) COLLATE sw_type_ci NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (salutation_id, language_id),
                CONSTRAINT "fk.salutation_translation.salutation_id" FOREIGN KEY (salutation_id)
                    REFERENCES salutation (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.salutation_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "salutation_translation__fk.salutation_translation.language_id" ON salutation_translation (language_id)');
    }
}

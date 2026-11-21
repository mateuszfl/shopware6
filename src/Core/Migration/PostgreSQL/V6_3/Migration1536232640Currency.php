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
class Migration1536232640Currency extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232640;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS currency (
                id BYTEA NOT NULL CONSTRAINT chk_sw_currency_id_uuid CHECK (octet_length(id) = 16),
                iso_code CHAR(3) COLLATE sw_type_ci NOT NULL,
                factor DOUBLE PRECISION NOT NULL,
                symbol VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                "position" INTEGER NOT NULL DEFAULT 1,
                decimal_precision INTEGER NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS currency_translation (
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_currency_translation_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_currency_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                short_name VARCHAR(255) COLLATE sw_type_ci NULL,
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (currency_id, language_id),
                CONSTRAINT "fk.currency_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.currency_translation.currency_id" FOREIGN KEY (currency_id) REFERENCES currency (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "currency_translation__fk.currency_translation.language_id" ON currency_translation (language_id)');
    }
}

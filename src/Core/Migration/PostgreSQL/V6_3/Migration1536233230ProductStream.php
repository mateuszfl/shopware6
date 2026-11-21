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
class Migration1536233230ProductStream extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233230;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_stream (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_stream_id_uuid CHECK (octet_length(id) = 16),
                api_filter JSONB NULL,
                invalid SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_product_stream_invalid_bool CHECK (invalid IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_stream_translation (
                product_stream_id BYTEA NOT NULL CONSTRAINT chk_sw_product_stream_translation_product_stream_id_uuid CHECK (octet_length(product_stream_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_product_stream_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (product_stream_id, language_id),
                CONSTRAINT "fk.product_stream_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_stream_translation.product_stream_id" FOREIGN KEY (product_stream_id)
                    REFERENCES product_stream (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_stream_translation__fk.product_stream__0f58d4636469838c" ON product_stream_translation (language_id)');
    }
}

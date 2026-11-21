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
class Migration1536232620SalesChannelType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232620;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_type (
                id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_type_id_uuid CHECK (octet_length(id) = 16),
                cover_url VARCHAR(500) COLLATE sw_type_ci NULL,
                icon_name VARCHAR(255) COLLATE sw_type_ci NULL,
                screenshot_urls JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_type_translation (
                sales_channel_type_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_type_translation_sales_channel_type_id_uuid CHECK (octet_length(sales_channel_type_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_type_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                manufacturer VARCHAR(255) COLLATE sw_type_ci NULL,
                description VARCHAR(255) COLLATE sw_type_ci NULL,
                description_long TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (sales_channel_type_id, language_id),
                CONSTRAINT "fk.sales_channel_type_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_type_translation.sales_channel_type_id" FOREIGN KEY (sales_channel_type_id) REFERENCES sales_channel_type (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_type_translation__fk.sales_channel_type_translation.language_id" ON sales_channel_type_translation (language_id)');
    }
}

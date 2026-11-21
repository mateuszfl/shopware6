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
class Migration1536233510DocumentConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233510;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS document_base_config (
                id BYTEA NOT NULL CONSTRAINT chk_sw_document_base_config_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(64) COLLATE sw_type_ci NOT NULL,
                filename_prefix VARCHAR(64) COLLATE sw_type_ci DEFAULT '',
                filename_suffix VARCHAR(64) COLLATE sw_type_ci DEFAULT '',
                document_number VARCHAR(64) COLLATE sw_type_ci DEFAULT '',
                global SMALLINT DEFAULT 0 CONSTRAINT chk_sw_document_base_config_global_bool CHECK (global IN (0, 1)),
                document_type_id BYTEA NOT NULL CONSTRAINT chk_sw_document_base_config_document_type_id_uuid CHECK (octet_length(document_type_id) = 16),
                logo_id BYTEA NULL CONSTRAINT chk_sw_document_base_config_logo_id_uuid CHECK (octet_length(logo_id) = 16),
                config JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.document_base_config.type_id" FOREIGN KEY (document_type_id)
                    REFERENCES document_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.document_base_config.logo_id" FOREIGN KEY (logo_id)
                    REFERENCES media (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document_base_config__idx.document_base_config.type_id" ON document_base_config (document_type_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document_base_config__fk.document_base_config.logo_id" ON document_base_config (logo_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS document_base_config_sales_channel (
                id BYTEA NOT NULL CONSTRAINT chk_sw_document_base_config_sales_channel_id_uuid CHECK (octet_length(id) = 16),
                document_base_config_id BYTEA NOT NULL CONSTRAINT chk_sw_document_base_config_sales_channel_docu_2c71bb24e62e4900 CHECK (octet_length(document_base_config_id) = 16),
                document_type_id BYTEA NOT NULL CONSTRAINT chk_sw_document_base_config_sales_channel_document_type_id_uuid CHECK (octet_length(document_type_id) = 16),
                sales_channel_id BYTEA NULL CONSTRAINT chk_sw_document_base_config_sales_channel_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT document_base_config_sales_channel__document_b_40ba2613c6adf052 UNIQUE (document_type_id, sales_channel_id),
                CONSTRAINT "fk.document_base_config_sales_channel.document_base_config_id" FOREIGN KEY (document_base_config_id)
                    REFERENCES document_base_config (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.document_base_config_sales_channel.document_type_id" FOREIGN KEY (document_type_id)
                    REFERENCES document_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.document_base_config_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document_base_config_sales_channel__fk.documen_36d42dd2ae285234" ON document_base_config_sales_channel (document_base_config_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document_base_config_sales_channel__fk.documen_4ab420c2a093684a" ON document_base_config_sales_channel (sales_channel_id)');
    }
}

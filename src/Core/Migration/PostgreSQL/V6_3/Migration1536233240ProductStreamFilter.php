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
class Migration1536233240ProductStreamFilter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233240;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_stream_filter (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_stream_filter_id_uuid CHECK (octet_length(id) = 16),
                product_stream_id BYTEA NOT NULL CONSTRAINT chk_sw_product_stream_filter_product_stream_id_uuid CHECK (octet_length(product_stream_id) = 16),
                parent_id BYTEA NULL CONSTRAINT chk_sw_product_stream_filter_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                field VARCHAR(255) COLLATE sw_type_ci NULL,
                operator VARCHAR(255) COLLATE sw_type_ci NULL,
                "value" TEXT COLLATE sw_type_ci NULL,
                parameters TEXT COLLATE sw_type_ci NULL,
                "position" INTEGER DEFAULT 0 NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.product_stream_filter.product_stream_id" FOREIGN KEY (product_stream_id)
                    REFERENCES product_stream (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_stream_filter.parent_id" FOREIGN KEY (parent_id)
                    REFERENCES product_stream_filter (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_stream_filter__fk.product_stream_filte_03f6a7a9d9e7a242" ON product_stream_filter (product_stream_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_stream_filter__fk.product_stream_filter.parent_id" ON product_stream_filter (parent_id)');
    }
}

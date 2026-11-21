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
class Migration1536233350ProductVisibility extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233350;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_visibility (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_visibility_id_uuid CHECK (octet_length(id) = 16),
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_visibility_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_visibility_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_product_visibility_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                visibility INTEGER NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.product_visibility.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_visibility.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_visibility__idx.product_visibility.product_id" ON product_visibility (product_id,product_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_visibility__idx.product_visibility.sales_channel_id" ON product_visibility (sales_channel_id)');
    }
}

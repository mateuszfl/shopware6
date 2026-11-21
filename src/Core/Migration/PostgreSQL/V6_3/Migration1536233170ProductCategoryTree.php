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
class Migration1536233170ProductCategoryTree extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233170;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_category_tree (
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_category_tree_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_category_tree_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                category_id BYTEA NOT NULL CONSTRAINT chk_sw_product_category_tree_category_id_uuid CHECK (octet_length(category_id) = 16),
                category_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_category_tree_category_version_id_uuid CHECK (octet_length(category_version_id) = 16),
                PRIMARY KEY (product_id, product_version_id, category_id, category_version_id),
                CONSTRAINT "fk.product_category_tree.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_category_tree.category_id" FOREIGN KEY (category_id, category_version_id)
                    REFERENCES category (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_category_tree__fk.product_category_tree.category_id" ON product_category_tree (category_id, category_version_id)');
    }
}

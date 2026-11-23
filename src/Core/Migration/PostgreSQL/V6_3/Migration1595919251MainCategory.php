<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare (strict_types=1);
namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
/**
 * @internal
 */
#[Package('framework')]
class Migration1595919251MainCategory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595919251;
    }
    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
                    CREATE TABLE IF NOT EXISTS "main_category" (
                      "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL PRIMARY KEY,
                      "product_id" BYTEA CHECK (octet_length("product_id") = 16) NOT NULL,
                      "product_version_id" BYTEA CHECK (octet_length("product_version_id") = 16) NOT NULL,
                      "category_id" BYTEA CHECK (octet_length("category_id") = 16) NOT NULL,
                      "category_version_id" BYTEA CHECK (octet_length("category_version_id") = 16) NOT NULL,
                      "sales_channel_id" BYTEA CHECK (octet_length("sales_channel_id") = 16) NOT NULL,
                      "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                      "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                      CONSTRAINT "uniq.main_category.sales_channel_product" UNIQUE ("product_id", "product_version_id", "sales_channel_id"),
                      CONSTRAINT "fk.main_category.sales_channel_id" FOREIGN KEY ("sales_channel_id")
                        REFERENCES "sales_channel" ("id") ON UPDATE CASCADE ON DELETE CASCADE,
                      CONSTRAINT "fk.main_category.category_id" FOREIGN KEY ("category_id", "category_version_id")
                        REFERENCES "category" ("id", "version_id") ON UPDATE CASCADE ON DELETE CASCADE,
                      CONSTRAINT "fk.main_category.product_id" FOREIGN KEY ("product_id", "product_version_id")
                        REFERENCES "product" ("id", "version_id") ON UPDATE CASCADE ON DELETE CASCADE
                    )
        SQL;
        $connection->executeStatement($sql);
    }
    public function updateDestructive(Connection $connection): void
    {
    }
}

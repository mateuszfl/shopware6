<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1604056421CustomerWishlistProducts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604056421;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "customer_wishlist_product" (
              "id"                    BYTEA CHECK (octet_length("id") = 16)  NOT NULL,
              "customer_wishlist_id"  BYTEA CHECK (octet_length("customer_wishlist_id") = 16)  NOT NULL,
              "product_id"            BYTEA CHECK (octet_length("product_id") = 16)  NOT NULL,
              "product_version_id"    BYTEA CHECK (octet_length("product_version_id") = 16)  NOT NULL,
              "custom_fields"         json        NULL,
              "created_at"            TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
              "updated_at"            TIMESTAMP(3) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY ("id"),
              CONSTRAINT "uniq.customer_wishlist.sales_channel_id__customer_id" UNIQUE ("customer_wishlist_id","product_id"),
              CONSTRAINT "fk.customer_wishlist_product.product_id" FOREIGN KEY ("product_id", "product_version_id") REFERENCES "product" ("id", "version_id") ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT "fk.customer_wishlist_product.customer_wishlist_id" FOREIGN KEY ("customer_wishlist_id") REFERENCES "customer_wishlist" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            )

CREATE INDEX "fk.customer_wishlist_product.product_id" ON "customer_wishlist_product" ("product_id","product_version_id");
CREATE INDEX "fk.customer_wishlist_product.customer_wishlist_id" ON "customer_wishlist_product" ("customer_wishlist_id");');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

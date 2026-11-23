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
class Migration1604056363CustomerWishlist extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604056363;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "customer_wishlist" (
              "id"                BYTEA CHECK (octet_length("id") = 16)   NOT NULL,
              "customer_id"       BYTEA CHECK (octet_length("customer_id") = 16)   NOT NULL,
              "sales_channel_id"  BYTEA CHECK (octet_length("sales_channel_id") = 16)   NOT NULL,
              "custom_fields"     json         NULL,
              "created_at"        TIMESTAMP(3) WITHOUT TIME ZONE  NOT NULL,
              "updated_at"        TIMESTAMP(3) WITHOUT TIME ZONE  DEFAULT NULL,
              PRIMARY KEY ("id"),
              UNIQUE KEY "uniq.customer_wishlist" ("sales_channel_id","customer_id"),
              KEY "fk.customer_wishlist.sales_channel_id" ("sales_channel_id"),
              CONSTRAINT "fk.customer_wishlist.customer_id" FOREIGN KEY ("customer_id") REFERENCES "customer" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT "fk.customer_wishlist.sales_channel_id" FOREIGN KEY ("sales_channel_id") REFERENCES "sales_channel" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

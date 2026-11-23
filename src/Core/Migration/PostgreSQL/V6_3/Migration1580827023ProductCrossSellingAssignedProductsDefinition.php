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
class Migration1580827023ProductCrossSellingAssignedProductsDefinition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580827023;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS "product_cross_selling_assigned_products" (
  "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL,
  "cross_selling_id" BYTEA CHECK (octet_length("cross_selling_id") = 16) NOT NULL,
  "product_id" BYTEA CHECK (octet_length("product_id") = 16) NOT NULL,
  "product_version_id" BYTEA CHECK (octet_length("product_version_id") = 16) NOT NULL,
  "position" int DEFAULT 0 NOT NULL,
  "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
  "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE DEFAULT NULL,
  PRIMARY KEY ("id"),
  KEY "fk.product_cross_selling_assigned_products.cross_selling_id" ("cross_selling_id"),
  KEY "product_id" ("product_id","product_version_id"),
  CONSTRAINT "fk.product_cross_selling_assigned_products.cross_selling_id" FOREIGN KEY ("cross_selling_id") REFERENCES "product_cross_selling" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT "product_cross_selling_assigned_products_ibfk_2" FOREIGN KEY ("product_id", "product_version_id") REFERENCES "product" ("id", "version_id") ON DELETE CASCADE ON UPDATE CASCADE
);
');
        $connection->executeStatement('ALTER TABLE "product_cross_selling"
ADD "type" varchar(255) NOT NULL;');
        $connection->executeStatement('ALTER TABLE "product_cross_selling"
CHANGE "product_stream_id" "product_stream_id" BYTEA CHECK (octet_length("product_stream_id") = 16) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

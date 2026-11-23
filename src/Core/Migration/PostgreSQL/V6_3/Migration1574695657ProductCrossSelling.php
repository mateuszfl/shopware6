<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare (strict_types=1);
namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
/**
 * @internal
 */
#[Package('framework')]
class Migration1574695657ProductCrossSelling extends MigrationStep
{
    use InheritanceUpdaterTrait;
    public function getCreationTimestamp(): int
    {
        return 1574695657;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "product_cross_selling" (
                "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL,
                "position" INT NOT NULL,
                "sort_by" VARCHAR(255) NOT NULL,
                "sort_direction" VARCHAR(255) NOT NULL,
                "active" SMALLINT NULL DEFAULT \'0\',
                "product_id" BYTEA CHECK (octet_length("product_id") = 16) NOT NULL,
                "product_version_id" BYTEA CHECK (octet_length("product_version_id") = 16) NOT NULL,
                "product_stream_id" BYTEA CHECK (octet_length("product_stream_id") = 16) NOT NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("id"),
                CONSTRAINT "fk.product_cross_selling.product_id"
                    FOREIGN KEY ("product_id","product_version_id") REFERENCES "product" ("id","version_id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_cross_selling.product_stream_id"
                    FOREIGN KEY ("product_stream_id") REFERENCES "product_stream" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "product_cross_selling_translation" (
                "product_cross_selling_id" BYTEA CHECK (octet_length("product_cross_selling_id") = 16) NOT NULL,
                "language_id" BYTEA CHECK (octet_length("language_id") = 16) NOT NULL,
                "name" VARCHAR(255) NOT NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("product_cross_selling_id","language_id"),
                CONSTRAINT "fk.product_cross_selling_translation.product_cross_selling_id"
                    FOREIGN KEY ("product_cross_selling_id") REFERENCES "product_cross_selling" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_cross_selling_translation.language_id"
                    FOREIGN KEY ("language_id") REFERENCES "language" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
        $this->updateInheritance($connection, 'product', 'crossSellings');
        $this->registerIndexer($connection, 'Swag.InheritanceIndexer');
    }
    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}

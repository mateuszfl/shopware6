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
class Migration1597830237Template extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597830237;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "app_template" (
              "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL,
              "template" LONGTEXT NOT NULL,
              "path" VARCHAR(1024) NOT NULL,
              "active" SMALLINT NOT NULL,
              "app_id" BYTEA CHECK (octet_length("app_id") = 16) NOT NULL,
              "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
              "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
              PRIMARY KEY ("id"),
              INDEX "idx.template.path" ("path"(256)),
              CONSTRAINT "fk.template.app_id" FOREIGN KEY ("app_id") REFERENCES "app" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}

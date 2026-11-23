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
class Migration1597762808Webhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597762808;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "webhook" (
                "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL,
                "name" VARCHAR(255) NOT NULL,
                "event_name" VARCHAR(500) NOT NULL,
                "url" VARCHAR(500) NOT NULL,
                "app_id" BYTEA CHECK (octet_length("app_id") = 16) NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("id"),
                CONSTRAINT "fk.webhook.app_id" FOREIGN KEY ("app_id") REFERENCES "app" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "uniq.webhook.name" UNIQUE ("name", "app_id")
            );
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}

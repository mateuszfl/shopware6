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
class Migration1599806595AddEventActionSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599806595;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "event_action_sales_channel" (
              "event_action_id" BYTEA CHECK (octet_length("event_action_id") = 16) NOT NULL,
              "sales_channel_id" BYTEA CHECK (octet_length("sales_channel_id") = 16) NOT NULL,
              PRIMARY KEY ("event_action_id","sales_channel_id"),
              KEY "sales_channel_id" ("sales_channel_id"),
              CONSTRAINT "fk.event_action_sales_channel.event_action_id" FOREIGN KEY ("event_action_id") REFERENCES "event_action" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT "fk.event_action_sales_channel.sales_channel_id" FOREIGN KEY ("sales_channel_id") REFERENCES "sales_channel" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

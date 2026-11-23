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
class Migration1597930227CustomerGroupRegistrationSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597930227;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS "customer_group_registration_sales_channels" (
    "customer_group_id" BYTEA CHECK (octet_length("customer_group_id") = 16) NOT NULL,
    "sales_channel_id" BYTEA CHECK (octet_length("sales_channel_id") = 16) NOT NULL,
    "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY ("customer_group_id","sales_channel_id"),
    KEY "fk.customer_group_registration_sales_channels.customer_group_id" ("customer_group_id"),
    KEY "fk.customer_group_registration_sales_channels.sales_channel_id" ("sales_channel_id"),
    CONSTRAINT "fk.customer_group_registration_sales_channels.customer_group_id" FOREIGN KEY ("customer_group_id") REFERENCES "customer_group" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "fk.customer_group_registration_sales_channels.sales_channel_id" FOREIGN KEY ("sales_channel_id") REFERENCES "sales_channel" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

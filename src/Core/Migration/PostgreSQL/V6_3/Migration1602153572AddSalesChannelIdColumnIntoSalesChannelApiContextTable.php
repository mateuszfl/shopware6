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
class Migration1602153572AddSalesChannelIdColumnIntoSalesChannelApiContextTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1602153572;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE "sales_channel_api_context" DROP CONSTRAINT "fk.sales_channel_api_context.customer_id";');
        $connection->executeStatement('ALTER TABLE "sales_channel_api_context" DROP CONSTRAINT IF EXISTS "sales_channel_api_context_customer_id_key";');
        $connection->executeStatement('ALTER TABLE "sales_channel_api_context" ADD "sales_channel_id" BYTEA CHECK (octet_length("sales_channel_id") = 16) NULL DEFAULT NULL;');
        $connection->executeStatement('
            ALTER TABLE "sales_channel_api_context"
            ADD CONSTRAINT "fk.sales_channel_api_context.sales_channel_id"
            FOREIGN KEY ("sales_channel_id")
            REFERENCES "sales_channel" ("id") ON DELETE CASCADE,
            ADD CONSTRAINT "fk.sales_channel_api_context.customer_id"
            FOREIGN KEY ("customer_id")
            REFERENCES "customer" ("id") ON DELETE CASCADE;
        ');
        $connection->executeStatement('ALTER TABLE "sales_channel_api_context" ADD CONSTRAINT "uniq.sales_channel_api_context.sales_channel_id_customer_id" UNIQUE ("sales_channel_id", "customer_id");');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

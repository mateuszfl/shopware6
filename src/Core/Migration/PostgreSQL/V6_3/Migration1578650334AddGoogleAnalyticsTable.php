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
class Migration1578650334AddGoogleAnalyticsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578650334;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
                    CREATE TABLE IF NOT EXISTS "sales_channel_analytics" (
                        "id" BYTEA CHECK (octet_length("id") = 16)  NOT NULL,
                        "tracking_id" VARCHAR(50) COLLATE "sw_type_ci" NOT NULL,
                        "active" SMALLINT NOT NULL DEFAULT '0',
                        "track_orders" SMALLINT NOT NULL DEFAULT '0',
                        "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                        "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                        PRIMARY KEY ("id")
                    );
        SQL);
        $connection->executeStatement(<<<'SQL'
                    ALTER TABLE "sales_channel"
                    ADD "analytics_id" binary(16) NULL,
                    ADD CONSTRAINT "fk.sales_channel.analytics_id" FOREIGN KEY ("analytics_id") REFERENCES "sales_channel_analytics" ("id") ON DELETE CASCADE ON UPDATE CASCADE
        SQL);
    }
    public function updateDestructive(Connection $connection): void
    {
    }
}

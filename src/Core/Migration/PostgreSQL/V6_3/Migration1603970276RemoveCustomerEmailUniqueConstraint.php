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
class Migration1603970276RemoveCustomerEmailUniqueConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1603970276;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE "customer" DROP CONSTRAINT IF EXISTS "uniq.customer.email_bound_sales_channel_id";');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

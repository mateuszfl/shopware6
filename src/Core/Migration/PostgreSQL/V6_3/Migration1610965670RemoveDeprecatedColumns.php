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
class Migration1610965670RemoveDeprecatedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610965670;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropDecimalPrecisionColumn($connection);
        $this->dropPurchasePrice($connection);
        $this->dropCustomFields($connection);
    }

    private function dropDecimalPrecisionColumn(Connection $connection): void
    {
        $this->removeTrigger($connection, 'currency_cash_rounding_insert');
        $this->removeTrigger($connection, 'currency_cash_rounding_update');
        $connection->executeStatement('ALTER TABLE IF EXISTS "currency" DROP COLUMN IF EXISTS "decimal_precision"');
    }

    private function dropPurchasePrice(Connection $connection): void
    {
        $this->removeTrigger($connection, 'product_purchase_prices_insert');
        $this->removeTrigger($connection, 'product_purchase_prices_update');
        $this->dropTriggerFunctionIfExists($connection, 'sync_product_purchase_prices');
        $connection->executeStatement('ALTER TABLE IF EXISTS "product" DROP COLUMN IF EXISTS "purchase_price"');
    }

    private function dropCustomFields(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE IF EXISTS "customer_wishlist_product" DROP COLUMN IF EXISTS "custom_fields"');
    }
}

<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1607415095PromotionRedemptionsNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607415095;
    }

    public function update(Connection $connection): void
    {
        $sql = str_replace(['#table#'], [PromotionDefinition::ENTITY_NAME], 'ALTER TABLE "#table#"
                ALTER COLUMN "max_redemptions_global" TYPE INTEGER, ALTER COLUMN "max_redemptions_global" DROP NOT NULL,
                ALTER COLUMN "max_redemptions_per_customer" TYPE INTEGER, ALTER COLUMN "max_redemptions_per_customer" DROP NOT NULL;');
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

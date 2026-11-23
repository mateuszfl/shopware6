<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1595480600RemoveGoogleShoppingSalesChannel extends MigrationStep
{
    final public const SALES_CHANNEL_TYPE_GOOGLE_SHOPPING = 'eda0a7980ee745fbbb7e58202dcdc04f';

    public function getCreationTimestamp(): int
    {
        return 1595480600;
    }

    public function update(Connection $connection): void
    {
        $googleShopping = Uuid::fromHexToBytes(self::SALES_CHANNEL_TYPE_GOOGLE_SHOPPING);
        $connection->delete('sales_channel_type_translation', ['sales_channel_type_id' => $googleShopping], ['sales_channel_type_id' => ParameterType::BINARY]);
        $connection->delete('sales_channel_type', ['id' => $googleShopping], ['id' => ParameterType::BINARY]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

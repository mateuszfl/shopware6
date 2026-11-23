<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1599112309AddListingFilterSystemConfigOption extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599112309;
    }

    public function update(Connection $connection): void
    {
        $value = $this->isInstallation() ? 'true' : 'false';
        $connection->insert('system_config', ['id' => Uuid::randomBytes(), 'configuration_key' => 'core.listing.disableEmptyFilterOptions', 'configuration_value' => '{"_value": ' . $value . '}', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)], ['id' => ParameterType::BINARY]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

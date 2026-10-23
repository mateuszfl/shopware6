<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\LegacySource;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * An audited portable synthetic migration.
 *
 * @internal
 */
#[Package('framework')]
class Migration1000000000Legacy extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000000;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('migration_contract_event', ['step' => 'legacy', 'engine' => 'portable']);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->insert('migration_contract_event', ['step' => 'legacy-destructive', 'engine' => 'portable']);
    }
}

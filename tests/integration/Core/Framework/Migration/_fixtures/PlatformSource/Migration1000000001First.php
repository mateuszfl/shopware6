<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1000000001First extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('migration_contract_event', ['step' => 'first', 'engine' => 'mysql']);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->insert('migration_contract_event', ['step' => 'first-destructive', 'engine' => 'mysql']);
    }
}

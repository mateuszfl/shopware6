<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Migration1000000001FirstPg extends Migration1000000001First
{
    public function update(Connection $connection): void
    {
        $connection->insert('migration_contract_event', ['step' => 'first', 'engine' => 'postgresql']);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->insert('migration_contract_event', ['step' => 'first-destructive', 'engine' => 'postgresql']);
    }
}

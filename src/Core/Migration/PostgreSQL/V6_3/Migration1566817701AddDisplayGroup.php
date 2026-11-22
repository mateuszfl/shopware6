<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1566817701AddDisplayGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1566817701;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product', 'display_group', 'VARCHAR(50) COLLATE sw_type_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product', 'display_in_listing');
    }
}

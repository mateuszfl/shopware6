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
class Migration1574672450RemoteAddressIntoCustomerAndOrderCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574672450;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'customer', 'remote_address', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'order_customer', 'remote_address', 'VARCHAR(255) COLLATE sw_type_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

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
class Migration1563949275AddCompanyToOrderCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563949275;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_customer', 'company', 'VARCHAR(255) COLLATE sw_type_ci NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

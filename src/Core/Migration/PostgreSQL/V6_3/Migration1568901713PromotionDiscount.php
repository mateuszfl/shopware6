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
class Migration1568901713PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568901713;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'promotion_discount', 'sorter_key', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'promotion_discount', 'applier_key', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'promotion_discount', 'usage_key', 'VARCHAR(255) COLLATE sw_type_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

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
class Migration1572957455AddAffiliateTrackingColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572957455;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'customer', 'affiliate_code', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'customer', 'campaign_code', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'order', 'affiliate_code', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'order', 'campaign_code', 'VARCHAR(255) COLLATE sw_type_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

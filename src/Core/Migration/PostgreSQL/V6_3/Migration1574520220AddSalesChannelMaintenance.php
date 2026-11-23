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
class Migration1574520220AddSalesChannelMaintenance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574520220;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel', 'maintenance', 'SMALLINT', false, '0');
        $connection->executeStatement(<<<'SQL'
            DO $$ BEGIN
                ALTER TABLE sales_channel ADD CONSTRAINT chk_sw_sales_channel_maintenance_bool
                    CHECK (maintenance IN (0, 1));
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$
            SQL);
        $this->addColumn($connection, 'sales_channel', 'maintenance_ip_whitelist', 'JSONB');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

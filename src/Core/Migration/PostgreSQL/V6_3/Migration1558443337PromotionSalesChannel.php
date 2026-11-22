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
class Migration1558443337PromotionSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558443337;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'promotion_sales_channel', 'fk.promotion_sales_channel.promotion_id');
        $this->dropForeignKeyIfExists($connection, 'promotion_sales_channel', 'fk.promotion_sales_channel.sales_channel_id');

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE promotion_sales_channel
                ADD CONSTRAINT "fk.promotion_sales_channel.promotion_id"
                    FOREIGN KEY (promotion_id) REFERENCES promotion (id) ON DELETE CASCADE ON UPDATE CASCADE,
                ADD CONSTRAINT "fk.promotion_sales_channel.sales_channel_id"
                    FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE
            SQL);
    }
}

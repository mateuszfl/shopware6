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
class Migration1562684474AddDeliveryTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562684474;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'product',
            'delivery_time_id',
            'BYTEA CONSTRAINT chk_sw_product_delivery_time_id_uuid CHECK (octet_length(delivery_time_id) = 16)',
        );
        $this->addColumn(
            $connection,
            'product',
            'deliveryTime',
            'BYTEA CONSTRAINT chk_sw_product_deliveryTime_uuid CHECK (octet_length("deliveryTime") = 16)',
        );

        $connection->executeStatement('ALTER TABLE product DROP COLUMN IF EXISTS min_delivery_time, DROP COLUMN IF EXISTS max_delivery_time');
        $connection->executeStatement('UPDATE product SET delivery_time_id = (SELECT id FROM delivery_time LIMIT 1) WHERE parent_id IS NULL AND delivery_time_id IS NULL');
    }
}

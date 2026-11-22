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
class Migration1562579120ProductAvailableFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562579120;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE product ALTER COLUMN stock SET NOT NULL');

        $this->addColumn($connection, 'product', 'available_stock', 'INTEGER');

        $this->addColumn(
            $connection,
            'product',
            'available',
            'SMALLINT CONSTRAINT chk_sw_product_available_bool CHECK (available IN (0, 1))',
            nullable: false,
            default: '1',
        );
    }
}

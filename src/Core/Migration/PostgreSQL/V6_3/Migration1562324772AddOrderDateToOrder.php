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
class Migration1562324772AddOrderDateToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562324772;
    }

    public function update(Connection $connection): void
    {
        if (!$this->hasOrderColumn($connection, 'order_date_time')) {
            $connection->executeStatement('ALTER TABLE "order" RENAME COLUMN order_date TO order_date_time');
        }

        if (!$this->hasOrderColumn($connection, 'order_date')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE "order"
                ADD COLUMN order_date DATE GENERATED ALWAYS AS (order_date_time::date) STORED
                SQL);
        }
    }

    private function hasOrderColumn(Connection $connection, string $column): bool
    {
        return $connection->fetchOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
            ['order', $column],
        ) !== false;
    }
}

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
class Migration1558105657CurrencyPrices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558105657;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'product_price', 'fk.product_price.currency_id');
        $this->dropIndexIfExists($connection, 'product_price', 'product_price__fk.product_price.currency_id');
        $this->dropColumnIfExists($connection, 'product_price', 'currency_id');
    }
}

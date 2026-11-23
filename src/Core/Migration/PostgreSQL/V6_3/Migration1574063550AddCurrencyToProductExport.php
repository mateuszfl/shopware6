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
class Migration1574063550AddCurrencyToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574063550;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_export', 'currency_id', 'BYTEA');
        $connection->executeStatement('UPDATE product_export SET currency_id = decode(repeat(\'00\', 16), \'hex\') WHERE currency_id IS NULL');
        $connection->executeStatement('ALTER TABLE product_export ALTER COLUMN currency_id SET NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

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
class Migration1571210820AddPaymentMethodIdsToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571210820;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel', 'payment_method_ids', 'JSON');

        $constraintExists = $connection->fetchOne(
            'SELECT 1 FROM pg_constraint WHERE conname = :name AND conrelid = to_regclass(:table)',
            ['name' => 'json.sales_channel.payment_method_ids', 'table' => 'sales_channel']
        );
        if ($constraintExists === false) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE sales_channel
                ADD CONSTRAINT "json.sales_channel.payment_method_ids"
                CHECK (payment_method_ids IS NULL OR jsonb_typeof(payment_method_ids::jsonb) IS NOT NULL)
                SQL);
        }

        $this->registerIndexer($connection, 'sales_channel.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

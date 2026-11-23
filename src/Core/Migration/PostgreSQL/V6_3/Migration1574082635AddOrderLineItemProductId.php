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
class Migration1574082635AddOrderLineItemProductId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574082635;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_line_item', 'product_id', 'BYTEA');
        $this->addColumn($connection, 'order_line_item', 'product_version_id', 'BYTEA');

        foreach (['product_id', 'product_version_id'] as $column) {
            $constraint = 'chk_sw_order_line_item_' . $column . '_uuid';
            $quotedConstraint = $connection->getDatabasePlatform()->quoteSingleIdentifier($constraint);
            $quotedColumn = $connection->getDatabasePlatform()->quoteSingleIdentifier($column);
            $connection->executeStatement('DO $$ BEGIN ALTER TABLE order_line_item ADD CONSTRAINT ' . $quotedConstraint . ' CHECK (' . $quotedColumn . ' IS NULL OR octet_length(' . $quotedColumn . ') = 16); EXCEPTION WHEN duplicate_object THEN NULL; END $$');
        }

        $connection->executeStatement(<<<'SQL'
            UPDATE order_line_item
            SET product_id = decode(referenced_id, 'hex')
            WHERE type = 'product'
                AND product_id IS NULL
                AND referenced_id COLLATE "C" ~ '^[0-9A-Fa-f]{32}$'
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "fk.order_line_item.product" ON order_line_item (product_id, product_version_id)');
        $constraintExists = $connection->fetchOne(
            'SELECT 1 FROM pg_constraint WHERE conname = :name AND conrelid = to_regclass(:table)',
            ['name' => 'fk.order_line_item.product', 'table' => 'order_line_item'],
        );
        if ($constraintExists === false) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE order_line_item
                ADD CONSTRAINT "fk.order_line_item.product"
                FOREIGN KEY (product_id, product_version_id) REFERENCES product (id, version_id)
                ON DELETE SET NULL ON UPDATE CASCADE
                SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

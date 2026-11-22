<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1563805586AddLanguageToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563805586;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'order',
            'language_id',
            'BYTEA CONSTRAINT chk_sw_order_language_id_uuid CHECK (octet_length(language_id) = 16)',
        );

        $connection->executeStatement(<<<'SQL'
            UPDATE "order"
            SET language_id = COALESCE(
                (
                    SELECT customer.language_id
                    FROM customer
                    INNER JOIN order_customer ON customer.id = order_customer.customer_id
                    WHERE order_customer.order_id = "order".id
                    LIMIT 1
                ),
                (
                    SELECT language.id
                    FROM language
                    INNER JOIN sales_channel_language ON language.id = sales_channel_language.language_id
                    WHERE sales_channel_language.sales_channel_id = "order".sales_channel_id
                    LIMIT 1
                )
            )
            WHERE language_id IS NULL
            SQL);

        $connection->executeStatement('ALTER TABLE "order" ALTER COLUMN language_id SET NOT NULL');

        if (!TableHelper::foreignKeyExists($connection, 'order', 'fk.language_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE "order"
                ADD CONSTRAINT "fk.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE RESTRICT ON UPDATE CASCADE
                SQL);
        }

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order__fk.language_id" ON "order" (language_id)');
    }
}

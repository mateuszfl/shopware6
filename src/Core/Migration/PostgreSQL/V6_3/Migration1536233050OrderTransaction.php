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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536233050OrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233050;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_transaction (
                id BYTEA NOT NULL CONSTRAINT chk_sw_order_transaction_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_transaction_version_id_uuid CHECK (octet_length(version_id) = 16),
                order_id BYTEA NOT NULL CONSTRAINT chk_sw_order_transaction_order_id_uuid CHECK (octet_length(order_id) = 16),
                order_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_transaction_order_version_id_uuid CHECK (octet_length(order_version_id) = 16),
                state_id BYTEA NOT NULL CONSTRAINT chk_sw_order_transaction_state_id_uuid CHECK (octet_length(state_id) = 16),
                payment_method_id BYTEA NOT NULL CONSTRAINT chk_sw_order_transaction_payment_method_id_uuid CHECK (octet_length(payment_method_id) = 16),
                amount JSONB NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.order_transaction.order_id" FOREIGN KEY (order_id, order_version_id)
                    REFERENCES "order" (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_transaction.state_id" FOREIGN KEY (state_id)
                    REFERENCES state_machine_state (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.order_transaction.payment_method_id" FOREIGN KEY (payment_method_id)
                    REFERENCES payment_method (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_transaction__idx.state_index" ON order_transaction (state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_transaction__fk.order_transaction.order_id" ON order_transaction (order_id, order_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_transaction__fk.order_transaction.payment_method_id" ON order_transaction (payment_method_id)');
    }
}

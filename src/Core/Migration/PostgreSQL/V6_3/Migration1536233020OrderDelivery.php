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
class Migration1536233020OrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233020;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_delivery (
                id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_version_id_uuid CHECK (octet_length(version_id) = 16),
                order_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_order_id_uuid CHECK (octet_length(order_id) = 16),
                order_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_order_version_id_uuid CHECK (octet_length(order_version_id) = 16),
                state_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_state_id_uuid CHECK (octet_length(state_id) = 16),
                shipping_order_address_id BYTEA NULL CONSTRAINT chk_sw_order_delivery_shipping_order_address_id_uuid CHECK (octet_length(shipping_order_address_id) = 16),
                shipping_order_address_version_id BYTEA NULL CONSTRAINT chk_sw_order_delivery_shipping_order_address_version_id_uuid CHECK (octet_length(shipping_order_address_version_id) = 16),
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                tracking_code VARCHAR(200) COLLATE sw_type_ci NULL,
                shipping_date_earliest DATE NOT NULL,
                shipping_date_latest DATE NOT NULL,
                shipping_costs JSONB NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.order_delivery.order_id" FOREIGN KEY (order_id, order_version_id)
                    REFERENCES "order" (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_delivery.shipping_order_address_id" FOREIGN KEY (shipping_order_address_id, shipping_order_address_version_id)
                    REFERENCES order_address (id, version_id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT "fk.order_delivery.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_delivery__idx.state_index" ON order_delivery (state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_delivery__fk.order_delivery.order_id" ON order_delivery (order_id, order_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_delivery__fk.order_delivery.shipping_order_address_id" ON order_delivery (shipping_order_address_id, shipping_order_address_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_delivery__fk.order_delivery.shipping_method_id" ON order_delivery (shipping_method_id)');
    }
}

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
class Migration1536233040OrderDeliveryPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233040;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_delivery_position (
                id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_position_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_position_version_id_uuid CHECK (octet_length(version_id) = 16),
                order_delivery_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_position_order_delivery_id_uuid CHECK (octet_length(order_delivery_id) = 16),
                order_delivery_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_position_order_delivery_version_id_uuid CHECK (octet_length(order_delivery_version_id) = 16),
                order_line_item_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_position_order_line_item_id_uuid CHECK (octet_length(order_line_item_id) = 16),
                order_line_item_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_delivery_position_order_line_item_version_id_uuid CHECK (octet_length(order_line_item_version_id) = 16),
                price JSONB NOT NULL,
                total_price INTEGER
                GENERATED ALWAYS AS (COALESCE(price ->> 'totalPrice', (price -> 'totalPrice')::text)::numeric::integer) VIRTUAL,
                unit_price INTEGER
                GENERATED ALWAYS AS (COALESCE(price ->> 'unitPrice', (price -> 'unitPrice')::text)::numeric::integer) VIRTUAL,
                quantity INTEGER
                GENERATED ALWAYS AS (COALESCE(price ->> 'quantity', (price -> 'quantity')::text)::numeric::integer) VIRTUAL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.order_delivery_position.order_delivery_id" FOREIGN KEY (order_delivery_id, order_delivery_version_id)
                    REFERENCES order_delivery (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_delivery_position.order_line_item_id" FOREIGN KEY (order_line_item_id, order_line_item_version_id)
                    REFERENCES order_line_item (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_delivery_position__fk.order_delivery_pos_8dc54ed72f4b256b" ON order_delivery_position (order_delivery_id, order_delivery_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_delivery_position__fk.order_delivery_pos_cb49c2e318a66407" ON order_delivery_position (order_line_item_id, order_line_item_version_id)');
    }
}

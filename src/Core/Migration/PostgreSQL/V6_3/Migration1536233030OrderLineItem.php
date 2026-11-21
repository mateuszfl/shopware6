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
class Migration1536233030OrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233030;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_line_item (
                id BYTEA NOT NULL CONSTRAINT chk_sw_order_line_item_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_line_item_version_id_uuid CHECK (octet_length(version_id) = 16),
                order_id BYTEA NOT NULL CONSTRAINT chk_sw_order_line_item_order_id_uuid CHECK (octet_length(order_id) = 16),
                order_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_line_item_order_version_id_uuid CHECK (octet_length(order_version_id) = 16),
                parent_id BYTEA NULL CONSTRAINT chk_sw_order_line_item_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                parent_version_id BYTEA NULL CONSTRAINT chk_sw_order_line_item_parent_version_id_uuid CHECK (octet_length(parent_version_id) = 16),
                identifier VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                referenced_id VARCHAR(255) COLLATE sw_type_ci NULL,
                label VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                description TEXT COLLATE sw_type_ci,
                cover_id BYTEA NULL CONSTRAINT chk_sw_order_line_item_cover_id_uuid CHECK (octet_length(cover_id) = 16),
                quantity INTEGER NOT NULL,
                unit_price DOUBLE PRECISION
                GENERATED ALWAYS AS (COALESCE(price ->> 'unitPrice', (price -> 'unitPrice')::text)::double precision) VIRTUAL
                    CHECK (unit_price > '-Infinity'::double precision AND unit_price < 'Infinity'::double precision),
                total_price DOUBLE PRECISION
                GENERATED ALWAYS AS (COALESCE(price ->> 'totalPrice', (price -> 'totalPrice')::text)::double precision) VIRTUAL
                    CHECK (total_price > '-Infinity'::double precision AND total_price < 'Infinity'::double precision),
                type VARCHAR(255) COLLATE sw_type_ci NULL,
                payload JSONB NULL,
                price_definition JSONB NULL,
                price JSONB NOT NULL,
                stackable SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_order_line_item_stackable_bool CHECK (stackable IN (0, 1)),
                removable SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_order_line_item_removable_bool CHECK (removable IN (0, 1)),
                good SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_order_line_item_good_bool CHECK (good IN (0, 1)),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.order_line_item.order_id" FOREIGN KEY (order_id, order_version_id)
                    REFERENCES "order" (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_line_item.parent_id" FOREIGN KEY (parent_id, parent_version_id)
                    REFERENCES order_line_item (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_line_item.cover_id" FOREIGN KEY (cover_id)
                    REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_line_item__fk.order_line_item.order_id" ON order_line_item (order_id, order_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_line_item__fk.order_line_item.parent_id" ON order_line_item (parent_id, parent_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_line_item__fk.order_line_item.cover_id" ON order_line_item (cover_id)');
    }
}

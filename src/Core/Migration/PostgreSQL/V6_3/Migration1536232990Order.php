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
class Migration1536232990Order extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232990;
    }

    public function update(Connection $connection): void
    {
        // Shift the signed sequence into the full unsigned range, starting at 1.
        $connection->executeStatement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS order_auto_increment_seq AS BIGINT
                MINVALUE -9223372036854775808 MAXVALUE 9223372036854775806
                START WITH -9223372036854775808
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS "order" (
                id BYTEA NOT NULL CONSTRAINT chk_sw_order_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_version_id_uuid CHECK (octet_length(version_id) = 16),
                state_id BYTEA NOT NULL CONSTRAINT chk_sw_order_state_id_uuid CHECK (octet_length(state_id) = 16),
                auto_increment NUMERIC(20, 0) DEFAULT (nextval('order_auto_increment_seq')::numeric + 9223372036854775809) NOT NULL
                CONSTRAINT chk_sw_order_auto_increment_unsigned CHECK (auto_increment BETWEEN 0 AND 18446744073709551615),
                order_number VARCHAR(64) COLLATE sw_type_ci,
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_order_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                currency_factor DOUBLE PRECISION NULL,
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_order_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                billing_address_id BYTEA NOT NULL CONSTRAINT chk_sw_order_billing_address_id_uuid CHECK (octet_length(billing_address_id) = 16),
                billing_address_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_billing_address_version_id_uuid CHECK (octet_length(billing_address_version_id) = 16),
                price JSONB NOT NULL,
                order_date TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                amount_total DOUBLE PRECISION
                GENERATED ALWAYS AS (COALESCE(price ->> 'totalPrice', (price -> 'totalPrice')::text)::double precision) VIRTUAL
                    CHECK (amount_total > '-Infinity'::double precision AND amount_total < 'Infinity'::double precision),
                amount_net DOUBLE PRECISION
                GENERATED ALWAYS AS (COALESCE(price ->> 'netPrice', (price -> 'netPrice')::text)::double precision) VIRTUAL
                    CHECK (amount_net > '-Infinity'::double precision AND amount_net < 'Infinity'::double precision),
                position_price DOUBLE PRECISION
                GENERATED ALWAYS AS (COALESCE(price ->> 'positionPrice', (price -> 'positionPrice')::text)::double precision) VIRTUAL
                    CHECK (position_price > '-Infinity'::double precision AND position_price < 'Infinity'::double precision),
                tax_status VARCHAR(255) COLLATE sw_type_ci
                GENERATED ALWAYS AS (COALESCE(price ->> 'taxStatus', (price -> 'taxStatus')::text)) VIRTUAL,
                shipping_costs JSONB NOT NULL,
                shipping_total DOUBLE PRECISION
                GENERATED ALWAYS AS (COALESCE(shipping_costs ->> 'totalPrice', (shipping_costs -> 'totalPrice')::text)::double precision) VIRTUAL
                    CHECK (shipping_total > '-Infinity'::double precision AND shipping_total < 'Infinity'::double precision),
                deep_link_code VARCHAR(32) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "order__order__uniq.auto_increment" UNIQUE (auto_increment),
                CONSTRAINT "order__order__uniq.deep_link_code" UNIQUE (deep_link_code, version_id),
                CONSTRAINT "char_length.order.deep_link_code" CHECK (CHAR_LENGTH(deep_link_code) = 32),
                CONSTRAINT "fk.order.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.order.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('ALTER SEQUENCE order_auto_increment_seq OWNED BY "order".auto_increment');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order__idx.state_index" ON "order" (state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order__fk.order.currency_id" ON "order" (currency_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order__fk.order.sales_channel_id" ON "order" (sales_channel_id)');
    }
}

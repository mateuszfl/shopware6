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
class Migration1536232980Cart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232980;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cart (
                token VARCHAR(50) COLLATE sw_type_ci NOT NULL,
                name VARCHAR(500) COLLATE sw_type_ci NOT NULL,
                cart TEXT COLLATE sw_type_ci NOT NULL,
                price REAL NOT NULL,
                line_item_count VARCHAR(42) COLLATE sw_type_ci NOT NULL,
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_cart_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_cart_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                payment_method_id BYTEA NOT NULL CONSTRAINT chk_sw_cart_payment_method_id_uuid CHECK (octet_length(payment_method_id) = 16),
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_cart_country_id_uuid CHECK (octet_length(country_id) = 16),
                customer_id BYTEA NULL CONSTRAINT chk_sw_cart_customer_id_uuid CHECK (octet_length(customer_id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_cart_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (token),
                CONSTRAINT "fk.cart.country_id" FOREIGN KEY (country_id)
                    REFERENCES country (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cart.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cart.customer_id" FOREIGN KEY (customer_id)
                    REFERENCES customer (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cart.payment_method_id" FOREIGN KEY (payment_method_id)
                    REFERENCES payment_method (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cart.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.cart.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cart__fk.cart.country_id" ON cart (country_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cart__fk.cart.currency_id" ON cart (currency_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cart__fk.cart.customer_id" ON cart (customer_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cart__fk.cart.payment_method_id" ON cart (payment_method_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cart__fk.cart.shipping_method_id" ON cart (shipping_method_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cart__fk.cart.sales_channel_id" ON cart (sales_channel_id)');
    }
}

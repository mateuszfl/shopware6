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
class Migration1536232960Customer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232960;
    }

    public function update(Connection $connection): void
    {
        // Shift the signed sequence into the full unsigned range, starting at 1.
        $connection->executeStatement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS customer_auto_increment_seq AS BIGINT
                MINVALUE -9223372036854775808 MAXVALUE 9223372036854775806
                START WITH -9223372036854775808
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customer (
                id BYTEA NOT NULL CONSTRAINT chk_sw_customer_id_uuid CHECK (octet_length(id) = 16),
                auto_increment NUMERIC(20, 0) DEFAULT (nextval('customer_auto_increment_seq')::numeric + 9223372036854775809) NOT NULL
                    CONSTRAINT chk_sw_customer_auto_increment_unsigned CHECK (auto_increment BETWEEN 0 AND 18446744073709551615),
                customer_group_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_customer_group_id_uuid CHECK (octet_length(customer_group_id) = 16),
                default_payment_method_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_default_payment_method_id_uuid CHECK (octet_length(default_payment_method_id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_language_id_uuid CHECK (octet_length(language_id) = 16),
                last_payment_method_id BYTEA NULL CONSTRAINT chk_sw_customer_last_payment_method_id_uuid CHECK (octet_length(last_payment_method_id) = 16),
                default_billing_address_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_default_billing_address_id_uuid CHECK (octet_length(default_billing_address_id) = 16),
                default_shipping_address_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_default_shipping_address_id_uuid CHECK (octet_length(default_shipping_address_id) = 16),
                customer_number VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                salutation_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_salutation_id_uuid CHECK (octet_length(salutation_id) = 16),
                first_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                last_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                company VARCHAR(255) COLLATE sw_type_ci NULL,
                password VARCHAR(1024) COLLATE sw_type_ci NULL,
                legacy_password VARCHAR(255) COLLATE sw_type_ci NULL,
                legacy_encoder VARCHAR(255) COLLATE sw_type_ci NULL,
                email VARCHAR(254) COLLATE sw_type_ci NOT NULL,
                title VARCHAR(100) COLLATE sw_type_ci NULL,
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_customer_active_bool CHECK (active IN (0, 1)),
                guest SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_customer_guest_bool CHECK (guest IN (0, 1)),
                first_login DATE NULL,
                last_login TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                newsletter SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_customer_newsletter_bool CHECK (newsletter IN (0, 1)),
                birthday DATE NULL,
                last_order_date TIMESTAMP(3) WITHOUT TIME ZONE,
                order_count INTEGER NOT NULL DEFAULT 0,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "customer__customer__uniq.auto_increment" UNIQUE (auto_increment),
                CONSTRAINT "fk.customer.customer_group_id" FOREIGN KEY (customer_group_id)
                    REFERENCES customer_group (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.customer.default_payment_method_id" FOREIGN KEY (default_payment_method_id)
                    REFERENCES payment_method (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.customer.last_payment_method_id" FOREIGN KEY (last_payment_method_id)
                    REFERENCES payment_method (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.customer.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.customer.salutation_id" FOREIGN KEY (salutation_id)
                    REFERENCES salutation (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('ALTER SEQUENCE customer_auto_increment_seq OWNED BY customer.auto_increment');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__idx.firstlogin" ON customer (first_login)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__idx.lastlogin" ON customer (last_login)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__idx.customer.default_billing_address_id" ON customer (default_billing_address_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__idx.customer.default_shipping_address_id" ON customer (default_shipping_address_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__fk.customer.customer_group_id" ON customer (customer_group_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__fk.customer.default_payment_method_id" ON customer (default_payment_method_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__fk.customer.last_payment_method_id" ON customer (last_payment_method_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__fk.customer.sales_channel_id" ON customer (sales_channel_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer__fk.customer.salutation_id" ON customer (salutation_id)');
    }
}

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
class Migration1536232940SalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232940;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel (
                id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_id_uuid CHECK (octet_length(id) = 16),
                type_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_type_id_uuid CHECK (octet_length(type_id) = 16),
                short_name VARCHAR(45) COLLATE sw_type_ci NULL,
                configuration JSONB NULL,
                access_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_language_id_uuid CHECK (octet_length(language_id) = 16),
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                payment_method_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_payment_method_id_uuid CHECK (octet_length(payment_method_id) = 16),
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_country_id_uuid CHECK (octet_length(country_id) = 16),
                navigation_category_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_navigation_category_id_uuid CHECK (octet_length(navigation_category_id) = 16),
                navigation_category_version_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_navigation_category_version_id_uuid CHECK (octet_length(navigation_category_version_id) = 16),
                footer_category_id BYTEA NULL CONSTRAINT chk_sw_sales_channel_footer_category_id_uuid CHECK (octet_length(footer_category_id) = 16),
                footer_category_version_id BYTEA NULL CONSTRAINT chk_sw_sales_channel_footer_category_version_id_uuid CHECK (octet_length(footer_category_version_id) = 16),
                service_category_id BYTEA NULL CONSTRAINT chk_sw_sales_channel_service_category_id_uuid CHECK (octet_length(service_category_id) = 16),
                service_category_version_id BYTEA NULL CONSTRAINT chk_sw_sales_channel_service_category_version_id_uuid CHECK (octet_length(service_category_version_id) = 16),
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_sales_channel_active_bool CHECK (active IN (0, 1)),
                navigation_id BYTEA NULL CONSTRAINT chk_sw_sales_channel_navigation_id_uuid CHECK (octet_length(navigation_id) = 16),
                navigation_version_id BYTEA CONSTRAINT chk_sw_sales_channel_navigation_version_id_uuid CHECK (octet_length(navigation_version_id) = 16),
                customer_group_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_customer_group_id_uuid CHECK (octet_length(customer_group_id) = 16),
                mail_header_footer_id BYTEA NULL CONSTRAINT chk_sw_sales_channel_mail_header_footer_id_uuid CHECK (octet_length(mail_header_footer_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "sales_channel__sales_channel__uniq.access_key" UNIQUE (access_key),
                CONSTRAINT "fk.sales_channel.country_id" FOREIGN KEY (country_id)
                    REFERENCES country (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.payment_method_id" FOREIGN KEY (payment_method_id)
                    REFERENCES payment_method (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.type_id" FOREIGN KEY (type_id)
                    REFERENCES sales_channel_type (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.navigation_category_id" FOREIGN KEY (navigation_category_id, navigation_category_version_id)
                    REFERENCES category (id, version_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.footer_category_id" FOREIGN KEY (footer_category_id, footer_category_version_id)
                    REFERENCES category (id, version_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.service_category_id" FOREIGN KEY (service_category_id, service_category_version_id)
                    REFERENCES category (id, version_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.navigation_id" FOREIGN KEY (navigation_id, navigation_version_id)
                    REFERENCES navigation (id, version_id) ON DELETE NO ACTION ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.customer_group_id" FOREIGN KEY (customer_group_id)
                    REFERENCES customer_group (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel.id" FOREIGN KEY (mail_header_footer_id)
                    REFERENCES mail_header_footer (id) ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.country_id" ON sales_channel (country_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.currency_id" ON sales_channel (currency_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.language_id" ON sales_channel (language_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.payment_method_id" ON sales_channel (payment_method_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.shipping_method_id" ON sales_channel (shipping_method_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.type_id" ON sales_channel (type_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.navigation_category_id" ON sales_channel (navigation_category_id, navigation_category_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.footer_category_id" ON sales_channel (footer_category_id, footer_category_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.service_category_id" ON sales_channel (service_category_id, service_category_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.navigation_id" ON sales_channel (navigation_id, navigation_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.customer_group_id" ON sales_channel (customer_group_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel__fk.sales_channel.id" ON sales_channel (mail_header_footer_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_translation (
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_translation_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (sales_channel_id, language_id),
                CONSTRAINT "fk.sales_channel_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_translation.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_translation__fk.sales_channel_tr_63a792c5f47617d9" ON sales_channel_translation (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_language (
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_language_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_language_language_id_uuid CHECK (octet_length(language_id) = 16),
                PRIMARY KEY (sales_channel_id, language_id),
                CONSTRAINT "fk.sales_channel_language.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_language.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_language__fk.sales_channel_language.language_id" ON sales_channel_language (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_currency (
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_currency_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_currency_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                PRIMARY KEY (sales_channel_id, currency_id),
                CONSTRAINT "fk.sales_channel_currency.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_currency.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_currency__fk.sales_channel_currency.currency_id" ON sales_channel_currency (currency_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_country (
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_country_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_country_country_id_uuid CHECK (octet_length(country_id) = 16),
                PRIMARY KEY (sales_channel_id, country_id),
                CONSTRAINT "fk.sales_channel_country.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_country.country_id" FOREIGN KEY (country_id)
                    REFERENCES country (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_country__fk.sales_channel_country.country_id" ON sales_channel_country (country_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_shipping_method (
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_shipping_method_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_shipping_method_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                PRIMARY KEY (sales_channel_id, shipping_method_id),
                CONSTRAINT "fk.sales_channel_shipping_method.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_shipping_method.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_shipping_method__fk.sales_channe_24155c17655a5736" ON sales_channel_shipping_method (shipping_method_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_payment_method (
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_payment_method_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                payment_method_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_payment_method_payment_method_id_uuid CHECK (octet_length(payment_method_id) = 16),
                PRIMARY KEY (sales_channel_id, payment_method_id),
                CONSTRAINT "fk.sales_channel_payment_method.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_payment_method.payment_method_id" FOREIGN KEY (payment_method_id)
                    REFERENCES payment_method (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_payment_method__fk.sales_channel_3b503a05741299ab" ON sales_channel_payment_method (payment_method_id)');
    }
}

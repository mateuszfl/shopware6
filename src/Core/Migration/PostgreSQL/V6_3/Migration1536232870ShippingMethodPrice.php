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
class Migration1536232870ShippingMethodPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232870;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS shipping_method_price (
                id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_price_id_uuid CHECK (octet_length(id) = 16),
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_price_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                calculation BIGINT NULL CONSTRAINT chk_sw_shipping_method_price_calculation_unsigned CHECK (calculation >= 0 AND calculation <= '4294967295'::bigint),
                rule_id BYTEA NULL CONSTRAINT chk_sw_shipping_method_price_rule_id_uuid CHECK (octet_length(rule_id) = 16),
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_price_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                calculation_rule_id BYTEA NULL CONSTRAINT chk_sw_shipping_method_price_calculation_rule_id_uuid CHECK (octet_length(calculation_rule_id) = 16),
                price DOUBLE PRECISION NOT NULL,
                quantity_start DOUBLE PRECISION NULL,
                quantity_end DOUBLE PRECISION NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "shipping_method_price__uniq.shipping_method_quantity_start" UNIQUE (shipping_method_id, rule_id, currency_id, quantity_start),
                CONSTRAINT "fk.shipping_method_price.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method_price.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method_price.rule_id" FOREIGN KEY (rule_id)
                    REFERENCES rule (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method_price.calculation_rule_id" FOREIGN KEY (calculation_rule_id)
                    REFERENCES rule (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method_price__fk.shipping_method_price.currency_id" ON shipping_method_price (currency_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method_price__fk.shipping_method_price.rule_id" ON shipping_method_price (rule_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method_price__fk.shipping_method_pric_13f338676ef87956" ON shipping_method_price (calculation_rule_id)');
    }
}

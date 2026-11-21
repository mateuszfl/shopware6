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
class Migration1536233190ProductPriceRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233190;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_price (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_price_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_price_version_id_uuid CHECK (octet_length(version_id) = 16),
                rule_id BYTEA NOT NULL CONSTRAINT chk_sw_product_price_rule_id_uuid CHECK (octet_length(rule_id) = 16),
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_price_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_price_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_product_price_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                price JSONB NOT NULL,
                quantity_start INTEGER NOT NULL,
                quantity_end INTEGER NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.product_price.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_price.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_price.rule_id" FOREIGN KEY (rule_id)
                    REFERENCES rule (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_price__fk.product_price.product_id" ON product_price (product_id, product_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_price__fk.product_price.currency_id" ON product_price (currency_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_price__fk.product_price.rule_id" ON product_price (rule_id)');
    }
}

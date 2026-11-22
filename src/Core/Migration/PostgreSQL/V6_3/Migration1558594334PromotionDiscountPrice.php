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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1558594334PromotionDiscountPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558594334;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_discount_prices (
                id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_discount_prices_id_uuid CHECK (octet_length(id) = 16),
                discount_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_discount_prices_discount_id_uuid CHECK (octet_length(discount_id) = 16),
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_discount_prices_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                price REAL NOT NULL DEFAULT 0,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.promotion_discount_prices.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.promotion_discount_prices.discount_id" FOREIGN KEY (discount_id)
                    REFERENCES promotion_discount (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_discount_prices__fk.promotion_discount_prices.discount_id" ON promotion_discount_prices (discount_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_discount_prices__fk.promotion_discount_prices.currency_id" ON promotion_discount_prices (currency_id)');
    }
}

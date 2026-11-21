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
class Migration1536233420PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233420;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_discount (
                id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_discount_id_uuid CHECK (octet_length(id) = 16),
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_discount_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                scope VARCHAR(32) COLLATE sw_type_ci NOT NULL,
                type VARCHAR(32) COLLATE sw_type_ci NOT NULL,
                "value" DOUBLE PRECISION NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                consider_advanced_rules SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_promotion_discount_consider_advanced_rules_bool CHECK (consider_advanced_rules IN (0, 1)),
                PRIMARY KEY (id),
                CONSTRAINT "fk.promotion_discount.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE CASCADE ON UPDATE NO ACTION
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_discount__idx.promotion_discount.promotion_id" ON promotion_discount (promotion_id ASC)');
    }
}

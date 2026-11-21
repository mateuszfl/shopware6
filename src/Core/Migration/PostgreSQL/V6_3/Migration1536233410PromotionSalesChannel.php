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
class Migration1536233410PromotionSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233410;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_sales_channel (
                id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_sales_channel_id_uuid CHECK (octet_length(id) = 16),
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_sales_channel_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_sales_channel_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                priority INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.promotion_sales_channel.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
                CONSTRAINT "fk.promotion_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE NO ACTION ON UPDATE NO ACTION
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_sales_channel__idx.promotion_sales_c_93d0bd50917cf867" ON promotion_sales_channel (sales_channel_id ASC)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_sales_channel__idx.promotion_sales_c_10652f44298ceeb1" ON promotion_sales_channel (promotion_id ASC)');
    }
}

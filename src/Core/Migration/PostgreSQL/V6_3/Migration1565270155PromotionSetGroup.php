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
class Migration1565270155PromotionSetGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565270155;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_setgroup (
                id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_setgroup_id_uuid CHECK (octet_length(id) = 16),
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_setgroup_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                packager_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                sorter_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                value DOUBLE PRECISION NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.promotion_setgroup.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE CASCADE ON UPDATE NO ACTION
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_setgroup__idx.promotion_setgroup.promotion_id" ON promotion_setgroup (promotion_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

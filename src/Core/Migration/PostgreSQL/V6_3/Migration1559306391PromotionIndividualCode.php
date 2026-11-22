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
class Migration1559306391PromotionIndividualCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559306391;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_individual_code (
                id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_individual_code_id_uuid CHECK (octet_length(id) = 16),
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_individual_code_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                code VARCHAR(255) COLLATE sw_type_ci NULL,
                payload JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.promotion_individual_code.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_individual_code__idx.promotion_individual_code.promotion_id" ON promotion_individual_code (promotion_id ASC)');
        $connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS promotion_individual_code__code ON promotion_individual_code (code)');
    }
}

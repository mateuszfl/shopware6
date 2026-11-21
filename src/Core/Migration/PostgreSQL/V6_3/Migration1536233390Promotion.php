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
class Migration1536233390Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233390;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion (
                id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_id_uuid CHECK (octet_length(id) = 16),
                active SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_promotion_active_bool CHECK (active IN (0, 1)),
                valid_from TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                valid_until TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                max_redemptions_global INT NOT NULL DEFAULT 1,
                max_redemptions_per_customer INT NOT NULL DEFAULT 1,
                order_count INT NOT NULL DEFAULT 0,
                orders_per_customer_count JSONB NULL,
                exclusive SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_promotion_exclusive_bool CHECK (exclusive IN (0, 1)),
                code VARCHAR(255) COLLATE sw_type_ci NULL UNIQUE,
                use_codes SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_promotion_use_codes_bool CHECK (use_codes IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_translation (
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_translation_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (promotion_id,language_id),
                CONSTRAINT "fk.promotion_translation.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.promotion_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_translation__fk.promotion_translation.promotion_id" ON promotion_translation (promotion_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_translation__fk.promotion_translation.language_id" ON promotion_translation (language_id)');
    }
}

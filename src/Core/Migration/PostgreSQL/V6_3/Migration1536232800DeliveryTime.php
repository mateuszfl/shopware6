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
class Migration1536232800DeliveryTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS delivery_time (
                id BYTEA NOT NULL CONSTRAINT chk_sw_delivery_time_id_uuid CHECK (octet_length(id) = 16),
                min INTEGER NOT NULL,
                max INTEGER NOT NULL,
                unit VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS delivery_time_translation (
                delivery_time_id BYTEA NOT NULL CONSTRAINT chk_sw_delivery_time_translation_delivery_time_id_uuid CHECK (octet_length(delivery_time_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_delivery_time_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (delivery_time_id, language_id),
                CONSTRAINT "fk.delivery_time_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.delivery_time_translation.delivery_time_id" FOREIGN KEY (delivery_time_id)
                    REFERENCES delivery_time (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "delivery_time_translation__fk.delivery_time_tr_2fa800798225d950" ON delivery_time_translation (language_id)');
    }
}

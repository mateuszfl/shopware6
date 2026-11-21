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
class Migration1536232860ShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232860;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS shipping_method (
                id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_id_uuid CHECK (octet_length(id) = 16),
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_shipping_method_active_bool CHECK (active IN (0, 1)),
                availability_rule_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_availability_rule_id_uuid CHECK (octet_length(availability_rule_id) = 16),
                media_id BYTEA NULL CONSTRAINT chk_sw_shipping_method_media_id_uuid CHECK (octet_length(media_id) = 16),
                delivery_time_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_delivery_time_id_uuid CHECK (octet_length(delivery_time_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.shipping_method.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method.availability_rule_id" FOREIGN KEY (availability_rule_id)
                    REFERENCES rule (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method.delivery_time_id" FOREIGN KEY (delivery_time_id)
                    REFERENCES delivery_time (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method__fk.shipping_method.media_id" ON shipping_method (media_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method__fk.shipping_method.availability_rule_id" ON shipping_method (availability_rule_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method__fk.shipping_method.delivery_time_id" ON shipping_method (delivery_time_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS shipping_method_translation (
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_translation_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (shipping_method_id, language_id),
                CONSTRAINT "fk.shipping_method_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method_translation.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method_translation__fk.shipping_metho_2ad7d755ffaf1701" ON shipping_method_translation (language_id)');
    }
}

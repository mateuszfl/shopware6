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
class Migration1536233160ProductConfigurator extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233160;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_configurator_setting (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_configurator_setting_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_configurator_setting_version_id_uuid CHECK (octet_length(version_id) = 16),
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_configurator_setting_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_configurator_setting_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                property_group_option_id BYTEA NOT NULL CONSTRAINT chk_sw_product_configurator_setting_property_g_3eadaacfdea21072 CHECK (octet_length(property_group_option_id) = 16),
                price JSONB NULL,
                "position" INTEGER NOT NULL DEFAULT 0,
                media_id BYTEA NULL CONSTRAINT chk_sw_product_configurator_setting_media_id_uuid CHECK (octet_length(media_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.product_configurator_setting.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_configurator_setting.property_group_option_id" FOREIGN KEY (property_group_option_id)
                    REFERENCES property_group_option (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_configurator_setting.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE SET NULL
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_configurator_setting__fk.product_confi_910e517b75a42032" ON product_configurator_setting (product_id, product_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_configurator_setting__fk.product_confi_6c41b0a5c10002b4" ON product_configurator_setting (property_group_option_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_configurator_setting__fk.product_confi_e0ca65a9dab4b2fb" ON product_configurator_setting (media_id)');
    }
}

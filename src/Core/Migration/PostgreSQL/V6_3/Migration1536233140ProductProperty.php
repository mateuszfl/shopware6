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
class Migration1536233140ProductProperty extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233140;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_property (
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_property_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_property_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                property_group_option_id BYTEA NOT NULL CONSTRAINT chk_sw_product_property_property_group_option_id_uuid CHECK (octet_length(property_group_option_id) = 16),
                PRIMARY KEY (product_id, product_version_id, property_group_option_id),
                CONSTRAINT "fk.product_property.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_property.configuration_option_id" FOREIGN KEY (property_group_option_id)
                    REFERENCES property_group_option (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_property__fk.product_property.configuration_option_id" ON product_property (property_group_option_id)');
    }
}

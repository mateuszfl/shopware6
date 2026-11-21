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
class Migration1536233110ProductManufacturer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233110;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_manufacturer (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_manufacturer_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_manufacturer_version_id_uuid CHECK (octet_length(version_id) = 16),
                link VARCHAR(255) COLLATE sw_type_ci NULL,
                media_id BYTEA NULL CONSTRAINT chk_sw_product_manufacturer_media_id_uuid CHECK (octet_length(media_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.product_manufacturer.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_manufacturer__fk.product_manufacturer.media_id" ON product_manufacturer (media_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_manufacturer_translation (
                product_manufacturer_id BYTEA NOT NULL CONSTRAINT chk_sw_product_manufacturer_translation_produc_61baec7ff5e642e7 CHECK (octet_length(product_manufacturer_id) = 16),
                product_manufacturer_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_manufacturer_translation_produc_ab32f03ca261d8f0 CHECK (octet_length(product_manufacturer_version_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_product_manufacturer_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (product_manufacturer_id, product_manufacturer_version_id, language_id),
                CONSTRAINT "fk.product_manufacturer_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_manufacturer_translation.product_manufacturer_id" FOREIGN KEY (product_manufacturer_id, product_manufacturer_version_id)
                    REFERENCES product_manufacturer (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_manufacturer_translation__fk.product_m_b744fcd08cf04f87" ON product_manufacturer_translation (language_id)');
    }
}

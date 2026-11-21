<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536233130ProductMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233130;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_media (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_media_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_media_version_id_uuid CHECK (octet_length(version_id) = 16),
                "position" INTEGER NOT NULL DEFAULT 1,
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_media_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_media_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                media_id BYTEA NOT NULL CONSTRAINT chk_sw_product_media_media_id_uuid CHECK (octet_length(media_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.product_media.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_media.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_media__fk.product_media.media_id" ON product_media (media_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_media__fk.product_media.product_id" ON product_media (product_id, product_version_id)');

        if (!TableHelper::foreignKeyExists($connection, 'product', 'fk.product.product_media_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE product ADD CONSTRAINT "fk.product.product_media_id" FOREIGN KEY (product_media_id, product_media_version_id)
                    REFERENCES product_media (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
                SQL);
        }

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product__fk.product.product_media_id" ON product (product_media_id, product_media_version_id)');
    }
}

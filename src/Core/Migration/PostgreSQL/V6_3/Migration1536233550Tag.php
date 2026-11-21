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
class Migration1536233550Tag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233550;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tag (
                id BYTEA NOT NULL CONSTRAINT chk_sw_tag_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_tag (
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_tag_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_tag_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_product_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (product_id, product_version_id, tag_id),
                CONSTRAINT "fk.product_tag.product_version_id__product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_tag__fk.product_tag.tag_id" ON product_tag (tag_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_tag (
                media_id BYTEA NOT NULL CONSTRAINT chk_sw_media_tag_media_id_uuid CHECK (octet_length(media_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_media_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (media_id, tag_id),
                CONSTRAINT "fk.media_tag.id" FOREIGN KEY (media_id)
                    REFERENCES media (id),
                CONSTRAINT "fk.media_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media_tag__fk.media_tag.tag_id" ON media_tag (tag_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS category_tag (
                category_id BYTEA NOT NULL CONSTRAINT chk_sw_category_tag_category_id_uuid CHECK (octet_length(category_id) = 16),
                category_version_id BYTEA NOT NULL CONSTRAINT chk_sw_category_tag_category_version_id_uuid CHECK (octet_length(category_version_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_category_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (category_id, category_version_id, tag_id),
                CONSTRAINT "fk.category_tag.category_tag__category_version_id" FOREIGN KEY (category_id, category_version_id)
                    REFERENCES category (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.category_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category_tag__fk.category_tag.tag_id" ON category_tag (tag_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customer_tag (
                customer_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_tag_customer_id_uuid CHECK (octet_length(customer_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (customer_id, tag_id),
                CONSTRAINT "fk.customer_tag.customer_id" FOREIGN KEY (customer_id)
                    REFERENCES customer (id),
                CONSTRAINT "fk.customer_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer_tag__fk.customer_tag.tag_id" ON customer_tag (tag_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_tag (
                order_id BYTEA NOT NULL CONSTRAINT chk_sw_order_tag_order_id_uuid CHECK (octet_length(order_id) = 16),
                order_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_tag_order_version_id_uuid CHECK (octet_length(order_version_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_order_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (order_id, order_version_id, tag_id),
                CONSTRAINT "fk.order_tag.order_tag__order_version_id" FOREIGN KEY (order_id, order_version_id)
                    REFERENCES "order" (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_tag__fk.order_tag.tag_id" ON order_tag (tag_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS shipping_method_tag (
                shipping_method_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_tag_shipping_method_id_uuid CHECK (octet_length(shipping_method_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_shipping_method_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (shipping_method_id, tag_id),
                CONSTRAINT "fk.shipping_method_tag.shipping_method_id" FOREIGN KEY (shipping_method_id)
                    REFERENCES shipping_method (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.shipping_method_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "shipping_method_tag__fk.shipping_method_tag.tag_id" ON shipping_method_tag (tag_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS newsletter_recipient_tag (
                newsletter_recipient_id BYTEA NOT NULL CONSTRAINT chk_sw_newsletter_recipient_tag_newsletter_recipient_id_uuid CHECK (octet_length(newsletter_recipient_id) = 16),
                tag_id BYTEA NOT NULL CONSTRAINT chk_sw_newsletter_recipient_tag_tag_id_uuid CHECK (octet_length(tag_id) = 16),
                PRIMARY KEY (newsletter_recipient_id, tag_id),
                CONSTRAINT "fk.newsletter_recipient_tag.newsletter_recipient_id" FOREIGN KEY (newsletter_recipient_id)
                    REFERENCES newsletter_recipient (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.newsletter_recipient_tag.tag_id" FOREIGN KEY (tag_id)
                    REFERENCES tag (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "newsletter_recipient_tag__fk.newsletter_recipient_tag.tag_id" ON newsletter_recipient_tag (tag_id)');
    }
}

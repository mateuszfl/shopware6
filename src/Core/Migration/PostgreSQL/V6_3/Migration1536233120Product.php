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
class Migration1536233120Product extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233120;
    }

    public function update(Connection $connection): void
    {
        // Shift the signed sequence into the full unsigned range, starting at 1.
        $connection->executeStatement(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS product_auto_increment_seq AS BIGINT
                MINVALUE -9223372036854775808 MAXVALUE 9223372036854775806
                START WITH -9223372036854775808
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_version_id_uuid CHECK (octet_length(version_id) = 16),
                auto_increment NUMERIC(20, 0) DEFAULT (nextval('product_auto_increment_seq')::numeric + 9223372036854775809) NOT NULL
                CONSTRAINT chk_sw_product_auto_increment_unsigned CHECK (auto_increment BETWEEN 0 AND 18446744073709551615),
                product_number VARCHAR(64) COLLATE sw_type_ci NULL,
                active SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_product_active_bool CHECK (active IN (0, 1)),
                parent_id BYTEA NULL CONSTRAINT chk_sw_product_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                parent_version_id BYTEA NULL CONSTRAINT chk_sw_product_parent_version_id_uuid CHECK (octet_length(parent_version_id) = 16),
                tax_id BYTEA NULL CONSTRAINT chk_sw_product_tax_id_uuid CHECK (octet_length(tax_id) = 16),
                product_manufacturer_id BYTEA NULL CONSTRAINT chk_sw_product_product_manufacturer_id_uuid CHECK (octet_length(product_manufacturer_id) = 16),
                product_manufacturer_version_id BYTEA NULL CONSTRAINT chk_sw_product_product_manufacturer_version_id_uuid CHECK (octet_length(product_manufacturer_version_id) = 16),
                product_media_id BYTEA NULL CONSTRAINT chk_sw_product_product_media_id_uuid CHECK (octet_length(product_media_id) = 16),
                product_media_version_id BYTEA NULL CONSTRAINT chk_sw_product_product_media_version_id_uuid CHECK (octet_length(product_media_version_id) = 16),
                unit_id BYTEA NULL CONSTRAINT chk_sw_product_unit_id_uuid CHECK (octet_length(unit_id) = 16),
                category_tree JSONB NULL,
                option_ids JSONB NULL,
                property_ids JSONB NULL,
                tax BYTEA NULL CONSTRAINT chk_sw_product_tax_uuid CHECK (octet_length(tax) = 16),
                manufacturer BYTEA NULL CONSTRAINT chk_sw_product_manufacturer_uuid CHECK (octet_length(manufacturer) = 16),
                cover BYTEA NULL CONSTRAINT chk_sw_product_cover_uuid CHECK (octet_length(cover) = 16),
                unit BYTEA NULL CONSTRAINT chk_sw_product_unit_uuid CHECK (octet_length(unit) = 16),
                media BYTEA NULL CONSTRAINT chk_sw_product_media_uuid CHECK (octet_length(media) = 16),
                prices BYTEA NULL CONSTRAINT chk_sw_product_prices_uuid CHECK (octet_length(prices) = 16),
                visibilities BYTEA NULL CONSTRAINT chk_sw_product_visibilities_uuid CHECK (octet_length(visibilities) = 16),
                properties BYTEA NULL CONSTRAINT chk_sw_product_properties_uuid CHECK (octet_length(properties) = 16),
                categories BYTEA NULL CONSTRAINT chk_sw_product_categories_uuid CHECK (octet_length(categories) = 16),
                translations BYTEA NULL CONSTRAINT chk_sw_product_translations_uuid CHECK (octet_length(translations) = 16),
                price JSONB NULL,
                listing_prices JSONB NULL,
                manufacturer_number VARCHAR(255) COLLATE sw_type_ci NULL,
                ean VARCHAR(255) COLLATE sw_type_ci NULL,
                stock INTEGER NULL,
                min_delivery_time INTEGER NULL,
                max_delivery_time INTEGER NULL,
                restock_time INTEGER NULL,
                is_closeout SMALLINT NULL CONSTRAINT chk_sw_product_is_closeout_bool CHECK (is_closeout IN (0, 1)),
                purchase_steps BIGINT NULL CONSTRAINT chk_sw_product_purchase_steps_unsigned CHECK (purchase_steps >= 0 AND purchase_steps <= '4294967295'::bigint),
                max_purchase BIGINT NULL CONSTRAINT chk_sw_product_max_purchase_unsigned CHECK (max_purchase >= 0 AND max_purchase <= '4294967295'::bigint),
                min_purchase BIGINT NULL CONSTRAINT chk_sw_product_min_purchase_unsigned CHECK (min_purchase >= 0 AND min_purchase <= '4294967295'::bigint),
                purchase_unit NUMERIC(11, 4) NULL CONSTRAINT chk_sw_product_purchase_unit_unsigned CHECK (purchase_unit >= 0),
                reference_unit NUMERIC(10, 3) NULL CONSTRAINT chk_sw_product_reference_unit_unsigned CHECK (reference_unit >= 0),
                shipping_free SMALLINT NULL CONSTRAINT chk_sw_product_shipping_free_bool CHECK (shipping_free IN (0, 1)),
                purchase_price DOUBLE PRECISION NULL,
                mark_as_topseller SMALLINT NULL CONSTRAINT chk_sw_product_mark_as_topseller_bool CHECK (mark_as_topseller IN (0, 1)),
                weight NUMERIC(10, 3) NULL CONSTRAINT chk_sw_product_weight_unsigned CHECK (weight >= 0),
                width NUMERIC(10, 3) NULL CONSTRAINT chk_sw_product_width_unsigned CHECK (width >= 0),
                height NUMERIC(10, 3) NULL CONSTRAINT chk_sw_product_height_unsigned CHECK (height >= 0),
                length NUMERIC(10, 3) NULL CONSTRAINT chk_sw_product_length_unsigned CHECK (length >= 0),
                release_date TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                whitelist_ids JSONB NULL,
                blacklist_ids JSONB NULL,
                tag_ids JSONB NULL,
                tags BYTEA NULL CONSTRAINT chk_sw_product_tags_uuid CHECK (octet_length(tags) = 16),
                variant_restrictions JSONB NULL,
                configurator_group_sorting JSONB NULL,
                "searchKeywords" BYTEA NULL CONSTRAINT "chk_sw_product_searchKeywords_uuid" CHECK (octet_length("searchKeywords") = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "product__uniq.product.product_number__version_id" UNIQUE (product_number, version_id),
                CONSTRAINT "fk.product.product_manufacturer_id" FOREIGN KEY (product_manufacturer_id, product_manufacturer_version_id)
                    REFERENCES product_manufacturer (id, version_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.product.tax_id" FOREIGN KEY (tax_id)
                    REFERENCES tax (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.product.unit_id" FOREIGN KEY (unit_id)
                    REFERENCES unit (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.product.parent_id" FOREIGN KEY (parent_id, parent_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('ALTER SEQUENCE product_auto_increment_seq OWNED BY product.auto_increment');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product__idx.auto_increment" ON product (auto_increment)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product__fk.product.product_manufacturer_id" ON product (product_manufacturer_id, product_manufacturer_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product__fk.product.tax_id" ON product (tax_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product__fk.product.unit_id" ON product (unit_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product__fk.product.parent_id" ON product (parent_id, parent_version_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_translation (
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_translation_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_translation_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_product_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                additional_text VARCHAR(255) COLLATE sw_type_ci NULL,
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                keywords TEXT COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                meta_title VARCHAR(255) COLLATE sw_type_ci NULL,
                pack_unit VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (product_id, product_version_id, language_id),
                CONSTRAINT "fk.product_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_translation.product_id" FOREIGN KEY (product_id, product_version_id)
                    REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_translation__fk.product_translation.language_id" ON product_translation (language_id)');
    }
}

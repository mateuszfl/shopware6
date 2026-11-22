<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1565705280ProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565705280;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_export (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_export_id_uuid CHECK (octet_length(id) = 16),
                product_stream_id BYTEA NOT NULL CONSTRAINT chk_sw_product_export_product_stream_id_uuid CHECK (octet_length(product_stream_id) = 16),
                storefront_sales_channel_id BYTEA NULL CONSTRAINT chk_sw_product_export_storefront_sales_channel_id_uuid CHECK (octet_length(storefront_sales_channel_id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_product_export_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                sales_channel_domain_id BYTEA NULL CONSTRAINT chk_sw_product_export_sales_channel_domain_id_uuid CHECK (octet_length(sales_channel_domain_id) = 16),
                file_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                access_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                encoding VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                file_format VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                include_variants SMALLINT NULL DEFAULT 0 CONSTRAINT chk_sw_product_export_include_variants_bool CHECK (include_variants IN (0, 1)),
                generate_by_cronjob SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_product_export_generate_by_cronjob_bool CHECK (generate_by_cronjob IN (0, 1)),
                generated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                interval INTEGER NOT NULL,
                header_template TEXT COLLATE sw_type_ci NULL,
                body_template TEXT COLLATE sw_type_ci NULL,
                footer_template TEXT COLLATE sw_type_ci NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "product_export__uniq.file_name" UNIQUE (file_name),
                CONSTRAINT "fk.product_export.product_stream_id" FOREIGN KEY (product_stream_id)
                    REFERENCES product_stream (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_export.storefront_sales_channel_id" FOREIGN KEY (storefront_sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_export.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_export.sales_channel_domain_id" FOREIGN KEY (sales_channel_domain_id)
                    REFERENCES sales_channel_domain (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_export__fk.product_export.product_stream_id" ON product_export (product_stream_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_export__fk.product_export.storefront_sales_channel_id" ON product_export (storefront_sales_channel_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_export__fk.product_export.sales_channel_id" ON product_export (sales_channel_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_export__fk.product_export.sales_channel_domain_id" ON product_export (sales_channel_domain_id)');

        $salesChannelTypeId = Uuid::fromHexToBytes('ed535e5722134ac1aa6524f73e26881b');
        $languageEnglish = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageGerman = $connection->fetchOne('SELECT id FROM language WHERE id <> ?', [$languageEnglish], [ParameterType::BINARY]);
        if (\is_resource($languageGerman)) {
            $languageGerman = stream_get_contents($languageGerman);
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $connection->executeStatement(
            'INSERT INTO sales_channel_type (id, icon_name, created_at) VALUES (?, ?, ?) ON CONFLICT (id) DO NOTHING',
            [$salesChannelTypeId, 'default-object-rocket', $createdAt],
            [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]
        );
        $connection->executeStatement(<<<'SQL'
            INSERT INTO sales_channel_type_translation (sales_channel_type_id, language_id, name, manufacturer, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (sales_channel_type_id, language_id) DO NOTHING
            SQL, [$salesChannelTypeId, $languageEnglish, 'Product comparison', 'shopware AG', 'Sales channel for product comparison platforms', $createdAt], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO sales_channel_type_translation (sales_channel_type_id, language_id, name, manufacturer, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (sales_channel_type_id, language_id) DO NOTHING
            SQL, [$salesChannelTypeId, $languageGerman, 'Produktvergleich', 'shopware AG', 'Verkaufskanal für Produktvergleichsportale', $createdAt], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

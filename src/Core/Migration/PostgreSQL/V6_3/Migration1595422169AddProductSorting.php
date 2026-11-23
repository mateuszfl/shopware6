<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('framework')]
class Migration1595422169AddProductSorting extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1595422169;
    }

    public function update(Connection $connection): void
    {
        $this->createTable($connection);
        $this->createTranslationTable($connection);
        $this->createDefaultSortingsWithTranslations($connection);
        $this->setDefaultSystemConfig($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    public function createDefaultSortingsWithTranslations(Connection $connection): void
    {
        foreach ($this->getDefaultSortings() as $sorting) {
            $translations = $sorting['translations'];
            unset($sorting['translations']);
            $connection->insert(ProductSortingDefinition::ENTITY_NAME, $sorting, ['id' => ParameterType::BINARY]);
            $translations = new Translations(['product_sorting_id' => $sorting['id'], 'label' => $translations['de-DE']], ['product_sorting_id' => $sorting['id'], 'label' => $translations['en-GB']]);
            $this->importTranslation('product_sorting_translation', $translations, $connection);
        }
    }

    private function createTable(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "product_sorting" (
              "id"                              BYTEA CHECK (octet_length("id") = 16)                              NOT NULL,
              "url_key"                         VARCHAR(255) COLLATE "sw_type_ci"                            NOT NULL,
              "priority"                        INT                                  NOT NULL,
              "active"                          SMALLINT                              NOt NULL DEFAULT 1,
              "fields"                          JSON                                    NOT NULL,
              "created_at"                      TIMESTAMP(3) WITHOUT TIME ZONE                             NOT NULL,
              "locked"                          SMALLINT                              NOT NULL DEFAULT 0,
              "updated_at"                      TIMESTAMP(3) WITHOUT TIME ZONE                             NULL,
              PRIMARY KEY ("id"),
              CONSTRAINT "uniq.product_sorting.url_key" UNIQUE ("url_key"),
              CONSTRAINT "json.product_sorting.fields" CHECK (TRUE)
            );
        ');
    }

    private function createTranslationTable(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "product_sorting_translation" (
              "product_sorting_id"          BYTEA CHECK (octet_length("product_sorting_id") = 16)              NOT NULL,
              "language_id"                 BYTEA CHECK (octet_length("language_id") = 16)              NOT NULL,
              "label"                       VARCHAR(255) COLLATE "sw_type_ci" NULL,
              "created_at"                  TIMESTAMP(3) WITHOUT TIME ZONE             NOT NULL,
              "updated_at"                  TIMESTAMP(3) WITHOUT TIME ZONE             NULL,
              PRIMARY KEY ("product_sorting_id", "language_id"),
              CONSTRAINT "fk.product_sorting_translation.language_id" FOREIGN KEY ("language_id")
                REFERENCES "language" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT "fk.product_sorting_translation.product_sorting_id" FOREIGN KEY ("product_sorting_id")
                REFERENCES "product_sorting" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }

    private function setDefaultSystemConfig(Connection $connection): void
    {
        $connection->insert('system_config', ['id' => Uuid::randomBytes(), 'configuration_key' => 'core.listing.defaultSorting', 'configuration_value' => '{"_value": "name-asc"}', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)], ['id' => ParameterType::BINARY]);
    }

    /**
     * @return list<array{id: string, url_key: string, priority: int, active: int, locked: int, fields: string, created_at: string, translations: array{de-DE: string, en-GB: string}}>
     */
    private function getDefaultSortings(): array
    {
        return [['id' => Uuid::randomBytes(), 'url_key' => 'name-asc', 'priority' => 4, 'active' => 1, 'locked' => 0, 'fields' => json_encode([['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 0]], \JSON_THROW_ON_ERROR), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'translations' => ['de-DE' => 'Name A-Z', 'en-GB' => 'Name A-Z']], ['id' => Uuid::randomBytes(), 'url_key' => 'name-desc', 'priority' => 3, 'active' => 1, 'locked' => 0, 'fields' => json_encode([['field' => 'product.name', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => 0]], \JSON_THROW_ON_ERROR), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'translations' => ['de-DE' => 'Name Z-A', 'en-GB' => 'Name Z-A']], ['id' => Uuid::randomBytes(), 'url_key' => 'price-asc', 'priority' => 2, 'active' => 1, 'locked' => 0, 'fields' => json_encode([['field' => 'product.listingPrices', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 0]], \JSON_THROW_ON_ERROR), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'translations' => ['de-DE' => 'Preis aufsteigend', 'en-GB' => 'Price ascending']], ['id' => Uuid::randomBytes(), 'url_key' => 'price-desc', 'priority' => 1, 'active' => 1, 'locked' => 0, 'fields' => json_encode([['field' => 'product.listingPrices', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => 0]], \JSON_THROW_ON_ERROR), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'translations' => ['de-DE' => 'Preis absteigend', 'en-GB' => 'Price descending']], ['id' => Uuid::randomBytes(), 'url_key' => 'score', 'priority' => 0, 'active' => 1, 'locked' => 1, 'fields' => json_encode([['field' => '_score', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => 0]], \JSON_THROW_ON_ERROR), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'translations' => ['de-DE' => 'Beste Ergebnisse', 'en-GB' => 'Top results']]];
    }
}

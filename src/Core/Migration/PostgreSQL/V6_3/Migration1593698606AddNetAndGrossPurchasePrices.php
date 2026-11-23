<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1593698606AddNetAndGrossPurchasePrices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1593698606;
    }

    public function update(Connection $connection): void
    {
        $this->migrateProductPurchasePriceField($connection);
        $this->migrateCartLineItemPurchasePriceRuleCondition($connection);
        // The original migration's trigger bodies use MySQL procedural SQL. Their PostgreSQL equivalents need
        // PL/pgSQL functions and are not safe to approximate here.
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function migrateCartLineItemPurchasePriceRuleCondition(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative('SELECT id, value FROM rule_condition WHERE type = \'cartLineItemPurchasePrice\'');
        foreach ($rows as $row) {
            $conditionValue = json_decode((string) $row['value'], true, 512, \JSON_THROW_ON_ERROR);
            if (\array_key_exists('isNet', $conditionValue)) {
                continue;
            }
            $conditionValue['isNet'] = false;
            $connection->executeStatement('UPDATE rule_condition SET value = :conditionValue WHERE id = :id', ['conditionValue' => json_encode($conditionValue, \JSON_THROW_ON_ERROR), 'id' => $row['id']]);
        }
    }

    private function migrateProductPurchasePriceField(Connection $connection): void
    {
        // Add new 'purchase_prices' JSON field
        $connection->executeStatement('ALTER TABLE "product" ADD "purchase_prices" JSON NULL;');
        // Convert any existing purchase price values into the new 'purchase_prices' JSON field
        $defaultCurrencyId = Defaults::CURRENCY;
        $connection->executeStatement(
            'UPDATE "product" AS product
            SET "purchase_prices" = CASE
                WHEN product."purchase_price" IS NULL THEN NULL
                ELSE jsonb_build_object(
                    :currencyKey,
                    jsonb_build_object(
                        \'net\', product."purchase_price" / (1 + (tax_data."tax_rate" / 100)),
                        \'gross\', product."purchase_price",
                        \'linked\', TRUE,
                        \'currencyId\', :currencyId
                    )
                )::json
            END
            FROM (
                SELECT product_row."id", tax."tax_rate"
                FROM "product" AS product_row
                LEFT JOIN "tax" AS tax ON product_row."tax_id" = tax."id"
            ) AS tax_data
            WHERE product."id" = tax_data."id"',
            ['currencyKey' => \sprintf('c%s', $defaultCurrencyId), 'currencyId' => $defaultCurrencyId]
        );
    }
}

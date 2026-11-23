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
        $this->addPurchasePriceTriggers($connection);
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
                    CAST(:currencyKey AS text),
                    jsonb_build_object(
                        \'net\', product."purchase_price" / (1 + (tax_data."tax_rate" / 100)),
                        \'gross\', product."purchase_price",
                        \'linked\', TRUE,
                        \'currencyId\', CAST(:currencyId AS text)
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

    private function addPurchasePriceTriggers(Connection $connection): void
    {
        $body = \sprintf(
            <<<'SQL'
            DECLARE
                previous_purchase_prices jsonb;
                previous_purchase_price double precision;
                effective_tax_id bytea := NEW.tax_id;
                product_tax_rate numeric;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    previous_purchase_prices := OLD.purchase_prices::jsonb;
                    previous_purchase_price := OLD.purchase_price;
                    effective_tax_id := COALESCE(effective_tax_id, OLD.tax_id);
                END IF;

                IF NEW.purchase_prices IS NOT NULL AND NEW.purchase_prices::jsonb IS DISTINCT FROM previous_purchase_prices THEN
                    -- Compare normalized JSON, but read the first price in the stored key order.
                    NEW.purchase_price := CASE WHEN json_typeof(NEW.purchase_prices) = 'object' THEN (
                        SELECT (price.value ->> 'gross')::double precision
                        FROM json_each(NEW.purchase_prices) WITH ORDINALITY AS price
                        WHERE price.ordinality = 1
                    ) END;
                ELSIF NEW.purchase_price IS NOT NULL AND (NEW.purchase_price <> previous_purchase_price OR NEW.purchase_prices IS NULL) THEN
                    SELECT tax.tax_rate INTO product_tax_rate FROM tax WHERE tax.id = effective_tax_id;

                    NEW.purchase_prices := CASE WHEN product_tax_rate IS NOT NULL THEN json_build_object(
                        'c%1$s', json_build_object(
                            'net', NEW.purchase_price / (1 + product_tax_rate / 100),
                            'gross', NEW.purchase_price,
                            'linked', TRUE,
                            'currencyId', '%1$s'
                        )
                    ) END;
                END IF;

                RETURN NEW;
            END;
            SQL,
            Defaults::CURRENCY
        );

        $this->createTriggerFunction($connection, 'sync_product_purchase_prices', $body);
        $this->createTrigger($connection, 'CREATE TRIGGER product_purchase_prices_insert BEFORE INSERT ON product FOR EACH ROW EXECUTE FUNCTION sync_product_purchase_prices()');
        $this->createTrigger($connection, 'CREATE TRIGGER product_purchase_prices_update BEFORE UPDATE ON product FOR EACH ROW EXECUTE FUNCTION sync_product_purchase_prices()');
    }
}

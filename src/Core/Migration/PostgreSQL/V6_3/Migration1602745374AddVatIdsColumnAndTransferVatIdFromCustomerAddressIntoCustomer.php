<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1602745374AddVatIdsColumnAndTransferVatIdFromCustomerAddressIntoCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1602745374;
    }

    public function update(Connection $connection): void
    {
        if (!$this->addColumn($connection, 'customer', 'vat_ids', 'JSON')) {
            return;
        }

        $this->addVatIdTriggers($connection);

        $connection->executeStatement(<<<'SQL'
            UPDATE customer
            SET vat_ids = json_build_array(customer_address.vat_id)
            FROM customer_address
            WHERE customer.default_billing_address_id = customer_address.id
              AND customer_address.vat_id IS NOT NULL
            SQL);
    }

    private function addVatIdTriggers(Connection $connection): void
    {
        $body = <<<'SQL'
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF OLD.vat_id COLLATE "C" IS NOT DISTINCT FROM NEW.vat_id COLLATE "C" THEN
                        RETURN NEW;
                    END IF;

                    IF OLD.vat_id IS NOT NULL THEN
                        UPDATE customer
                        SET vat_ids = (vat_ids::jsonb - OLD.vat_id::text)::json
                        WHERE default_billing_address_id = NEW.id
                          AND vat_ids::jsonb @> jsonb_build_array(OLD.vat_id);
                    END IF;
                END IF;

                IF NEW.vat_id IS NOT NULL THEN
                    UPDATE customer
                    SET vat_ids = (COALESCE(vat_ids::jsonb, '[]'::jsonb) || jsonb_build_array(NEW.vat_id))::json
                    WHERE default_billing_address_id = NEW.id
                      AND (vat_ids IS NULL OR NOT vat_ids::jsonb @> jsonb_build_array(NEW.vat_id));
                END IF;

                RETURN NEW;
            END;
            SQL;

        $this->createTriggerFunction($connection, 'sync_customer_address_vat_id', $body);
        $this->createTrigger($connection, 'CREATE TRIGGER customer_address_vat_id_insert AFTER INSERT ON customer_address FOR EACH ROW EXECUTE FUNCTION sync_customer_address_vat_id()');
        $this->createTrigger($connection, 'CREATE TRIGGER customer_address_vat_id_update AFTER UPDATE ON customer_address FOR EACH ROW EXECUTE FUNCTION sync_customer_address_vat_id()');
    }
}

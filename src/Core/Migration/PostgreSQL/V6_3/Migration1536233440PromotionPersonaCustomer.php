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
class Migration1536233440PromotionPersonaCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233440;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_persona_customer (
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_persona_customer_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                customer_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_persona_customer_customer_id_uuid CHECK (octet_length(customer_id) = 16),
                PRIMARY KEY (promotion_id, customer_id),
                CONSTRAINT "fk.promotion_persona_customer.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE CASCADE,
                CONSTRAINT "fk.promotion_persona_customer.customer_id" FOREIGN KEY (customer_id)
                    REFERENCES customer (id) ON DELETE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_persona_customer__fk.promotion_perso_78e468b5ef9bd361" ON promotion_persona_customer (customer_id)');
    }
}

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
class Migration1536233000OrderCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS order_customer (
                id BYTEA NOT NULL CONSTRAINT chk_sw_order_customer_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_customer_version_id_uuid CHECK (octet_length(version_id) = 16),
                customer_id BYTEA NULL CONSTRAINT chk_sw_order_customer_customer_id_uuid CHECK (octet_length(customer_id) = 16),
                order_id BYTEA NOT NULL CONSTRAINT chk_sw_order_customer_order_id_uuid CHECK (octet_length(order_id) = 16),
                order_version_id BYTEA NOT NULL CONSTRAINT chk_sw_order_customer_order_version_id_uuid CHECK (octet_length(order_version_id) = 16),
                email VARCHAR(254) COLLATE sw_type_ci NOT NULL,
                salutation_id BYTEA NOT NULL CONSTRAINT chk_sw_order_customer_salutation_id_uuid CHECK (octet_length(salutation_id) = 16),
                first_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                last_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                title VARCHAR(100) COLLATE sw_type_ci NULL,
                customer_number VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT "fk.order_customer.customer_id" FOREIGN KEY (customer_id)
                    REFERENCES customer (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT "fk.order_customer.order_id" FOREIGN KEY (order_id, order_version_id)
                    REFERENCES "order" (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.order_customer.salutation_id" FOREIGN KEY (salutation_id)
                    REFERENCES salutation (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_customer__fk.order_customer.customer_id" ON order_customer (customer_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_customer__fk.order_customer.order_id" ON order_customer (order_id, order_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "order_customer__fk.order_customer.salutation_id" ON order_customer (salutation_id)');
    }
}

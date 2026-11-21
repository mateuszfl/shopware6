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
class Migration1536232970CustomerAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232970;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customer_address (
                id BYTEA NOT NULL CONSTRAINT chk_sw_customer_address_id_uuid CHECK (octet_length(id) = 16),
                customer_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_address_customer_id_uuid CHECK (octet_length(customer_id) = 16),
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_address_country_id_uuid CHECK (octet_length(country_id) = 16),
                country_state_id BYTEA NULL CONSTRAINT chk_sw_customer_address_country_state_id_uuid CHECK (octet_length(country_state_id) = 16),
                company VARCHAR(255) COLLATE sw_type_ci NULL,
                department VARCHAR(35) COLLATE sw_type_ci NULL,
                salutation_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_address_salutation_id_uuid CHECK (octet_length(salutation_id) = 16),
                title VARCHAR(100) COLLATE sw_type_ci NULL,
                first_name VARCHAR(50) COLLATE sw_type_ci NOT NULL,
                last_name VARCHAR(60) COLLATE sw_type_ci NOT NULL,
                street VARCHAR(255) COLLATE sw_type_ci NULL,
                zipcode VARCHAR(50) COLLATE sw_type_ci NOT NULL,
                city VARCHAR(70) COLLATE sw_type_ci NOT NULL,
                vat_id VARCHAR(50) COLLATE sw_type_ci NULL,
                phone_number VARCHAR(40) COLLATE sw_type_ci NULL,
                additional_address_line1 VARCHAR(255) COLLATE sw_type_ci NULL,
                additional_address_line2 VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.customer_address.country_id" FOREIGN KEY (country_id)
                    REFERENCES country (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.customer_address.country_state_id" FOREIGN KEY (country_state_id)
                    REFERENCES country_state (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT "fk.customer_address.customer_id" FOREIGN KEY (customer_id)
                    REFERENCES customer (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.customer_address.salutation_id" FOREIGN KEY (salutation_id)
                    REFERENCES salutation (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer_address__fk.customer_address.country_id" ON customer_address (country_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer_address__fk.customer_address.country_state_id" ON customer_address (country_state_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer_address__fk.customer_address.customer_id" ON customer_address (customer_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer_address__fk.customer_address.salutation_id" ON customer_address (salutation_id)');
    }
}

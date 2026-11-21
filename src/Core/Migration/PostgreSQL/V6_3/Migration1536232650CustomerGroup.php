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
class Migration1536232650CustomerGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232650;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customer_group (
                id BYTEA NOT NULL CONSTRAINT chk_sw_customer_group_id_uuid CHECK (octet_length(id) = 16),
                display_gross SMALLINT NOT NULL DEFAULT 1 CONSTRAINT chk_sw_customer_group_display_gross_bool CHECK (display_gross IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customer_group_translation (
                customer_group_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_group_translation_customer_group_id_uuid CHECK (octet_length(customer_group_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_customer_group_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (customer_group_id, language_id),
                CONSTRAINT "fk.customer_group_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.customer_group_translation.customer_group_id" FOREIGN KEY (customer_group_id) REFERENCES customer_group (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "customer_group_translation__fk.customer_group_translation.language_id" ON customer_group_translation (language_id)');
    }
}

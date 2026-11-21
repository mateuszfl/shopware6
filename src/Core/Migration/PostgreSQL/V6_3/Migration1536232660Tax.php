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
class Migration1536232660Tax extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232660;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tax (
                id BYTEA NOT NULL CONSTRAINT chk_sw_tax_id_uuid CHECK (octet_length(id) = 16),
                tax_rate DECIMAL(10, 2) NOT NULL,
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "tax__idx.tax.tax" ON tax (tax_rate)');
    }
}

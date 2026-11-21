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
class Migration1536232680Rule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232680;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rule (
                id BYTEA NOT NULL CONSTRAINT chk_sw_rule_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(500) COLLATE sw_type_ci NOT NULL,
                description TEXT COLLATE sw_type_ci NULL,
                priority INTEGER NOT NULL,
                payload BYTEA NULL,
                invalid SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_rule_invalid_bool CHECK (invalid IN (0, 1)),
                module_types JSONB NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);
    }
}

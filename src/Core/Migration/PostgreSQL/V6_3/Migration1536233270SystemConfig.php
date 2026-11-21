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
class Migration1536233270SystemConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233270;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS system_config (
                id BYTEA NOT NULL CONSTRAINT chk_sw_system_config_id_uuid CHECK (octet_length(id) = 16),
                configuration_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                configuration_value JSONB NOT NULL,
                sales_channel_id BYTEA NULL CONSTRAINT chk_sw_system_config_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "system_config__uniq.system_config.configuratio_7ca43f18852d23bf" UNIQUE (configuration_key, sales_channel_id)
            )
            SQL);
    }
}

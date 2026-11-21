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
class Migration1536232830MediaDefaultFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232830;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_default_folder (
                id BYTEA NOT NULL CONSTRAINT chk_sw_media_default_folder_id_uuid CHECK (octet_length(id) = 16),
                association_fields JSONB NOT NULL,
                entity VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT media_default_folder__media_default_folder__un_849ceab531009c01 UNIQUE (entity)
            )
            SQL);
    }
}

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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1554200141ImportExportFile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554200141;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS import_export_file (
                id BYTEA NOT NULL CONSTRAINT chk_sw_import_export_file_id_uuid CHECK (octet_length(id) = 16),
                original_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                path VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                expire_date TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                size INTEGER,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                access_token VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
    }
}

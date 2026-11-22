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
class Migration1554203706AddImportExportLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554203706;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS import_export_log (
                id BYTEA NOT NULL CONSTRAINT chk_sw_import_export_log_id_uuid CHECK (octet_length(id) = 16),
                activity varchar(255) COLLATE sw_type_ci NOT NULL,
                state varchar(255) COLLATE sw_type_ci NOT NULL,
                records INTEGER NOT NULL,
                username varchar(255) COLLATE sw_type_ci NULL,
                profile_name varchar(255) COLLATE sw_type_ci NULL,
                user_id BYTEA CONSTRAINT chk_sw_import_export_log_user_id_uuid CHECK (octet_length(user_id) = 16),
                profile_id BYTEA CONSTRAINT chk_sw_import_export_log_profile_id_uuid CHECK (octet_length(profile_id) = 16),
                file_id BYTEA CONSTRAINT chk_sw_import_export_log_file_id_uuid CHECK (octet_length(file_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE,
                PRIMARY KEY (id),
                CONSTRAINT "fk.import_export_log.user_id" FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
                CONSTRAINT "fk.import_export_log.profile_id" FOREIGN KEY (profile_id) REFERENCES import_export_profile (id) ON DELETE SET NULL,
                CONSTRAINT "fk.import_export_log.file_id" FOREIGN KEY (file_id) REFERENCES import_export_file (id) ON DELETE SET NULL
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "import_export_log__fk.import_export_log.user_id" ON import_export_log (user_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "import_export_log__fk.import_export_log.profile_id" ON import_export_log (profile_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "import_export_log__fk.import_export_log.file_id" ON import_export_log (file_id)');
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1558505525Logging extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558505525;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS log_entry (
                id BYTEA NOT NULL CONSTRAINT chk_sw_log_entry_id_uuid CHECK (octet_length(id) = 16),
                message VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                level SMALLINT NOT NULL,
                channel VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                context JSONB NULL,
                extra JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "json.log_entry.context" CHECK (context IS NULL OR jsonb_typeof(context) IS NOT NULL),
                CONSTRAINT "json.log_entry.extra" CHECK (extra IS NULL OR jsonb_typeof(extra) IS NOT NULL)
            )
            SQL);

        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE system_config IN SHARE ROW EXCLUSIVE MODE');

            foreach ([
                'core.logging.cleanupInterval' => '86400',
                'core.logging.entryLimit' => '10000000',
                'core.logging.entryLifetimeSeconds' => '2678400',
            ] as $key => $value) {
                if ($connection->fetchOne('SELECT 1 FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]) !== false) {
                    continue;
                }

                $connection->insert('system_config', [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => $key,
                    'configuration_value' => json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
            }
        });
    }
}

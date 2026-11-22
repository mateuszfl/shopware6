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
class Migration1563288227MarkAsNewConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563288227;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE system_config IN SHARE ROW EXCLUSIVE MODE');
            if ($connection->fetchOne('SELECT 1 FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['core.listing.markAsNew']) !== false) {
                return;
            }

            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.markAsNew',
                'configuration_value' => '{"_value": "30"}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        });
    }
}

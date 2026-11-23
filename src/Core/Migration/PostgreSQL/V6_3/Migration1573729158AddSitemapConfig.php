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
class Migration1573729158AddSitemapConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1573729158;
    }

    public function update(Connection $connection): void
    {
        $this->insertDefault($connection, 'core.sitemap.sitemapRefreshTime', '{"_value": 3600}');
        $this->insertDefault($connection, 'core.sitemap.sitemapRefreshStrategy', '{"_value": "2"}');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function insertDefault(Connection $connection, string $key, string $value): void
    {
        $exists = $connection->fetchOne('SELECT 1 FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL', ['key' => $key]);
        if ($exists !== false) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => $value,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
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
class Migration1570187167AddedAppConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570187167;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_config (
                "key" VARCHAR(50) COLLATE sw_type_ci NOT NULL,
                value TEXT COLLATE sw_type_ci NOT NULL,
                PRIMARY KEY ("key")
            )
            SQL);

        $connection->executeStatement(
            'INSERT INTO app_config ("key", value) VALUES (:key, :value) ON CONFLICT ("key") DO NOTHING',
            ['key' => 'cache-id', 'value' => Uuid::randomHex()]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

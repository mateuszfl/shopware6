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
class Migration1572264837AddCacheId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572264837;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM app_config');

        // The PostgreSQL app_config table already has this primary key from its create-table migration.

        $connection->executeStatement(
            'INSERT INTO app_config ("key", value) VALUES (:key, :value) ON CONFLICT ("key") DO NOTHING',
            ['key' => 'cache-id', 'value' => Uuid::randomHex()]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

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
class Migration1565007156RemoveAutoIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565007156;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE product DROP COLUMN IF EXISTS auto_increment');
        $connection->executeStatement('ALTER TABLE category DROP COLUMN IF EXISTS auto_increment');
    }
}

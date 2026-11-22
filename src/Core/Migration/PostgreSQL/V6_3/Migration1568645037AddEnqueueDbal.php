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
class Migration1568645037AddEnqueueDbal extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568645037;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS enqueue (
                id CHAR(36) COLLATE sw_type_ci NOT NULL,
                published_at BIGINT NOT NULL,
                body TEXT COLLATE sw_type_ci NULL,
                headers TEXT COLLATE sw_type_ci NULL,
                properties TEXT COLLATE sw_type_ci NULL,
                redelivered SMALLINT NULL,
                queue VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                priority SMALLINT NULL,
                delayed_until BIGINT NULL,
                time_to_live BIGINT NULL,
                delivery_id CHAR(36) COLLATE sw_type_ci NULL,
                redeliver_after BIGINT NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "IDX_CFC35A6862A6DC27E0D4FDE17FFD7F63121369211A065DF8BF396750" ON enqueue (priority, published_at, queue, delivery_id, delayed_until, id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "IDX_CFC35A68AA0BDFF712136921" ON enqueue (redeliver_after, delivery_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "IDX_CFC35A68E0669C0612136921" ON enqueue (time_to_live, delivery_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

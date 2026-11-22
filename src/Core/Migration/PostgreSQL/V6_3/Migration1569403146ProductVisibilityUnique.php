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
class Migration1569403146ProductVisibilityUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1569403146;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            DELETE FROM product_visibility
            WHERE id IN (
                SELECT id
                FROM (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY product_id, product_version_id, sales_channel_id
                        ORDER BY created_at DESC, id DESC
                    ) AS duplicate_number
                    FROM product_visibility
                ) AS ranked_visibility
                WHERE duplicate_number > 1
            )
            SQL);

        $connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS "uniq.product_id__sales_channel_id" ON product_visibility (product_id, product_version_id, sales_channel_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

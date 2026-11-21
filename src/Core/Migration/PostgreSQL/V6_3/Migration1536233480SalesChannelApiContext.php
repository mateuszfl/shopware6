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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536233480SalesChannelApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233480;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_api_context (
                token VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                payload JSONB NOT NULL,
                PRIMARY KEY (token)
            )
            SQL);
    }
}

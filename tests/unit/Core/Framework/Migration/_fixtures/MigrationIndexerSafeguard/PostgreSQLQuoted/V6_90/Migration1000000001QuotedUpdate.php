<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\PostgreSQLQuoted\V6_90;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1000000001QuotedUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE "fixture_indexed_table" SET "watched_column" = NULL');
    }
}

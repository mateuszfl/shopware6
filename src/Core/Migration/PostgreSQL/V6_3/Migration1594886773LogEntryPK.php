<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1594886773LogEntryPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594886773;
    }

    public function update(Connection $connection): void
    {
        foreach ($connection->createSchemaManager()->listTableIndexes('log_entry') as $index) {
            if ($index->isPrimary()) {
                return;
            }
        }

        $connection->executeStatement('
            ALTER TABLE "log_entry"
            ADD PRIMARY KEY ("id");
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}

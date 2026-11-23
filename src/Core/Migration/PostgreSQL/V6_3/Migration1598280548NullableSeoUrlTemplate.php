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
class Migration1598280548NullableSeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1598280548;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE "seo_url_template" ALTER COLUMN "template" TYPE VARCHAR(750), ALTER COLUMN "template" DROP NOT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

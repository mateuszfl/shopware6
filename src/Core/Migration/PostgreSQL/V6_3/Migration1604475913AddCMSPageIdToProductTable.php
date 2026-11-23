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
class Migration1604475913AddCMSPageIdToProductTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604475913;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE "product"
            ADD COLUMN "cms_page_id" BINARY(16) NULL,
            ADD CONSTRAINT "fk.product.cms_page_id" FOREIGN KEY ("cms_page_id")
            REFERENCES "cms_page" ("id") ON DELETE RESTRICT ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

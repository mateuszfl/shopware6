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
class Migration1594650256AddMailTemplateSalesChannelPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594650256;
    }

    public function update(Connection $connection): void
    {
        foreach ($connection->createSchemaManager()->listTableIndexes('mail_template_sales_channel') as $index) {
            if ($index->isPrimary()) {
                return;
            }
        }

        $connection->executeStatement('
            ALTER TABLE "mail_template_sales_channel"
            ADD PRIMARY KEY ("id");
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}

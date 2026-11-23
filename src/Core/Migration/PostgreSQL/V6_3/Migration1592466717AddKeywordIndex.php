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
class Migration1592466717AddKeywordIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1592466717;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "idx.product_search_keyword.keyword_language" ON product_search_keyword (keyword, language_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

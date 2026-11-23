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
class Migration1586158920AddImportExportProfileConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586158920;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE import_export_profile
            ADD COLUMN config JSON,
            ADD CONSTRAINT "json.import_export_profile.config" CHECK (TRUE)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

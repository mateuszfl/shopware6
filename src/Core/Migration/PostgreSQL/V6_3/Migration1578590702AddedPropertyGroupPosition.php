<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare (strict_types=1);
namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
/**
 * @internal
 */
#[Package('framework')]
class Migration1578590702AddedPropertyGroupPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578590702;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE "property_group_translation" ADD "position" INT NULL DEFAULT 1;');
    }
    public function updateDestructive(Connection $connection): void
    {
    }
}

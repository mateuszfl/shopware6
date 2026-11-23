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
class Migration1591167126RoleDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591167126;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE "acl_role" ADD "description" longtext COLLATE \'utf8mb4_unicode_ci\' NULL;');
        $connection->executeStatement('ALTER TABLE "user" ADD "title" varchar(255) COLLATE \'utf8mb4_unicode_ci\' NULL;');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

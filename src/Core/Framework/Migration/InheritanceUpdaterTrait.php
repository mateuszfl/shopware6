<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait InheritanceUpdaterTrait
{
    protected function updateInheritance(Connection $connection, string $entity, string $propertyName): void
    {
        $platform = $connection->getDatabasePlatform();
        $type = $platform instanceof PostgreSQLPlatform ? 'BYTEA' : 'binary(16)';
        $sql = 'ALTER TABLE ' . $platform->quoteSingleIdentifier($entity)
            . ' ADD COLUMN ' . $platform->quoteSingleIdentifier($propertyName) . ' ' . $type . ' NULL';
        if ($platform instanceof PostgreSQLPlatform) {
            $column = $platform->quoteSingleIdentifier($propertyName);
            $sql .= ' CHECK (' . $column . ' IS NULL OR octet_length(' . $column . ') = 16)';
        }

        NonStandardFkGuard::executeDdl($connection, $sql);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class MigrationDatabaseHelper
{
    /**
     * @return non-empty-list<string>
     */
    public static function getPrimaryKeyColumnNames(Connection $connection, string $table): array
    {
        if ($table === '') {
            throw MigrationException::logicError('A non-empty table name is required.');
        }

        $primaryKey = $connection->createSchemaManager()->introspectTablePrimaryKeyConstraint(OptionallyQualifiedName::unquoted($table));
        if ($primaryKey === null) {
            throw MigrationException::noPrimaryKey();
        }

        return array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $primaryKey->getColumnNames());
    }

    public static function normalizeBinaryId(mixed $id): string
    {
        if (\is_resource($id)) {
            $id = stream_get_contents($id);
        }

        if (!\is_string($id) || \strlen($id) !== 16) {
            throw MigrationException::logicError('A binary UUID must contain exactly 16 bytes.');
        }

        return $id;
    }
}

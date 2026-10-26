<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationDatabaseHelper;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
trait ImportTranslationsTrait
{
    /**
     * @param non-empty-string $table
     */
    protected function importTranslation(string $table, Translations $translations, Connection $connection): TranslationWriteResult
    {
        $germanIds = $this->getLanguageIds($connection, 'de-DE');
        $englishIds = array_unique(array_diff(
            array_merge($this->getLanguageIds($connection, 'en-GB'), [Defaults::LANGUAGE_SYSTEM]),
            $germanIds
        ));

        $columns = [];
        $values = [];

        $keys = array_merge($translations->getColumns(), ['created_at', 'language_id']);
        foreach ($keys as $column) {
            $columns[] = $connection->getDatabasePlatform()->quoteSingleIdentifier($column);
            $values[] = ':' . $column;
        }

        $quotedTable = $connection->getDatabasePlatform()->quoteSingleIdentifier($table);
        $sql = 'REPLACE INTO ' . $quotedTable . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')';
        $types = [];
        $deleteSql = '';
        foreach ($keys as $key) {
            if ($key === 'id' || str_ends_with($key, '_id')) {
                $types[$key] = ParameterType::BINARY;
            }
        }

        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $primaryKeyColumns = MigrationDatabaseHelper::getPrimaryKeyColumnNames($connection, $table);
            $conditions = array_map(fn (string $column): string => $connection->getDatabasePlatform()->quoteSingleIdentifier($column) . ' = :' . $column, $primaryKeyColumns);
            $deleteSql = 'DELETE FROM ' . $quotedTable . ' WHERE ' . implode(' AND ', $conditions);
        }

        foreach ($englishIds as $id) {
            $data = array_merge($translations->getEnglish(), [
                'language_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $connection->transactional(static function (Connection $connection) use ($deleteSql, $data, $types, $sql): void {
                    $connection->executeStatement($deleteSql, $data, $types);
                    $connection->executeStatement(str_replace('REPLACE INTO', 'INSERT INTO', $sql), $data, $types);
                });
            } else {
                $connection->executeStatement($sql, $data, $types);
            }
        }

        foreach ($germanIds as $id) {
            $data = array_merge($translations->getGerman(), [
                'language_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $connection->transactional(static function (Connection $connection) use ($deleteSql, $data, $types, $sql): void {
                    $connection->executeStatement($deleteSql, $data, $types);
                    $connection->executeStatement(str_replace('REPLACE INTO', 'INSERT INTO', $sql), $data, $types);
                });
            } else {
                $connection->executeStatement($sql, $data, $types);
            }
        }

        return new TranslationWriteResult($englishIds, $germanIds);
    }

    /**
     * @return array<string>
     */
    protected function getLanguageIds(Connection $connection, string $locale): array
    {
        $quotedLanguage = $connection->getDatabasePlatform()->quoteSingleIdentifier('language');
        $idExpression = $connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'encode(' . $quotedLanguage . '.id, \'hex\')' : 'LOWER(HEX(' . $quotedLanguage . '.id))';
        $ids = $connection->fetchFirstColumn('SELECT ' . $idExpression . ' AS id FROM ' . $quotedLanguage . ' INNER JOIN locale ON ' . $quotedLanguage . '.locale_id = locale.id AND locale.code = :locale', ['locale' => $locale]);

        return array_unique(array_filter($ids));
    }
}

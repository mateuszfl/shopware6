<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class IndexerQueuer
{
    final public const INDEXER_KEY = 'core.scheduled_indexers';

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, array<string>>
     */
    public function getIndexers(): array
    {
        $current = self::fetchCurrent($this->connection);

        $indexers = [];

        if ($current !== null) {
            $decodedValue = json_decode((string) $current['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);

            $indexers = $decodedValue['_value'] ?? [];
        }

        // Upgrade possible old format to empty array
        return array_map(static function ($options) {
            return \is_array($options) ? $options : [];
        }, $indexers);
    }

    /**
     * @param list<string> $names
     */
    public function finishIndexer(array $names): void
    {
        $current = self::fetchCurrent($this->connection);
        $indexerList = [];
        if ($current !== null) {
            $decodedValue = json_decode((string) $current['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);
            $indexerList = $decodedValue['_value'] ?? [];
        }

        $newList = [];
        foreach ($indexerList as $indexerName => $options) {
            if (!\in_array($indexerName, $names, true)) {
                // Upgrade possible old format to empty array
                $newList[$indexerName] = \is_array($options) ? $options : [];
            }
        }

        self::upsert($this->connection, $current['id'] ?? null, $newList);
    }

    /**
     * @param list<string> $requiredIndexers
     */
    public static function registerIndexer(Connection $connection, string $name, array $requiredIndexers = []): void
    {
        $current = self::fetchCurrent($connection);

        $id = null;
        $indexerList = [];

        if ($current !== null) {
            $id = $current['id'];
            $decodedValue = json_decode((string) $current['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);
            $indexerList = $decodedValue['_value'] ?? [];
        }

        // Upgrade old entries to new format
        foreach ($indexerList as $key => $value) {
            if (\is_int($value)) {
                $indexerList[$key] = [];
            }
        }

        $indexerList[$name] = isset($indexerList[$name]) ? array_unique(array_merge($indexerList[$name], $requiredIndexers)) : $requiredIndexers;

        self::upsert($connection, $id, $indexerList);
    }

    /**
     * @param string|resource|null $id
     * @param array<array-key, array<string>> $indexerList
     */
    private static function upsert(Connection $connection, mixed $id, array $indexerList): void
    {
        if ($id !== null) {
            $id = MigrationDatabaseHelper::normalizeBinaryId($id);
        }

        if ($indexerList === [] && $id !== null) {
            $connection->delete('system_config', ['id' => $id], ['id' => ParameterType::BINARY]);

            return;
        }

        $newValue = json_encode(['_value' => $indexerList], \JSON_THROW_ON_ERROR);
        $date = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        if ($id) {
            $connection->update(
                'system_config',
                ['configuration_value' => $newValue, 'updated_at' => $date],
                ['id' => $id],
                ['id' => ParameterType::BINARY]
            );
        } else {
            $connection->insert(
                'system_config',
                [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => self::INDEXER_KEY,
                    'configuration_value' => $newValue,
                    'created_at' => $date,
                ],
                ['id' => ParameterType::BINARY]
            );
        }
    }

    /**
     * @return array{id: string|resource, configuration_value: string}|null
     */
    private static function fetchCurrent(Connection $connection): ?array
    {
        $currentRow = $connection->fetchAssociative(
            'SELECT id, configuration_value
             FROM system_config
             WHERE configuration_key = :key
             AND sales_channel_id IS NULL',
            ['key' => self::INDEXER_KEY]
        );

        if ($currentRow === false) {
            return null;
        }

        return [
            'id' => MigrationDatabaseHelper::normalizeBinaryId($currentRow['id'] ?? null),
            'configuration_value' => (string) ($currentRow['configuration_value'] ?? ''),
        ];
    }
}

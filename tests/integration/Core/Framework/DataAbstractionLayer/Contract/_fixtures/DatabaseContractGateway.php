<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialect;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;

/**
 * Portable lower-level gateway used to prove the fixture and shared scenarios.
 * Production DAL gateways replace this class in subsequent porting tasks.
 *
 * @internal
 */
final class DatabaseContractGateway
{
    private readonly DatabaseDialect $dialect;

    public function __construct(private readonly Connection $connection)
    {
        $this->dialect = (new DatabasePlatformResolver($connection))->dialect();
    }

    /**
     * @param array{
     *     languages: list<array{id: string, name: string}>,
     *     products: list<array{id: string, versionId: string, parentId: ?string, parentVersionId: ?string, payload: ?array<string, mixed>, prices: list<array{currency: string, gross: float, net: float}>, active: bool, createdAt: string}>,
     *     translations: list<array{productId: string, productVersionId: string, languageId: string, name: string, description: ?string, payload: ?array<string, mixed>, createdAt: string}>,
     *     tags: list<array{id: string, name: string}>,
     *     productTags: list<array{productId: string, productVersionId: string, tagId: string}>
     * } $data
     */
    public function write(array $data): int
    {
        return $this->connection->transactional(function () use ($data): int {
            $this->insertLanguages($data['languages']);
            $this->insertTags($data['tags']);
            $productBatches = $this->insertProducts($data['products']);
            $this->insertTranslations($data['translations']);
            $this->insertProductTags($data['productTags']);

            return $productBatches;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $languageId): array
    {
        $hexId = $this->dialect->hex(['product', 'id']);
        $hexVersion = $this->dialect->hex(['product', 'version_id']);
        $hexParent = $this->dialect->hex(['product', 'parent_id']);
        $hexParentVersion = $this->dialect->hex(['product', 'parent_version_id']);
        $language = $this->dialect->fromHex('?');

        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT $hexId AS id,
                   $hexVersion AS version_id,
                   $hexParent AS parent_id,
                   $hexParentVersion AS parent_version_id,
                   product.payload,
                   product.prices,
                   product.active,
                   product.created_at,
                   translation.name,
                   translation.description,
                   translation.payload AS translation_payload
            FROM sw_contract_product product
            LEFT JOIN sw_contract_product_translation translation
              ON translation.product_id = product.id
             AND translation.product_version_id = product.version_id
             AND translation.language_id = $language
            SQL, [$languageId], [ParameterType::STRING]);

        $tags = $this->readTags();
        $state = [];
        foreach ($rows as $row) {
            $key = $row['id'] . ':' . $row['version_id'];
            $state[] = [
                'id' => (string) $row['id'],
                'versionId' => (string) $row['version_id'],
                'parentId' => $row['parent_id'] === null ? null : (string) $row['parent_id'],
                'parentVersionId' => $row['parent_version_id'] === null ? null : (string) $row['parent_version_id'],
                'payload' => self::decodeJson($row['payload']),
                'prices' => self::decodeJson($row['prices']),
                'active' => (bool) $row['active'],
                'createdAt' => (string) $row['created_at'],
                'name' => $row['name'] === null ? null : (string) $row['name'],
                'description' => $row['description'] === null ? null : (string) $row['description'],
                'translationPayload' => self::decodeJson($row['translation_payload']),
                'tags' => $tags[$key] ?? [],
            ];
        }

        return $state;
    }

    public function countProducts(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sw_contract_product');
    }

    /**
     * @param list<array{id: string, name: string}> $rows
     */
    private function insertLanguages(array $rows): void
    {
        $this->insertSimpleIdRows('sw_contract_language', $rows);
    }

    /**
     * @param list<array{id: string, name: string}> $rows
     */
    private function insertTags(array $rows): void
    {
        $this->insertSimpleIdRows('sw_contract_tag', $rows);
    }

    /**
     * @param list<array{id: string, name: string}> $rows
     */
    private function insertSimpleIdRows(string $table, array $rows): void
    {
        foreach (array_chunk($rows, DatabaseContractScenario::BATCH_SIZE) as $batch) {
            $values = [];
            $parameters = [];
            $types = [];
            foreach ($batch as $row) {
                $values[] = '(' . $this->dialect->fromHex('?') . ', ?)';
                array_push($parameters, $row['id'], $row['name']);
                array_push($types, ParameterType::STRING, ParameterType::STRING);
            }
            $this->executeInsert($table, ['id', 'name'], $values, $parameters, $types);
        }
    }

    /**
     * @param list<array{id: string, versionId: string, parentId: ?string, parentVersionId: ?string, payload: ?array<string, mixed>, prices: list<array{currency: string, gross: float, net: float}>, active: bool, createdAt: string}> $rows
     */
    private function insertProducts(array $rows): int
    {
        $batches = 0;
        foreach (array_chunk($rows, DatabaseContractScenario::BATCH_SIZE) as $batch) {
            ++$batches;
            $values = [];
            $parameters = [];
            $types = [];
            foreach ($batch as $row) {
                $binary = $this->dialect->fromHex('?');
                $values[] = "($binary, $binary, $binary, $binary, ?, ?, ?, ?)";
                array_push(
                    $parameters,
                    $row['id'],
                    $row['versionId'],
                    $row['parentId'],
                    $row['parentVersionId'],
                    self::encodeJson($row['payload']),
                    self::encodeJson($row['prices']),
                    $row['active'],
                    $row['createdAt'],
                );
                array_push(
                    $types,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::BOOLEAN,
                    ParameterType::STRING,
                );
            }
            $this->executeInsert(
                'sw_contract_product',
                ['id', 'version_id', 'parent_id', 'parent_version_id', 'payload', 'prices', 'active', 'created_at'],
                $values,
                $parameters,
                $types,
            );
        }

        return $batches;
    }

    /**
     * @param list<array{productId: string, productVersionId: string, languageId: string, name: string, description: ?string, payload: ?array<string, mixed>, createdAt: string}> $rows
     */
    private function insertTranslations(array $rows): void
    {
        foreach (array_chunk($rows, DatabaseContractScenario::BATCH_SIZE) as $batch) {
            $values = [];
            $parameters = [];
            $types = [];
            foreach ($batch as $row) {
                $binary = $this->dialect->fromHex('?');
                $values[] = "($binary, $binary, $binary, ?, ?, ?, ?)";
                array_push(
                    $parameters,
                    $row['productId'],
                    $row['productVersionId'],
                    $row['languageId'],
                    $row['name'],
                    $row['description'],
                    self::encodeJson($row['payload']),
                    $row['createdAt'],
                );
                array_push(
                    $types,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                );
            }
            $this->executeInsert(
                'sw_contract_product_translation',
                ['product_id', 'product_version_id', 'language_id', 'name', 'description', 'payload', 'created_at'],
                $values,
                $parameters,
                $types,
            );
        }
    }

    /**
     * @param list<array{productId: string, productVersionId: string, tagId: string}> $rows
     */
    private function insertProductTags(array $rows): void
    {
        foreach (array_chunk($rows, DatabaseContractScenario::BATCH_SIZE) as $batch) {
            $values = [];
            $parameters = [];
            $types = [];
            foreach ($batch as $row) {
                $binary = $this->dialect->fromHex('?');
                $values[] = "($binary, $binary, $binary)";
                array_push($parameters, $row['productId'], $row['productVersionId'], $row['tagId']);
                array_push($types, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING);
            }
            $this->executeInsert(
                'sw_contract_product_tag',
                ['product_id', 'product_version_id', 'tag_id'],
                $values,
                $parameters,
                $types,
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function readTags(): array
    {
        $id = $this->dialect->hex(['product_tag', 'product_id']);
        $version = $this->dialect->hex(['product_tag', 'product_version_id']);
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT $id AS product_id, $version AS product_version_id, tag.name
            FROM sw_contract_product_tag product_tag
            INNER JOIN sw_contract_tag tag ON tag.id = product_tag.tag_id
            ORDER BY tag.name
            SQL);

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['product_id'] . ':' . $row['product_version_id']][] = (string) $row['name'];
        }

        return $tags;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $values
     * @param list<mixed> $parameters
     * @param list<ParameterType> $types
     */
    private function executeInsert(string $table, array $columns, array $values, array $parameters, array $types): void
    {
        if ($values === []) {
            return;
        }

        $quotedColumns = array_map($this->dialect->quoteIdentifier(...), $columns);
        $this->connection->executeStatement(
            'INSERT INTO ' . $this->dialect->quoteIdentifier($table)
            . ' (' . implode(', ', $quotedColumns) . ') VALUES ' . implode(', ', $values),
            $parameters,
            $types,
        );
    }

    private static function encodeJson(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function decodeJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (!\is_string($value)) {
            throw new \RuntimeException('The database returned a non-string JSON value');
        }

        $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new \RuntimeException('The database returned a non-object JSON value');
        }

        return $decoded;
    }
}

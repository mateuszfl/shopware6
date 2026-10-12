<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class PromotionExclusionUpdater
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * function is called when a promotion is saved.
     * the exclusions of promotions will be checked and are written/deleted if necessary
     *
     * @param array<string> $ids
     */
    public function update(array $ids): void
    {
        // if there are no ids, we don't have to do anything
        if ($ids === []) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($ids): void {
            // Exclusions are mirrored across promotions. Serialize the read/repair of both
            // sides in one deterministic lock order, including references that are removed.
            $this->connection->fetchFirstColumn('SELECT id FROM promotion ORDER BY id FOR UPDATE');
            foreach ($ids as $id) {
                // get exclusions for this id and prepare it as hex array
                $exclusions = $this->getExclusionIds($id);

                // create empty array if there are no exclusions
                $promotionExclusions = [];

                if ($exclusions !== []) {
                    $firstResult = array_shift($exclusions);
                    if (\array_key_exists('exclusion_ids', $firstResult)) {
                        // if there are exclusions, set them in array
                        $promotionExclusions = json_decode((string) ($firstResult['exclusion_ids'] ?? '[]'), null, 512, \JSON_THROW_ON_ERROR);
                    }
                }

                $this->deleteFromJSON($id, $promotionExclusions);

                // if there are no references in exclusions we don't need to update anything
                if ((is_countable($promotionExclusions) ? \count($promotionExclusions) : 0) === 0) {
                    continue;
                }

                // check for corrupted data in database. If a excluded promotion could not be found it will not be present in results
                $results = $this->getExistingIds($promotionExclusions);

                if (\count($results) === (is_countable($promotionExclusions) ? \count($promotionExclusions) : 0)) {
                    // if there is no corrupted data we will add id to all excluded promotions too
                    $this->addToJSON($id, $promotionExclusions);

                    continue;
                }

                // there is corrupted data we have to update data with only valid exclusions
                $onlyAddThisExistingIds = [];

                // converting from hex to byte ensures that case sensitivity in hex values doesn't matter
                $resultValues = $this->convertHexArrayToByteArray($results);

                // select valid excluded promotions
                foreach ($promotionExclusions as $excludedId) {
                    // if a value is not a valid hex value, we ignore this value
                    if (!Uuid::isValid((string) $excludedId)) {
                        continue;
                    }

                    // if our byte value could be found in our byte array we add hex value to our array
                    if (\in_array(Uuid::fromHexToBytes($excludedId), $resultValues, true)) {
                        $onlyAddThisExistingIds[] = $excludedId;
                    }
                }

                // write valid values to our promotion
                $this->updateJSON($id, $onlyAddThisExistingIds);

                // add exclusions to all excluded promotions too
                $this->addToJSON($id, $onlyAddThisExistingIds);
            }
        });
    }

    /**
     * deletes all referenced exclusions in all promotions that id is not in excludeThisIds
     * returns affected hex uuids
     *
     * @param array<string> $excludeThisIds
     *
     * @return array<string>
     */
    private function deleteFromJSON(string $deleteId, array $excludeThisIds): array
    {
        $affectedIds = [];
        $tags = [];
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $contains = $dialect->jsonListContains('promotion.exclusion_ids', ':value_json');
        $sqlStatement = 'SELECT sw_uuid_hex(id) as id FROM promotion WHERE ' . $contains;

        $params = ['value_json' => json_encode([$deleteId], \JSON_THROW_ON_ERROR)];

        $types = [];

        if ($excludeThisIds !== []) {
            $sqlStatement .= ' AND id NOT IN (:excludedIds)';
            $params['excludedIds'] = $this->convertHexArrayToByteArray($excludeThisIds);
            $types['excludedIds'] = ArrayParameterType::BINARY;
        }

        $results = $this->connection->executeQuery($sqlStatement, $params, $types)->fetchAllAssociative();

        if ($results === []) {
            return [];
        }

        foreach ($results as $row) {
            $affectedIds[] = Uuid::fromHexToBytes($row['id']);
            $tags[] = $row['id'];
        }

        RetryableQuery::retryable($this->connection, function () use ($affectedIds, $deleteId, $dialect, $contains): void {
            $remove = $dialect->capabilities()->supportsJsonb
                ? 'exclusion_ids - (SELECT (entry.ordinality - 1)::int FROM jsonb_array_elements(exclusion_ids) WITH ORDINALITY entry(value, ordinality) WHERE entry.value = to_jsonb(CAST(:value AS text)) ORDER BY entry.ordinality LIMIT 1)'
                : 'JSON_REMOVE(exclusion_ids, JSON_UNQUOTE(JSON_SEARCH(exclusion_ids, \'one\', :value)))';
            $this->connection->executeStatement(
                'UPDATE promotion SET exclusion_ids = ' . $remove . ' WHERE id IN (:affectedIds) AND ' . $contains,
                ['value' => $deleteId, 'affectedIds' => $affectedIds, 'value_json' => json_encode([$deleteId], \JSON_THROW_ON_ERROR)],
                ['affectedIds' => ArrayParameterType::BINARY, 'value' => ParameterType::STRING, 'value_json' => ParameterType::STRING],
            );
        });

        return $tags;
    }

    /**
     * appends addId in all promotions that id is in ids
     *
     * @param array<string> $ids
     */
    private function addToJSON(string $addId, array $ids): void
    {
        if (\count($ids) < 1) {
            return;
        }

        RetryableQuery::retryable($this->connection, function () use ($addId, $ids): void {
            $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
            $append = $dialect->capabilities()->supportsJsonb
                ? 'COALESCE(exclusion_ids, \'[]\'::jsonb) || jsonb_build_array(CAST(:value AS text))'
                : 'JSON_ARRAY_APPEND(COALESCE(exclusion_ids, JSON_ARRAY()), \'$\', :value)';
            $this->connection->executeStatement(
                'UPDATE promotion SET exclusion_ids = ' . $append . '
                 WHERE id IN (:addToTheseIds) AND NOT COALESCE(' . $dialect->jsonListContains('exclusion_ids', ':value_json') . ', FALSE)',
                [
                    'value' => $addId,
                    'value_json' => json_encode([$addId], \JSON_THROW_ON_ERROR),
                    'addToTheseIds' => $this->convertHexArrayToByteArray($ids),
                ],
                [
                    'addToTheseIds' => ArrayParameterType::BINARY,
                    'value' => ParameterType::STRING,
                    'value_json' => ParameterType::STRING,
                ]
            );
        });
    }

    /**
     * sets all ids in onlyAddThisExistingIds as exclusion in promotion with id
     *
     * @param array<string> $onlyAddThisExistingIds
     */
    private function updateJSON(string $id, array $onlyAddThisExistingIds): void
    {
        $value = '[]';
        if ($onlyAddThisExistingIds !== []) {
            $value = json_encode($onlyAddThisExistingIds, \JSON_THROW_ON_ERROR);
        }

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE promotion SET exclusion_ids=:value WHERE id=:id'),
            ['id' => ParameterType::BINARY, 'value' => ParameterType::STRING],
        );

        $query->execute([
            'value' => $value,
            'id' => Uuid::fromHexToBytes($id),
        ]);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getExistingIds(array $ids): array
    {
        $sqlStatement = 'SELECT sw_uuid_hex(id) as uuid FROM promotion WHERE id IN (:ids)';

        $bytes = $this->convertHexArrayToByteArray($ids);

        $params = ['ids' => $bytes];

        $type = ['ids' => ArrayParameterType::BINARY];

        $rows = $this->connection
            ->executeQuery($sqlStatement, $params, $type)
            ->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = $row['uuid'];
        }

        return $results;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function getExclusionIds(string $id): array
    {
        if (!Uuid::isValid($id)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();
        $query->select('exclusion_ids');
        $query->from(PromotionDefinition::ENTITY_NAME);
        $query->andWhere($query->expr()->eq('id', ':id'));

        $query->setParameter('id', Uuid::fromHexToBytes($id), ParameterType::BINARY);

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<string> $hexIds
     *
     * @return array<string>
     */
    private function convertHexArrayToByteArray(array $hexIds): array
    {
        if ($hexIds === []) {
            return [];
        }

        $validValues = array_values(array_filter($hexIds, static fn ($hexId) => Uuid::isValid($hexId)));

        if ($validValues === []) {
            return [];
        }

        $bytes = array_map(static fn (string $id) => Uuid::fromHexToBytes($id), $validValues);

        return $bytes;
    }
}

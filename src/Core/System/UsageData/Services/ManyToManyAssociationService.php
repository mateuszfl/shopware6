<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('data-services')]
class ManyToManyAssociationService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param ManyToManyAssociationField[] $associationFields
     * @param array<int, array<string, string>> $primaryKeys
     *
     * @return array<string, array<int|string, array<int, string>>>
     */
    public function getMappingIdsForAssociationFields(array $associationFields, array $primaryKeys, string $primaryKeyName): array
    {
        $mappingIds = [];
        foreach ($associationFields as $associationField) {
            $propertyName = $associationField->getPropertyName();
            $mappingIds[$propertyName] = [];

            $mappingDefinition = $associationField->getMappingDefinition();
            $localColumn = $associationField->getMappingLocalColumn();
            $referenceColumn = $associationField->getMappingReferenceColumn();
            $primaryKeyFields = $mappingDefinition->getPrimaryKeys()->getElements();

            $queryBuilder = (new QueryBuilder($this->connection))
                ->select(EntityDefinitionQueryHelper::escape($referenceColumn, $this->connection), EntityDefinitionQueryHelper::escape($localColumn, $this->connection))
                ->from(EntityDefinitionQueryHelper::escape($mappingDefinition->getEntityName(), $this->connection))
                ->where(EntityDefinitionQueryHelper::escape($localColumn, $this->connection) . ' IN (:ids)');

            $entityPrimaryKeyIds = [];
            foreach (array_column($primaryKeys, $primaryKeyName) as $value) {
                $entityPrimaryKeyIds[] = Uuid::fromHexToBytes($value);
            }

            $queryBuilder->setParameter('ids', $entityPrimaryKeyIds, ArrayParameterType::BINARY);
            $queryBuilder = $this->addReferenceVersionFieldConstraint($queryBuilder, $primaryKeyFields);

            $manyToManyMappingQueryResult = $queryBuilder->executeQuery()->fetchAllAssociative();
            foreach ($manyToManyMappingQueryResult as $mappingData) {
                $mappingIds[$propertyName][Uuid::fromHexToBytes(Uuid::fromBytesToHex($mappingData[$localColumn]))][] = Uuid::fromBytesToHex($mappingData[$referenceColumn]);
            }
        }

        return $mappingIds;
    }

    /**
     * @param array<Field> $primaryKeys
     */
    private function addReferenceVersionFieldConstraint(QueryBuilder $queryBuilder, array $primaryKeys): QueryBuilder
    {
        $hasReferenceVersionFields = false;

        foreach ($primaryKeys as $primaryKey) {
            if ($primaryKey instanceof ReferenceVersionField) {
                $hasReferenceVersionFields = true;
                $queryBuilder->andWhere(EntityDefinitionQueryHelper::escape($primaryKey->getStorageName(), $this->connection) . ' = :versionId');
            }
        }

        if ($hasReferenceVersionFields) {
            $queryBuilder->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY);
        }

        return $queryBuilder;
    }
}

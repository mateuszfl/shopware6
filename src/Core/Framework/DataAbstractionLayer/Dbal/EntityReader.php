<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AsArray;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal\EntityReaderTest
 */
#[Package('framework')]
class EntityReader implements EntityReaderInterface
{
    final public const INTERNAL_MAPPING_STORAGE = 'internal_mapping_storage';
    final public const FOREIGN_KEYS = 'foreignKeys';

    /**
     * Marks temporary queries that select limited ids for to-many associations.
     *
     * EntityReader sets this state before applying association criteria in loadManyToManyWithCriteria() and
     * fetchPaginatedOneToManyMapping(). Those queries are later wrapped as subqueries to enforce per-parent limits.
     * They intentionally do not add their own GROUP BY, so CriteriaQueryBuilder must keep ORDER BY expressions
     * unaggregated even when filters add to-many joins and mark the query as grouped.
     */
    final public const TO_MANY_ASSOCIATION_LIMIT_QUERY = 'to_many_association_limit_query';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityHydrator $hydrator,
        private readonly EntityDefinitionQueryHelper $queryHelper,
        private readonly SqlQueryParser $parser,
        private readonly CriteriaQueryBuilder $criteriaQueryBuilder,
        private readonly LoggerInterface $logger,
        private readonly CriteriaFieldsResolver $criteriaFieldsResolver
    ) {
    }

    /**
     * @return EntityCollection<Entity>
     */
    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        $criteria->resetSorting();
        $criteria->resetQueries();

        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $definition->getCollectionClass();

        $fieldsForPartialLoading = $this->criteriaFieldsResolver->resolve($criteria, $definition);

        if ($criteria->getExcludedFields() !== []) {
            $this->assertExcludableFields($definition, $criteria->getExcludedFields());
        }

        return $this->_read(
            $criteria,
            $definition,
            $context,
            new $collectionClass(),
            $definition->getFields()->getBasicFields(),
            true,
            $fieldsForPartialLoading,
            $fieldsForPartialLoading !== [],
        );
    }

    protected function getParser(): SqlQueryParser
    {
        return $this->parser;
    }

    /**
     * Rejects unknown fields and Required/WriteProtected fields — they back non-nullable entity
     * properties that must not be left unset. The actual omission happens in {@see joinBasic()}.
     *
     * @param list<string> $excludedFields
     */
    private function assertExcludableFields(EntityDefinition $definition, array $excludedFields): void
    {
        foreach ($excludedFields as $propertyName) {
            $field = $definition->getFields()->get($propertyName);

            if ($field === null) {
                throw DataAbstractionLayerException::cannotExcludeUnknownField($propertyName, $definition->getEntityName());
            }

            if ($field->is(Required::class)) {
                throw DataAbstractionLayerException::fieldCannotBeExcluded($propertyName, $definition->getEntityName(), 'it is required');
            }

            if ($field->is(WriteProtected::class)) {
                throw DataAbstractionLayerException::fieldCannotBeExcluded($propertyName, $definition->getEntityName(), 'it is write-protected and maps to a non-nullable property that would be left uninitialized');
            }
        }
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     *
     * @return EntityCollection<Entity>
     */
    private function _read(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        EntityCollection $collection,
        FieldCollection $fields,
        bool $performEmptySearch,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): EntityCollection {
        $hasFilters = $criteria->getFilters() !== [] || $criteria->getPostFilters() !== [];
        $hasIds = $criteria->getIds() !== [];

        if (!$performEmptySearch && !$hasFilters && !$hasIds) {
            return $collection;
        }

        // Do not re-use `$isPartialLoading` here, as this method could be called for associations
        // and only the initial call is relevant for marking the whole read as partial
        if ($fieldsForPartialLoading !== []) {
            $fields = $definition->getFields()->filter(static function (Field $field) use (&$fieldsForPartialLoading) {
                if ($field->getFlag(PrimaryKey::class)) {
                    $fieldsForPartialLoading[$field->getPropertyName()] = [];

                    return true;
                }

                return isset($fieldsForPartialLoading[$field->getPropertyName()]);
            });
        }

        // always add the criteria fields to the collection, otherwise we have conflicts between criteria.fields and criteria.association logic
        $fields = $this->addAssociationFieldsToCriteria($criteria, $definition, $fields);

        if ($definition->isInheritanceAware() && $criteria->hasAssociation('parent')) {
            throw DataAbstractionLayerException::parentAssociationCannotBeFetched();
        }

        $rows = $this->fetch($criteria, $definition, $context, $fields, $fieldsForPartialLoading);

        $collection = $this->hydrator->hydrate(
            $collection,
            $definition->getEntityClass(),
            $definition,
            $rows,
            $definition->getEntityName(),
            $context,
            $fieldsForPartialLoading,
        );

        $collection = $this->fetchAssociations(
            $criteria,
            $definition,
            $context,
            $collection,
            $fields,
            $fieldsForPartialLoading,
            $isPartialLoading,
        );

        $hasIds = $criteria->getIds() !== [];
        if ($hasIds && $criteria->getSorting() === []) {
            $collection->sortByIdArray($criteria->getIds());
        }

        return $collection;
    }

    /**
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function joinBasic(
        EntityDefinition $definition,
        Context $context,
        string $root,
        QueryBuilder $query,
        FieldCollection $fields,
        ?Criteria $criteria = null,
        array $fieldsForPartialLoading = [],
    ): void {
        $isPartial = $fieldsForPartialLoading !== [];
        $excludedFields = $criteria?->getExcludedFields() ?? [];
        $filtered = $fields->filter(static function (Field $field) use ($isPartial, $fieldsForPartialLoading) {
            if ($field->is(Runtime::class)) {
                return false;
            }

            if (!$isPartial || $field->getFlag(PrimaryKey::class)) {
                return true;
            }

            return isset($fieldsForPartialLoading[$field->getPropertyName()]);
        });

        $parentAssociation = null;

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            $parentAssociation = $definition->getFields()->get('parent');

            if ($parentAssociation !== null) {
                $this->queryHelper->resolveField($parentAssociation, $definition, $root, $query, $context);
            }
        }

        $addTranslation = false;

        foreach ($filtered as $field) {
            // skip fields excluded via Criteria::excludeFields() (primary keys are always kept)
            if ($excludedFields !== [] && !$field->getFlag(PrimaryKey::class) && \in_array($field->getPropertyName(), $excludedFields, true)) {
                continue;
            }

            // translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                $addTranslation = true;

                continue;
            }

            // self references can not be resolved if set to autoload, otherwise we get an endless loop
            if (!$field instanceof ParentAssociationField
                && $field instanceof AssociationField
                && $field->getAutoload()
                && $field->getReferenceDefinition() === $definition
            ) {
                continue;
            }

            // many-to-one associations can be directly fetched in same query
            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $reference = $field->getReferenceDefinition();

                $basics = $reference->getFields()->getBasicFields();

                $this->queryHelper->resolveField($field, $definition, $root, $query, $context);

                $fieldPropertyName = $field->getPropertyName();
                $alias = $root . '.' . $fieldPropertyName;

                $joinCriteria = null;
                if ($criteria && $criteria->hasAssociation($fieldPropertyName)) {
                    $joinCriteria = $criteria->getAssociation($fieldPropertyName);
                    $basics = $this->addAssociationFieldsToCriteria($joinCriteria, $reference, $basics);
                }

                $referenceField = $reference->getFields()->getByStorageName($field->getReferenceField());
                if ($isPartial && $referenceField
                    && !isset($fieldsForPartialLoading[$fieldPropertyName][$referenceField->getPropertyName()])
                ) {
                    $fieldsForPartialLoading[$fieldPropertyName] ??= [];
                    $fieldsForPartialLoading[$fieldPropertyName][$referenceField->getPropertyName()] = [];
                }

                $this->joinBasic(
                    $reference,
                    $context,
                    $alias,
                    $query,
                    $basics,
                    $joinCriteria,
                    $fieldsForPartialLoading[$field->getPropertyName()] ?? [],
                );

                continue;
            }

            // add sub select for many to many field
            if ($field instanceof ManyToManyAssociationField) {
                /**
                 * When the association is filtered or sorted we do a seperate query to load the ids of the association.
                 * Therefore we do not need to add the select here to the main query.
                 *
                 * @see self::loadManyToManyWithCriteria()
                 */
                if ($this->isAssociationRestricted($criteria, $field->getPropertyName())) {
                    continue;
                }

                /**
                 * When the association is not filtered we select the ids of the association directly in the main query.
                 *
                 * @see self::loadManyToManyOverExtension()
                 */
                $this->addManyToManySelect($definition, $root, $field, $query, $context);

                continue;
            }

            // other associations like OneToManyAssociationField fetched lazy by additional query
            if ($field instanceof AssociationField) {
                continue;
            }

            if ($parentAssociation !== null
                && $field instanceof StorageAware
                && $field->is(Inherited::class)
                && $context->considerInheritance()
            ) {
                $parentAlias = $root . '.' . $parentAssociation->getPropertyName();

                // contains the field accessor for the child value (eg. `product.name`.`name`)
                $childAccessor = $this->quote($root) . '.'
                    . $this->quote($field->getStorageName());

                // contains the field accessor for the parent value (eg. `product.parent`.`name`)
                $parentAccessor = $this->quote($parentAlias) . '.'
                    . $this->quote($field->getStorageName());

                // contains the alias for the resolved field (eg. `product.name`)
                $fieldAlias = $this->quote($root . '.' . $field->getPropertyName());

                if ($field instanceof JsonField) {
                    // merged in hydrator
                    $parentFieldAlias = $this->quote($root . '.' . $field->getPropertyName() . '.inherited');
                    $query->addSelect(\sprintf('%s as %s', $parentAccessor, $parentFieldAlias));
                }
                // add selection for resolved parent-child inheritance field
                $query->addSelect(\sprintf('COALESCE(%s, %s) as %s', $childAccessor, $parentAccessor, $fieldAlias));

                continue;
            }

            // all other StorageAware fields are stored inside the main entity
            if ($field instanceof StorageAware) {
                $query->addSelect(
                    $this->quote($root) . '.'
                    . $this->quote($field->getStorageName()) . ' as '
                    . $this->quote($root . '.' . $field->getPropertyName())
                );
            }
        }

        if ($addTranslation) {
            $this->queryHelper->addTranslationSelect(
                $root,
                $definition,
                $query,
                $context,
                $fieldsForPartialLoading,
                $excludedFields,
            );
        }
    }

    /**
     * @param array<string, mixed> $fieldsForPartialLoading
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        FieldCollection $fields,
        array $fieldsForPartialLoading = [],
    ): array {
        $table = $definition->getEntityName();

        $query = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->connection),
            $definition,
            $criteria,
            $context
        );

        $this->joinBasic(
            $definition,
            $context,
            $table,
            $query,
            $fields,
            $criteria,
            $fieldsForPartialLoading,
        );

        if ($criteria->getIds() !== []) {
            $this->queryHelper->addIdCondition($criteria, $definition, $query);
        }

        $this->queryHelper->addGroupBy($definition, $criteria, $context, $query, $table);

        if ($query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN) && $criteria->getGroupFields() === []) {
            // PostgreSQL cannot infer functional dependencies across to-one joins. Group the selected non-score
            // values as well as the complete root primary key when a to-many sorting introduces duplicate rows.
            foreach ($query->getSelectParts() as $select) {
                if (str_ends_with($select, ' as ' . Criteria::SCORE_FIELD)) {
                    continue;
                }
                $alias = strrpos($select, ' as ');
                $query->addGroupBy($alias === false ? $select : substr($select, $alias + 4));
            }
        }

        foreach ($definition->getPrimaryKeys() as $primaryKey) {
            if ($primaryKey instanceof StorageAware) {
                $query->addOrderBy($this->quote($table) . '.' . $this->quote($primaryKey->getStorageName()));
            }
        }

        if ($criteria->getTitle()) {
            $query->setTitle($criteria->getTitle() . '::read');
        }

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadManyToMany(
        Criteria $criteria,
        EntityDefinition $definition,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        $associationCriteria = $criteria->getAssociation($association->getPropertyName());

        if (!$associationCriteria->getTitle() && $criteria->getTitle()) {
            $associationCriteria->setTitle(
                $criteria->getTitle() . '::association::' . $association->getPropertyName()
            );
        }

        // check if the requested criteria is restricted (limit, offset, sorting, filtering)
        if ($this->isAssociationRestricted($criteria, $association->getPropertyName())) {
            // if restricted load paginated list of many to many
            $this->loadManyToManyWithCriteria(
                $definition,
                $associationCriteria,
                $association,
                $context,
                $collection,
                $fieldsForPartialLoading,
                $isPartialLoading,
            );

            return;
        }

        // otherwise the association is loaded in the root query of the entity as sub select which contains all ids
        // the ids are extracted in the entity hydrator (see: \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::extractManyToManyIds)
        $this->loadManyToManyOverExtension(
            $associationCriteria,
            $association,
            $context,
            $collection,
            $fieldsForPartialLoading,
            $isPartialLoading,
        );
    }

    private function addManyToManySelect(
        EntityDefinition $definition,
        string $root,
        ManyToManyAssociationField $field,
        QueryBuilder $query,
        Context $context
    ): void {
        $mapping = $field->getMappingDefinition();

        $versionCondition = '';
        if ($mapping->isVersionAware() && $definition->isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionField = $definition->getEntityName() . '_version_id';
            $versionCondition = ' AND #alias#.' . $versionField . ' = #root#.version_id';
        }

        $source = $this->quote($root) . '.' . $this->quote($field->getLocalField());
        if ($field->is(Inherited::class) && $context->considerInheritance()) {
            $source = $this->quote($root) . '.' . $this->quote($field->getPropertyName());
        }

        $parameters = [
            '#alias#' => $this->quote($root . '.' . $field->getPropertyName() . '.mapping'),
            '#mapping_reference_column#' => $this->quote($field->getMappingReferenceColumn()),
            '#mapping_table#' => $this->quote($mapping->getEntityName()),
            '#mapping_local_column#' => $this->quote($field->getMappingLocalColumn()),
            '#root#' => $this->quote($root),
            '#source#' => $source,
            '#property#' => $this->quote($root . '.' . $field->getPropertyName() . '.id_mapping'),
        ];

        $aggregate = (new DatabasePlatformResolver($this->connection))->dialect()->hexAggregate([
            $root . '.' . $field->getPropertyName() . '.mapping',
            $field->getMappingReferenceColumn(),
        ]);
        $separator = 'mappingSeparator' . Hasher::hash($root . '.' . $field->getPropertyName());
        $query->setParameter($separator, $aggregate->parameters[0], $aggregate->types[0]);
        $parameters['#aggregate#'] = str_replace('?', ':' . $separator, $aggregate->sql);

        $query->addSelect(
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '(SELECT #aggregate#
                  FROM #mapping_table# #alias#
                  WHERE #alias#.#mapping_local_column# = #source#'
                  . $versionCondition
                  . ' ) as #property#'
            )
        );
    }

    /**
     * @param EntityCollection<Entity> $collection
     *
     * @return array<string>
     */
    private function collectManyToManyIds(EntityCollection $collection, AssociationField $association): array
    {
        $ids = [];
        $property = $association->getPropertyName();
        foreach ($collection as $struct) {
            $ext = $struct->getExtension(self::INTERNAL_MAPPING_STORAGE);
            if (!$ext instanceof ArrayStruct) {
                continue;
            }
            foreach ($ext->get($property) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadOneToMany(
        Criteria $criteria,
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        $fieldCriteria = new Criteria();
        if ($criteria->hasAssociation($association->getPropertyName())) {
            $fieldCriteria = $criteria->getAssociation($association->getPropertyName());
        }

        if (!$fieldCriteria->getTitle() && $criteria->getTitle()) {
            $fieldCriteria->setTitle(
                $criteria->getTitle() . '::association::' . $association->getPropertyName()
            );
        }

        // association should not be paginated > load data over foreign key condition
        if ($fieldCriteria->getLimit() === null && ($fieldCriteria->getOffset() ?? 0) === 0) {
            $this->loadOneToManyWithoutPagination(
                $definition,
                $association,
                $context,
                $collection,
                $fieldCriteria,
                $fieldsForPartialLoading,
                $isPartialLoading,
            );

            return;
        }

        // load association paginated > use internal counter loops
        $this->loadOneToManyWithPagination(
            $definition,
            $association,
            $context,
            $collection,
            $fieldCriteria,
            $fieldsForPartialLoading,
            $isPartialLoading,
        );
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadOneToManyWithoutPagination(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        $ref = $association->getReferenceDefinition()->getFields()->getByStorageName(
            $association->getReferenceField()
        );

        if (!$ref instanceof Field) {
            throw DataAbstractionLayerException::referenceFieldByStorageNameNotFound(
                $association->getReferenceDefinition()->getEntityName(),
                $association->getReferenceField()
            );
        }

        $propertyName = $ref->getPropertyName();
        if ($association instanceof ChildrenAssociationField) {
            $propertyName = 'parentId';
        }

        // build orm property accessor to add field sortings and conditions `customer_address.customerId`
        $propertyAccessor = $association->getReferenceDefinition()->getEntityName() . '.' . $propertyName;

        $ids = array_values($collection->getIds());

        $isInheritanceAware = $definition->isInheritanceAware() && $context->considerInheritance();

        if ($isInheritanceAware) {
            $parentIds = array_values(\array_filter($collection->map(static fn (Entity $entity) => $entity->get('parentId'))));

            $ids = array_unique([...$ids, ...$parentIds]);
        }

        $fieldCriteria->addFilter(new EqualsAnyFilter($propertyAccessor, $ids));

        $referenceClass = $association->getReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        if ($isPartialLoading) {
            // Make sure our collection index will be loaded
            $fieldsForPartialLoading[$propertyName] = [];
            $collectionClass = EntityCollection::class;
        }

        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            new $collectionClass(),
            $referenceClass->getFields()->getBasicFields(),
            false,
            $fieldsForPartialLoading,
            $isPartialLoading,
        );

        $grouped = [];
        foreach ($data as $entity) {
            $fk = $entity->get($propertyName);

            $grouped[$fk][] = $entity;
        }

        // assign loaded data to root entities
        foreach ($collection as $entity) {
            $structData = new $collectionClass();
            if (isset($grouped[$entity->getUniqueIdentifier()])) {
                $structData->fill($grouped[$entity->getUniqueIdentifier()]);
            }

            // assign data of child immediately
            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
            } else {
                if ($association->is(AsArray::class)) {
                    $structData = $structData->getElements();
                }

                // otherwise the data will be assigned directly as properties
                $entity->assign([$association->getPropertyName() => $structData]);
            }

            if (!$association->is(Inherited::class) || \count($structData) > 0 || !$context->considerInheritance()) {
                continue;
            }

            // if association can be inherited by the parent and the struct data is empty, filter again for the parent id
            $structData = new $collectionClass();
            if (isset($grouped[$entity->get('parentId')])) {
                $structData->fill($grouped[$entity->get('parentId')]);
            }

            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);

                continue;
            }

            if ($association->is(AsArray::class)) {
                $structData = $structData->getElements();
            }

            $entity->assign([$association->getPropertyName() => $structData]);
        }
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadOneToManyWithPagination(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        // Do not re-use `$isPartialLoading` here, as this method could be called for associations
        // and only the initial call is relevant for marking the whole read as partial
        if ($fieldsForPartialLoading !== []) {
            // Make sure our collection index will be loaded
            $fieldsForPartialLoading[$association->getPropertyName()] = [];
        }

        $ids = array_values($collection->getIds());

        $isInheritanceAware = $definition->isInheritanceAware() && $context->considerInheritance();
        if ($isInheritanceAware) {
            $parentIds = array_values(\array_filter($collection->map(static fn (Entity $entity) => $entity->get('parentId'))));

            $ids = array_unique([...$ids, ...$parentIds]);
        }

        $fieldCriteria->addFilter(
            new EqualsAnyFilter($this->buildOneToManyPropertyAccessor($definition, $association), $ids)
        );

        $mapping = $this->fetchPaginatedOneToManyMapping($definition, $association, $context, $collection, $fieldCriteria);

        $ids = [];
        foreach ($mapping as $associationIds) {
            foreach ($associationIds as $associationId) {
                $ids[] = $associationId;
            }
        }

        $filteredIds = \array_filter($ids);
        if ($filteredIds !== []) {
            $fieldCriteria->setIds($filteredIds);
        }

        $fieldCriteria->resetSorting();
        $fieldCriteria->resetFilters();
        $fieldCriteria->resetPostFilters();

        $referenceClass = $association->getReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        $data = $this->_read(
            $fieldCriteria,
            $referenceClass,
            $context,
            new $collectionClass(),
            $referenceClass->getFields()->getBasicFields(),
            false,
            $fieldsForPartialLoading,
            $isPartialLoading,
        );

        // assign loaded reference collections to root entities
        foreach ($collection as $entity) {
            // extract mapping ids for the current entity
            $mappingIds = $mapping[$entity->getUniqueIdentifier()] ?? [];

            $structData = $data->getList($mappingIds);

            // assign data of child immediately
            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
            } else {
                if ($association->is(AsArray::class)) {
                    $structData = $structData->getElements();
                }

                $entity->assign([$association->getPropertyName() => $structData]);
            }

            if (!$association->is(Inherited::class) || \count($structData) || !$context->considerInheritance()) {
                continue;
            }

            $parentId = $entity->get('parentId');

            if ($parentId === null) {
                continue;
            }

            // extract mapping ids for the current entity
            $mappingIds = $mapping[$parentId];

            $structData = $data->getList($mappingIds);

            // assign data of child immediately
            if ($association->is(Extension::class)) {
                $entity->addExtension($association->getPropertyName(), $structData);
            } else {
                if ($association->is(AsArray::class)) {
                    $structData = $structData->getElements();
                }

                $entity->assign([$association->getPropertyName() => $structData]);
            }
        }
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadManyToManyOverExtension(
        Criteria $criteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        // collect all ids of many-to-many association which already stored inside the struct instances
        $ids = $this->collectManyToManyIds($collection, $association);

        $referenceClass = $association->getToManyReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        if ($criteria->getIds() !== []) {
            $ids = array_values(array_intersect($ids, $criteria->getIds()));
        }

        if ($ids !== []) {
            $criteria->setIds($ids);

            $data = $this->_read(
                $criteria,
                $referenceClass,
                $context,
                new $collectionClass(),
                $referenceClass->getFields()->getBasicFields(),
                false,
                $fieldsForPartialLoading,
                $isPartialLoading,
            );
        } else {
            $data = new $collectionClass();
        }

        foreach ($collection as $struct) {
            $extension = $struct->getExtension(self::INTERNAL_MAPPING_STORAGE);
            if (!$extension instanceof ArrayStruct) {
                continue;
            }

            $fks = $extension->get($association->getPropertyName()) ?? [];

            // use assign function to avoid setter name building
            $structData = $data->getList($fks);

            // if the association is added as extension (for plugins), we have to add the data as extension
            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
            } else {
                if ($association->is(AsArray::class)) {
                    $structData = $structData->getElements();
                }

                $struct->assign([$association->getPropertyName() => $structData]);
            }
        }
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadManyToManyWithCriteria(
        EntityDefinition $definition,
        Criteria $fieldCriteria,
        ManyToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        $fields = $association->getToManyReferenceDefinition()->getFields();
        $reference = null;
        foreach ($fields as $field) {
            if (!$field instanceof ManyToManyAssociationField) {
                continue;
            }

            if ($field->getReferenceDefinition() !== $association->getReferenceDefinition()) {
                continue;
            }

            $reference = $field;

            break;
        }

        if (!$reference) {
            throw DataAbstractionLayerException::noInverseAssociationFound($association->getPropertyName());
        }

        $root = $this->quote(
            $association->getToManyReferenceDefinition()->getEntityName() . '.' . $reference->getPropertyName() . '.association_mapping'
        );

        $query = new QueryBuilder($this->connection);
        // To-many criteria can add joins that mark the query as grouped. In that case the order by parts are executed
        // with MIN/MAX aggregation, but this limit query is moved into a subquery without its own group by.
        // The state prevents aggregate sorting for those temporary to-many association limit queries.
        $query->addState(self::TO_MANY_ASSOCIATION_LIMIT_QUERY);

        $query = $this->criteriaQueryBuilder->build(
            $query,
            $association->getToManyReferenceDefinition(),
            $fieldCriteria,
            $context
        );

        $localColumn = $this->quote($association->getMappingLocalColumn());
        $referenceColumn = $this->quote($association->getMappingReferenceColumn());

        $condition = $root . '.' . $referenceColumn . ' = '
            . $this->quote($association->getToManyReferenceDefinition()->getEntityName()) . '.id';

        if (str_ends_with($association->getMappingReferenceColumn(), '_id')) {
            $referenceVersionColumn = u($association->getMappingReferenceColumn())->trimSuffix('_id')->append('_version_id')->toString();
        } else {
            $referenceVersionColumn = $association->getMappingReferenceColumn() . '_version_id';
        }

        if ($association->getToManyReferenceDefinition()->isVersionAware()
            && $association->getMappingDefinition()->getField($referenceVersionColumn)
        ) {
            $condition .= ' AND ' . $root . '.' . $this->quote($referenceVersionColumn) . ' = '
                . $this->quote($association->getToManyReferenceDefinition()->getEntityName()) . '.version_id';
        }

        $query
            ->leftJoin(
                $this->quote($association->getToManyReferenceDefinition()->getEntityName()),
                $this->quote($association->getMappingDefinition()->getEntityName()),
                $root,
                $condition
            );

        if (!$association->is(Inherited::class) || !$context->considerInheritance()) {
            $query->andWhere($root . '.' . $localColumn . ' IN (:localIds)');
            $query->setParameter('localIds', Uuid::fromHexToBytesList($collection->getIds()), ArrayParameterType::BINARY);
        } else {
            // When the association is inherited, we need to join the base entity to the local table
            // the "join column" (column name = property name) contains the id of the parent entity if the association is inherited
            $joinCondition = $root . '.' . $localColumn . ' = '
                . $this->quote($definition->getEntityName()) . '.'
                . $this->quote($association->getPropertyName());

            if ($definition->isVersionAware()) {
                $joinCondition .= ' AND ' . $root . '.'
                    . $this->quote($definition->getEntityName() . '_version_id') . ' = '
                    . $this->quote($definition->getEntityName()) . '.version_id';
            }
            $query->innerJoin(
                $root,
                $this->quote($definition->getEntityName()),
                $this->quote($definition->getEntityName()),
                $joinCondition
            );

            $query->andWhere($this->quote($definition->getEntityName()) . '.id IN (:localIds)');
            $query->setParameter('localIds', Uuid::fromHexToBytesList($collection->getIds()), ArrayParameterType::BINARY);
        }

        if ($definition->isVersionAware() && $association->is(CascadeDelete::class)) {
            $inherited = $association->is(Inherited::class) && $context->considerInheritance();
            $ownerId = $inherited ? $this->quote($definition->getEntityName()) . '.id' : $root . '.' . $localColumn;
            $ownerVersion = $inherited ? $this->quote($definition->getEntityName()) . '.version_id' : $root . '.' . $this->quote($definition->getEntityName() . '_version_id');
            $versions = [];
            foreach (array_values($collection->getElements()) as $index => $entity) {
                $versions[] = '(' . $ownerId . ' = :mappingId' . $index . ' AND ' . $ownerVersion . ' = :mappingVersion' . $index . ')';
                $query->setParameter('mappingId' . $index, Uuid::fromHexToBytes($entity->getUniqueIdentifier()), ParameterType::BINARY);
                $query->setParameter('mappingVersion' . $index, Uuid::fromHexToBytes($entity->get('versionId')), ParameterType::BINARY);
            }
            $query->andWhere('(' . implode(' OR ', $versions) . ')');
        }

        $scoreExpression = null;
        foreach ($query->getSelectParts() as $select) {
            if (str_ends_with($select, ' as ' . Criteria::SCORE_FIELD)) {
                $scoreExpression = substr($select, 0, -\strlen(' as ' . Criteria::SCORE_FIELD));
            }
        }
        $orderBy = $query->getOrderByPairs();
        $query->resetOrderBy();
        $query->select($root . '.' . $localColumn . ' as local_id', $root . '.' . $referenceColumn . ' as reference_id');
        if ($scoreExpression !== null || $query->hasState(CriteriaQueryBuilder::COUNT_SORTING)) {
            if ($scoreExpression !== null) {
                $query->addSelect($scoreExpression . ' as ' . Criteria::SCORE_FIELD);
            }
            $query->addGroupBy($root . '.' . $localColumn, $root . '.' . $referenceColumn);
            foreach ($association->getToManyReferenceDefinition()->getPrimaryKeys() as $primary) {
                if ($primary instanceof StorageAware) {
                    $query->addGroupBy($this->quote($association->getToManyReferenceDefinition()->getEntityName()) . '.' . $this->quote($primary->getStorageName()));
                }
            }
        }

        $windowOrder = [];
        foreach ($orderBy as $index => [$expression, $direction]) {
            if ($expression === Criteria::SCORE_FIELD && $scoreExpression !== null) {
                $expression = $scoreExpression;
            }
            $query->addSelect($expression . ' as sort_' . $index);
            $windowOrder[] = 'sort_' . $index . ' ' . $direction;
        }
        $windowOrder[] = 'reference_id';

        // Choose the first sorted joined row for each relation before counting per-parent slots. A DISTINCT
        // projection is insufficient when a nested to-many sorting contributes different values for one relation.
        $deduplicated = new QueryBuilder($this->connection);
        $deduplicated->select('filtered_mapping.*', 'ROW_NUMBER() OVER (PARTITION BY local_id, reference_id ORDER BY ' . implode(', ', $windowOrder) . ') as duplicate_position');
        $deduplicated->from('(' . $query->getSQL() . ')', 'filtered_mapping');

        $ranked = new QueryBuilder($this->connection);
        $ranked->select('local_id', 'reference_id', 'ROW_NUMBER() OVER (PARTITION BY local_id ORDER BY ' . implode(', ', $windowOrder) . ') as position');
        $ranked->from('(' . $deduplicated->getSQL() . ')', 'unique_mapping');
        $ranked->andWhere('duplicate_position = 1');

        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $wrapper = new QueryBuilder($this->connection);
        $wrapper->select($dialect->hex(['mapping', 'local_id']) . ' as ' . $this->quote('key'), $dialect->hex(['mapping', 'reference_id']) . ' as value');
        $wrapper->from('(' . $ranked->getSQL() . ')', 'mapping');
        $wrapper->andWhere('position > :associationOffset');
        $wrapper->setParameter('associationOffset', $fieldCriteria->getOffset() ?? 0, ParameterType::INTEGER);
        if ($fieldCriteria->getLimit() !== null) {
            $wrapper->andWhere('position <= :associationEnd');
            $wrapper->setParameter('associationEnd', ($fieldCriteria->getOffset() ?? 0) + $fieldCriteria->getLimit(), ParameterType::INTEGER);
        }
        $wrapper->orderBy('local_id');
        $wrapper->addOrderBy('position');
        foreach ($query->getParameters() as $key => $value) {
            $wrapper->setParameter($key, $value, $query->getParameterType($key));
        }

        $mapping = [];
        $ids = [];
        foreach ($wrapper->executeQuery()->fetchAllAssociative() as $row) {
            $mapping[$row['key']][] = $row['value'];
            $ids[] = $row['value'];
        }
        // The per-parent mapping determines the order of the hydrated collections.
        $fieldCriteria->resetSorting();

        $referenceClass = $association->getToManyReferenceDefinition();
        /** @var EntityCollection<Entity> $collectionClass */
        $collectionClass = $referenceClass->getCollectionClass();

        if ($fieldCriteria->getIds() !== []) {
            $ids = array_values(array_intersect($ids, $fieldCriteria->getIds()));
        }

        if ($ids !== []) {
            // only read data when we have found mapped IDs
            // otherwise we would load the whole reference table
            $fieldCriteria->setIds($ids);

            $data = $this->_read(
                $fieldCriteria,
                $referenceClass,
                $context,
                new $collectionClass(),
                $referenceClass->getFields()->getBasicFields(),
                false,
                $fieldsForPartialLoading,
                $isPartialLoading,
            );
        } else {
            $data = new $collectionClass();
        }

        foreach ($collection as $struct) {
            $structData = new $collectionClass();

            $id = $struct->getUniqueIdentifier();

            $parentId = $struct->has('parentId') ? $struct->get('parentId') : '';

            if (\array_key_exists($struct->getUniqueIdentifier(), $mapping)) {
                // filter mapping list of whole data array
                $structData = $data->getList($mapping[$id]);

                // sort list by ids if the criteria contained a sorting
                $structData->sortByIdArray($mapping[$id]);
            } elseif (\array_key_exists($parentId, $mapping) && $association->is(Inherited::class) && $context->considerInheritance()) {
                // filter mapping for the inherited parent association
                $structData = $data->getList($mapping[$parentId]);

                // sort list by ids if the criteria contained a sorting
                $structData->sortByIdArray($mapping[$parentId]);
            }

            // if the association is added as extension (for plugins), we have to add the data as extension
            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
            } else {
                if ($association->is(AsArray::class)) {
                    $structData = $structData->getElements();
                }

                $struct->assign([$association->getPropertyName() => $structData]);
            }
        }
    }

    /**
     * @param EntityCollection<Entity> $collection
     *
     * @return array<string, string[]>
     */
    private function fetchPaginatedOneToManyMapping(
        EntityDefinition $definition,
        OneToManyAssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $fieldCriteria
    ): array {
        $query = new QueryBuilder($this->connection);
        $query->addState(self::TO_MANY_ASSOCIATION_LIMIT_QUERY);

        // build query based on provided association criteria (sortings, search, filter)
        $query = $this->criteriaQueryBuilder->build(
            $query,
            $association->getReferenceDefinition(),
            $fieldCriteria,
            $context
        );

        $foreignKey = $association->getReferenceField();

        if (!$association->getReferenceDefinition()->getField('id')) {
            throw DataAbstractionLayerException::noIdForAssociation(
                $definition->getEntityName(),
                $association->getPropertyName()
            );
        }

        // build sql accessor for foreign key field in reference table `customer_address.customer_id`
        $sqlAccessor = $this->quote($association->getReferenceDefinition()->getEntityName()) . '.'
            . $this->quote($foreignKey);

        $primaryKeyAccessor = $this->quote($association->getReferenceDefinition()->getEntityName()) . '.id';

        $orderBy = $query->getOrderByPairs();
        $scoreExpression = null;
        foreach ($query->getSelectParts() as $select) {
            if (str_ends_with($select, ' as ' . Criteria::SCORE_FIELD)) {
                $scoreExpression = substr($select, 0, -\strlen(' as ' . Criteria::SCORE_FIELD));
            }
        }
        $query->resetOrderBy();
        $query->select($sqlAccessor . ' as ' . $this->quote($foreignKey), $primaryKeyAccessor . ' as id');
        if ($scoreExpression !== null || $query->hasState(CriteriaQueryBuilder::COUNT_SORTING)) {
            foreach ($association->getReferenceDefinition()->getPrimaryKeys() as $primary) {
                if ($primary instanceof StorageAware) {
                    $query->addGroupBy($this->quote($association->getReferenceDefinition()->getEntityName()) . '.' . $this->quote($primary->getStorageName()));
                }
            }
        }
        $windowOrder = [];
        foreach ($orderBy as $index => [$expression, $direction]) {
            if ($expression === Criteria::SCORE_FIELD && $scoreExpression !== null) {
                $expression = $scoreExpression;
            }
            $query->addSelect($expression . ' as sort_' . $index);
            $windowOrder[] = 'sort_' . $index . ' ' . $direction;
        }
        $windowOrder[] = 'id';
        $ordering = implode(', ', $windowOrder);
        $deduplicated = new QueryBuilder($this->connection);
        $deduplicated->select('child_values.*', 'ROW_NUMBER() OVER (PARTITION BY ' . $this->quote($foreignKey) . ', id ORDER BY ' . $ordering . ') as duplicate_position');
        $deduplicated->from('(' . $query->getSQL() . ')', 'child_values');
        $ranked = new QueryBuilder($this->connection);
        $ranked->select('unique_children.*', 'ROW_NUMBER() OVER (PARTITION BY ' . $this->quote($foreignKey) . ' ORDER BY ' . $ordering . ') as id_count');
        $ranked->from('(' . $deduplicated->getSQL() . ')', 'unique_children')->andWhere('duplicate_position = 1');

        $root = $this->quote($definition->getEntityName());

        // create a wrapper query which select the root primary key and the grouped reference ids
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $wrapper = new QueryBuilder($this->connection);
        $this->queryHelper->getBaseQuery($wrapper, $definition, $context);
        $wrapper->select(
            $dialect->hex([$definition->getEntityName(), 'id']) . ' as id',
            $dialect->hex(['child', 'id']) . ' as child_id',
        );

        $wrapper->orderBy($root . '.id');
        $wrapper->addOrderBy('child.id_count');

        // wrap query into a sub select to restrict the association count from the outer query
        $wrapper->leftJoin(
            $root,
            '(' . $ranked->getSQL() . ')',
            'child',
            'child.' . $this->quote($foreignKey) . ' = ' . $root . '.id AND id_count >= :offset'
                . ($fieldCriteria->getLimit() !== null ? ' AND id_count <= :limit' : '')
        );

        // filter result to loaded root entities
        $wrapper->andWhere($root . '.id IN (:rootIds)');

        $bytes = $collection->map(
            static fn (Entity $entity) => Uuid::fromHexToBytes($entity->getUniqueIdentifier())
        );

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            foreach ($collection->getElements() as $entity) {
                if ($entity->get('parentId')) {
                    $bytes[$entity->get('parentId')] = Uuid::fromHexToBytes($entity->get('parentId'));
                }
            }
        }

        $wrapper->setParameter('rootIds', $bytes, ArrayParameterType::BINARY);

        $limit = (int) $fieldCriteria->getOffset() + (int) $fieldCriteria->getLimit();
        $offset = (int) $fieldCriteria->getOffset() + 1;

        if ($fieldCriteria->getLimit() !== null) {
            $wrapper->setParameter('limit', $limit, ParameterType::INTEGER);
        }
        $wrapper->setParameter('offset', $offset, ParameterType::INTEGER);

        foreach ($query->getParameters() as $key => $value) {
            $type = $query->getParameterType($key);
            $wrapper->setParameter($key, $value, $type);
        }

        $rows = $wrapper->executeQuery()->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];

            if (!isset($grouped[$id])) {
                $grouped[$id] = [];
            }

            if ($row['child_id'] === null) {
                continue;
            }

            $grouped[$id][] = (string) $row['child_id'];
        }

        return $grouped;
    }

    private function buildOneToManyPropertyAccessor(EntityDefinition $definition, OneToManyAssociationField $association): string
    {
        $reference = $association->getReferenceDefinition();

        if ($association instanceof ChildrenAssociationField) {
            return $reference->getEntityName() . '.parentId';
        }

        $ref = $reference->getFields()->getByStorageName(
            $association->getReferenceField()
        );

        if (!$ref) {
            throw DataAbstractionLayerException::referenceFieldNotFound(
                $association->getReferenceField(),
                $reference->getEntityName(),
                $definition->getEntityName()
            );
        }

        return $reference->getEntityName() . '.' . $ref->getPropertyName();
    }

    private function isAssociationRestricted(?Criteria $criteria, string $accessor): bool
    {
        if ($criteria === null) {
            return false;
        }

        if (!$criteria->hasAssociation($accessor)) {
            return false;
        }

        $fieldCriteria = $criteria->getAssociation($accessor);

        return $fieldCriteria->getOffset() !== null
            || $fieldCriteria->getLimit() !== null
            || $fieldCriteria->getSorting() !== []
            || $fieldCriteria->getFilters() !== []
            || $fieldCriteria->getPostFilters() !== []
        ;
    }

    private function addAssociationFieldsToCriteria(
        Criteria $criteria,
        EntityDefinition $definition,
        FieldCollection $fields
    ): FieldCollection {
        foreach ($criteria->getAssociations() as $fieldName => $_fieldCriteria) {
            $field = $definition->getFields()->get($fieldName);
            if (!$field) {
                $this->logger->warning(
                    \sprintf('Criteria association "%s" could not be resolved. Double check your Criteria!', $fieldName)
                );

                continue;
            }

            $fields->add($field);
        }

        return $fields;
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     */
    private function loadToOne(
        AssociationField $association,
        Context $context,
        EntityCollection $collection,
        Criteria $criteria,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): void {
        if (!$association instanceof OneToOneAssociationField && !$association instanceof ManyToOneAssociationField) {
            return;
        }

        if (!$criteria->hasAssociation($association->getPropertyName())) {
            return;
        }

        $associationCriteria = $criteria->getAssociation($association->getPropertyName());
        if (!$associationCriteria->getAssociations()) {
            return;
        }

        if (!$associationCriteria->getTitle() && $criteria->getTitle()) {
            $associationCriteria->setTitle(
                $criteria->getTitle() . '::association::' . $association->getPropertyName()
            );
        }

        $related = \array_filter($collection->map(static function (Entity $entity) use ($association) {
            if ($association->is(Extension::class)) {
                return $entity->getExtension($association->getPropertyName());
            }

            return $entity->get($association->getPropertyName());
        }));

        $referenceDefinition = $association->getReferenceDefinition();
        $collectionClass = $referenceDefinition->getCollectionClass();

        if ($isPartialLoading) {
            $collectionClass = EntityCollection::class;
        }

        $fields = $referenceDefinition->getFields()->getBasicFields();
        $fields = $this->addAssociationFieldsToCriteria($associationCriteria, $referenceDefinition, $fields);

        // This line removes duplicate entries, so after fetchAssociations the association must be reassigned
        $relatedCollection = new $collectionClass();
        if (!$relatedCollection instanceof EntityCollection) {
            throw DataAbstractionLayerException::notAnInstanceOfEntityCollection($collectionClass);
        }

        $relatedCollection->fill($related);

        $this->fetchAssociations(
            $associationCriteria,
            $referenceDefinition,
            $context,
            $relatedCollection,
            $fields,
            $fieldsForPartialLoading,
            $isPartialLoading,
        );

        foreach ($collection as $entity) {
            if ($association->is(Extension::class)) {
                $item = $entity->getExtension($association->getPropertyName());
            } else {
                $item = $entity->get($association->getPropertyName());
            }

            if (!$item instanceof Entity) {
                continue;
            }

            if ($association->is(Extension::class)) {
                $extension = $relatedCollection->get($item->getUniqueIdentifier());
                if ($extension !== null) {
                    $entity->addExtension($association->getPropertyName(), $extension);
                }

                continue;
            }

            $entity->assign([
                $association->getPropertyName() => $relatedCollection->get($item->getUniqueIdentifier()),
            ]);
        }
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<string, mixed> $fieldsForPartialLoading
     *
     * @return EntityCollection<Entity>
     */
    private function fetchAssociations(
        Criteria $criteria,
        EntityDefinition $definition,
        Context $context,
        EntityCollection $collection,
        FieldCollection $fields,
        array $fieldsForPartialLoading,
        bool $isPartialLoading,
    ): EntityCollection {
        if ($collection->count() <= 0) {
            return $collection;
        }

        foreach ($fields as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            // Do not re-use `$isPartialLoading` here, as this method could be called for associations
            // and only the initial call is relevant for marking the whole read as partial
            if ($fieldsForPartialLoading !== [] && !\array_key_exists($association->getPropertyName(), $fieldsForPartialLoading)) {
                continue;
            }

            if ($association instanceof OneToOneAssociationField || $association instanceof ManyToOneAssociationField) {
                $this->loadToOne(
                    $association,
                    $context,
                    $collection,
                    $criteria,
                    $fieldsForPartialLoading[$association->getPropertyName()] ?? [],
                    $isPartialLoading,
                );

                continue;
            }

            if ($association instanceof OneToManyAssociationField) {
                $this->loadOneToMany(
                    $criteria,
                    $definition,
                    $association,
                    $context,
                    $collection,
                    $fieldsForPartialLoading[$association->getPropertyName()] ?? [],
                    $isPartialLoading,
                );

                continue;
            }

            if ($association instanceof ManyToManyAssociationField) {
                $this->loadManyToMany(
                    $criteria,
                    $definition,
                    $association,
                    $context,
                    $collection,
                    $fieldsForPartialLoading[$association->getPropertyName()] ?? [],
                    $isPartialLoading,
                );
            }
        }

        foreach ($collection as $struct) {
            $struct->removeExtension(self::INTERNAL_MAPPING_STORAGE);
        }

        return $collection;
    }

    private function quote(string $identifier): string
    {
        return EntityDefinitionQueryHelper::escape($identifier, $this->connection);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class ManyToOneAssociationFieldResolver extends AbstractFieldResolver
{
    public function __construct(
        private readonly EntityDefinitionQueryHelper $queryHelper,
        private readonly Connection $connection,
    ) {
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            return $context->getAlias();
        }

        $alias = $context->getAlias() . '.' . $field->getPropertyName();
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }
        $context->getQuery()->addState($alias);

        $reference = $field->getReferenceDefinition();

        $table = $reference->getEntityName();

        $versionAware = $context->getDefinition()->isVersionAware() && $reference->isVersionAware();

        $source = $this->getSourceColumn($context->getDefinition(), $field, $context->getAlias(), $context->getContext());

        $referenceColumn = $this->getReferenceColumn($field, $context->getContext());

        // specified version requested, use sub version call to solve live version or specified
        if ($versionAware && $context->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            $this->joinVersion($field, $context->getAlias(), $alias, $context->getQuery(), $context->getContext(), $source, $referenceColumn);

            return $alias;
        }

        // No Blacklisting Whitelisting for ManyToOne Association because of possible Dependencies on subentities
        $parameters = [
            '#source#' => $source,
            '#root#' => $this->quote($context->getAlias()),
            '#alias#' => $this->quote($alias),
            '#reference_column#' => $referenceColumn,
        ];

        $context->getQuery()->leftJoin(
            $this->quote($context->getAlias()),
            $this->quote($table),
            $this->quote($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#' . $this->buildVersionWhere($context, $field),
            ),
        );

        return $alias;
    }

    protected function getSourceColumn(EntityDefinition $definition, AssociationField $field, string $root, Context $context): string
    {
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            throw new \RuntimeException('Expected field of type ManyToOneAssociationField or OneToOneAssociationField');
        }

        if (!$field->is(Inherited::class)) {
            return $this->quote($root) . '.' . $this->quote($field->getStorageName());
        }

        if (!$context->considerInheritance()) {
            return $this->quote($root) . '.' . $this->quote($field->getStorageName());
        }

        $inherited = $this->quote($root) . '.' . $this->quote($field->getPropertyName());

        $fk = $definition->getFields()->getByStorageName($field->getStorageName());

        if (!$fk) {
            throw new \RuntimeException(\sprintf('Can not find foreign key for table column %s.%s', $definition->getEntityName(), $field->getStorageName()));
        }

        if ($fk instanceof IdField && $field->is(PrimaryKey::class)) {
            return $inherited;
        }

        if ($fk instanceof FkField && $field->is(Required::class)) {
            return \sprintf(
                'COALESCE(%s, %s)',
                $this->quote($root) . '.' . $this->quote($field->getStorageName()),
                $this->quote($root . '.parent') . '.' . $this->quote($field->getStorageName())
            );
        }

        return $inherited;
    }

    /**
     * Builds a versioning string to append in the where condition based on the available fields
     */
    private function buildVersionWhere(FieldResolverContext $context, ManyToOneAssociationField|OneToOneAssociationField $field): string
    {
        if (!$field->getReferenceDefinition()->isVersionAware()) {
            return '';
        }

        $fkVersionId = $this->getVersionField($context, $field);
        if ($fkVersionId) {
            if ($field->is(Inherited::class) && $context->getContext()->considerInheritance()) {
                return \sprintf(
                    ' AND COALESCE(#root#.%s, %s.%s) = #alias#.%s',
                    $this->quote($fkVersionId),
                    $this->quote($context->getAlias() . '.parent'),
                    $this->quote($fkVersionId),
                    $this->quote('version_id'),
                );
            }

            return \sprintf(
                ' AND #root#.%s = #alias#.%s',
                $this->quote($fkVersionId),
                $this->quote('version_id'),
            );
        }

        if ($field instanceof OneToOneAssociationField) {
            // When the field is a OneToOneAssociation (e.g. order <> order_customer) the other definition may have a
            // fk version id pointing to the current definition.
            $ownFkVersionIdk = $context->getDefinition()->getEntityName() . '_version_id';
            if ($field->getReferenceDefinition()->getFields()->getByStorageName($ownFkVersionIdk)) {
                return \sprintf(
                    ' AND #root#.%s = #alias#.%s',
                    $this->quote('version_id'),
                    $this->quote($ownFkVersionIdk),
                );
            }
        }

        $context->getQuery()->setParameter('associationLiveVersion', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY);

        return ' AND #alias#.' . $this->quote('version_id') . ' = :associationLiveVersion';
    }

    private function getVersionField(FieldResolverContext $context, ManyToOneAssociationField|OneToOneAssociationField $field): ?string
    {
        $variants = [
            $field->getReferenceDefinition()->getEntityName() . '_version_id',
            \substr($field->getStorageName(), 0, -3) . '_version_id',
        ];

        foreach ($variants as $variant) {
            if ($context->getDefinition()->getFields()->getByStorageName($variant)) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Constructs a sub query that returns the version of the entity requested in the context. IF that version DOES NOT
     * exist, the live version is returned instead.
     */
    private function createSubVersionQuery(
        AssociationField $field,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): QueryBuilder {
        $subRoot = $field->getReferenceDefinition()->getEntityName();

        $versionQuery = new QueryBuilder($this->connection);
        $versionQuery->select($this->quote($subRoot) . '.*');
        $versionQuery->from(
            $this->quote($subRoot),
            $this->quote($subRoot),
        );
        $queryHelper->joinVersion($versionQuery, $field->getReferenceDefinition(), $subRoot, $context);

        return $versionQuery;
    }

    private function getReferenceColumn(AssociationField $field, Context $context): string
    {
        if ($field->is(ReverseInherited::class) && $context->considerInheritance()) {
            return $this->quote($field->getFlag(ReverseInherited::class)->getReversedPropertyName());
        }

        return $this->quote($field->getReferenceField());
    }

    /**
     * Adds a left join with a sub-select to the query that results in the entity version requested in the context or the
     * live version if that version does not exist.
     *
     * @param string $root e.g. "document"
     * @param string $alias e.g. "document.order"
     * @param string $source e.g. "document.order_id
     * @param string $referenceColumn e.g. "id" (for the order)
     */
    private function joinVersion(AssociationField $field, string $root, string $alias, QueryBuilder $query, Context $context, string $source, string $referenceColumn): void
    {
        $versionQuery = $this->createSubVersionQuery($field, $context, $this->queryHelper);

        $parameters = [
            '#source#' => $source,
            '#root#' => $this->quote($root),
            '#alias#' => $this->quote($alias),
            '#reference_column#' => $referenceColumn,
        ];

        $query->leftJoin(
            $this->quote($root),
            '(' . $versionQuery->getSQL() . ')',
            $this->quote($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#',
            ),
        );

        foreach ($versionQuery->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $versionQuery->getParameterType($key));
        }
    }

    private function quote(string $identifier): string
    {
        return EntityDefinitionQueryHelper::escape($identifier, $this->connection);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ManyToManyAssociationFieldResolver extends AbstractFieldResolver
{
    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof ManyToManyAssociationField) {
            throw new \InvalidArgumentException('Expected ' . ManyToManyAssociationField::class);
        }

        $alias = $context->getAlias() . '.' . $field->getPropertyName();
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }
        $context->getQuery()->addState($alias);
        $context->getQuery()->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $mappingAlias = $alias . '.mapping';

        $source = $this->getMappingSourceColumn($context->getAlias(), $field, $context->getContext());

        $parameters = [
            '#root#' => $this->quote($context->getAlias()),
            '#source#' => $source,
            '#alias#' => $this->quote($mappingAlias),
            '#reference_column#' => $this->quote($field->getMappingLocalColumn()),
        ];

        $context->getQuery()->leftJoin(
            $this->quote($context->getAlias()),
            $this->quote($field->getMappingDefinition()->getEntityName()),
            $this->quote($mappingAlias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column# '
                . $this->buildMappingVersionWhere($field, $context->getDefinition())
            )
        );

        $parameters = [
            '#mapping#' => $this->quote($mappingAlias),
            '#source_column#' => $this->quote($field->getMappingReferenceColumn()),
            '#alias#' => $this->quote($alias),
            '#reference_column#' => $this->getReferenceColumn($context, $field),
        ];

        $context->getQuery()->leftJoin(
            $this->quote($mappingAlias),
            $this->quote($field->getToManyReferenceDefinition()->getEntityName()),
            $this->quote($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#mapping#.#source_column# = #alias#.#reference_column# '
                . $this->buildVersionWhere($field->getToManyReferenceDefinition(), $field)
            )
        );

        return $alias;
    }

    private function buildMappingVersionWhere(ManyToManyAssociationField $association, EntityDefinition $definition): string
    {
        if (!$definition->isVersionAware()) {
            return '';
        }
        if (!$association->is(CascadeDelete::class)) {
            return '';
        }
        $versionField = $definition->getEntityName() . '_version_id';

        return ' AND #root#.version_id = #alias#.' . $this->quote($versionField);
    }

    private function getMappingSourceColumn(string $root, ManyToManyAssociationField $association, Context $context): string
    {
        if ($association->is(Inherited::class) && $context->considerInheritance()) {
            return $this->quote($root) . '.' . $this->quote($association->getPropertyName());
        }

        return $this->quote($root) . '.' . $this->quote($association->getLocalField());
    }

    private function getReferenceColumn(FieldResolverContext $context, ManyToManyAssociationField $field): string
    {
        if (!$field->is(ReverseInherited::class)) {
            return $this->quote($field->getReferenceField());
        }

        if (!$context->getContext()->considerInheritance()) {
            return $this->quote($field->getReferenceField());
        }

        return $this->quote($field->getFlag(ReverseInherited::class)->getReversedPropertyName());
    }

    private function buildVersionWhere(EntityDefinition $definition, ManyToManyAssociationField $field): string
    {
        if (!$definition->isVersionAware()) {
            return '';
        }
        if (!$field->is(CascadeDelete::class)) {
            return '';
        }

        $versionField = $this->quote($definition->getEntityName() . '_version_id');

        return ' AND #alias#.version_id = #mapping#.' . $versionField;
    }

    private function quote(string $identifier): string
    {
        return EntityDefinitionQueryHelper::escape($identifier, $this->connection);
    }
}

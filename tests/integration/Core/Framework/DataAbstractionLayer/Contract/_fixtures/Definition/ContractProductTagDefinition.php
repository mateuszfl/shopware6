<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * @internal
 */
final class ContractProductTagDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'sw_contract_product_tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return true;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('product_id', 'productId', ContractProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ContractProductDefinition::class, 'product_version_id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('tag_id', 'tagId', ContractTagDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('product', 'product_id', ContractProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('tag', 'tag_id', ContractTagDefinition::class, 'id', false),
        ]);
    }
}

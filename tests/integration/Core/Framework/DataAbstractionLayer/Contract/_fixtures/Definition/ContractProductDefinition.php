<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
final class ContractProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sw_contract_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new Required()),
            new ParentFkField(self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),
            new ParentAssociationField(self::class, 'id'),
            new JsonField('payload', 'payload'),
            (new PriceField('prices', 'prices'))->addFlags(new Required()),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            (new CreatedAtField())->addFlags(new Required()),
            new OneToManyAssociationField('translations', ContractProductTranslationDefinition::class, 'product_id'),
            new ManyToManyAssociationField('tags', ContractTagDefinition::class, ContractProductTagDefinition::class, 'product_id', 'tag_id'),
        ]);
    }
}

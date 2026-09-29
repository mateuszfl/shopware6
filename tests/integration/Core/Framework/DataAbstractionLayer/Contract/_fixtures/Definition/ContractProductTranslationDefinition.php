<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
final class ContractProductTranslationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sw_contract_product_translation';

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
            (new FkField('language_id', 'languageId', ContractLanguageDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new JsonField('payload', 'payload'),
            (new CreatedAtField())->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ContractProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', ContractLanguageDefinition::class, 'id', false),
        ]);
    }
}

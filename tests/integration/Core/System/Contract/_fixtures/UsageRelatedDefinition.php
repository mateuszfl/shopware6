<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Contract\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
class UsageRelatedDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'usage_related';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new Field\IdField('id', 'id'))->addFlags(new Field\Flag\PrimaryKey(), new Field\Flag\Required())]);
    }
}

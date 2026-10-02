<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\FieldNotStorageAwareException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class DefaultFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): string
    {
        if (!$field instanceof StorageAware) {
            throw new FieldNotStorageAwareException($root . '.' . $field->getPropertyName());
        }

        return $this->quote($root) . '.' . $this->quote($field->getStorageName());
    }

    private function quote(string $identifier): string
    {
        return EntityDefinitionQueryHelper::escape($identifier, $this->connection);
    }
}

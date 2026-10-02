<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface FragmentFieldAccessorBuilderInterface extends FieldAccessorBuilderInterface
{
    public function buildAccessorFragment(string $root, Field $field, Context $context, string $accessor): ?SqlFragment;
}

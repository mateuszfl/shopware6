<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldService;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldsAccessorBuilder extends JsonFieldAccessorBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CustomFieldService $customFieldService,
        Connection $connection
    ) {
        parent::__construct($connection);
    }

    public function buildAccessorFragment(string $root, Field $field, Context $context, string $accessor): ?SqlFragment
    {
        if (!$field instanceof CustomFields) {
            return null;
        }

        $path = JsonPath::parse(substr($accessor, \strlen($field->getPropertyName())));
        if ($path->steps === []) {
            return parent::buildAccessorFragment($root, $field, $context, $accessor);
        }
        $attributeName = $path->steps[0]['value'];
        $attributeField = $this->customFieldService->getCustomField($attributeName);

        $field->setPropertyMapping([$attributeField]);

        return parent::buildAccessorFragment($root, $field, $context, $accessor);
    }
}

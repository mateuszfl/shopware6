<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class JsonFieldAccessorBuilder implements FragmentFieldAccessorBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(protected readonly Connection $connection)
    {
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        return $this->buildAccessorFragment($root, $field, $context, $accessor)?->inline($this->connection);
    }

    public function buildAccessorFragment(string $root, Field $field, Context $context, string $accessor): ?SqlFragment
    {
        if (!$field instanceof JsonField) {
            return null;
        }

        $column = EntityDefinitionQueryHelper::escape($root, $this->connection) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName(), $this->connection);
        $suffix = substr($accessor, \strlen($field->getPropertyName()));
        if ($suffix === '') {
            return new SqlFragment($column);
        }

        $path = JsonPath::parse($suffix);
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $value = $dialect->jsonExtractPath($column, $path);
        $embeddedField = $this->getField($path, $field->getPropertyMapping());
        $type = match (true) {
            $embeddedField instanceof IntField, $embeddedField instanceof FloatField => 'number',
            $embeddedField instanceof BoolField => 'bool',
            $embeddedField instanceof DateTimeField => 'datetime',
            $embeddedField instanceof DateField => 'date',
            default => 'text',
        };

        return new SqlFragment($dialect->jsonScalar($value->sql, $type), $value->parameters, $value->types);
    }

    /**
     * @param Field[] $fields
     */
    private function getField(JsonPath $path, array $fields): ?Field
    {
        $mapping = $fields;
        $embedded = null;
        foreach ($path->steps as $step) {
            if ($step['kind'] !== 'member') {
                return null;
            }
            $embedded = null;
            foreach ($mapping as $candidate) {
                if ($candidate->getPropertyName() === $step['value']) {
                    $embedded = $candidate;
                    break;
                }
            }
            $mapping = $embedded instanceof JsonField ? $embedded->getPropertyMapping() : [];
        }

        return $embedded;
    }
}

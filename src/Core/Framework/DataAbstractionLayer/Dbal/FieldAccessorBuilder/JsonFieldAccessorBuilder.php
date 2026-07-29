<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\AbstractSqlDialect;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\JsonExtractType;
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
class JsonFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractSqlDialect $dialect
    ) {
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof JsonField) {
            return null;
        }

        $jsonPath = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '#',
            '',
            $accessor
        );

        if ($jsonPath === null || $jsonPath === '') {
            return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
        }

        // enquote hyphenated json keys in path
        if (str_contains($jsonPath, '-')) {
            $jsonPathParts = explode('.', $jsonPath);
            foreach ($jsonPathParts as $index => $jsonPathPart) {
                if ($index === 0) {
                    continue;
                }
                if (str_contains($jsonPathPart, '-')) {
                    $jsonPathParts[$index] = \sprintf('"%s"', $jsonPathPart);
                }
            }
            $jsonPath = implode('.', $jsonPathParts);
        }

        $column = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
        $path = $this->connection->quote('$' . $jsonPath);

        $embeddedField = $this->getField($jsonPath, $field->getPropertyMapping());

        // The dialect renders the JSON extraction, the target-type coercion and the json-null to
        // sql-null conversion. On MySQL this yields the exact previous expression
        // (IF(JSON_TYPE(...) != "NULL", <typed accessor>, NULL)).
        return $this->dialect->jsonExtractTyped($column, $path, $this->resolveType($embeddedField));
    }

    /**
     * @param Field[] $fields
     */
    private function getField(string $path, array $fields): ?Field
    {
        /** @var string $fieldName */
        $fieldName = preg_replace(
            '#^\.("([^"]*)"|([^.]*)).*#',
            '$2$3',
            $path
        );
        $subPath = mb_substr($path, mb_strlen($fieldName) + 1);

        foreach ($fields as $field) {
            if ($field->getPropertyName() !== $fieldName) {
                continue;
            }

            if ($field instanceof JsonField && $field->getPropertyMapping() !== []) {
                return $this->getField($subPath, $field->getPropertyMapping());
            }

            return $field;
        }

        return null;
    }

    private function resolveType(?Field $field): JsonExtractType
    {
        if ($field instanceof IntField) {
            return JsonExtractType::INT;
        }

        if ($field instanceof FloatField) {
            return JsonExtractType::FLOAT;
        }

        if ($field instanceof BoolField) {
            return JsonExtractType::BOOL;
        }

        if ($field instanceof DateTimeField) {
            return JsonExtractType::DATETIME;
        }

        if ($field instanceof DateField) {
            return JsonExtractType::DATE;
        }

        return JsonExtractType::STRING;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class PriceFieldAccessorBuilder implements FragmentFieldAccessorBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        return $this->buildAccessorFragment($root, $field, $context, $accessor)?->inline($this->connection);
    }

    public function buildAccessorFragment(string $root, Field $field, Context $context, string $accessor): ?SqlFragment
    {
        if (!$field instanceof PriceField) {
            return null;
        }

        $currencyId = $context->getCurrencyId();
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $factorKey = 'currency_factor_' . Hasher::hash(\sprintf('%F', $context->getCurrencyFactor()), 'sha256');
        $currencyFactor = new SqlFragment(':' . $factorKey, [$factorKey => \sprintf('%F', $context->getCurrencyFactor())]);
        $jsonAccessor = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $jsonAccessor = 'gross';
        }

        $parts = explode('.', $accessor);

        // is tax state explicitly requested? => overwrite selector
        if (\in_array(array_last($parts), ['net', 'gross'], true)) {
            $jsonAccessor = array_last($parts);
            array_pop($parts);
        }

        // filter / search / sort for list prices? => extend selector
        if (array_last($parts) === 'listPrice') {
            $jsonAccessor = 'listPrice.' . $jsonAccessor;
            array_pop($parts);
        }

        $isPercentageAccessor = array_last($parts) === 'percentage';
        if ($isPercentageAccessor) {
            $jsonAccessor = 'percentage.' . $jsonAccessor;
            array_pop($parts);
        }

        // is specific currency id provided? => overwrite currency id and currency factor
        $lastPart = (string) array_last($parts);
        if (Uuid::isValid($lastPart)) {
            $currencyId = $lastPart;
            $currencyFactor = (new SqlFragment(
                '(SELECT factor FROM currency WHERE id = ' . $dialect->fromHex('?') . ')',
                [$currencyId]
            ))->named();
        }

        $column = EntityDefinitionQueryHelper::escape($root, $this->connection) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName(), $this->connection);
        $selected = $dialect->jsonExtractPath($column, JsonPath::members(array_merge(['c' . $currencyId], explode('.', $jsonAccessor))));
        $select = [$dialect->jsonScalar($selected->sql, 'number')];
        $parameters = $selected->parameters;
        $types = $selected->types;

        if ($currencyId !== Defaults::CURRENCY) {
            $fallback = $dialect->jsonExtractPath($column, JsonPath::members(array_merge(['c' . Defaults::CURRENCY], explode('.', $jsonAccessor))));
            $select[] = '(' . $dialect->jsonScalar($fallback->sql, 'number') . ' * ' . $currencyFactor->sql . ')';
            $parameters = array_merge($parameters, $fallback->parameters, $currencyFactor->parameters);
            $types = array_merge($types, $fallback->types, $currencyFactor->types);
        }

        $result = self::roundPrice('COALESCE(' . implode(', ', $select) . ')', $context);

        // The DB stores the discount percentage (e.g. 25 for "25% off"), but the API
        // exposes the price-to-list-price ratio (e.g. 75 for "pay 75% of list price").
        // Invert here so filters/sorts operate on the intuitive ratio scale.
        if ($isPercentageAccessor) {
            $result = \sprintf('(100 - %s)', $result);
        }

        return new SqlFragment($result, $parameters, $types);
    }

    public static function roundPrice(string $expression, Context $context): string
    {
        $result = \sprintf('(ROUND(CAST(%s AS DECIMAL(30, 20)), %d))', $expression, $context->getRounding()->getDecimals());
        if (self::useCashRounding($context)) {
            $multiplier = 1 / $context->getRounding()->getInterval();
            $result = \sprintf('(ROUND(%s * %s, 0) / %s)', $result, (string) $multiplier, (string) $multiplier);
        }

        return $result;
    }

    private static function useCashRounding(Context $context): bool
    {
        if ($context->getRounding()->getDecimals() !== 2) {
            return false;
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return true;
        }

        return $context->getRounding()->roundForNet();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FragmentFieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

#[Package('framework')]
class CheapestPriceAccessorBuilder implements FragmentFieldAccessorBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly int $maxRulePrices,
        private readonly LoggerInterface $logger,
        private readonly ?Connection $connection = null
    ) {
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        return $this->buildAccessorFragment($root, $field, $context, $accessor)?->inline($this->getConnection());
    }

    public function buildAccessorFragment(string $root, Field $field, Context $context, string $accessor): ?SqlFragment
    {
        if (!$field instanceof CheapestPriceField) {
            return null;
        }

        // cheapest price is only indexed for parent product
        $keys = $context->getRuleIds();
        if (\count($keys) > $this->maxRulePrices) {
            $this->logger->warning(\sprintf('More than %d rules are active, only the first %d rules are considered for the cheapest price calculation', $this->maxRulePrices, $this->maxRulePrices));
            $this->logger->info(
                'More rules then the configured `dal.max_rule_prices` are active, thus not all rule prices are considered for the cheapest price. You can increase the `dal.max_rule_prices`, but this will have a negative performance impact. Consider restructuring your rules, so that not so many match at the same time.'
            );
            $keys = \array_slice($keys, 0, $this->maxRulePrices);
        }
        $keys[] = 'default';

        $jsonAccessor = 'net';
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $jsonAccessor = 'gross';
        }

        $parts = explode('.', $accessor);
        $lastAccessorPart = array_last($parts);

        // is tax state explicitly requested? => overwrite selector
        if (\in_array($lastAccessorPart, ['net', 'gross'], true)) {
            $jsonAccessor = $lastAccessorPart;
            array_pop($parts);
            $lastAccessorPart = array_last($parts);
        }

        // filter / search / sort for list prices? => extend selector
        if ($lastAccessorPart === 'listPrice') {
            $jsonAccessor = 'listPrice.' . $jsonAccessor;
            array_pop($parts);
            $lastAccessorPart = array_last($parts);
        }

        $isPercentageAccessor = $lastAccessorPart === 'percentage';
        if ($isPercentageAccessor) {
            $jsonAccessor = 'percentage.' . $jsonAccessor;
            array_pop($parts);
        }

        $connection = $this->getConnection();
        $dialect = (new DatabasePlatformResolver($connection))->dialect();
        $column = EntityDefinitionQueryHelper::escape($root, $connection) . '.' . EntityDefinitionQueryHelper::escape('cheapest_price_accessor', $connection);
        $select = [];
        $parameters = [];
        $types = [];
        foreach ($keys as $ruleId) {
            $currencies = [$context->getCurrencyId() => '1'];
            if ($context->getCurrencyId() !== Defaults::CURRENCY) {
                $currencies[Defaults::CURRENCY] = (string) $context->getCurrencyFactor();
            }
            foreach ($currencies as $currencyId => $factor) {
                $value = $dialect->jsonExtractPath($column, JsonPath::members(array_merge(['rule' . $ruleId, 'currency' . $currencyId], explode('.', $jsonAccessor))));
                $factorKey = 'price_factor_' . Hasher::hash($factor, 'sha256');
                $select[] = PriceFieldAccessorBuilder::roundPrice('(' . $dialect->jsonScalar($value->sql, 'number') . ' * :' . $factorKey . ')', $context);
                $parameters = array_merge($parameters, $value->parameters, [$factorKey => $factor]);
                $types = array_merge($types, $value->types);
            }
        }
        $result = 'COALESCE(' . implode(', ', $select) . ')';

        // The DB stores the discount percentage (e.g. 25 for "25% off"), but the API
        // exposes the price-to-list-price ratio (e.g. 75 for "pay 75% of list price").
        // Invert here so filters/sorts operate on the intuitive ratio scale.
        if ($isPercentageAccessor) {
            $result = \sprintf('(100 - %s)', $result);
        }

        return new SqlFragment($result, $parameters, $types);
    }

    private function getConnection(): Connection
    {
        return $this->connection ?? DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.0']);
    }
}

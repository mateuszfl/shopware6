<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheapestPriceAccessorBuilder::class)]
class CheapestPriceAccessorBuilderTest extends TestCase
{
    public function testLegacyConstructorAndStringApiRemainUsableWithoutConnecting(): void
    {
        $builder = new CheapestPriceAccessorBuilder(1, new NullLogger());
        static::assertNotNull($builder->buildAccessor('product', new CheapestPriceField('cheapest_price_accessor', 'cheapestPrice'), Context::createDefaultContext(), 'cheapestPrice'));
        static::assertNull($builder->buildAccessor('product', new StringField('name', 'name'), Context::createDefaultContext(), 'name'));
    }

    public function testRuleLimitPreservesPriorityThenDefaultFallback(): void
    {
        $builder = new CheapestPriceAccessorBuilder(1, new NullLogger());
        $context = Context::createDefaultContext();
        $context->setRuleIds(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);
        $fragment = $builder->buildAccessorFragment('product', new CheapestPriceField('cheapest_price_accessor', 'cheapestPrice'), $context, 'cheapestPrice');
        static::assertNotNull($fragment);
        $paths = array_values(array_filter($fragment->parameters, static fn (mixed $value): bool => \is_string($value) && str_starts_with($value, '$.')));
        static::assertCount(2, $paths);
        static::assertStringContainsString('ruleaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $paths[0]);
        static::assertStringContainsString('ruledefault', $paths[1]);
        static::assertStringNotContainsString('rulebbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', implode('', $paths));
    }

    public function testPercentageInvertsDiscountAndExplicitTaxStateOverridesContext(): void
    {
        $builder = new CheapestPriceAccessorBuilder(1, new NullLogger());
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);
        $field = new CheapestPriceField('cheapest_price_accessor', 'cheapestPrice');
        $fragment = $builder->buildAccessorFragment('product', $field, $context, 'cheapestPrice.percentage.gross');
        static::assertNotNull($fragment);
        static::assertStringStartsWith('(100 - ', $fragment->sql);
        static::assertStringContainsString('"percentage"."gross"', implode('', $fragment->parameters));
        $fragment = $builder->buildAccessorFragment('product', $field, $context, 'cheapestPrice.listPrice');
        static::assertNotNull($fragment);
        static::assertStringNotContainsString('100 -', $fragment->sql);
        static::assertStringContainsString('"listPrice"."net"', implode('', $fragment->parameters));
    }
}

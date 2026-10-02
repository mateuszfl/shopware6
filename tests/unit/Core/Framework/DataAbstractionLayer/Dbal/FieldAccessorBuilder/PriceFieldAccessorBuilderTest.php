<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PriceFieldAccessorBuilder::class)]
class PriceFieldAccessorBuilderTest extends TestCase
{
    private PriceFieldAccessorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new PriceFieldAccessorBuilder(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4']));
    }

    public function testReturnsNullForNonPriceField(): void
    {
        static::assertNull($this->builder->buildAccessor('product', new StringField('name', 'name'), Context::createDefaultContext(), 'name'));
    }

    /**
     * @return iterable<string, array{string, string, list<string>, bool}>
     */
    public static function selectors(): iterable
    {
        yield 'gross context' => ['price', CartPrice::TAX_STATE_GROSS, ['gross'], false];
        yield 'net context' => ['price', CartPrice::TAX_STATE_NET, ['net'], false];
        yield 'list price' => ['price.listPrice', CartPrice::TAX_STATE_GROSS, ['listPrice', 'gross'], false];
        yield 'percentage ratio gross' => ['price.percentage', CartPrice::TAX_STATE_GROSS, ['percentage', 'gross'], true];
        yield 'percentage ratio net' => ['price.percentage', CartPrice::TAX_STATE_NET, ['percentage', 'net'], true];
        yield 'explicit gross overrides net context' => ['price.percentage.gross', CartPrice::TAX_STATE_NET, ['percentage', 'gross'], true];
        yield 'explicit net overrides gross context' => ['price.percentage.net', CartPrice::TAX_STATE_GROSS, ['percentage', 'net'], true];
    }

    /**
     * @param list<string> $selector
     */
    #[DataProvider('selectors')]
    public function testSelectsThePricePropertyAndExposesPercentageRatio(string $accessor, string $taxState, array $selector, bool $ratio): void
    {
        $context = Context::createDefaultContext();
        $context->setTaxState($taxState);
        $field = new PriceField('price', 'price');
        $fragment = $this->builder->buildAccessorFragment('product', $field, $context, $accessor);
        static::assertNotNull($fragment);
        $path = '$."c' . Defaults::CURRENCY . '".' . implode('.', array_map(static fn (string $part): string => '"' . $part . '"', $selector));
        static::assertSame([$path], array_values($fragment->parameters));
        static::assertSame($ratio, str_starts_with($fragment->sql, '(100 - '));
        static::assertNotNull($this->builder->buildAccessor('product', $field, $context, $accessor));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslationFieldResolver::class)]
class TranslationFieldResolverTest extends TestCase
{
    public function testLongTranslationAliasIsShortenedBeforeSqlGeneration(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_pgsql', 'serverVersion' => '18.6']);
        $definition = new CountryStateDefinition();
        $root = 'order_delivery.shippingOrderAddress.countryState';
        $query = new QueryBuilder($connection);
        $query->select('1');
        $field = $definition->getFields()->get('name');
        static::assertInstanceOf(TranslatedField::class, $field);

        (new TranslationFieldResolver($connection))->join(new FieldResolverContext(
            $root . '.name',
            $root,
            $field,
            $definition,
            $definition,
            $query,
            Context::createDefaultContext(),
            null
        ));

        $translationAlias = $root . '.translation.name';
        $shortAlias = EntityDefinitionQueryHelper::shortenPostgreSqlAlias($translationAlias);
        $sql = $query->getSQL();

        static::assertStringContainsString('"' . $shortAlias . '"', $sql);
        static::assertStringNotContainsString('"' . $translationAlias . '"', $sql);
    }
}

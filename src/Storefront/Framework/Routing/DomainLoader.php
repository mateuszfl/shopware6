<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('discovery')]
class DomainLoader extends AbstractDomainLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractDomainLoader
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    public function load(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'loadDomains()')
        );

        return $this->fetch();
    }

    public function loadDomains(): DomainCollection
    {
        return DomainCollection::fromArray($this->fetch());
    }

    /**
     * @return array<string, Domain>
     */
    private function fetch(): array
    {
        $query = $this->connection->createQueryBuilder();
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();

        $query->select(
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') AS ' . $dialect->quoteIdentifier('key'),
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') url',
            $dialect->hex(['domain', 'id']) . ' AS id',
            $dialect->hex(['sales_channel', 'id']) . ' AS ' . $dialect->quoteIdentifier('salesChannelId'),
            $dialect->hex(['sales_channel', 'type_id']) . ' AS ' . $dialect->quoteIdentifier('typeId'),
            $dialect->hex(['domain', 'snippet_set_id']) . ' AS ' . $dialect->quoteIdentifier('snippetSetId'),
            $dialect->hex(['domain', 'currency_id']) . ' AS ' . $dialect->quoteIdentifier('currencyId'),
            $dialect->hex(['domain', 'language_id']) . ' AS ' . $dialect->quoteIdentifier('languageId'),
            $dialect->hex(['theme', 'id']) . ' AS ' . $dialect->quoteIdentifier('themeId'),
            'sales_channel.maintenance maintenance',
            // @deprecated tag:v6.8.0 - remove the COALESCE fallback to the deprecated `maintenance_ip_whitelist` column
            'COALESCE(sales_channel.maintenance_ip_allowlist, sales_channel.maintenance_ip_whitelist) AS ' . $dialect->quoteIdentifier('maintenanceIpAllowlist'),
            'snippet_set.iso as locale',
            'theme.technical_name as ' . $dialect->quoteIdentifier('themeName'),
            'parentTheme.technical_name as ' . $dialect->quoteIdentifier('parentThemeName'),
        );

        $query->from('sales_channel');
        $query->innerJoin('sales_channel', 'sales_channel_domain', 'domain', 'domain.sales_channel_id = sales_channel.id');
        $query->innerJoin('domain', 'snippet_set', 'snippet_set', 'snippet_set.id = domain.snippet_set_id');
        $query->leftJoin('sales_channel', 'theme_sales_channel', 'theme_sales_channel', 'sales_channel.id = theme_sales_channel.sales_channel_id');
        $query->leftJoin('theme_sales_channel', 'theme', 'theme', 'theme_sales_channel.theme_id = theme.id');
        $query->leftJoin('theme', 'theme', 'parentTheme', 'theme.parent_theme_id = parentTheme.id');
        $query->where('sales_channel.type_id = :typeId');
        $query->andWhere('sales_channel.active');
        $query->setParameter('typeId', Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), ParameterType::BINARY);

        /** @var array<string, Domain> $domains */
        $domains = FetchModeHelper::groupUnique($query->executeQuery()->fetchAllAssociative());

        return $domains;
    }
}

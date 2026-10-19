<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelDomainProvider extends AbstractSalesChannelDomainProvider
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function fetchSalesChannelDomains(): SalesChannelDomainCollection
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $sql = 'SELECT ' . $dialect->hex(['sales_channel', 'id']) . ' AS sales_channel_id,
                   MIN(sales_channel_domain.url) AS url
            FROM sales_channel_domain
            INNER JOIN sales_channel ON sales_channel_domain.sales_channel_id = sales_channel.id
            WHERE sales_channel.type_id = :typeId
            AND sales_channel.active = :active
            GROUP BY sales_channel.id';

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), 'active' => true],
            ['typeId' => ParameterType::BINARY, 'active' => ParameterType::BOOLEAN]
        );

        $collection = array_map(
            static fn ($domain) => SalesChannelDomain::create($domain['sales_channel_id'], $domain['url']),
            $result
        );

        return new SalesChannelDomainCollection($collection);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Mail;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Storefront\Theme\Mail\MailThemeIdLoaderTest
 */
#[Package('discovery')]
class MailThemeIdLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function load(string $salesChannelId): ?string
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $themeId = $this->connection->fetchOne(
            'SELECT ' . $dialect->hex('theme_id') . ' FROM theme_sales_channel WHERE sales_channel_id = :salesChannelId ORDER BY theme_id LIMIT 1',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)],
            ['salesChannelId' => ParameterType::BINARY],
        );

        if (!\is_string($themeId) || !Uuid::isValid($themeId)) {
            return null;
        }

        return $themeId;
    }
}

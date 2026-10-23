<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Migration1000000002SecondPg extends Migration1000000002Second
{
    protected function engine(): string
    {
        return 'postgresql';
    }
}

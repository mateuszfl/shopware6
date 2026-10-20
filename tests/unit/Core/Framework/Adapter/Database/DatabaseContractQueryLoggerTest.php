<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseContractQueryLogger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class DatabaseContractQueryLoggerTest extends TestCase
{
    public function testCaptureStoresNormalizedSqlWithoutBoundValues(): void
    {
        $path = sys_get_temp_dir() . '/database-contract-query-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $logger = new DatabaseContractQueryLogger($path);

        try {
            $logger->debug('Executing statement: {sql}', [
                'sql' => 'SELECT * FROM product WHERE name = \'O\\\'Reilly\' AND note = E\'private\\\'value\' AND pg = $$private$$ AND tag = $secret$hidden$secret$ AND id = ?',
                'params' => ['private value'],
            ]);

            static::assertSame('SELECT * FROM product WHERE name = ? AND note = ? AND pg = ? AND tag = ? AND id = ?' . "\n", file_get_contents($path));
        } finally {
            $filesystem->remove($path);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\Migration1000000001Successful;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\TestableMigrationRuntime;

/**
 * SQL execution and error recovery are covered against both engines by MigrationPlatformContractTest.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationRuntime::class)]
class MigrationRuntimeTest extends TestCase
{
    public function testMigrateIsLazy(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $runtime = new TestableMigrationRuntime($connection, new NullLogger());
        $runtime->executableMigrations = [Migration1000000001Successful::class];

        $runtime->migrate(new MigrationSource('test'));

        static::assertFalse($runtime->storageEngineSet);
        static::assertFalse($connection->isConnected());
    }

    public function testMySqlSkipsNoLongerExistingClassWithNotice(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice')->with(static::stringContains('DoesNotExistAnymore'));
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $runtime = new TestableMigrationRuntime($connection, $logger);
        $runtime->executableMigrations = ['Shopware\\Core\\Migration\\DoesNotExistAnymore'];

        static::assertSame([], iterator_to_array($runtime->migrate(new MigrationSource('test'))));
        static::assertFalse($connection->isConnected());
    }
}

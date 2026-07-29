<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\MySqlDialect;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\PostgreSqlDialect;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\SqlDialectProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SqlDialectProvider::class)]
class SqlDialectProviderTest extends TestCase
{
    protected function setUp(): void
    {
        SqlDialectProvider::reset();
    }

    protected function tearDown(): void
    {
        SqlDialectProvider::reset();
    }

    public function testDefaultsToMysql(): void
    {
        static::assertInstanceOf(MySqlDialect::class, SqlDialectProvider::get());
    }

    public function testExplicitSetWins(): void
    {
        SqlDialectProvider::set(new PostgreSqlDialect());
        static::assertInstanceOf(PostgreSqlDialect::class, SqlDialectProvider::get());
    }

    public function testLazyResolverIsUsed(): void
    {
        $called = 0;
        SqlDialectProvider::setResolver(function () use (&$called): PostgreSqlDialect {
            ++$called;

            return new PostgreSqlDialect();
        });

        static::assertInstanceOf(PostgreSqlDialect::class, SqlDialectProvider::get());
        // Resolved once and then cached.
        SqlDialectProvider::get();
        static::assertSame(1, $called);
    }

    public function testFailingResolverFallsBackToMysqlWithoutCaching(): void
    {
        $attempts = 0;
        SqlDialectProvider::setResolver(function () use (&$attempts): MySqlDialect {
            ++$attempts;

            throw new \RuntimeException('no database yet');
        });

        static::assertInstanceOf(MySqlDialect::class, SqlDialectProvider::get());
        // A failed resolution is not cached, so it is retried on the next call.
        SqlDialectProvider::get();
        static::assertSame(2, $attempts);
    }

    public function testResetClearsState(): void
    {
        SqlDialectProvider::set(new PostgreSqlDialect());
        SqlDialectProvider::reset();
        static::assertInstanceOf(MySqlDialect::class, SqlDialectProvider::get());
    }
}

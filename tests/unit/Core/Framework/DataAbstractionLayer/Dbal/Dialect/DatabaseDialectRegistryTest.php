<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\DatabaseDialectRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\MySqlDialect;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\PostgreSqlDialect;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseDialectRegistry::class)]
class DatabaseDialectRegistryTest extends TestCase
{
    private DatabaseDialectRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new DatabaseDialectRegistry([new MySqlDialect(), new PostgreSqlDialect()]);
    }

    public function testResolvesMysql(): void
    {
        static::assertInstanceOf(MySqlDialect::class, $this->registry->get($this->connectionFor(new MySQLPlatform())));
    }

    public function testResolvesMariadbAsMysql(): void
    {
        static::assertInstanceOf(MySqlDialect::class, $this->registry->get($this->connectionFor(new MariaDBPlatform())));
    }

    public function testResolvesPostgres(): void
    {
        static::assertInstanceOf(PostgreSqlDialect::class, $this->registry->get($this->connectionFor(new PostgreSQLPlatform())));
    }

    public function testThrowsOnUnsupportedPlatform(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);

        $this->expectException(DataAbstractionLayerException::class);
        $this->registry->get($this->connectionFor($platform));
    }

    private function connectionFor(AbstractPlatform $platform): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return $connection;
    }
}

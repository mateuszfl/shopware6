<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\DatabaseDiff;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\DbState;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DbState::class)]
class DbStateTest extends TestCase
{
    private Connection&MockObject $connection;

    /**
     * @var AbstractSchemaManager<AbstractPlatform>&MockObject
     */
    private AbstractSchemaManager&MockObject $schemaManager;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
    }

    public function testRememberCurrentDbState(): void
    {
        $dbState = new DbState($this->connection);

        $this->schemaManager
            ->expects($this->once())
            ->method('introspectTableNames')
            ->willReturn([
                OptionallyQualifiedName::unquoted('table1'),
                OptionallyQualifiedName::unquoted('table2'),
            ]);

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(10, 20);

        $dbState->rememberCurrentDbState();

        static::assertSame(['table1' => 10, 'table2' => 20], $dbState->tableCounts);
    }

    public function testGetDiff(): void
    {
        $dbState = new DbState($this->connection);

        $dbState->tableCounts = ['table1' => 10, 'table2' => 20];

        $this->schemaManager
            ->expects($this->once())
            ->method('introspectTableNames')
            ->willReturn([
                OptionallyQualifiedName::unquoted('table1'),
                OptionallyQualifiedName::unquoted('table3'),
            ]);

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(15, 30);

        $diff = $dbState->getDiff();

        static::assertSame(['added' => ['table3'], 'deleted' => ['table2'], 'changed' => ['table1' => 5]], $diff);
    }
}

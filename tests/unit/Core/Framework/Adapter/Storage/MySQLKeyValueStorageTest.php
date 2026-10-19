<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MySQLKeyValueStorage::class)]
class MySQLKeyValueStorageTest extends TestCase
{
    /**
     * @var Connection&Stub
     */
    private Connection $connectionMock;

    private MySQLKeyValueStorage $keyValueStorage;

    protected function setUp(): void
    {
        $this->connectionMock = static::createStub(Connection::class);
        $this->connectionMock->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $this->connectionMock->method('getParams')->willReturn(['driver' => 'pdo_mysql']);
        $this->keyValueStorage = new MySQLKeyValueStorage($this->connectionMock);
    }

    public function testHas(): void
    {
        $this->connectionMock->method('fetchAllKeyValue')->willReturn([
            'key-1' => 'value-1',
            'key-2' => null,
        ]);

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertTrue($this->keyValueStorage->has('key-2'));
        static::assertFalse($this->keyValueStorage->has('key-3'));
    }

    public function testGet(): void
    {
        $this->connectionMock->method('fetchAllKeyValue')->willReturn([
            'key-1' => 'value-1',
            'key-2' => null,
        ]);

        static::assertSame('value-1', $this->keyValueStorage->get('key-1', 'default'));
        static::assertNull($this->keyValueStorage->get('key-2'));
        static::assertSame('default', $this->keyValueStorage->get('key-2', 'default'));
        static::assertSame('default', $this->keyValueStorage->get('key-3', 'default'));
    }

    public function testGetAfterRemoving(): void
    {
        $this->connectionMock->method('fetchAllKeyValue')->willReturn([
            'key-1' => 'value-1',
        ]);

        static::assertTrue($this->keyValueStorage->has('key-1'));

        $this->keyValueStorage->remove('key-1');

        static::assertSame('default', $this->keyValueStorage->get('key-1', 'default'));
    }

    public function testGetAfterSet(): void
    {
        $this->connectionMock->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            ['key-1' => 'value-1'],
            ['key-1' => null],
        );

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertSame('value-1', $this->keyValueStorage->get('key-1'));

        $this->keyValueStorage->set('key-1', null);

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertNull($this->keyValueStorage->get('key-1'));
    }
}

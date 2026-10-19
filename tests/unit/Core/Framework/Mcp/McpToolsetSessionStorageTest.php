<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolsetSessionStorage::class)]
class McpToolsetSessionStorageTest extends TestCase
{
    public function testEnabledToolsetsReturnsListOfStrings(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['shopware-entity', 'shopware-media']);
        $storage = new McpToolsetSessionStorage($connection, new NativeClock());
        static::assertSame(['shopware-entity', 'shopware-media'], $storage->enabledToolsets('session-a'));
    }

    public function testSessionIdsReturnsListOfStrings(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['session-a', 'session-b']);
        $storage = new McpToolsetSessionStorage($connection, new NativeClock());
        static::assertSame(['session-a', 'session-b'], $storage->sessionIds());
    }
}

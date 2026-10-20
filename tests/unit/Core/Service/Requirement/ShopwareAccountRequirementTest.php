<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareAccountRequirement::class)]
class ShopwareAccountRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('shopware_account', ShopwareAccountRequirement::getName());
    }

    public function testGatesPrivileges(): void
    {
        static::assertSame(Gate::PRIVILEGES, (new ShopwareAccountRequirement(static::createStub(Connection::class)))->getGate());
    }

    public function testDispermitsStateChange(): void
    {
        static::assertFalse((new ShopwareAccountRequirement(static::createStub(Connection::class)))->permitsStateChange());
    }

    public function testIsSatisfiedWhenUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('1');

        $requirement = new ShopwareAccountRequirement($connection);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenNoUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $requirement = new ShopwareAccountRequirement($connection);

        static::assertFalse($requirement->isSatisfied());
    }
}

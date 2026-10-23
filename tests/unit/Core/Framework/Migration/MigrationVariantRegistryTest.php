<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000001First as First;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000001FirstPg as FirstPg;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000002Second as Second;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationVariantRegistry::class)]
class MigrationVariantRegistryTest extends TestCase
{
    public function testResolvesExplicitVariantAndIdentifiesItsDiscoveryClass(): void
    {
        $registry = new MigrationVariantRegistry([[First::class, 1000000001, FirstPg::class]]);
        static::assertInstanceOf(FirstPg::class, $registry->resolve(First::class, 1000000001));
        static::assertTrue($registry->isVariant(FirstPg::class));
        static::assertFalse($registry->isVariant(First::class));
    }

    public function testAuditedPortableStepCanExplicitlyMapToItself(): void
    {
        $registry = new MigrationVariantRegistry([[First::class, 1000000001, First::class]]);
        static::assertInstanceOf(First::class, $registry->resolve(First::class, 1000000001));
        static::assertFalse($registry->isVariant(First::class));
    }

    public function testTimestampMustMatchBothOriginalAndImplementation(): void
    {
        $this->expectExceptionObject(MigrationException::invalidArgument('Migration variant timestamp mismatch: ' . First::class . ' -> ' . Second::class));
        new MigrationVariantRegistry([[First::class, 1000000001, Second::class]]);
    }

    public function testLogicalTimestampMismatchIsRejected(): void
    {
        $this->expectExceptionObject(MigrationException::invalidArgument('Migration variant timestamp mismatch: ' . First::class . ' -> ' . FirstPg::class));
        new MigrationVariantRegistry([[First::class, 1000000002, FirstPg::class]]);
    }

    public function testDuplicateRegistrationIsRejected(): void
    {
        $registry = new MigrationVariantRegistry([[First::class, 1000000001, FirstPg::class]]);
        $this->expectExceptionObject(MigrationException::invalidArgument('Duplicate migration variant: ' . First::class . ' -> ' . FirstPg::class));
        $registry->register(First::class, 1000000001, FirstPg::class);
    }

    public function testCounterpartCannotBecomeAnIndependentLogicalStep(): void
    {
        $registry = new MigrationVariantRegistry([[First::class, 1000000001, FirstPg::class]]);
        $this->expectExceptionObject(MigrationException::invalidArgument('Duplicate migration variant: ' . FirstPg::class . ' -> ' . FirstPg::class));
        $registry->register(FirstPg::class, 1000000001, FirstPg::class);
    }

    public function testMissingVariantFailsClosed(): void
    {
        $this->expectExceptionObject(MigrationException::migrationError('No PostgreSQL migration variant for ' . First::class . ' (timestamp 1000000001).'));
        (new MigrationVariantRegistry())->resolve(First::class, 1000000001);
    }

    public function testHistoryTimestampMismatchIsRejected(): void
    {
        $registry = new MigrationVariantRegistry([[First::class, 1000000001, FirstPg::class]]);
        $this->expectExceptionObject(MigrationException::migrationError('PostgreSQL migration history timestamp mismatch for ' . First::class . '.'));
        $registry->resolve(First::class, 1000000002);
    }
}

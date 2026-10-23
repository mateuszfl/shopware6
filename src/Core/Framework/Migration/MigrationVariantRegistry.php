<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Log\Package;

/**
 * Explicit PostgreSQL implementations. An identity always belongs to the original source.
 * Core entries live here so the installer can resolve them before the container boots.
 *
 * @internal
 */
#[Package('framework')]
final class MigrationVariantRegistry
{
    /**
     * @var list<array{class-string<MigrationStep>, int, class-string<MigrationStep>}>
     */
    private const CORE_VARIANTS = [];

    /**
     * @var array<class-string<MigrationStep>, array{int, class-string<MigrationStep>}>
     */
    private array $variants = [];

    /**
     * @param iterable<array{class-string<MigrationStep>, int, class-string<MigrationStep>}> $variants
     */
    public function __construct(iterable $variants = [])
    {
        foreach ([self::CORE_VARIANTS, $variants] as $entries) {
            foreach ($entries as [$logicalClass, $timestamp, $implementation]) {
                $this->register($logicalClass, $timestamp, $implementation);
            }
        }
    }

    /**
     * Register the original class itself only after auditing it as portable.
     *
     * @param class-string<MigrationStep> $logicalClass
     * @param class-string<MigrationStep> $implementation
     */
    public function register(string $logicalClass, int $timestamp, string $implementation): void
    {
        if (isset($this->variants[$logicalClass]) || $this->isVariant($logicalClass)
            || ($implementation !== $logicalClass && (isset($this->variants[$implementation]) || $this->isVariant($implementation)))) {
            throw MigrationException::invalidArgument('Duplicate migration variant: ' . $logicalClass . ' -> ' . $implementation);
        }
        if ((new $logicalClass())->getPlausibleCreationTimestamp() !== $timestamp
            || (new $implementation())->getPlausibleCreationTimestamp() !== $timestamp) {
            throw MigrationException::invalidArgument('Migration variant timestamp mismatch: ' . $logicalClass . ' -> ' . $implementation);
        }
        $this->variants[$logicalClass] = [$timestamp, $implementation];
    }

    public function isVariant(string $class): bool
    {
        foreach ($this->variants as $logicalClass => [, $implementation]) {
            if ($class === $implementation && $class !== $logicalClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<MigrationStep> $logicalClass
     */
    public function resolve(string $logicalClass, int $timestamp): MigrationStep
    {
        $variant = $this->variants[$logicalClass] ?? null;
        if ($variant === null) {
            throw MigrationException::migrationError('No PostgreSQL migration variant for ' . $logicalClass . ' (timestamp ' . $timestamp . ').');
        }
        if ($variant[0] !== $timestamp) {
            throw MigrationException::migrationError('PostgreSQL migration history timestamp mismatch for ' . $logicalClass . '.');
        }

        $implementation = $variant[1];

        return new $implementation();
    }
}

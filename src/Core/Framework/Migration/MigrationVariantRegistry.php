<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\PostgreSQL\V6_3 as PostgreSQL63;
use Shopware\Core\Migration\V6_3 as Core63;

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
    private const CORE_VARIANTS = [
        [Core63\Migration1536232600Language::class, 1536232600, PostgreSQL63\Migration1536232600Language::class],
        [Core63\Migration1536232610Locale::class, 1536232610, PostgreSQL63\Migration1536232610Locale::class],
        [Core63\Migration1536232620SalesChannelType::class, 1536232620, PostgreSQL63\Migration1536232620SalesChannelType::class],
        [Core63\Migration1536232630PropertyGroup::class, 1536232630, PostgreSQL63\Migration1536232630PropertyGroup::class],
        [Core63\Migration1536232640Currency::class, 1536232640, PostgreSQL63\Migration1536232640Currency::class],
        [Core63\Migration1536232650CustomerGroup::class, 1536232650, PostgreSQL63\Migration1536232650CustomerGroup::class],
        [Core63\Migration1536232660Tax::class, 1536232660, PostgreSQL63\Migration1536232660Tax::class],
        [Core63\Migration1536232670Unit::class, 1536232670, PostgreSQL63\Migration1536232670Unit::class],
        [Core63\Migration1536232680Rule::class, 1536232680, PostgreSQL63\Migration1536232680Rule::class],
        [Core63\Migration1536232690Version::class, 1536232690, PostgreSQL63\Migration1536232690Version::class],
        [Core63\Migration1536232700VersionCommit::class, 1536232700, PostgreSQL63\Migration1536232700VersionCommit::class],
        [Core63\Migration1536232710Integration::class, 1536232710, PostgreSQL63\Migration1536232710Integration::class],
        [Core63\Migration1536232720Country::class, 1536232720, PostgreSQL63\Migration1536232720Country::class],
        [Core63\Migration1536232730CountryState::class, 1536232730, PostgreSQL63\Migration1536232730CountryState::class],
        [Core63\Migration1536232740SnippetSet::class, 1536232740, PostgreSQL63\Migration1536232740SnippetSet::class],
        [Core63\Migration1536232750Snippet::class, 1536232750, PostgreSQL63\Migration1536232750Snippet::class],
    ];

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

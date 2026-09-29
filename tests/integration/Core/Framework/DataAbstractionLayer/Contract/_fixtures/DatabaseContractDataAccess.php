<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

/**
 * Shared boundary for the lower-level fixture gateway and the production DAL
 * adapter added by the DAL porting tasks.
 *
 * @phpstan-type ContractData array{
 *     languages: list<array{id: string, name: string}>,
 *     products: list<array{id: string, versionId: string, parentId: ?string, parentVersionId: ?string, payload: ?array<string, mixed>, prices: list<array{currency: string, gross: float, net: float}>, active: bool, createdAt: string}>,
 *     translations: list<array{productId: string, productVersionId: string, languageId: string, name: string, description: ?string, payload: ?array<string, mixed>, createdAt: string}>,
 *     tags: list<array{id: string, name: string}>,
 *     productTags: list<array{productId: string, productVersionId: string, tagId: string}>
 * }
 *
 * @internal
 */
interface DatabaseContractDataAccess
{
    /**
     * @param ContractData $data
     */
    public function write(array $data): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $languageId): array;

    public function countProducts(): int;
}

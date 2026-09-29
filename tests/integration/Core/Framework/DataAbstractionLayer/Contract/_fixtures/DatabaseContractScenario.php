<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use PHPUnit\Framework\Assert;

/**
 * One input and assertion set for DAL repositories and lower-level gateways.
 *
 * @internal
 */
final class DatabaseContractScenario
{
    public const BATCH_SIZE = 3;
    public const LIVE_VERSION = '0fa91ce3e96a4bc2be4bd9ce752c3425';
    public const DRAFT_VERSION = '11111111111141118111111111111111';
    public const ENGLISH_LANGUAGE = '22222222222242228222222222222222';
    public const GERMAN_LANGUAGE = '33333333333343338333333333333333';

    /**
     * @return array{
     *     languages: list<array{id: string, name: string}>,
     *     products: list<array{id: string, versionId: string, parentId: ?string, parentVersionId: ?string, payload: ?array<string, mixed>, prices: list<array{currency: string, gross: float, net: float}>, active: bool, createdAt: string}>,
     *     translations: list<array{productId: string, productVersionId: string, languageId: string, name: string, description: ?string, payload: ?array<string, mixed>, createdAt: string}>,
     *     tags: list<array{id: string, name: string}>,
     *     productTags: list<array{productId: string, productVersionId: string, tagId: string}>
     * }
     */
    public static function data(): array
    {
        $products = [];
        $translations = [];
        for ($index = 0; $index < 6; ++$index) {
            $id = self::id('product-' . $index);
            $products[] = [
                'id' => $id,
                'versionId' => self::LIVE_VERSION,
                'parentId' => $index === 1 ? self::id('product-0') : null,
                'parentVersionId' => $index === 1 ? self::LIVE_VERSION : null,
                'payload' => $index === 2 ? null : [
                    'position' => $index,
                    'unicode' => 'Zażółć gęślą jaźń 商品 ' . $index,
                    'nullable' => null,
                    'enabled' => $index % 2 === 0,
                ],
                'prices' => [[
                    'currency' => 'EUR',
                    'gross' => 19.99 + $index,
                    'net' => 16.7983 + $index,
                ]],
                'active' => $index % 2 === 0,
                'createdAt' => '2024-02-29 23:59:59.' . str_pad((string) $index, 3, '0', \STR_PAD_LEFT),
            ];
            $translations[] = [
                'productId' => $id,
                'productVersionId' => self::LIVE_VERSION,
                'languageId' => self::ENGLISH_LANGUAGE,
                'name' => 'Product ' . $index,
                'description' => $index === 2 ? null : 'Description ' . $index,
                'payload' => $index === 3 ? null : ['search' => 'term-' . $index],
                'createdAt' => '2024-02-29 23:59:59.' . str_pad((string) $index, 3, '0', \STR_PAD_LEFT),
            ];
        }

        $products[] = [
            'id' => self::id('product-0'),
            'versionId' => self::DRAFT_VERSION,
            'parentId' => null,
            'parentVersionId' => null,
            'payload' => ['draft' => true],
            'prices' => [['currency' => 'EUR', 'gross' => 99.99, 'net' => 84.0252]],
            'active' => false,
            'createdAt' => '2024-03-01 00:00:00.000',
        ];
        $translations[] = [
            'productId' => self::id('product-0'),
            'productVersionId' => self::DRAFT_VERSION,
            'languageId' => self::ENGLISH_LANGUAGE,
            'name' => 'Draft product',
            'description' => null,
            'payload' => ['draft' => true],
            'createdAt' => '2024-03-01 00:00:00.000',
        ];

        return [
            'languages' => [
                ['id' => self::ENGLISH_LANGUAGE, 'name' => 'English'],
                ['id' => self::GERMAN_LANGUAGE, 'name' => 'Deutsch'],
            ],
            'products' => $products,
            'translations' => $translations,
            'tags' => [
                ['id' => self::id('tag-new'), 'name' => 'new'],
                ['id' => self::id('tag-sale'), 'name' => 'sale'],
                ['id' => self::id('tag-unicode'), 'name' => 'żółty'],
            ],
            'productTags' => [
                ['productId' => self::id('product-0'), 'productVersionId' => self::LIVE_VERSION, 'tagId' => self::id('tag-new')],
                ['productId' => self::id('product-0'), 'productVersionId' => self::LIVE_VERSION, 'tagId' => self::id('tag-sale')],
                ['productId' => self::id('product-1'), 'productVersionId' => self::LIVE_VERSION, 'tagId' => self::id('tag-unicode')],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $actual
     */
    public static function assertState(array $actual): void
    {
        $expected = [];
        $data = self::data();
        foreach ($data['products'] as $product) {
            $translation = null;
            foreach ($data['translations'] as $candidate) {
                if ($candidate['productId'] === $product['id'] && $candidate['productVersionId'] === $product['versionId'] && $candidate['languageId'] === self::ENGLISH_LANGUAGE) {
                    $translation = $candidate;
                    break;
                }
            }

            $tags = [];
            foreach ($data['productTags'] as $productTag) {
                if ($productTag['productId'] !== $product['id'] || $productTag['productVersionId'] !== $product['versionId']) {
                    continue;
                }
                foreach ($data['tags'] as $tag) {
                    if ($tag['id'] === $productTag['tagId']) {
                        $tags[] = $tag['name'];
                    }
                }
            }
            sort($tags);

            $expected[] = [
                'id' => $product['id'],
                'versionId' => $product['versionId'],
                'parentId' => $product['parentId'],
                'parentVersionId' => $product['parentVersionId'],
                'payload' => $product['payload'],
                'prices' => $product['prices'],
                'active' => $product['active'],
                'createdAt' => $product['createdAt'],
                'name' => $translation['name'] ?? null,
                'description' => $translation['description'] ?? null,
                'translationPayload' => $translation['payload'] ?? null,
                'tags' => $tags,
            ];
        }

        self::sortState($expected);
        self::sortState($actual);
        Assert::assertSame($expected, $actual);
    }

    public static function assertRoundTrip(DatabaseContractDataAccess $dataAccess): void
    {
        $dataAccess->write(self::data());
        self::assertState($dataAccess->read(self::ENGLISH_LANGUAGE));
    }

    /**
     * @param list<array<string, mixed>> $state
     */
    private static function sortState(array &$state): void
    {
        foreach ($state as &$row) {
            foreach (['payload', 'prices', 'translationPayload'] as $jsonField) {
                $row[$jsonField] = self::canonicalizeJson($row[$jsonField]);
            }
            if (\is_string($row['createdAt']) && str_ends_with($row['createdAt'], '.000')) {
                $row['createdAt'] = substr($row['createdAt'], 0, -4);
            }
        }
        unset($row);

        usort($state, static fn (array $left, array $right): int => [$left['id'], $left['versionId']] <=> [$right['id'], $right['versionId']]);
    }

    private static function canonicalizeJson(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        foreach ($value as &$item) {
            $item = self::canonicalizeJson($item);
        }
        unset($item);

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private static function id(string $seed): string
    {
        return md5('shopware-database-contract-' . $seed);
    }
}

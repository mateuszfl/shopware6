<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\SimpleQueryStringQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Search;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;

#[Package('inventory')]
final class ProductAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly ElasticsearchFieldBuilder $fieldBuilder,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $productIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'productNumber',
            'ean',
            'manufacturerNumber',
            'active',
            'manufacturerId',
            'price',
            'stock',
            'releaseDate',
        ]);

        $translations = $event->getPrimaryKeysWithPropertyChange(ProductTranslationDefinition::ENTITY_NAME, [
            'name',
            'customSearchKeywords',
        ]);

        $categories = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $event->getPrimaryKeysWithPropertyChange(ProductCategoryDefinition::ENTITY_NAME, [
            'categoryId',
        ]) : [];

        $visibilities = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $event->getPrimaryKeysWithPropertyChange(ProductVisibilityDefinition::ENTITY_NAME, [
            'salesChannelId',
        ]) : [];

        $media = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $event->getPrimaryKeysWithPropertyChange(ProductMediaDefinition::ENTITY_NAME, [
            'mediaId',
        ]) : [];

        $tags = $event->getPrimaryKeysWithPropertyChange(ProductTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach (array_merge($translations, $tags, $visibilities, $categories, $media) as $pks) {
            if (isset($pks['productId'])) {
                $productIds[] = $pks['productId'];
            }
        }

        return array_values(array_unique(array_filter($productIds, '\is_string')));
    }

    public function getName(): string
    {
        return 'product-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociations(['options.group']);

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search($criteria, $context)->getEntities(),
        ];
    }

    public function globalCriteria(string $term, Search $criteria): Search
    {
        $splitTerms = explode(' ', $term);
        $lastPart = (string) end($splitTerms);
        $identifierTerm = mb_strtolower($term);
        $identifierFields = ['ean', 'productNumber', 'manufacturerNumber'];
        $isPrefixTerm = preg_match('/^[\p{L}0-9]+$/u', $lastPart) === 1;
        $textBoostedTerm = $isPrefixTerm ? $term . '*' : $term;

        foreach ($identifierFields as $field) {
            $criteria->addQuery(
                new TermQuery($field, $identifierTerm, ['boost' => SearchRanking::HIGH_SEARCH_RANKING]),
                BoolQuery::SHOULD
            );
        }

        $criteria->addQuery(
            new SimpleQueryStringQuery($textBoostedTerm, [
                'fields' => ['textBoosted'],
                'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                'lenient' => true,
            ]),
            BoolQuery::SHOULD
        );

        return $criteria;
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'available' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'name' => $languageFields,
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'sales' => AbstractElasticsearchDefinition::INT_FIELD,
            'type' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'states' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'productNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'ean' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'stock' => AbstractElasticsearchDefinition::INT_FIELD,
            'price' => ['type' => 'object', 'dynamic' => true],
            'releaseDate' => ElasticsearchFieldBuilder::datetime(),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'updatedAt' => ElasticsearchFieldBuilder::datetime(),
            'categories' => ElasticsearchFieldBuilder::nested(),
            'tags' => ElasticsearchFieldBuilder::nested(),
            'manufacturer' => ElasticsearchFieldBuilder::nested([
                'name' => $languageFields,
            ]),
            'media' => ElasticsearchFieldBuilder::nested(),
            'visibilities' => ElasticsearchFieldBuilder::nested([
                'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
        ];

        $mapping['properties'] = array_merge($mapping['properties'] ?? [], $override);

        $mapping['dynamic_templates'][] = [
            'price_fields' => [
                'path_match' => 'price.*.*',
                'mapping' => ['type' => 'double'],
            ],
        ];

        return $mapping;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetch(array $ids): array
    {
        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return $this->advancedFetch($ids);
        }

        $customSearchKeywords = (new DatabasePlatformResolver($this->connection))->capabilities()->supportsJsonb
            ? 'CAST(translation.custom_search_keywords AS text)'
            : 'translation.custom_search_keywords';

        $data = $this->connection->fetchAllAssociative(
            str_replace(
                ['#nameAggregate#', '#customSearchKeywords#', '#tagsAggregate#', '#productGroupBy#'],
                [
                    IndexingSql::distinctAggregate($this->connection, 'translation.name', "\n"),
                    'CONCAT(\'[\', ' . IndexingSql::aggregate($this->connection, $customSearchKeywords, ',', 'NULL') . ', \']\')',
                    IndexingSql::distinctAggregate($this->connection, 'tag.name', ' '),
                    (new DatabasePlatformResolver($this->connection))->capabilities()->supportsJsonb ? 'product.id, product.version_id' : 'product.id',
                ],
                '
            SELECT sw_uuid_hex(product.id) as id,
                   #nameAggregate# as name,
                   #customSearchKeywords# as custom_search_keywords,
                   #tagsAggregate# as tags,
                   product.product_number,
                   product.ean,
                   product.manufacturer_number
            FROM product
                INNER JOIN product_translation AS translation
                    ON product.id = translation.product_id AND product.version_id = translation.product_version_id
                LEFT JOIN product_tag
                    ON product.id = product_tag.product_id AND product.version_id = product_tag.product_version_id
                LEFT JOIN tag
                    ON product_tag.tag_id = tag.id
            WHERE product.id IN (:ids)
            AND product.version_id = :versionId
            GROUP BY #productGroupBy#
        '
            ),
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
                'versionId' => ParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $row = self::mapDatabaseRowKeys($row);
            $names = $this->splitTranslatedNames(\is_string($row['name'] ?? null) ? $row['name'] : '');
            $textBoosted = (string) ($row['productNumber'] ?? '');

            if ($row['customSearchKeywords']) {
                $row['customSearchKeywords'] = json_decode((string) $row['customSearchKeywords'], true, 512, \JSON_THROW_ON_ERROR);
                $textBoosted = trim($textBoosted . ' ' . implode(' ', array_unique(array_merge(...$row['customSearchKeywords']))));
            }

            $id = (string) $row['id'];
            unset($row['name'], $row['customSearchKeywords']);
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = [
                'id' => $id,
                'textBoosted' => \strtolower($textBoosted),
                'text' => \strtolower($text),
                'completion' => $this->buildCompletion($names),
            ];
        }

        return $mapped;
    }

    /**
     * @return list<string>
     */
    private function splitTranslatedNames(string $concat): array
    {
        if ($concat === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $concat)),
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * @description to keep the writing fast we do a more complex fetch here only if the feature flag ENABLE_OPENSEARCH_FOR_ADMIN_API is enabled to reduce the number of joins in the sql query
     *
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function advancedFetch(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $binaryIds = array_values(Uuid::fromHexToBytesList($ids));

        $baseRows = $this->fetchProductBaseRows($binaryIds, $versionId);

        if ($baseRows === []) {
            return [];
        }

        $productIds = $this->collectHexColumnValues($baseRows, 'id');
        $parentIds = $this->collectHexColumnValues($baseRows, 'parentId');
        $translationIds = array_values(array_unique(array_merge($productIds, $parentIds)));
        $translationsByProductId = $this->fetchTranslationsByProductIds($translationIds, $versionId);

        $manufacturerIds = $this->collectHexColumnValues($baseRows, 'manufacturerId');
        $manufacturerById = $this->fetchManufacturerTranslationsByIds($manufacturerIds, $versionId);

        $tagsByProductId = $this->fetchTagsByProductIds($binaryIds, $versionId);

        $visibilityIds = $this->collectHexColumnValues($baseRows, 'visibilitiesId');
        $visibilitiesByProductId = $this->fetchVisibilitiesByIds($visibilityIds, $versionId);

        return $this->mapAdvancedRows(
            $baseRows,
            $translationsByProductId,
            $manufacturerById,
            $tagsByProductId,
            $visibilitiesByProductId
        );
    }

    /**
     * @param list<string> $binaryIds
     *
     * @return list<array<string, mixed>>
     */
    private function fetchProductBaseRows(array $binaryIds, string $versionId): array
    {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT sw_uuid_hex(product.id) as id,
                   COALESCE(product.active, parent.active) AS active,
                   product.available AS available,
                   sw_uuid_hex(product.parent_id) as parent_id,
                   product.product_number as product_number,
                   product.ean as ean,
                   product.manufacturer_number as manufacturer_number,
                   product.sales as sales,
                   product.type as type,
                   product.states as states,
                   sw_uuid_hex(product.manufacturer) AS manufacturer_id,
                   sw_uuid_hex(product.visibilities) AS visibilities_id,
                   COALESCE(product.category_ids, parent.category_ids) AS category_ids,
                   product.stock as stock,
                   COALESCE(product.price, parent.price) AS price_raw,
                   COALESCE(product.release_date, parent.release_date) AS release_date,
                   sw_uuid_hex(product.cover) AS media_id,
                   product.created_at as created_at,
                   product.updated_at as updated_at
            FROM product
                LEFT JOIN product parent
                    ON product.parent_id = parent.id
                    AND parent.version_id = :versionId
            WHERE product.id IN (:ids)
            AND product.version_id = :versionId
SQL,
            ['ids' => $binaryIds, 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY, 'versionId' => ParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $translationIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchTranslationsByProductIds(array $translationIds, string $versionId): array
    {
        if ($translationIds === []) {
            return [];
        }

        $customSearchKeywords = (new DatabasePlatformResolver($this->connection))->capabilities()->supportsJsonb
            ? 'CAST(product_translation.custom_search_keywords AS text)'
            : 'product_translation.custom_search_keywords';

        $rows = $this->connection->fetchAllAssociative(
            str_replace(
                ['#nameAggregate#', '#translatedNames#', '#customSearchKeywords#'],
                [
                    IndexingSql::distinctAggregate($this->connection, 'product_translation.name', ' '),
                    IndexingSql::jsonObjectAggregate($this->connection, [
                        'languageId' => 'sw_uuid_hex(product_translation.language_id)',
                        'name' => 'product_translation.name',
                    ], filter: 'product_translation.name IS NOT NULL'),
                    'CONCAT(\'[\', COALESCE(' . IndexingSql::aggregate($this->connection, $customSearchKeywords, ',', 'NULL') . ', \'\'), \']\')',
                ],
                <<<'SQL'
            SELECT sw_uuid_hex(product_translation.product_id) as id,
                   #nameAggregate# as name,
                   #translatedNames# as translated_names,
                   #customSearchKeywords# as custom_search_keywords
            FROM product_translation
            WHERE product_translation.product_id IN (:ids)
            AND product_translation.product_version_id = :versionId
            AND (
                product_translation.name IS NOT NULL
                OR product_translation.custom_search_keywords IS NOT NULL
            )
            GROUP BY product_translation.product_id, product_translation.product_version_id
SQL
            ),
            ['ids' => Uuid::fromHexToBytesList($translationIds), 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY, 'versionId' => ParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<string> $manufacturerIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchManufacturerTranslationsByIds(array $manufacturerIds, string $versionId): array
    {
        if ($manufacturerIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            str_replace(
                '#translatedManufacturerNames#',
                IndexingSql::jsonObjectAggregate($this->connection, [
                    'languageId' => 'sw_uuid_hex(product_manufacturer_translation.language_id)',
                    'name' => 'product_manufacturer_translation.name',
                ]),
                <<<'SQL'
            SELECT sw_uuid_hex(product_manufacturer_translation.product_manufacturer_id) as id,
                   #translatedManufacturerNames# as translated_manufacturer_names
            FROM product_manufacturer_translation
            WHERE product_manufacturer_translation.product_manufacturer_id IN (:ids)
            AND product_manufacturer_translation.product_manufacturer_version_id = :versionId
            GROUP BY product_manufacturer_translation.product_manufacturer_id, product_manufacturer_translation.product_manufacturer_version_id
SQL
            ),
            ['ids' => Uuid::fromHexToBytesList($manufacturerIds), 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY, 'versionId' => ParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<string> $binaryIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchTagsByProductIds(array $binaryIds, string $versionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            str_replace(
                ['#tagsAggregate#', '#tagIdsAggregate#'],
                [
                    IndexingSql::distinctAggregate($this->connection, 'tag.name', ' '),
                    IndexingSql::aggregate($this->connection, 'sw_uuid_hex(tag.id)', ' ', 'tag.id'),
                ],
                <<<'SQL'
            SELECT sw_uuid_hex(product_tag.product_id) as id,
                   #tagsAggregate# as tags,
                   #tagIdsAggregate# as tag_ids
            FROM product_tag
                LEFT JOIN tag
                    ON product_tag.tag_id = tag.id
            WHERE product_tag.product_id IN (:ids)
            AND product_tag.product_version_id = :versionId
            GROUP BY product_tag.product_id, product_tag.product_version_id
SQL
            ),
            ['ids' => $binaryIds, 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY, 'versionId' => ParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<string> $visibilityIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchVisibilitiesByIds(array $visibilityIds, string $versionId): array
    {
        if ($visibilityIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            str_replace(
                '#visibilities#',
                IndexingSql::jsonObjectAggregate($this->connection, [
                    'salesChannelId' => 'sw_uuid_hex(product_visibility.sales_channel_id)',
                ], distinct: true),
                <<<'SQL'
            SELECT sw_uuid_hex(product_visibility.product_id) as id,
                   #visibilities# as visibilities
            FROM product_visibility
            WHERE product_visibility.product_id IN (:ids)
            AND product_visibility.product_version_id = :versionId
            GROUP BY product_visibility.product_id, product_visibility.product_version_id
SQL
            ),
            ['ids' => Uuid::fromHexToBytesList($visibilityIds), 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY, 'versionId' => ParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<array<string, mixed>> $baseRows
     * @param array<string, array<string, mixed>> $translationsByProductId
     * @param array<string, array<string, mixed>> $manufacturerById
     * @param array<string, array<string, mixed>> $tagsByProductId
     * @param array<string, array<string, mixed>> $visibilitiesByProductId
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapAdvancedRows(
        array $baseRows,
        array $translationsByProductId,
        array $manufacturerById,
        array $tagsByProductId,
        array $visibilitiesByProductId
    ): array {
        $mapped = [];
        foreach ($baseRows as $row) {
            $id = \is_string($row['id'] ?? null) ? $row['id'] : null;
            if ($id === null) {
                continue;
            }

            $parentId = \is_string($row['parentId'] ?? null) ? $row['parentId'] : null;
            $manufacturerId = \is_string($row['manufacturerId'] ?? null) ? $row['manufacturerId'] : null;
            $visibilitiesId = \is_string($row['visibilitiesId'] ?? null) ? $row['visibilitiesId'] : null;

            $ownTranslation = $translationsByProductId[$id] ?? null;
            $parentTranslation = $parentId !== null ? ($translationsByProductId[$parentId] ?? null) : null;

            $name = \is_string($ownTranslation['name'] ?? null) && $ownTranslation['name'] !== ''
                ? $ownTranslation['name']
                : (\is_string($parentTranslation['name'] ?? null) ? $parentTranslation['name'] : '');
            $translatedNamesEncoded = \is_string($ownTranslation['translatedNames'] ?? null) && $ownTranslation['translatedNames'] !== '[]'
                ? $ownTranslation['translatedNames']
                : (\is_string($parentTranslation['translatedNames'] ?? null) ? $parentTranslation['translatedNames'] : '');
            $customSearchKeywordsEncoded = \is_string($ownTranslation['customSearchKeywords'] ?? null) && $ownTranslation['customSearchKeywords'] !== '[]'
                ? $ownTranslation['customSearchKeywords']
                : (\is_string($parentTranslation['customSearchKeywords'] ?? null) ? $parentTranslation['customSearchKeywords'] : '');
            $translatedManufacturerNamesEncoded = ($manufacturerId !== null && \is_string($manufacturerById[$manufacturerId]['translatedManufacturerNames'] ?? null))
                ? $manufacturerById[$manufacturerId]['translatedManufacturerNames']
                : '';
            $tags = \is_string($tagsByProductId[$id]['tags'] ?? null) ? $tagsByProductId[$id]['tags'] : '';
            $tagIds = \is_string($tagsByProductId[$id]['tagIds'] ?? null) ? $tagsByProductId[$id]['tagIds'] : '';
            $visibilitiesEncoded = ($visibilitiesId !== null && \is_string($visibilitiesByProductId[$visibilitiesId]['visibilities'] ?? null))
                ? $visibilitiesByProductId[$visibilitiesId]['visibilities']
                : '';

            $productNumber = \is_string($row['productNumber'] ?? null) ? $row['productNumber'] : '';
            $textBoosted = $productNumber;
            if ($customSearchKeywordsEncoded !== '') {
                $customSearchKeywords = json_decode($customSearchKeywordsEncoded, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($customSearchKeywords) && $customSearchKeywords !== []) {
                    $textBoosted = trim($textBoosted . ' ' . implode(' ', array_unique(array_merge(...$customSearchKeywords))));
                }
            }

            $translatedNames = $this->decodeTranslatedValues($translatedNamesEncoded);
            $states = ElasticsearchIndexingUtils::parseJson(['states' => $row['states'] ?? null], 'states');
            $categoryIds = ElasticsearchIndexingUtils::parseJson(['categoryIds' => $row['categoryIds'] ?? null], 'categoryIds');
            $visibilities = ElasticsearchIndexingUtils::parseJson(['visibilities' => $visibilitiesEncoded ?: null], 'visibilities');
            $parsedTagIds = $this->parseTagIds(['tagIds' => $tagIds]);
            $price = $this->parsePrice($row);
            $textParts = array_filter([
                $tags,
                $productNumber,
                \is_string($row['ean'] ?? null) ? $row['ean'] : null,
                \is_string($row['manufacturerNumber'] ?? null) ? $row['manufacturerNumber'] : null,
                $id,
            ]);

            $mapped[$id] = [
                'id' => $id,
                'textBoosted' => \strtolower($textBoosted),
                'text' => \strtolower(implode(' ', $textParts)),
                'completion' => $this->buildCompletion(array_values($translatedNames) ?: [$name]),
                'name' => $translatedNames,
                'parentId' => $parentId,
                'productNumber' => \is_string($row['productNumber'] ?? null) ? $row['productNumber'] : null,
                'ean' => \is_string($row['ean'] ?? null) ? $row['ean'] : null,
                'manufacturerNumber' => \is_string($row['manufacturerNumber'] ?? null) ? $row['manufacturerNumber'] : null,
                'manufacturerId' => $manufacturerId,
                'sales' => (int) $row['sales'],
                'active' => (bool) $row['active'],
                'available' => (bool) $row['available'],
                'stock' => (int) $row['stock'],
                'price' => $price,
                'type' => $row['type'] ?? null,
                'states' => $states,
                'manufacturer' => $manufacturerId ? [
                    'id' => $manufacturerId,
                    'name' => $this->decodeTranslatedValues($translatedManufacturerNamesEncoded),
                ] : null,
                'categories' => array_map(static function (string $categoryId): array {
                    return [
                        'id' => $categoryId,
                        'versionId' => Defaults::LIVE_VERSION,
                        '_count' => 1,
                    ];
                }, $categoryIds),
                'visibilities' => array_map(static function (array $visibility): array {
                    return array_merge(['_count' => 1], $visibility);
                }, $visibilities),
                'media' => \is_string($row['mediaId'] ?? null) ? [['id' => $row['mediaId'], '_count' => 1]] : [],
                'tags' => $parsedTagIds,
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
                'updatedAt' => $this->formatDateTime($row, 'updatedAt'),
                'releaseDate' => $this->formatDateTime($row, 'releaseDate'),
            ];
        }

        return $mapped;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    private function collectHexColumnValues(array $rows, string $column): array
    {
        return array_values(array_unique(array_filter(
            array_column($rows, $column),
            static fn (mixed $value): bool => \is_string($value) && Uuid::isValid($value)
        )));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexRowsById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $id = \is_string($row['id'] ?? null) ? $row['id'] : null;
            if ($id === null || !Uuid::isValid($id)) {
                continue;
            }
            $indexed[$id] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, array{gross: float, net: float}>|null
     */
    private function parsePrice(array $row): ?array
    {
        $raw = $row['priceRaw'] ?? null;

        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        $prices = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($prices)) {
            return null;
        }

        $result = [];
        foreach ($prices as $key => $priceData) {
            if (!\is_array($priceData) || !isset($priceData['gross'])) {
                continue;
            }

            $currencyId = \is_string($key) && str_starts_with($key, 'c') ? substr($key, 1) : $key;

            $result['c_' . $currencyId] = [
                'gross' => (float) $priceData['gross'],
                'net' => (float) ($priceData['net'] ?? 0),
            ];
        }

        return $result !== [] ? $result : null;
    }
}

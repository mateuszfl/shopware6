<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;

#[Package('inventory')]
final class OrderAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'order-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize, Defaults::LIVE_VERSION);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $orderIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'orderNumber',
            'amountTotal',
            'orderDateTime',
            'stateId',
        ]);

        $addresses = $event->getPrimaryKeysWithPropertyChange(OrderAddressDefinition::ENTITY_NAME, [
            'city',
            'street',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryId',
            'orderId',
        ]);

        $orderDocuments = $event->getPrimaryKeysWithPropertyChange(DocumentDefinition::ENTITY_NAME, [
            'config',
            'orderId',
        ]);

        $transactions = $event->getPrimaryKeysWithPropertyChange(OrderTransactionDefinition::ENTITY_NAME, [
            'stateId',
        ]);

        $deliveries = $event->getPrimaryKeysWithPropertyChange(OrderDeliveryDefinition::ENTITY_NAME, [
            'stateId',
        ]);

        if ($addresses !== [] || $orderDocuments !== [] || $transactions !== [] || $deliveries !== []) {
            $orderIds = array_merge($orderIds, $event->getPrimaryKeys($this->getEntity()));
        }

        $tags = $event->getPrimaryKeysWithPropertyChange(OrderTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['orderId'])) {
                $orderIds[] = $pks['orderId'];
            }
        }

        return array_values(array_unique(array_filter($orderIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'orderNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'amountTotal' => AbstractElasticsearchDefinition::FLOAT_FIELD,
            'orderDate' => ElasticsearchFieldBuilder::datetime(),
            'orderDateTime' => ElasticsearchFieldBuilder::datetime(),
            'stateId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'stateMachineState' => ElasticsearchFieldBuilder::nested(),
            'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'affiliateCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'campaignCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
            'billingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'orderCustomer' => ElasticsearchFieldBuilder::nested([
                'customer' => ElasticsearchFieldBuilder::nested([
                    'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                    'customerNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                ]),
            ]),
            'lineItems' => ElasticsearchFieldBuilder::nested([
                'productId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'payload' => [
                    'properties' => [
                        'code' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                    ],
                ],
            ]),
            'primaryOrderTransaction' => ElasticsearchFieldBuilder::nested([
                'stateMachineState' => ElasticsearchFieldBuilder::nested(),
                'paymentMethodId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'primaryOrderDelivery' => ElasticsearchFieldBuilder::nested([
                'stateMachineState' => ElasticsearchFieldBuilder::nested(),
                'shippingMethodId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'shippingOrderAddress' => ElasticsearchFieldBuilder::nested([
                    'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                ]),
            ]),
            'documents' => ElasticsearchFieldBuilder::nested(),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @return array<string, array{
     *     id: string,
     *     text: string,
     *     completion: list<string>,
     *     orderNumber?: mixed,
     *     amountTotal?: float|null,
     *     orderDate?: string|null,
     *     orderDateTime?: string|null,
     *     stateId?: mixed,
     *     stateMachineState?: array{
     *         id: mixed,
     *         _count: int
     *     }|null,
     *     salesChannelId?: mixed,
     *     affiliateCode?: mixed,
     *     campaignCode?: mixed,
     *     tags?: list<array{id: string, _count: int}>,
     *     billingAddress?: array{
     *         id: string,
     *         _count: int,
     *         countryId: string
     *     }|null,
     *     orderCustomer?: array{
     *         id: string,
     *         _count: int,
     *         customer: array{
     *             id: string,
     *             _count: int,
     *             groupId: string,
     *             customerNumber: string
     *         }|null
     *     }|null,
     *     lineItems?: list<array{
     *         id: string,
     *         _count: int,
     *         productId: string|null,
     *         payload: array{code: string|null}
     *     }>,
     *     primaryOrderTransaction?: array{
     *         id: string,
     *         _count: int,
     *         stateMachineState: array{id: string, _count: int},
     *         paymentMethodId: string
     *     }|null,
     *     primaryOrderDelivery?: array{
     *         id: string,
     *         _count: int,
     *         stateMachineState: array{id: string, _count: int},
     *         shippingMethodId: string,
     *         shippingOrderAddress: array{id: string, _count: int, countryId: string}
     *     }|null,
     *     documents?: list<array{id: string, _count: int}>,
     *     createdAt?: string|null
     * }>
     */
    public function fetch(array $ids): array
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $orderTable = $dialect->quoteIdentifier('order');
        $promotionCode = $dialect->capabilities()->supportsJsonb
            ? 'CASE WHEN order_line_item.promotion_id IS NOT NULL THEN order_line_item.payload::jsonb ->> \'code\' END'
            : 'CASE WHEN order_line_item.promotion_id IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(order_line_item.payload, \'$.code\')) END';

        $baseSql = str_replace(
            ['#orderTable#', '#tagsAggregate#', '#tagIdsAggregate#', '#countryAggregate#', '#cityAggregate#', '#streetAggregate#', '#zipcodeAggregate#', '#phoneAggregate#', '#addressLine1Aggregate#', '#addressLine2Aggregate#', '#documentNumberAggregate#', '#documentIdsAggregate#', '#lineItemsAggregate#'],
            [
                $orderTable,
                IndexingSql::distinctAggregate($this->connection, 'tag.name', ' '),
                IndexingSql::aggregate($this->connection, 'sw_uuid_hex(tag.id)', ' ', 'tag.id'),
                IndexingSql::distinctAggregate($this->connection, 'country_translation.name', ' '),
                IndexingSql::distinctAggregate($this->connection, 'order_address.city', ' '),
                IndexingSql::distinctAggregate($this->connection, 'order_address.street', ' '),
                IndexingSql::distinctAggregate($this->connection, 'order_address.zipcode', ' '),
                IndexingSql::distinctAggregate($this->connection, 'order_address.phone_number', ' '),
                IndexingSql::distinctAggregate($this->connection, 'order_address.additional_address_line1', ' '),
                IndexingSql::distinctAggregate($this->connection, 'order_address.additional_address_line2', ' '),
                IndexingSql::distinctAggregate($this->connection, 'document.document_number', ' '),
                IndexingSql::distinctAggregate($this->connection, 'sw_uuid_hex(document.id)', ' '),
                IndexingSql::jsonObjectAggregate($this->connection, [
                    'id' => 'sw_uuid_hex(order_line_item.id)',
                    'productId' => 'sw_uuid_hex(order_line_item.product_id)',
                    'code' => $promotionCode,
                ], distinct: true),
            ],
            <<<'SQL'
            SELECT sw_uuid_hex(#orderTable#.id) as id,
                   tag_agg.tags as tags,
                   tag_agg.tag_ids as tag_ids,
                   address_agg.country as country,
                   address_agg.city as city,
                   address_agg.street as street,
                   address_agg.zipcode as zipcode,
                   address_agg.phone_number as phone_number,
                   address_agg.additional_address_line1 as additional_address_line1,
                   address_agg.additional_address_line2 as additional_address_line2,
                   document_agg.document_number as document_number,
                   order_customer.first_name,
                   order_customer.last_name,
                   order_customer.email,
                   order_customer.company,
                   order_customer.customer_number,
                   #orderTable#.order_number,
                   #orderTable#.amount_total,
                   #orderTable#.order_date_time,
                   sw_uuid_hex(#orderTable#.state_id) AS state_id,
                   sw_uuid_hex(#orderTable#.sales_channel_id) AS sales_channel_id,
                   #orderTable#.affiliate_code AS affiliate_code,
                   #orderTable#.campaign_code AS campaign_code,
                   #orderTable#.created_at as created_at,
                   primary_delivery.tracking_codes,
                   document_agg.document_ids as document_ids,
                   sw_uuid_hex(billing_address.id) as billing_address_id,
                   sw_uuid_hex(billing_address.country_id) as billing_address_country_id,
                   sw_uuid_hex(order_customer.id) as order_customer_id,
                   sw_uuid_hex(order_customer.customer_id) as customer_id,
                   sw_uuid_hex(customer.customer_group_id) as customer_group_id,
                   customer.customer_number as live_customer_number,
                   line_item_agg.line_items as line_items,
                   sw_uuid_hex(primary_transaction.id) as primary_transaction_id,
                   sw_uuid_hex(primary_transaction.state_id) as primary_transaction_state_id,
                   sw_uuid_hex(primary_transaction.payment_method_id) as primary_transaction_payment_method_id,
                   sw_uuid_hex(primary_delivery.id) as primary_delivery_id,
                   sw_uuid_hex(primary_delivery.state_id) as primary_delivery_state_id,
                   sw_uuid_hex(primary_delivery.shipping_method_id) as primary_delivery_shipping_method_id,
                   sw_uuid_hex(primary_delivery_address.country_id) as primary_delivery_country_id
            FROM #orderTable#
                LEFT JOIN order_customer
                    ON #orderTable#.id = order_customer.order_id AND #orderTable#.version_id = order_customer.order_version_id
                LEFT JOIN (
                    SELECT order_tag.order_id,
                           order_tag.order_version_id,
                           #tagsAggregate# as tags,
                           #tagIdsAggregate# as tag_ids
                    FROM order_tag
                    LEFT JOIN tag
                        ON order_tag.tag_id = tag.id
                    WHERE order_tag.order_id IN (:ids)
                    AND order_tag.order_version_id = :versionId
                    GROUP BY order_tag.order_id, order_tag.order_version_id
                ) as tag_agg
                    ON #orderTable#.id = tag_agg.order_id AND #orderTable#.version_id = tag_agg.order_version_id
                LEFT JOIN (
                    SELECT order_address.order_id,
                           order_address.order_version_id,
                           #countryAggregate# as country,
                           #cityAggregate# as city,
                           #streetAggregate# as street,
                           #zipcodeAggregate# as zipcode,
                           #phoneAggregate# as phone_number,
                           #addressLine1Aggregate# as additional_address_line1,
                           #addressLine2Aggregate# as additional_address_line2
                    FROM order_address
                    LEFT JOIN country
                        ON order_address.country_id = country.id
                    LEFT JOIN country_translation
                        ON country.id = country_translation.country_id
                    WHERE order_address.order_id IN (:ids)
                    AND order_address.order_version_id = :versionId
                    GROUP BY order_address.order_id, order_address.order_version_id
                ) as address_agg
                    ON #orderTable#.id = address_agg.order_id AND #orderTable#.version_id = address_agg.order_version_id
                LEFT JOIN (
                    SELECT document.order_id,
                           #documentNumberAggregate# as document_number,
                           #documentIdsAggregate# as document_ids
                    FROM document
                    WHERE document.order_id IN (:ids)
                    GROUP BY document.order_id
                ) as document_agg
                    ON #orderTable#.id = document_agg.order_id
                LEFT JOIN order_address AS billing_address
                    ON #orderTable#.billing_address_id = billing_address.id AND #orderTable#.billing_address_version_id = billing_address.version_id
                LEFT JOIN customer
                    ON order_customer.customer_id = customer.id
                LEFT JOIN (
                    SELECT order_line_item.order_id,
                           order_line_item.order_version_id,
                           #lineItemsAggregate# as line_items
                    FROM order_line_item
                    WHERE order_line_item.order_id IN (:ids)
                    AND order_line_item.order_version_id = :versionId
                    GROUP BY order_line_item.order_id, order_line_item.order_version_id
                ) as line_item_agg
                    ON #orderTable#.id = line_item_agg.order_id AND #orderTable#.version_id = line_item_agg.order_version_id
                LEFT JOIN order_transaction AS primary_transaction
                    ON #orderTable#.primary_order_transaction_id = primary_transaction.id AND #orderTable#.primary_order_transaction_version_id = primary_transaction.version_id
                LEFT JOIN order_delivery AS primary_delivery
                    ON #orderTable#.primary_order_delivery_id = primary_delivery.id AND #orderTable#.primary_order_delivery_version_id = primary_delivery.version_id
                LEFT JOIN order_address AS primary_delivery_address
                    ON primary_delivery.shipping_order_address_id = primary_delivery_address.id AND primary_delivery.shipping_order_address_version_id = primary_delivery_address.version_id
            WHERE #orderTable#.id IN (:ids)
            AND #orderTable#.version_id = :versionId
SQL
        );

        $data = $this->connection->fetchAllAssociative(
            $baseSql,
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
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['order_number'] ?? '',
                $row['email'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['company'] ?? '',
                $row['customer_number'] ?? '',
                $row['tags'] ?? '',
                $row['country'] ?? '',
                $row['city'] ?? '',
                $row['street'] ?? '',
                $row['zipcode'] ?? '',
                $row['phone_number'] ?? '',
                $row['additional_address_line1'] ?? '',
                $row['additional_address_line2'] ?? '',
                $row['documentNumber'] ?? '',
                $row['amount_total'] ?? '',
                $row['tracking_codes'] ?? '',
                $id,
            ]));

            $completion = $this->buildCompletion([
                \is_string($row['order_number'] ?? null) ? $row['order_number'] : null,
                \is_string($row['email'] ?? null) ? $row['email'] : null,
                \is_string($row['first_name'] ?? null) ? $row['first_name'] : null,
                \is_string($row['last_name'] ?? null) ? $row['last_name'] : null,
                \is_string($row['company'] ?? null) ? $row['company'] : null,
            ]);

            if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                $mapped[$id] = [
                    'id' => $id,
                    'text' => \strtolower($text),
                    'completion' => $completion,
                ];

                continue;
            }

            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'completion' => $completion,
                'orderNumber' => $row['order_number'] ?? null,
                'amountTotal' => isset($row['amount_total']) ? (float) $row['amount_total'] : null,
                'orderDate' => $this->formatDateTime($row, 'order_date_time'),
                'orderDateTime' => $this->formatDateTime($row, 'order_date_time'),
                'stateId' => $row['stateId'] ?? null,
                'stateMachineState' => isset($row['stateId']) ? ['id' => $row['stateId'], '_count' => 1] : null,
                'salesChannelId' => $row['salesChannelId'] ?? null,
                'affiliateCode' => $row['affiliateCode'] ?? null,
                'campaignCode' => $row['campaignCode'] ?? null,
                'tags' => $this->parseTagIds($row),
                'billingAddress' => $this->parseAddress($row, 'billingAddressId', 'billingAddressCountryId'),
                'orderCustomer' => $this->parseOrderCustomer($row),
                'lineItems' => $this->parseLineItems($row),
                'primaryOrderTransaction' => $this->parsePrimaryOrderTransaction($row),
                'primaryOrderDelivery' => $this->parsePrimaryOrderDelivery($row),
                'documents' => $this->parseTagIds($row, 'documentIds'),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, countryId: string}|null
     */
    private function parseAddress(array $row, string $idKey, string $countryIdKey): ?array
    {
        if (!isset($row[$idKey]) || $row[$idKey] === '') {
            return null;
        }

        return [
            'id' => $row[$idKey],
            '_count' => 1,
            'countryId' => $row[$countryIdKey] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, customer: array{id: string, _count: int, groupId: string, customerNumber: string}|null}|null
     */
    private function parseOrderCustomer(array $row): ?array
    {
        if (!isset($row['orderCustomerId']) || $row['orderCustomerId'] === '') {
            return null;
        }

        $customer = null;
        if (isset($row['customerId']) && $row['customerId'] !== '') {
            $customer = [
                'id' => $row['customerId'],
                '_count' => 1,
                'groupId' => $row['customerGroupId'] ?? '',
                'customerNumber' => $row['liveCustomerNumber'] ?? '',
            ];
        }

        return [
            'id' => $row['orderCustomerId'],
            '_count' => 1,
            'customer' => $customer,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{id: string, _count: int, productId: string|null, payload: array{code: string|null}}>
     */
    private function parseLineItems(array $row): array
    {
        return array_values(array_map(static function (array $item) {
            return [
                'id' => (string) ($item['id'] ?? ''),
                '_count' => 1,
                'productId' => \is_string($item['productId']) ? $item['productId'] : null,
                'payload' => ['code' => \is_string($item['code']) ? $item['code'] : null],
            ];
        }, ElasticsearchIndexingUtils::parseJson($row, 'lineItems')));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, stateMachineState: array{id: string, _count: int}, paymentMethodId: string}|null
     */
    private function parsePrimaryOrderTransaction(array $row): ?array
    {
        if (!isset($row['primaryTransactionId']) || $row['primaryTransactionId'] === '') {
            return null;
        }

        return [
            'id' => $row['primaryTransactionId'],
            '_count' => 1,
            'stateMachineState' => [
                'id' => $row['primaryTransactionStateId'] ?? '',
                '_count' => 1,
            ],
            'paymentMethodId' => $row['primaryTransactionPaymentMethodId'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, stateMachineState: array{id: string, _count: int}, shippingMethodId: string, shippingOrderAddress: array{id: string, _count: int, countryId: string}}|null
     */
    private function parsePrimaryOrderDelivery(array $row): ?array
    {
        if (!isset($row['primaryDeliveryId']) || $row['primaryDeliveryId'] === '') {
            return null;
        }

        return [
            'id' => $row['primaryDeliveryId'],
            '_count' => 1,
            'stateMachineState' => [
                'id' => $row['primaryDeliveryStateId'] ?? '',
                '_count' => 1,
            ],
            'shippingMethodId' => $row['primaryDeliveryShippingMethodId'] ?? '',
            'shippingOrderAddress' => [
                'id' => $row['primaryDeliveryId'],
                '_count' => 1,
                'countryId' => $row['primaryDeliveryCountryId'] ?? '',
            ],
        ];
    }
}

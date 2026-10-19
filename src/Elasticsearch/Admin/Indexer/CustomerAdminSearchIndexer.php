<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
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

#[Package('inventory')]
final class CustomerAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $repository
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
        return CustomerDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'customer-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $customerIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'firstName',
            'lastName',
            'email',
            'company',
            'customerNumber',
            'active',
            'groupId',
            'defaultBillingAddressId',
            'defaultShippingAddressId',
        ]);

        $addresses = $event->getPrimaryKeysWithPropertyChange(CustomerAddressDefinition::ENTITY_NAME, [
            'firstName',
            'lastName',
            'company',
            'city',
            'street',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryId',
        ]);

        if ($addresses !== []) {
            $customerIds = array_merge($customerIds, $event->getPrimaryKeys($this->getEntity()));
        }

        $tags = $event->getPrimaryKeysWithPropertyChange(CustomerTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['customerId'])) {
                $customerIds[] = $pks['customerId'];
            }
        }

        return array_values(array_unique(array_filter($customerIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'email' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'firstName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'lastName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'customerNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'company' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'affiliateCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'campaignCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'salutationId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'boundSalesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'requestedGroupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'defaultBillingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'defaultShippingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
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
     *     active?: bool,
     *     email?: mixed,
     *     firstName?: mixed,
     *     lastName?: mixed,
     *     customerNumber?: mixed,
     *     company?: mixed,
     *     affiliateCode?: mixed,
     *     campaignCode?: mixed,
     *     groupId?: mixed,
     *     salutationId?: mixed,
     *     boundSalesChannelId?: mixed,
     *     requestedGroupId?: mixed,
     *     defaultBillingAddress?: array{
     *         id: string,
     *         _count: int,
     *         countryId: string
     *     }|null,
     *     defaultShippingAddress?: array{
     *         id: string,
     *         _count: int,
     *         countryId: string
     *     }|null,
     *     tags?: list<array{
     *         id: string,
     *         _count: int
     *     }>,
     *     createdAt?: string|null
     * }>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            str_replace(
                ['#countryAggregate#', '#addressFirstNameAggregate#', '#addressLastNameAggregate#', '#addressCompanyAggregate#', '#cityAggregate#', '#streetAggregate#', '#zipcodeAggregate#', '#phoneAggregate#', '#addressLine1Aggregate#', '#addressLine2Aggregate#', '#tagsAggregate#', '#tagIdsAggregate#'],
                [
                    IndexingSql::distinctAggregate($this->connection, 'country_translation.name', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.first_name', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.last_name', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.company', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.city', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.street', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.zipcode', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.phone_number', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.additional_address_line1', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'customer_address.additional_address_line2', ' '),
                    IndexingSql::distinctAggregate($this->connection, 'tag.name', ' '),
                    IndexingSql::aggregate($this->connection, 'sw_uuid_hex(tag.id)', ' ', 'tag.id'),
                ],
                <<<'SQL'
            SELECT sw_uuid_hex(customer.id) as id,
                   tag_agg.tags as tags,
                   tag_agg.tag_ids as tag_ids,
                   address_agg.country as country,
                   address_agg.address_first_name as address_first_name,
                   address_agg.address_last_name as address_last_name,
                   address_agg.address_company as address_company,
                   address_agg.city as city,
                   address_agg.street as street,
                   address_agg.zipcode as zipcode,
                   address_agg.phone_number as phone_number,
                   address_agg.additional_address_line1 as additional_address_line1,
                   address_agg.additional_address_line2 as additional_address_line2,
                   customer.first_name,
                   customer.last_name,
                   customer.email,
                   customer.company,
                   customer.customer_number,
                   customer.active AS active,
                   customer.affiliate_code AS affiliate_code,
                   customer.campaign_code AS campaign_code,
                   sw_uuid_hex(customer.customer_group_id) AS group_id,
                   sw_uuid_hex(customer.salutation_id) AS salutation_id,
                   sw_uuid_hex(customer.bound_sales_channel_id) AS bound_sales_channel_id,
                   sw_uuid_hex(customer.requested_customer_group_id) AS requested_group_id,
                   sw_uuid_hex(customer.default_billing_address_id) AS default_billing_address_id,
                   sw_uuid_hex(default_billing_address.country_id) AS default_billing_address_country_id,
                   sw_uuid_hex(customer.default_shipping_address_id) AS default_shipping_address_id,
                   sw_uuid_hex(default_shipping_address.country_id) AS default_shipping_address_country_id,
                   customer.created_at as created_at
            FROM customer
                LEFT JOIN (
                    SELECT customer_address.customer_id,
                           #countryAggregate# as country,
                           #addressFirstNameAggregate# as address_first_name,
                           #addressLastNameAggregate# as address_last_name,
                           #addressCompanyAggregate# as address_company,
                           #cityAggregate# as city,
                           #streetAggregate# as street,
                           #zipcodeAggregate# as zipcode,
                           #phoneAggregate# as phone_number,
                           #addressLine1Aggregate# as additional_address_line1,
                           #addressLine2Aggregate# as additional_address_line2
                    FROM customer_address
                    LEFT JOIN country
                        ON customer_address.country_id = country.id
                    LEFT JOIN country_translation
                        ON country.id = country_translation.country_id
                    WHERE customer_address.customer_id IN (:ids)
                    GROUP BY customer_address.customer_id
                ) as address_agg
                    ON address_agg.customer_id = customer.id
                LEFT JOIN customer_address AS default_billing_address
                    ON customer.default_billing_address_id = default_billing_address.id
                LEFT JOIN customer_address AS default_shipping_address
                    ON customer.default_shipping_address_id = default_shipping_address.id
                LEFT JOIN (
                    SELECT customer_tag.customer_id,
                           #tagsAggregate# as tags,
                           #tagIdsAggregate# as tag_ids
                    FROM customer_tag
                    LEFT JOIN tag
                        ON customer_tag.tag_id = tag.id
                    WHERE customer_tag.customer_id IN (:ids)
                    GROUP BY customer_tag.customer_id
                ) as tag_agg
                    ON tag_agg.customer_id = customer.id
            WHERE customer.id IN (:ids)
SQL
            ),
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $row = self::mapDatabaseRowKeys($row);
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['email'] ?? '',
                $row['customer_number'] ?? '',
                $row['company'] ?? '',
                $row['tags'] ?? '',
                $row['country'] ?? '',
                $row['address_first_name'] ?? '',
                $row['address_last_name'] ?? '',
                $row['address_company'] ?? '',
                $row['city'] ?? '',
                $row['street'] ?? '',
                $row['zipcode'] ?? '',
                $row['phone_number'] ?? '',
                $row['additional_address_line1'] ?? '',
                $row['additional_address_line2'] ?? '',
                $id,
            ]));

            $completion = $this->buildCompletion([
                \is_string($row['first_name'] ?? null) ? $row['first_name'] : null,
                \is_string($row['last_name'] ?? null) ? $row['last_name'] : null,
                \is_string($row['email'] ?? null) ? $row['email'] : null,
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
                'active' => (bool) $row['active'],
                'email' => $row['email'] ?? null,
                'firstName' => $row['first_name'] ?? null,
                'lastName' => $row['last_name'] ?? null,
                'customerNumber' => $row['customer_number'] ?? null,
                'company' => $row['company'] ?? null,
                'affiliateCode' => $row['affiliateCode'] ?? null,
                'campaignCode' => $row['campaignCode'] ?? null,
                'groupId' => $row['groupId'] ?? null,
                'salutationId' => $row['salutationId'] ?? null,
                'boundSalesChannelId' => $row['boundSalesChannelId'] ?? null,
                'requestedGroupId' => $row['requestedGroupId'] ?? null,
                'defaultBillingAddress' => $this->parseAddress($row, 'defaultBillingAddressId', 'defaultBillingAddressCountryId'),
                'defaultShippingAddress' => $this->parseAddress($row, 'defaultShippingAddressId', 'defaultShippingAddressCountryId'),
                'tags' => $this->parseTagIds($row),
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
}

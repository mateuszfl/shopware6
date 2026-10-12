<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('after-sales')]
class CustomerNewsletterSalesChannelsUpdater
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, bool $reverseUpdate = false): void
    {
        if ($ids === []) {
            return;
        }

        $ids = array_unique($ids);

        $selection = $reverseUpdate
            ? 'customer.id IN (:ids)'
            : 'EXISTS(SELECT 1 FROM newsletter_recipient selected WHERE selected.email = customer.email AND selected.id IN (:ids))';
        $aggregate = IndexingSql::objectMap($this->connection, 'sw_uuid_hex(recipient.id)', 'sw_uuid_hex(recipient.sales_channel_id)');
        $sql = 'UPDATE customer SET newsletter_sales_channel_ids = (SELECT ' . $aggregate . ' FROM newsletter_recipient recipient WHERE recipient.email = customer.email AND recipient.status IN (:states)) WHERE ' . $selection;

        RetryableQuery::retryable($this->connection, function () use ($sql, $ids): void {
            $this->connection->executeStatement($sql, [
                'ids' => Uuid::fromHexToBytesList($ids),
                'states' => [NewsletterSubscribeRoute::STATUS_DIRECT, NewsletterSubscribeRoute::STATUS_OPT_IN],
            ], ['ids' => ArrayParameterType::BINARY, 'states' => ArrayParameterType::STRING]);
        });
    }

    /**
     * @param array<string> $ids
     */
    public function delete(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $expressions = [];
        $parameters = [];
        $types = [];
        foreach ($ids as $id) {
            $fragment = $dialect->jsonExtract('newsletter_sales_channel_ids', [$id])->named();
            $expressions[] = $fragment->sql . ' IS NOT NULL';
            $parameters = array_replace($parameters, $fragment->parameters);
            $types = array_replace($types, $fragment->types);
        }
        $customerIds = $this->connection->fetchFirstColumn('SELECT sw_uuid_hex(id) FROM customer WHERE ' . implode(' OR ', $expressions), $parameters, $types);
        if ($customerIds !== []) {
            $this->update($customerIds, true);
        }
    }

    /**
     * @param array<string> $ids
     */
    public function updateCustomersRecipient(array $ids): void
    {
        $ids = array_unique($ids);

        $customers = $this->connection->fetchAllAssociative(
            'SELECT newsletter_sales_channel_ids, email, first_name, last_name FROM customer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $parameters = [];

        foreach ($customers as $customer) {
            if (!$customer['newsletter_sales_channel_ids']) {
                continue;
            }

            $newsletterIds = array_keys(json_decode((string) $customer['newsletter_sales_channel_ids'], true, 512, \JSON_THROW_ON_ERROR));
            $newsletterIds = array_map('\strval', $newsletterIds);

            $parameters[] = [
                'newsletter_ids' => $newsletterIds,
                'email' => $customer['email'],
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
            ];
        }

        if ($parameters === []) {
            return;
        }

        foreach ($parameters as $parameter) {
            RetryableQuery::retryable($this->connection, function () use ($parameter): void {
                // a confirmed double opt-in was given for the old address, it must not carry over to the new one
                $this->connection->executeStatement(
                    'UPDATE newsletter_recipient SET status = (:notSet), confirmed_at = NULL WHERE id IN (:ids) AND email <> :email AND status = :optIn',
                    [
                        'ids' => Uuid::fromHexToBytesList($parameter['newsletter_ids']),
                        'email' => $parameter['email'],
                        'notSet' => NewsletterSubscribeRoute::STATUS_NOT_SET,
                        'optIn' => NewsletterSubscribeRoute::STATUS_OPT_IN,
                    ],
                    ['ids' => ArrayParameterType::BINARY],
                );

                $this->connection->executeStatement(
                    'UPDATE newsletter_recipient SET email = (:email), first_name = (:firstName), last_name = (:lastName) WHERE id IN (:ids)',
                    [
                        'ids' => Uuid::fromHexToBytesList($parameter['newsletter_ids']),
                        'email' => $parameter['email'],
                        'firstName' => $parameter['first_name'],
                        'lastName' => $parameter['last_name'],
                    ],
                    ['ids' => ArrayParameterType::BINARY],
                );
            });
        }
    }
}

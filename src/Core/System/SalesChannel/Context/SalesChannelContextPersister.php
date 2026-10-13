<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class SalesChannelContextPersister
{
    private readonly string $lifetimeInterval;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCartPersister $cartPersister,
        private readonly ClockInterface $clock,
        ?string $lifetimeInterval = 'P1D',
    ) {
        $this->lifetimeInterval = $lifetimeInterval ?? 'P1D';
    }

    /**
     * @codeCoverageIgnore
     *
     * @see \Shopware\Tests\Integration\Core\System\SalesChannel\Context\SalesChannelContextPersisterTest
     *
     * @param array<string, mixed> $newParameters
     */
    public function save(string $token, array $newParameters, string $salesChannelId, ?string $customerId = null): void
    {
        $existing = $this->load($token, $salesChannelId, $customerId);

        $parameters = array_replace_recursive($existing, $newParameters);
        if (isset($newParameters['permissions']) && $newParameters['permissions'] === []) {
            $parameters['permissions'] = [];
        }

        unset($parameters['token']);

        $data = [
            'token' => $token,
            'payload' => json_encode($parameters, \JSON_THROW_ON_ERROR),
            'salesChannelId' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
            'customerId' => $customerId ? Uuid::fromHexToBytes($customerId) : null,
            'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        if ((new DatabasePlatformResolver($this->connection))->capabilities()->supportsConflictTarget) {
            RetryableTransaction::retryable($this->connection, function () use ($data): void {
                try {
                    $this->connection->transactional(function () use ($data): void {
                        $this->connection->executeStatement(
                            'INSERT INTO sales_channel_api_context (token, payload, sales_channel_id, customer_id, updated_at)
                             VALUES (:token, :payload, :salesChannelId, :customerId, :updatedAt)
                             ON CONFLICT (token) DO UPDATE SET
                                 payload = EXCLUDED.payload, sales_channel_id = EXCLUDED.sales_channel_id, customer_id = EXCLUDED.customer_id, updated_at = EXCLUDED.updated_at',
                            $data,
                            ['salesChannelId' => $data['salesChannelId'] === null ? ParameterType::NULL : ParameterType::BINARY, 'customerId' => $data['customerId'] === null ? ParameterType::NULL : ParameterType::BINARY]
                        );
                    });
                } catch (UniqueConstraintViolationException) {
                    // Recover the savepoint before replacing every row conflicting with either unique key.
                    $this->connection->executeStatement(
                        'DELETE FROM sales_channel_api_context WHERE token = :token OR (sales_channel_id = :salesChannelId AND customer_id = :customerId)',
                        ['salesChannelId' => $data['salesChannelId'], 'token' => $data['token'], 'customerId' => $data['customerId']],
                        ['salesChannelId' => $data['salesChannelId'] === null ? ParameterType::NULL : ParameterType::BINARY, 'customerId' => $data['customerId'] === null ? ParameterType::NULL : ParameterType::BINARY]
                    );
                    $this->connection->insert('sales_channel_api_context', [
                        'token' => $data['token'], 'payload' => $data['payload'],
                        'sales_channel_id' => $data['salesChannelId'], 'customer_id' => $data['customerId'], 'updated_at' => $data['updatedAt'],
                    ], ['sales_channel_id' => $data['salesChannelId'] === null ? ParameterType::NULL : ParameterType::BINARY, 'customer_id' => $data['customerId'] === null ? ParameterType::NULL : ParameterType::BINARY]);
                }
            });

            return;
        }

        // Update in place for the usual single-key conflict to avoid REPLACE's delete and insert lock pattern.
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(<<<'SQL'
                INSERT INTO sales_channel_api_context (`token`, `payload`, `sales_channel_id`, `customer_id`, `updated_at`)
                VALUES (:token, :payload, :salesChannelId, :customerId, :updatedAt)
                ON DUPLICATE KEY UPDATE
                    `token` = :token,
                    `payload` = :payload,
                    `sales_channel_id` = :salesChannelId,
                    `customer_id` = :customerId,
                    updated_at = :updatedAt
                SQL)
        );

        try {
            $query->execute($data);
        } catch (UniqueConstraintViolationException) {
            // When both unique keys conflict, retain REPLACE's behavior of removing both conflicting rows.
            $query = new RetryableQuery(
                $this->connection,
                $this->connection->prepare(
                    'REPLACE INTO sales_channel_api_context (`token`, `payload`, `sales_channel_id`, `customer_id`, `updated_at`)
                    VALUES (:token, :payload, :salesChannelId, :customerId, :updatedAt)'
                )
            );

            $query->execute($data);
        }
    }

    public function delete(string $token, string $salesChannelId, ?string $customerId = null): void
    {
        $this->connection->executeStatement(
            'DELETE FROM sales_channel_api_context WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }

    public function replace(string $oldToken, SalesChannelContext $context): string
    {
        $newToken = Random::getAlphanumericString(32);

        $affected = $this->connection->executeStatement(
            'UPDATE sales_channel_api_context
                   SET token = :newToken,
                       updated_at = :updatedAt
                   WHERE token = :oldToken',
            [
                'newToken' => $newToken,
                'oldToken' => $oldToken,
                'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        if ($affected === 0) {
            $customer = $context->getCustomer();

            $this->connection->insert('sales_channel_api_context', [
                'token' => $newToken,
                'payload' => json_encode([]),
                'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                'customer_id' => $customer ? Uuid::fromHexToBytes($customer->getId()) : null,
                'updated_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], ['sales_channel_id' => ParameterType::BINARY, 'customer_id' => $customer === null ? ParameterType::NULL : ParameterType::BINARY]);
        }

        $this->cartPersister->replace($oldToken, $newToken, $context);

        $context->assign(['token' => $newToken]);
        $this->eventDispatcher->dispatch(new SalesChannelContextTokenChangeEvent($context, $oldToken, $newToken));

        return $newToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $token, string $salesChannelId, ?string $customerId = null): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('token', 'payload', 'updated_at', 'sw_uuid_hex(sales_channel_id) AS sales_channel_id', 'sw_uuid_hex(customer_id) AS customer_id');
        $qb->from('sales_channel_api_context');

        $qb->where('sales_channel_id = :salesChannelId');
        $qb->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId), ParameterType::BINARY);

        if ($customerId !== null) {
            $qb->andWhere('(token = :token OR customer_id = :customerId)');
            $qb->setParameter('token', $token);
            $qb->setParameter('customerId', Uuid::fromHexToBytes($customerId), ParameterType::BINARY);
            $qb->setMaxResults(2);
        } else {
            $qb->andWhere('token = :token');
            $qb->setParameter('token', $token);
            $qb->setMaxResults(1);
        }

        $data = $qb->executeQuery()->fetchAllAssociative();

        if ($data === []) {
            return [];
        }

        $customerContext = $salesChannelId && $customerId ? $this->getCustomerContext($data, $salesChannelId, $customerId) : null;

        $context = $customerContext ?? array_shift($data);

        $updatedAt = new \DateTimeImmutable($context['updated_at']);
        $expiredTime = $updatedAt->add(new \DateInterval($this->lifetimeInterval));

        $payload = array_filter(json_decode((string) $context['payload'], true, 512, \JSON_THROW_ON_ERROR));
        $now = $this->clock->now();
        if ($expiredTime < $now) {
            // context is expired
            if ($customerId !== null) {
                $payload['expired'] = true;
            } else {
                $payload = ['expired' => true];
            }
        } else {
            $payload['expired'] = false;
        }

        $payload['token'] = $context['token'];

        if (!isset($payload[SalesChannelContextService::CUSTOMER_ID])
            && \is_string($context['customer_id'] ?? null)
            && $context['customer_id'] !== ''
        ) {
            $payload[SalesChannelContextService::CUSTOMER_ID] = $context['customer_id'];
        }

        return $payload;
    }

    public function revokeAllCustomerTokens(string $customerId, string ...$preserveTokens): void
    {
        $revokeParams = [
            'customerId' => null,
            'billingAddressId' => null,
            'shippingAddressId' => null,
        ];

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->update('sales_channel_api_context')
            ->set('payload', ':payload')
            ->set('customer_id', 'NULL')
            ->set('updated_at', ':updatedAt')
            ->where('customer_id = :customerId')
            ->setParameter('updatedAt', $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->setParameter('payload', json_encode($revokeParams))
            ->setParameter('customerId', Uuid::fromHexToBytes($customerId), ParameterType::BINARY);

        // keep tokens valid, which are given in $preserveTokens
        if ($preserveTokens) {
            $qb
                ->andWhere($qb->expr()->notIn('token', ':preserveTokens'))
                ->setParameter('preserveTokens', $preserveTokens, ArrayParameterType::STRING);
        }

        $qb->executeStatement();
    }

    /**
     * @param array<array<string, mixed>> $data
     *
     * @return array<string, mixed>|null
     */
    private function getCustomerContext(array $data, string $salesChannelId, string $customerId): ?array
    {
        foreach ($data as $row) {
            $customerIdFromRow = $row['customer_id'] ?? null;
            if (\is_string($customerIdFromRow) && $customerIdFromRow !== ''
&& $row['sales_channel_id'] === $salesChannelId
&& $customerIdFromRow === $customerId
            ) {
                return $row;
            }
        }

        return null;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerMetaFieldSubscriberTest
 */
#[Package('checkout')]
class CustomerMetaFieldSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'fillCustomerMetaDataFields',
            PreWriteValidationEvent::class => 'deleteOrder',
        ];
    }

    public function fillCustomerMetaDataFields(StateMachineTransitionEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED && $event->getFromPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED) {
            return;
        }

        $this->updateCustomer([$event->getEntityId()]);
    }

    public function deleteOrder(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderIds = [];
        foreach ($event->getDeletedPrimaryKeys(OrderDefinition::ENTITY_NAME) as $primaryKey) {
            $orderIds[] = Uuid::fromBytesToHex($primaryKey['id']);
        }

        $this->updateCustomer($orderIds, true);
    }

    /**
     * @param array<string> $orderIds
     */
    private function updateCustomer(array $orderIds, bool $isDelete = false): void
    {
        if ($orderIds === []) {
            return;
        }

        $customerIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT sw_uuid_hex(customer_id) FROM order_customer WHERE order_id IN (:ids) AND order_version_id = :version AND customer_id IS NOT NULL',
            ['ids' => Uuid::fromHexToBytesList($orderIds), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY, 'version' => ParameterType::BINARY]
        );

        if ($customerIds === []) {
            return;
        }

        $parameters = [
            'customerIds' => Uuid::fromHexToBytesList($customerIds),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'state' => OrderStates::STATE_COMPLETED,
        ];
        $types = [
            'customerIds' => ArrayParameterType::BINARY,
            'version' => ParameterType::BINARY,
        ];

        $whereOrder = '';
        if ($isDelete) {
            $whereOrder = 'AND orders.id NOT IN (:exceptOrderIds)';
            $parameters['exceptOrderIds'] = Uuid::fromHexToBytesList($orderIds);
            $types['exceptOrderIds'] = ArrayParameterType::BINARY;
        }

        $orderTable = $this->connection->quoteSingleIdentifier('order');
        $select = "
            SELECT sw_uuid_hex(order_customer.customer_id) as customer_id,
                   order_customer.customer_id as id,
                   COUNT(orders.id) as order_count,
                   ROUND(SUM(orders.amount_total / orders.currency_factor), 2) as order_total_amount,
                   MAX(orders.order_date_time) as last_order_date

            FROM order_customer

            INNER JOIN {$orderTable} orders
                ON orders.id = order_customer.order_id
                AND orders.version_id = order_customer.order_version_id
                AND orders.version_id = :version
                {$whereOrder}

            INNER JOIN state_machine_state
                ON state_machine_state.id = orders.state_id
                AND state_machine_state.technical_name = :state

            WHERE order_customer.customer_id IN (:customerIds)
            GROUP BY order_customer.customer_id
        ";

        $data = [];
        foreach ($customerIds as $customerId) {
            $data[$customerId] = [
                'id' => Uuid::fromHexToBytes($customerId),
                'order_count' => 0,
                'order_total_amount' => 0,
                'last_order_date' => null,
            ];
        }

        // indexed by the hex customer id (first column), matching the keys of the reset values above
        $aggregated = $this->connection->fetchAllAssociativeIndexed($select, $parameters, $types);

        $data = array_replace($data, $aggregated);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE customer SET order_count = :order_count, order_total_amount = :order_total_amount, last_order_date = :last_order_date WHERE id = :id'),
            ['id' => ParameterType::BINARY, 'order_count' => ParameterType::INTEGER]
        );

        foreach ($data as $record) {
            $record['id'] = Uuid::fromHexToBytes(Uuid::fromBytesToHex($record['id']));
            $update->execute($record);
        }
    }
}

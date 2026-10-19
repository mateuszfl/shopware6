<?php declare(strict_types=1);

namespace Shopware\Administration\Dashboard;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class OrderAmountService
{
    public function __construct(
        private Connection $connection,
        private CashRounding $rounding,
        private bool $timeZoneSupportEnabled,
    ) {
    }

    /**
     * @return list<array{date:string, amount:float, count:int}>
     */
    public function load(Context $context, string $since, bool $paid, string $timezone = 'UTC'): array
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $query = $this->connection->createQueryBuilder();
        $orderTable = $dialect->quoteIdentifier('order');
        $orderDate = $orderTable . '.order_date_time';
        // @deprecated tag:v6.8.0 - time zone support always enabled, remove if, but keep content
        $timezoneEnabled = $this->timeZoneSupportEnabled || Feature::isActive('v6.8.0.0');
        $localDateBucket = $dialect->dateBucket(
            $orderDate,
            'day',
            $timezoneEnabled ? $timezone : null,
        )->named();
        $dateBucket = $timezoneEnabled
            ? new SqlFragment(
                'COALESCE(' . $localDateBucket->sql . ', ' . $dialect->dateBucket($orderDate, 'day')->sql . ')',
                $localDateBucket->parameters,
                $localDateBucket->types,
            )
            : $localDateBucket;

        $query->select(
            'CAST(' . $dateBucket->sql . ' AS DATE) AS ' . $dialect->quoteIdentifier('date'),
            'COUNT(' . $orderTable . '.id) AS ' . $dialect->quoteIdentifier('count'),
            'SUM(' . $orderTable . '.amount_total / COALESCE(' . $orderTable . '.currency_factor, currency.factor)) AS ' . $dialect->quoteIdentifier('amount'),
        );
        $query->setParameters($dateBucket->parameters, $dateBucket->types);

        $query->from($orderTable);
        $query->leftJoin($orderTable, 'currency', 'currency', $orderTable . '.currency_factor IS NULL AND currency.id = ' . $orderTable . '.currency_id');
        $query->andWhere($orderDate . ' >= :since');
        $query->andWhere($orderTable . '.version_id = :version');

        $query->groupBy($dialect->quoteIdentifier('date'));

        if ($paid) {
            $paidId = $this->connection->fetchOne('
                SELECT state.id FROM state_machine_state state
                INNER JOIN state_machine ON state.state_machine_id = state_machine.id
                WHERE state_machine.technical_name = :state_machine AND state.technical_name = :state
            ', [
                'state_machine' => OrderTransactionStates::STATE_MACHINE,
                'state' => OrderTransactionStates::STATE_PAID,
            ]);

            $query->innerJoin($orderTable, 'order_transaction', 'transactions', 'transactions.order_id = ' . $orderTable . '.id AND transactions.version_id = ' . $orderTable . '.version_id AND transactions.state_id = :paidId');
            $query->setParameter('paidId', $paidId, ParameterType::BINARY);
        }

        $query->setParameter('since', $since);
        $query->setParameter('version', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY);

        $query->orderBy($dialect->quoteIdentifier('date'), 'ASC');

        $data = $query->executeQuery()->fetchAllAssociative();

        $rounding = $context->getRounding();

        $mapped = [];
        foreach ($data as $row) {
            $mapped[] = [
                'date' => $row['date'],
                'count' => (int) $row['count'],
                'amount' => $this->rounding->cashRound((float) $row['amount'], $rounding),
            ];
        }

        return $mapped;
    }
}

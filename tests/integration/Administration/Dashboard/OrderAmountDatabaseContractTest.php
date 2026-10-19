<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Dashboard;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Dashboard\OrderAmountService;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderAmountDatabaseContractTest extends TestCase
{
    private Connection $connection;

    private string $orderTable;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $this->orderTable = $dialect->quoteIdentifier('order');
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $prefix = $postgres ? 'pg_temp.' : '';
        $drop = $postgres ? 'DROP TABLE IF EXISTS ' : 'DROP TEMPORARY TABLE IF EXISTS ';
        foreach (['order_transaction', 'state_machine_state', 'state_machine', 'currency', 'order'] as $table) {
            $this->connection->executeStatement($drop . $prefix . ($table === 'order' ? $this->orderTable : $table));
        }

        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $dateTime = $postgres ? 'TIMESTAMP WITHOUT TIME ZONE' : 'DATETIME(3)';
        $temporary = 'CREATE TEMPORARY TABLE ';
        $this->connection->executeStatement("$temporary$this->orderTable (
            id $binary PRIMARY KEY,
            version_id $binary NOT NULL,
            order_date_time $dateTime NOT NULL,
            amount_total DECIMAL(12, 2) NOT NULL,
            currency_factor DECIMAL(12, 4) NULL,
            currency_id $binary NOT NULL
        )");
        $this->connection->executeStatement("$temporary currency (id $binary PRIMARY KEY, factor DECIMAL(12, 4) NOT NULL)");
        $this->connection->executeStatement("$temporary state_machine (id $binary PRIMARY KEY, technical_name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("$temporary state_machine_state (
            id $binary PRIMARY KEY,
            state_machine_id $binary NOT NULL,
            technical_name VARCHAR(255) NOT NULL
        )");
        $this->connection->executeStatement("$temporary order_transaction (
            id $binary PRIMARY KEY,
            order_id $binary NOT NULL,
            version_id $binary NOT NULL,
            state_id $binary NOT NULL
        )");
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
            $prefix = $postgres ? 'pg_temp.' : '';
            $drop = $postgres ? 'DROP TABLE IF EXISTS ' : 'DROP TEMPORARY TABLE IF EXISTS ';
            foreach (['order_transaction', 'state_machine_state', 'state_machine', 'currency', 'order'] as $table) {
                $this->connection->executeStatement($drop . $prefix . ($table === 'order' ? $this->orderTable : $table));
            }
            $this->connection->close();
        }
    }

    public function testTimezoneBucketsCurrencyFallbackPaidStateAndLiveVersion(): void
    {
        $currencyId = random_bytes(16);
        $stateMachineId = random_bytes(16);
        $paidStateId = random_bytes(16);
        $openStateId = random_bytes(16);
        $this->connection->insert('currency', ['id' => $currencyId, 'factor' => 2.0], ['id' => ParameterType::BINARY]);
        $this->connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderTransactionStates::STATE_MACHINE,
        ], ['id' => ParameterType::BINARY]);
        $this->connection->insert('state_machine_state', [
            'id' => $paidStateId,
            'state_machine_id' => $stateMachineId,
            'technical_name' => OrderTransactionStates::STATE_PAID,
        ], ['id' => ParameterType::BINARY, 'state_machine_id' => ParameterType::BINARY]);
        $this->connection->insert('state_machine_state', [
            'id' => $openStateId,
            'state_machine_id' => $stateMachineId,
            'technical_name' => 'open',
        ], ['id' => ParameterType::BINARY, 'state_machine_id' => ParameterType::BINARY]);

        $this->insertOrder('2024-01-01 23:30:00.000', null, 20.00, Defaults::LIVE_VERSION, $currencyId, $paidStateId);
        $this->insertOrder('2024-01-02 00:30:00.000', 1.0, 30.00, Defaults::LIVE_VERSION, $currencyId, $openStateId);
        $this->insertOrder('2024-01-02 00:45:00.000', 1.0, 99.00, Uuid::randomHex(), $currencyId, $paidStateId);

        $service = new OrderAmountService($this->connection, new CashRounding(), true);
        $actual = $service->load(Context::createDefaultContext(), '2024-01-01', true, 'Europe/Warsaw');

        static::assertSame([
            ['date' => '2024-01-02', 'count' => 1, 'amount' => 10.0],
        ], $actual);
    }

    private function insertOrder(string $date, ?float $currencyFactor, float $amount, string $versionId, string $currencyId, string $stateId): void
    {
        $id = random_bytes(16);
        $this->connection->executeStatement('INSERT INTO ' . $this->orderTable . ' (id, version_id, order_date_time, amount_total, currency_factor, currency_id) VALUES (:id, :versionId, :date, :amount, :currencyFactor, :currencyId)', [
            'id' => $id,
            'versionId' => Uuid::fromHexToBytes($versionId),
            'date' => $date,
            'amount' => $amount,
            'currencyFactor' => $currencyFactor,
            'currencyId' => $currencyId,
        ], [
            'id' => ParameterType::BINARY,
            'versionId' => ParameterType::BINARY,
            'currencyId' => ParameterType::BINARY,
        ]);
        $this->connection->insert('order_transaction', [
            'id' => random_bytes(16),
            'order_id' => $id,
            'version_id' => Uuid::fromHexToBytes($versionId),
            'state_id' => $stateId,
        ], [
            'id' => ParameterType::BINARY,
            'order_id' => ParameterType::BINARY,
            'version_id' => ParameterType::BINARY,
            'state_id' => ParameterType::BINARY,
        ]);
    }
}

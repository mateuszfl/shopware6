<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Real payment/refund SQL reads with local handlers; no external payment requests.
 *
 * @internal
 */
#[Package('checkout')]
class PaymentSqlContractTest extends TestCase
{
    private const METHOD = '0000000000000000000000000000ff01';
    private const TRANSACTION = '0000000000000000000000000000ff02';
    private const CAPTURE = '0000000000000000000000000000ff03';
    private const REFUND = '0000000000000000000000000000ff04';
    private const STATE = '0000000000000000000000000000ff05';

    private const TABLES = ['app_payment_method', 'payment_method', 'order_transaction_capture_refund', 'order_transaction_capture', 'order_transaction', 'state_machine_state'];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        if ($postgres) {
            $this->connection->executeStatement('CREATE SCHEMA sw_payment_contract');
            $this->connection->executeStatement('SET search_path TO sw_payment_contract');
        }
        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $this->connection->executeStatement("CREATE TABLE payment_method (id $binary PRIMARY KEY, handler_identifier VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE app_payment_method (id $binary PRIMARY KEY, payment_method_id $binary)");
        $this->connection->executeStatement("CREATE TABLE state_machine_state (id $binary PRIMARY KEY, technical_name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE order_transaction (id $binary PRIMARY KEY, payment_method_id $binary)");
        $this->connection->executeStatement("CREATE TABLE order_transaction_capture (id $binary PRIMARY KEY, order_transaction_id $binary)");
        $this->connection->executeStatement("CREATE TABLE order_transaction_capture_refund (id $binary PRIMARY KEY, capture_id $binary, state_id $binary)");
        $this->connection->insert('payment_method', ['id' => Uuid::fromHexToBytes(self::METHOD), 'handler_identifier' => 'fixture_handler'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('state_machine_state', ['id' => Uuid::fromHexToBytes(self::STATE), 'technical_name' => 'open'], ['id' => ParameterType::BINARY]);
        $this->connection->insert('order_transaction', ['id' => Uuid::fromHexToBytes(self::TRANSACTION), 'payment_method_id' => Uuid::fromHexToBytes(self::METHOD)], ['id' => ParameterType::BINARY, 'payment_method_id' => ParameterType::BINARY]);
        $this->connection->insert('order_transaction_capture', ['id' => Uuid::fromHexToBytes(self::CAPTURE), 'order_transaction_id' => Uuid::fromHexToBytes(self::TRANSACTION)], ['id' => ParameterType::BINARY, 'order_transaction_id' => ParameterType::BINARY]);
        $this->connection->insert('order_transaction_capture_refund', ['id' => Uuid::fromHexToBytes(self::REFUND), 'capture_id' => Uuid::fromHexToBytes(self::CAPTURE), 'state_id' => Uuid::fromHexToBytes(self::STATE)], ['id' => ParameterType::BINARY, 'capture_id' => ParameterType::BINARY, 'state_id' => ParameterType::BINARY]);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('DROP SCHEMA sw_payment_contract CASCADE');
        } else {
            foreach (self::TABLES as $table) {
                $this->connection->executeStatement('DROP TABLE ' . $table);
            }
        }
        $this->connection->close();
    }

    public function testRegistryResolvesCoreAndAppHandlersAndMissingMethod(): void
    {
        $handler = static::createStub(AbstractPaymentHandler::class);
        $appHandler = static::createStub(AppPaymentHandler::class);
        $registry = new PaymentHandlerRegistry(new ServiceLocator([
            'fixture_handler' => static fn () => $handler,
            AppPaymentHandler::class => static fn () => $appHandler,
        ]), $this->connection);

        static::assertSame($handler, $registry->getPaymentMethodHandler(self::METHOD));
        static::assertNull($registry->getPaymentMethodHandler(Uuid::randomHex()));
        $this->connection->insert('app_payment_method', ['id' => Uuid::randomBytes(), 'payment_method_id' => Uuid::fromHexToBytes(self::METHOD)], ['id' => ParameterType::BINARY, 'payment_method_id' => ParameterType::BINARY]);
        static::assertSame($appHandler, $registry->getPaymentMethodHandler(self::METHOD));
    }

    public function testRefundReadsBinaryReferencesAndCallsTheLocalHandler(): void
    {
        $context = Context::createDefaultContext();
        $handler = static::createMock(AbstractPaymentHandler::class);
        $handler->expects($this->once())->method('supports')->with(PaymentHandlerType::REFUND, self::METHOD, $context)->willReturn(true);
        $handler->expects($this->once())->method('refund')->willReturnCallback(static function (RefundPaymentTransactionStruct $struct, Context $actualContext) use ($context): void {
            static::assertSame(self::REFUND, $struct->getRefundId());
            static::assertSame(self::TRANSACTION, $struct->getOrderTransactionId());
            static::assertSame($context, $actualContext);
        });
        $stateHandler = static::createMock(OrderTransactionCaptureRefundStateHandler::class);
        $stateHandler->expects($this->never())->method('fail');
        $factory = static::createMock(AbstractPaymentTransactionStructFactory::class);
        $factory->expects($this->once())->method('refund')->with(self::REFUND, self::TRANSACTION)->willReturn(new RefundPaymentTransactionStruct(self::REFUND, self::TRANSACTION));
        $registry = new PaymentHandlerRegistry(new ServiceLocator(['fixture_handler' => static fn () => $handler]), $this->connection);

        (new PaymentRefundProcessor($this->connection, $stateHandler, $registry, $factory))->processRefund(self::REFUND, $context);
    }

    public function testCompletedRefundIsRejectedBeforeCallingTheHandler(): void
    {
        $this->connection->executeStatement('UPDATE state_machine_state SET technical_name = :state', ['state' => 'completed']);
        $registry = new PaymentHandlerRegistry(new ServiceLocator([]), $this->connection);
        $processor = new PaymentRefundProcessor($this->connection, static::createStub(OrderTransactionCaptureRefundStateHandler::class), $registry, static::createStub(AbstractPaymentTransactionStructFactory::class));

        $this->expectExceptionObject(PaymentException::refundInvalidTransition(self::REFUND, 'completed'));
        $processor->processRefund(self::REFUND, Context::createDefaultContext());
    }

    public function testMissingRefundIsRejected(): void
    {
        $registry = new PaymentHandlerRegistry(new ServiceLocator([]), $this->connection);
        $processor = new PaymentRefundProcessor($this->connection, static::createStub(OrderTransactionCaptureRefundStateHandler::class), $registry, static::createStub(AbstractPaymentTransactionStructFactory::class));
        $id = Uuid::randomHex();

        $this->expectExceptionObject(PaymentException::unknownRefund($id));
        $processor->processRefund($id, Context::createDefaultContext());
    }
}

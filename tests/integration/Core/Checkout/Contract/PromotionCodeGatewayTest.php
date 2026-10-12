<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionCodeGateway;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionCodeGatewayTest extends TestCase
{
    private const CODE = '0000000000000000000000000000ff04';

    private Connection $connection;

    private PromotionCodeGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        if ($postgres) {
            $this->connection->executeStatement('CREATE SCHEMA sw_promotion_code_contract');
            $this->connection->executeStatement('SET search_path TO sw_promotion_code_contract');
        }
        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $postgres ? 'JSONB' : 'JSON';
        $this->connection->executeStatement("CREATE TABLE promotion_individual_code (id $binary PRIMARY KEY, payload $json)");
        $this->connection->insert('promotion_individual_code', ['id' => Uuid::fromHexToBytes(self::CODE)], ['id' => ParameterType::BINARY]);
        $this->gateway = new PromotionCodeGateway($this->connection);
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeStatement('DROP SCHEMA sw_promotion_code_contract CASCADE');
        } else {
            $this->connection->executeStatement('DROP TABLE promotion_individual_code');
        }
        $this->connection->close();
    }

    public function testRepeatedClaimForTheSameOrderPreservesTheOriginalPayload(): void
    {
        $payload = ['orderId' => Uuid::randomHex(), 'customerName' => 'First customer'];
        $this->gateway->claim(self::CODE, 'ONE', $payload);
        $this->gateway->claim(self::CODE, 'ONE', ['orderId' => $payload['orderId'], 'customerName' => 'Updated customer']);

        static::assertEquals($payload, json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testAnotherOrderCannotReplaceTheClaim(): void
    {
        $payload = ['orderId' => Uuid::randomHex()];
        $this->gateway->claim(self::CODE, 'ONE', $payload);
        try {
            $this->gateway->claim(self::CODE, 'ONE', ['orderId' => Uuid::randomHex()]);
            static::fail('Another order cannot redeem this code');
        } catch (PromotionException $exception) {
            static::assertEquals(PromotionException::codeAlreadyRedeemed('ONE'), $exception);
        }

        static::assertEquals($payload, json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR));
        static::assertFalse($this->connection->isTransactionActive());
    }

    public function testRollbackAllowsAnotherOrderToClaimTheCode(): void
    {
        $this->connection->beginTransaction();
        $this->gateway->claim(self::CODE, 'ONE', ['orderId' => Uuid::randomHex()]);
        $this->connection->rollBack();
        static::assertNull($this->connection->fetchOne('SELECT payload FROM promotion_individual_code'));

        $payload = ['orderId' => Uuid::randomHex()];
        $this->gateway->claim(self::CODE, 'ONE', $payload);
        static::assertEquals($payload, json_decode((string) $this->connection->fetchOne('SELECT payload FROM promotion_individual_code'), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testMissingCodeIsRejected(): void
    {
        $this->expectExceptionObject(PromotionException::codeAlreadyRedeemed('MISSING'));
        $this->gateway->claim(Uuid::randomHex(), 'MISSING', ['orderId' => Uuid::randomHex()]);
    }
}

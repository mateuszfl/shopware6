<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Contract\PromotionCodeGatewayTest
 */
#[Package('checkout')]
class PromotionCodeGateway
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Reserve the code before the repository emits its usual write events.
     * Repeated discounts belonging to the same order are idempotent.
     *
     * @param array<string, string> $payload
     */
    public function claim(string $id, string $code, array $payload): void
    {
        $this->connection->transactional(function () use ($id, $code, $payload): void {
            $bytes = Uuid::fromHexToBytes($id);
            $affected = $this->connection->executeStatement(
                'UPDATE promotion_individual_code SET payload = :payload WHERE id = :id AND payload IS NULL',
                ['id' => $bytes, 'payload' => json_encode($payload, \JSON_THROW_ON_ERROR)],
                ['id' => ParameterType::BINARY, 'payload' => ParameterType::STRING],
            );
            if ($affected !== 0) {
                return;
            }

            $stored = $this->connection->fetchOne(
                'SELECT payload FROM promotion_individual_code WHERE id = :id FOR UPDATE',
                ['id' => $bytes],
                ['id' => ParameterType::BINARY],
            );
            if (\is_string($stored) && (json_decode($stored, true, 512, \JSON_THROW_ON_ERROR)['orderId'] ?? null) === $payload['orderId']) {
                return;
            }

            throw PromotionException::codeAlreadyRedeemed($code);
        });
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class OrderPersister implements OrderPersisterInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderConverter $converter,
        private readonly CartSerializationCleaner $cartSerializationCleaner,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws CartException
     * @throws OrderException
     * @throws InvalidCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function persist(Cart $cart, SalesChannelContext $context): string
    {
        if ($cart->getErrors()->blockOrder()) {
            throw CartException::invalidCart($cart->getErrors());
        }

        if (!$context->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        if ($cart->getLineItems()->count() <= 0) {
            throw CartException::cartEmpty();
        }

        // cleanup cart before converting it to an order
        $this->cartSerializationCleaner->cleanupCart($cart);

        $order = $this->converter->convertToOrder($cart, $context, new OrderConversionContext());

        // Raw DBAL scope deliberately retains gateway-local success callbacks inside this
        // transaction: stock, promotion redemption and repository write events must succeed
        // together with the order. Do not retry externally observable event handlers here.
        $this->connection->transactional(function () use ($order, $context): void {
            $promotionIds = [];
            foreach ($order['lineItems'] ?? [] as $lineItem) {
                if (isset($lineItem['promotionId'])) {
                    $promotionIds[] = $lineItem['promotionId'];
                }
            }
            $promotionIds = array_values(array_unique($promotionIds));
            sort($promotionIds);
            if ($promotionIds !== []) {
                // Lock before DAL reads establish a MySQL repeatable-read snapshot.
                $this->connection->fetchFirstColumn(
                    'SELECT id FROM promotion WHERE id IN (:ids) ORDER BY id FOR UPDATE',
                    ['ids' => Uuid::fromHexToBytesList($promotionIds)],
                    ['ids' => ArrayParameterType::BINARY],
                );
            }
            $context->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($order): void {
                $this->orderRepository->create([$order], $context);
            });
        });

        return $order['id'];
    }
}

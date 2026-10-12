<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class StockStorage extends AbstractStockStorage
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getDecorated(): AbstractStockStorage
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection
    {
        return new StockDataCollection([]);
    }

    /**
     * @param list<StockAlteration> $changes
     */
    public function alter(array $changes, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($changes === []) {
            return;
        }

        $sql = 'UPDATE product
            SET available_stock = stock + :quantity, stock = stock + :quantity, sales = sales - :quantity, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND version_id = :version';

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare($sql),
            ['quantity' => ParameterType::INTEGER, 'id' => ParameterType::BINARY, 'version' => ParameterType::BINARY]
        );

        foreach ($changes as $alteration) {
            $query->execute([
                'quantity' => $alteration->quantityDelta(),
                'id' => Uuid::fromHexToBytes($alteration->productId),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }

        $this->updateAvailableFlag(array_column($changes, 'productId'), $context);

        $this->dispatcher->dispatch(new ProductStockAlteredEvent(array_column($changes, 'productId'), $context));
    }

    /**
     * @param list<string> $productIds
     */
    public function index(array $productIds, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->updateAvailableFlag($productIds, $context);
    }

    /**
     * @param list<string> $ids
     */
    private function updateAvailableFlag(array $ids, Context $context): void
    {
        $ids = array_filter(array_unique($ids));

        if ($ids === []) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = IndexingSql::joinedUpdate(
            $this->connection,
            'product',
            'target',
            '(SELECT child.id, child.version_id, CASE WHEN '
            . 'COALESCE(child.is_closeout, parent.is_closeout, 0) * child.stock >= '
            . 'COALESCE(child.is_closeout, parent.is_closeout, 0) * COALESCE(child.min_purchase, parent.min_purchase) '
            . 'THEN 1 ELSE 0 END AS available '
            . 'FROM product child LEFT JOIN product parent ON parent.id = child.parent_id AND parent.version_id = child.version_id) availability',
            ['available' => 'availability.available', 'updated_at' => 'CURRENT_TIMESTAMP'],
            'target.id = availability.id AND target.version_id = availability.version_id AND target.id IN (:ids) AND target.version_id = :version',
        );

        $before = $this->connection->fetchAllKeyValue(
            'SELECT sw_uuid_hex(id), available FROM product WHERE id IN (:ids) AND product.version_id = :version',
            ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => ArrayParameterType::BINARY, 'version' => ParameterType::BINARY]
        );

        RetryableQuery::retryable($this->connection, function () use ($sql, $context, $bytes): void {
            $this->connection->executeStatement(
                $sql,
                ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
                ['ids' => ArrayParameterType::BINARY, 'version' => ParameterType::BINARY]
            );
        });

        $after = $this->connection->fetchAllKeyValue(
            'SELECT sw_uuid_hex(id), available FROM product WHERE id IN (:ids) AND product.version_id = :version',
            ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => ArrayParameterType::BINARY, 'version' => ParameterType::BINARY]
        );

        $updated = [];
        foreach ($before as $id => $available) {
            if ($available !== $after[$id]) {
                $updated[] = (string) $id;
            }
        }

        if ($updated !== []) {
            $this->dispatcher->dispatch(new ProductNoLongerAvailableEvent($updated, $context));
        }
    }
}

<?php declare(strict_types=1);

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionCodeGateway;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionExclusionUpdater;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Integration\Core\Checkout\Contract\CheckoutPersistenceContractTest;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

require dirname(__DIR__, 6) . '/vendor/autoload.php';
require dirname(__DIR__) . '/CheckoutPersistenceContractTest.php';

$order = json_decode($argv[1], true, 512, \JSON_THROW_ON_ERROR);
$connection = CheckoutPersistenceContractTest::connection();
$runtime = CheckoutPersistenceContractTest::runtime($connection, $order);
$barrier = static function () use ($argv): void {
    echo "holding\n";
    flush();
    $deadline = microtime(true) + 15;
    while (!is_file($argv[2])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('checkout barrier timeout');
        }
        usleep(10000);
    }
};
if ($argv[3] === 'hold') {
    $runtime['dispatcher']->addListener(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, $barrier, -50);
}
$connectionId = $connection->fetchOne($connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'SELECT pg_backend_pid()' : 'SELECT CONNECTION_ID()');
echo 'started:' . $connectionId . "\n";
flush();
try {
    if (($argv[4] ?? 'order') === 'cart') {
        $dispatcher = new EventDispatcher();
        if ($argv[3] === 'hold') {
            $dispatcher->addListener(CartSavedEvent::class, $barrier);
        }
        $context = Generator::generateSalesChannelContext();
        $context->setRuleIds([$order['id']]);
        $cart = new Cart('contended-cart');
        $cart->add((new LineItem('binary', 'custom'))->setPayload(['writer' => $order['id'], 'binary' => "\x00\xffpayload"]));
        $persister = new CartPersister($connection, $dispatcher, new CartSerializationCleaner($connection, $dispatcher), new CartCompressor($argv[3] === 'hold', 'gzip'), new MockClock($argv[3] === 'hold' ? '2026-10-09 13:00:00.123' : '2026-10-09 13:00:01.123'));
        $connection->transactional(static function () use ($persister, $cart, $context): void {
            $persister->save($cart, $context);
        });
        $id = $order['id'];
    } elseif (($argv[4] ?? 'order') === 'exclusion') {
        $connection->transactional(static function () use ($connection, $order, $argv, $barrier): void {
            (new PromotionExclusionUpdater($connection))->update([$order['id']]);
            if ($argv[3] === 'hold') {
                $barrier();
            }
        });
        $id = $order['id'];
    } elseif (($argv[4] ?? 'order') === 'claim') {
        $connection->transactional(static function () use ($connection, $order, $argv, $barrier): void {
            (new PromotionCodeGateway($connection))->claim(CheckoutPersistenceContractTest::CODE, 'ONE', ['orderId' => $order['id']]);
            if ($argv[3] === 'hold') {
                $barrier();
            }
        });
        $id = $order['id'];
    } else {
        $id = CheckoutPersistenceContractTest::persist($runtime['persister']);
    }
    echo json_encode(['status' => 'ok', 'id' => $id], \JSON_THROW_ON_ERROR) . "\n";
} catch (PromotionException $exception) {
    echo json_encode(['status' => 'rejected', 'reason' => $exception->getErrorCode()], \JSON_THROW_ON_ERROR) . "\n";
}
$connection->close();

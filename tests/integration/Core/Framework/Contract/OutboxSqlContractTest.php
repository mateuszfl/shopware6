<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition as Status;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Framework\Contract\_fixtures\FrameworkSqlFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class OutboxSqlContractTest extends TestCase
{
    private const WEBHOOK = '0000000000000000000000000000ff01';
    private const EVENT = '0000000000000000000000000000ff02';

    private FrameworkSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    private WebhookOutboxStore $store;

    private StreamLockService $locks;

    protected function setUp(): void
    {
        $this->fixture = new FrameworkSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-18 12:00:00 UTC');
        $this->store = new WebhookOutboxStore($this->connection, $this->clock);
        $this->locks = new StreamLockService($this->connection, $this->clock);
        $this->fixture->insert('webhook', ['id' => self::WEBHOOK, 'name' => 'contract', 'event_name' => 'order.written', 'url' => 'https://example.invalid', 'only_live_version' => 1, 'active' => 1]);
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testBinaryPayloadDuplicateSavepointAndOuterRollback(): void
    {
        $insert = new OutboxInsert(self::EVENT, self::WEBHOOK, Uuid::fromHexToBytes(self::WEBHOOK), "\0\xffbinary");
        $this->connection->beginTransaction();
        $entry = $this->store->recordOutboxEntry($insert);
        static::assertNotNull($entry);
        static::assertNull($this->store->recordOutboxEntry($insert));
        static::assertSame("\0\xffbinary", $this->store->fetchDue($insert->partitionKey, [Status::STATUS_QUEUED], 10)[0]->serializedWebhookMessage);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_delivery'));
        $this->connection->rollBack();
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_delivery'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_stream'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_event_log'));
    }

    public function testDeliveryRetryDueTimeAndStaleAttemptCannotOverwriteSuccess(): void
    {
        $insert = OutboxInsert::fromMessage($this->message(self::EVENT));
        $this->store->recordOutboxEntry($insert);
        $first = $this->store->markRunning(self::EVENT);
        static::assertNotNull($first);
        static::assertSame(1, $first->executionCount);
        static::assertNull($this->store->markRunning(self::EVENT));
        static::assertTrue($this->store->markPendingRetry($first, $this->clock->now()->modify('+60 seconds'), new DeliveryResponse('{}', responseContent: '{"error":true}')));
        static::assertSame([], $this->store->fetchDue($insert->partitionKey, [Status::STATUS_PENDING_RETRY], 10));
        $this->clock->sleep(60);
        static::assertCount(1, $this->store->fetchDue($insert->partitionKey, [Status::STATUS_PENDING_RETRY], 10));
        $second = $this->store->markRunning(self::EVENT);
        static::assertNotNull($second);
        static::assertSame(2, $second->executionCount);
        static::assertFalse($this->store->markFailed($first, null));
        static::assertTrue($this->store->markSuccess($second, new DeliveryResponse('{}', responseContent: '{}', responseStatusCode: 200)));
        static::assertFalse($this->store->markPendingRetry($first, $this->clock->now(), null));
        static::assertSame(Status::STATUS_SUCCESS, $this->connection->fetchOne('SELECT delivery_status FROM webhook_event_log'));
        static::assertFalse($this->store->hasDeliveryRow(self::EVENT));
    }

    public function testLeaseOwnershipExpiryHeartbeatCrashRecoveryAndOrphanCleanup(): void
    {
        $insert = OutboxInsert::fromMessage($this->message(self::EVENT));
        $this->store->recordOutboxEntry($insert);
        $lease = $this->locks->claimNext('one', 10, [Status::STATUS_QUEUED]);
        static::assertNotNull($lease);
        static::assertNull($this->locks->claimNext('two', 10, [Status::STATUS_QUEUED]));
        $running = $this->store->markRunning(self::EVENT);
        static::assertNotNull($running);
        $this->clock->sleep(11);
        $replacement = $this->locks->claimNext('two', 10, [Status::STATUS_QUEUED]);
        static::assertNotNull($replacement);
        static::assertNull($this->locks->verifyOwnership($lease));
        static::assertNull($this->locks->heartbeat($lease, 60));
        $this->locks->release($lease);
        static::assertNotNull($this->locks->verifyOwnership($replacement));
        static::assertSame(1, $this->store->resetRunningForPartition($insert->partitionKey, 10));
        $retried = $this->store->markRunning(self::EVENT);
        static::assertNotNull($retried);
        static::assertSame(2, $retried->executionCount);
        static::assertFalse($this->store->markSuccess($running, null));
        static::assertTrue($this->store->markSuccess($retried, null));
        $this->locks->release($replacement);
        static::assertSame(0, $this->locks->deleteOrphanedStreams(1));
        $this->clock->sleep(61);
        static::assertSame(1, $this->locks->deleteOrphanedStreams(1));
    }

    public function testConcurrentClaimsSkipLockedStreamAndPreferNeverClaimed(): void
    {
        $first = OutboxInsert::fromMessage($this->message(self::EVENT, 'first'));
        $second = OutboxInsert::fromMessage($this->message(Uuid::randomHex(), 'second'));
        $this->store->recordOutboxEntry($first);
        $this->store->recordOutboxEntry($second);
        $other = FrameworkSqlFixture::connect();
        $other->beginTransaction();
        try {
            $one = (new StreamLockService($other, $this->clock))->claimNext('one', 60, [Status::STATUS_QUEUED]);
            static::assertNotNull($one);
            $two = $this->locks->claimNext('two', 60, [Status::STATUS_QUEUED]);
            static::assertNotNull($two);
            static::assertNotSame($one->partitionKey, $two->partitionKey);
            $other->commit();
            $this->locks->release($one);
            $this->locks->release($two);
            $third = OutboxInsert::fromMessage($this->message(Uuid::randomHex(), 'never'));
            $this->store->recordOutboxEntry($third);
            static::assertSame($third->partitionKey, $this->locks->claimNext('three', 60, [Status::STATUS_QUEUED])?->partitionKey);
        } finally {
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
    }

    public function testReceiverYieldsMessagesAndRejectLeavesRecoveryForNewWorker(): void
    {
        $insert = OutboxInsert::fromMessage($this->message(self::EVENT));
        $this->store->recordOutboxEntry($insert);
        $receiver = new MySQLWebhookReceiver($this->locks, $this->store, $this->clock, new NullLogger());
        $messages = iterator_to_array($receiver->get());
        static::assertCount(1, $messages);
        static::assertInstanceOf(WebhookEventMessage::class, $messages[0]->getMessage());
        $this->store->markRunning(self::EVENT);
        $receiver->reject($messages[0]);
        $receiver->reset();
        $this->clock->sleep(MySQLWebhookReceiver::LEASE_SECONDS + 1);
        $replacement = new MySQLWebhookReceiver($this->locks, $this->store, $this->clock, new NullLogger());
        static::assertCount(1, iterator_to_array($replacement->get()));
        $entry = $this->store->markRunning(self::EVENT);
        static::assertNotNull($entry);
        static::assertSame(2, $entry->executionCount);
        $this->store->markSuccess($entry, null);
        $replacement->reset();
    }

    public function testBrokenReceiverPayloadIsFailedWithoutIncrementingAttempt(): void
    {
        $this->store->recordOutboxEntry(new OutboxInsert(self::EVENT, self::WEBHOOK, Uuid::fromHexToBytes(self::WEBHOOK), serialize(new \stdClass())));
        $receiver = new MySQLWebhookReceiver($this->locks, $this->store, $this->clock, new NullLogger());
        static::assertSame([], iterator_to_array($receiver->get()));
        static::assertFalse($this->store->hasDeliveryRow(self::EVENT));
        static::assertSame(Status::STATUS_FAILED, $this->connection->fetchOne('SELECT delivery_status FROM webhook_event_log'));
        $receiver->reset();
    }

    public function testClaimPagesPastOneHundredLockedCandidates(): void
    {
        $other = FrameworkSqlFixture::connect();
        foreach (range(1, 102) as $number) {
            $id = str_pad(dechex($number), 32, '0', \STR_PAD_LEFT);
            $this->fixture->insert('webhook_event_log', ['id' => $id, 'delivery_status' => Status::STATUS_QUEUED]);
            $this->fixture->insert('webhook_delivery', ['webhook_event_log_id' => $id, 'partition_key' => $id, 'delivery_status' => Status::STATUS_QUEUED]);
            $this->fixture->insert('webhook_stream', ['id' => $id, 'partition_key' => $id, 'created_at' => '2026-10-18 11:00:00']);
        }
        $other->beginTransaction();
        try {
            foreach (range(1, 101) as $number) {
                $other->fetchOne(
                    'SELECT id FROM webhook_stream WHERE partition_key = :key FOR UPDATE',
                    ['key' => Uuid::fromHexToBytes(str_pad(dechex($number), 32, '0', \STR_PAD_LEFT))],
                    ['key' => ParameterType::BINARY]
                );
            }
            $lease = $this->locks->claimNext('after-page', 60, [Status::STATUS_QUEUED]);
            static::assertNotNull($lease);
            static::assertSame(str_pad(dechex(102), 32, '0', \STR_PAD_LEFT), Uuid::fromBytesToHex($lease->partitionKey));
        } finally {
            $other->rollBack();
            $other->close();
        }
    }

    public function testBackfillCreatesMissingDeliveryOnlyForQueuedLegacyEvent(): void
    {
        $insert = OutboxInsert::fromMessage($this->message(self::EVENT));
        $this->fixture->insert('webhook_event_log', ['id' => self::EVENT, 'delivery_status' => Status::STATUS_QUEUED]);
        $entry = $this->store->backfillDelivery($insert);
        static::assertNotNull($entry);
        static::assertTrue($this->store->hasDeliveryRow(self::EVENT));
        static::assertNull($this->connection->fetchOne('SELECT sequence FROM webhook_event_log'));
        static::assertNull($this->store->backfillDelivery($insert));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_delivery'));
    }

    public function testCleanupBatchesTerminalLogsAndRetainsQueuedLogsWithDelivery(): void
    {
        foreach (range(1, 501) as $number) {
            $this->fixture->insert('webhook_event_log', ['id' => str_pad(dechex($number), 32, '0', \STR_PAD_LEFT), 'delivery_status' => Status::STATUS_SUCCESS, 'created_at' => '2026-10-18 10:00:00']);
        }
        $this->store->recordOutboxEntry(OutboxInsert::fromMessage($this->message(self::EVENT)));
        $this->connection->executeStatement('UPDATE webhook_event_log SET created_at = :old WHERE delivery_status = :status', ['old' => '2026-10-18 09:00:00', 'status' => Status::STATUS_QUEUED]);
        $boundary = Uuid::randomHex();
        $this->fixture->insert('webhook_event_log', ['id' => $boundary, 'delivery_status' => Status::STATUS_SUCCESS, 'created_at' => '2026-10-18 11:00:00']);
        $this->fixture->insert('webhook_event_log', ['id' => Uuid::randomHex(), 'delivery_status' => Status::STATUS_QUEUED, 'created_at' => '2026-10-18 09:00:00']);
        $this->fixture->insert('webhook_stream', ['id' => self::WEBHOOK, 'partition_key' => self::WEBHOOK, 'created_at' => '2026-10-18 10:00:00']);
        $config = static::createStub(SystemConfigService::class);
        $config->method('getInt')->willReturn(3600);
        (new WebhookCleanup($config, $this->connection, $this->locks, $this->clock))->removeOldLogs();
        static::assertSame([self::EVENT, $boundary], $this->connection->fetchFirstColumn('SELECT sw_uuid_hex(id) FROM webhook_event_log ORDER BY id'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_stream'));
    }

    private function message(string $eventId, string $partition = 'contract'): WebhookEventMessage
    {
        return new WebhookEventMessage($eventId, ['unicode' => 'Łódź'], null, self::WEBHOOK, '6.7', 'https://example.invalid', null, Defaults::LANGUAGE_SYSTEM, 'en-GB', partitionKey: $partition);
    }
}

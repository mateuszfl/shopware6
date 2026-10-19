<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Mcp\Server\Session\InMemorySessionStore;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Demodata\PersonalData\CleanPersonalDataCommand;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTaskHandler;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTaskHandler;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Integration\Core\Framework\Contract\_fixtures\FrameworkSqlFixture;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class FrameworkStorageSqlContractTest extends TestCase
{
    private FrameworkSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->fixture = new FrameworkSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-18 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testDialectRandomExpressionReturnsUnitInterval(): void
    {
        $expression = (new DatabasePlatformResolver($this->connection))->dialect()->random();
        $value = (float) $this->connection->fetchOne('SELECT ' . $expression);
        static::assertGreaterThanOrEqual(0.0, $value);
        static::assertLessThan(1.0, $value);
    }

    public function testKeyValueOverwriteResetAndRemoval(): void
    {
        $storage = new MySQLKeyValueStorage($this->connection);
        static::assertFalse($storage->has('key'));
        $storage->set('key', ['unicode' => 'Łódź', 'false' => false]);
        static::assertSame('{"unicode":"\u0141\u00f3d\u017a","false":false}', $storage->get('key'));
        $storage->set('key', 'changed');
        static::assertSame('changed', $storage->get('key'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_config'));
        $storage->remove('key');
        static::assertFalse($storage->has('key'));
        static::assertSame('default', $storage->get('key', 'default'));
        $storage->reset();
        static::assertFalse($storage->has('key'));
    }

    public function testInvalidationSkipsLockedMiddleRowWithoutDeletingIt(): void
    {
        foreach ([1 => 'first', 2 => 'locked', 3 => 'last'] as $id => $tag) {
            $this->fixture->insert('invalidation_tags', ['id' => str_pad(dechex($id), 32, '0', \STR_PAD_LEFT), 'tag' => $tag]);
        }
        $other = FrameworkSqlFixture::connect();
        $other->beginTransaction();
        try {
            $other->fetchOne('SELECT id FROM invalidation_tags WHERE tag = :tag FOR UPDATE', ['tag' => 'locked']);
            $storage = new MySQLInvalidatorStorage($this->connection, new NullLogger());
            static::assertSame(['first', 'last'], $storage->loadAndDelete());
            static::assertSame(['locked'], $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags'));
            $other->commit();
            static::assertSame(['locked'], $storage->loadAndDelete());
            static::assertSame([], $storage->loadAndDelete());
        } finally {
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
        }
    }

    public function testInvalidationBatchesDuplicatesAndParticipatesInRollback(): void
    {
        $storage = new MySQLInvalidatorStorage($this->connection, new NullLogger());
        $tags = array_map(static fn (int $id): string => 'tag-' . $id, range(1, 1003));
        $storage->store([...$tags, $tags[0]]);
        static::assertCount(1003, $storage->loadAndDelete());
        $this->connection->beginTransaction();
        $storage->store(['rolled-back']);
        static::assertSame(['rolled-back'], $storage->loadAndDelete());
        $this->connection->rollBack();
        static::assertSame([], $storage->loadAndDelete());
    }

    public function testIncrementPartitionsClampsPagingResetAndRollback(): void
    {
        $storage = new MySQLIncrementer($this->connection, $this->clock);
        $storage->setPool('one');
        $storage->increment('cluster', 'key');
        $storage->increment('cluster', 'key');
        $storage->increment('cluster', 'other');
        static::assertSame(2, $storage->list('cluster')['key']['count']);
        static::assertSame(['other'], array_keys($storage->list('cluster', 1, 1)));
        $storage->decrement('cluster', 'other');
        $storage->decrement('cluster', 'other');
        static::assertSame(0, $storage->list('cluster')['other']['count']);
        $this->connection->beginTransaction();
        $storage->increment('cluster', 'key');
        $this->connection->rollBack();
        static::assertSame(2, $storage->list('cluster')['key']['count']);
        $storage->reset('cluster', 'key');
        static::assertSame(0, $storage->list('cluster')['key']['count']);
        $storage->delete('cluster', ['other']);
        static::assertSame(['key'], array_keys($storage->list('cluster', -1)));
        $storage->setPool('two');
        static::assertSame([], $storage->list('cluster'));
    }

    #[DataProvider('incrementStates')]
    public function testConcurrentIncrementWaitsForNewAndExistingCounter(bool $existing): void
    {
        $storage = new MySQLIncrementer($this->connection, $this->clock);
        $storage->setPool('concurrent');
        if ($existing) {
            $storage->increment('cluster', 'key');
        }
        $this->connection->beginTransaction();
        $storage->increment('cluster', 'key');
        $worker = new Process([\PHP_BINARY, __DIR__ . '/_fixtures/increment.php']);
        $worker->setTimeout(15);
        $worker->start();
        $observer = ContractConnectionFactory::create('DATABASE_FUNCTION_INSTALL_URL');
        try {
            $deadline = microtime(true) + 5;
            while (!str_contains($worker->getOutput(), 'started:') && microtime(true) < $deadline) {
                usleep(10000);
            }
            preg_match('/started:(\\d+)/', $worker->getOutput(), $waiter);
            static::assertArrayHasKey(1, $waiter, $worker->getErrorOutput());
            $blocker = (int) $this->connection->fetchOne($this->fixture->schema->isPostgreSQL() ? 'SELECT pg_backend_pid()' : 'SELECT CONNECTION_ID()');
            $sql = $this->fixture->schema->isPostgreSQL()
                ? 'SELECT COUNT(*) FROM unnest(pg_blocking_pids(:waiter)) blocker(id) WHERE id = :blocker'
                : ($observer->getDatabasePlatform() instanceof MariaDBPlatform
                    ? 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id WHERE r.trx_mysql_thread_id = :waiter AND b.trx_mysql_thread_id = :blocker'
                    : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID JOIN information_schema.INNODB_TRX b ON b.TRX_ID = w.BLOCKING_ENGINE_TRANSACTION_ID WHERE r.PROCESSLIST_ID = :waiter AND b.TRX_MYSQL_THREAD_ID = :blocker');
            $waiting = 0;
            while ($waiting === 0 && microtime(true) < $deadline) {
                $waiting = (int) $observer->fetchOne($sql, ['waiter' => (int) $waiter[1], 'blocker' => $blocker], ['waiter' => ParameterType::INTEGER, 'blocker' => ParameterType::INTEGER]);
                usleep(10000);
            }
            static::assertGreaterThan(0, $waiting, 'The second increment must wait for the first transaction');
            $this->connection->commit();
            $worker->wait();
            static::assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            static::assertSame($existing ? 3 : 2, $storage->list('cluster')['key']['count']);
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $worker->stop();
            $observer->close();
        }
    }

    public static function incrementStates(): \Generator
    {
        yield 'concurrent first insert' => [false];
        yield 'concurrent existing counter update' => [true];
    }

    public function testStatisticsUseLatestOccurrencePerTypeWithStableTies(): void
    {
        $storage = new MySQLStatsRepository($this->connection, 3600);
        static::assertNull($storage->getStats());
        $storage->updateMessageStats('B', 10);
        $storage->updateMessageStats('A', 20);
        $storage->updateMessageStats('A', 30);
        $stats = $storage->getStats();
        static::assertNotNull($stats);
        static::assertSame(3, $stats->totalMessagesProcessed);
        static::assertSame(20.0, $stats->averageTimeInQueue);
        static::assertSame(['A', 'B'], array_map(static fn ($type): string => $type->type, array_values($stats->messageTypeStats->getElements())));
        $this->connection->executeStatement('UPDATE messenger_stats SET created_at = :old', ['old' => '2000-01-01 00:00:00']);
        $storage->updateMessageStats('fresh', 1);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_stats'));
    }

    public function testSqlLoggingAndDeterministicRetentionLimit(): void
    {
        $handler = new DoctrineSQLHandler($this->connection, $this->clock);
        foreach (range(1, 4) as $id) {
            $handler->handle(new LogRecord($this->clock->now(), 'contract', Level::Warning, 'entry-' . $id, ['unicode' => 'Łódź']));
            $this->clock->sleep(1);
        }
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM log_entry'));
        $config = static::createStub(SystemConfigService::class);
        $config->method('getInt')->willReturnMap([['core.logging.entryLifetimeSeconds', -1], ['core.logging.entryLimit', 2]]);
        (new LogCleanupTaskHandler(StaticEntityRepository::of(ScheduledTaskCollection::class), new NullLogger(), $config, $this->connection))->run();
        static::assertSame(['entry-4', 'entry-3'], $this->connection->fetchFirstColumn('SELECT message FROM log_entry ORDER BY created_at DESC'));
    }

    public function testLogFallbackRollsBackFailedInsertSavepointAndKeepsOuterTransactionUsable(): void
    {
        $check = $this->fixture->schema->isPostgreSQL() ? 'context = \'[]\'::jsonb' : 'JSON_LENGTH(context) = 0';
        $this->connection->executeStatement('ALTER TABLE log_entry ADD CONSTRAINT contract_log_fallback CHECK (' . $check . ')');
        $this->connection->beginTransaction();
        (new DoctrineSQLHandler($this->connection, $this->clock))->handle(new LogRecord($this->clock->now(), 'contract', Level::Warning, 'fallback', ['rejected' => true]));
        static::assertSame([], json_decode($this->connection->fetchOne('SELECT context FROM log_entry'), true, flags: \JSON_THROW_ON_ERROR));
        $this->connection->rollBack();
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM log_entry'));
    }

    public function testSessionToolsetsDuplicateEnablePreservesCreationAndResultIsolation(): void
    {
        $storage = new McpToolsetSessionStorage($this->connection, $this->clock);
        $storage->enable('session', 'orders');
        $created = $this->connection->fetchOne('SELECT created_at FROM mcp_toolset_session');
        $this->clock->sleep(60);
        $storage->enable('session', 'orders');
        $storage->enable('session', 'catalog');
        static::assertSame(['catalog', 'orders'], $storage->enabledToolsets('session'));
        static::assertSame($created, $this->connection->fetchOne('SELECT created_at FROM mcp_toolset_session WHERE toolset_name = :name', ['name' => 'orders']));
        $cache = new ToolResultCacheStorage($this->connection, $this->clock);
        $id = $cache->store('session', '{"data":"Łódź"}');
        static::assertSame(['content' => '{"data":"Łódź"}', 'mimeType' => 'application/json'], $cache->read($id, 'session'));
        static::assertNull($cache->read($id, 'other'));
        $storage->deleteForSession('session');
        $cache->deleteForSession('session');
        static::assertSame([], $storage->sessionIds());
        static::assertNull($cache->read($id, 'session'));
    }

    public function testSessionCleanupPreservesLiveSessionsAcrossEndpointsAndExpiresAbandonedOnes(): void
    {
        $storage = new McpToolsetSessionStorage($this->connection, $this->clock);
        $admin = new InMemorySessionStore(10, $this->clock);
        $storeApi = new InMemorySessionStore(20, $this->clock);
        $expired = Uuid::v4();
        $live = Uuid::v4();
        $admin->write($expired, 'admin');
        $storeApi->write($live, 'store');
        $storage->enable($expired->toRfc4122(), 'orders');
        $storage->enable($live->toRfc4122(), 'catalog');
        $handler = new McpToolsetSessionCleanupTaskHandler(StaticEntityRepository::of(ScheduledTaskCollection::class), new NullLogger(), $storage, $admin, $storeApi);
        $this->clock->sleep(11);
        $admin->gc();
        $handler->run();
        static::assertSame([$live->toRfc4122()], $storage->sessionIds());
        $this->clock->sleep(10);
        $storeApi->gc();
        $handler->run();
        static::assertSame([], $storage->sessionIds());
    }

    /**
     * @param list<string> $retained
     */
    #[DataProvider('cartRetentionDays')]
    public function testCartCleanupUsesWholeUtcDayAndSignedDays(int $days, array $retained): void
    {
        foreach (['16' => '2026-10-16 23:59:59.999', '17' => '2026-10-17 00:00:00', '18' => '2026-10-18 23:59:59.999', '19' => '2026-10-19 00:00:00', '20' => '2026-10-20 00:00:00'] as $token => $date) {
            $this->connection->insert('cart', ['token' => $token, 'created_at' => $date]);
        }
        $command = new CommandTester(new CleanPersonalDataCommand($this->connection, StaticEntityRepository::of(CustomerCollection::class), $this->clock));
        $command->execute(['type' => 'carts', '--days' => $days]);
        static::assertSame(0, $command->getStatusCode());
        static::assertSame($retained, $this->connection->fetchFirstColumn('SELECT token FROM cart ORDER BY token'));
    }

    public static function cartRetentionDays(): \Generator
    {
        yield 'two days retains the midnight after the last expired day' => [2, ['17', '18', '19', '20']];
        yield 'zero days expires all of today including its last millisecond' => [0, ['19', '20']];
        yield 'negative days preserves the signed interval behavior' => [-1, ['20']];
    }
}

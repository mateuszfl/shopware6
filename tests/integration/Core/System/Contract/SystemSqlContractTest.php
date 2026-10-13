<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Contract;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Core\System\NumberRange\Command\MigrateIncrementStorageCommand;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Shopware\Core\System\SalesChannel\Context\ContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SystemConfig\SymfonySystemConfigService;
use Shopware\Core\System\SystemConfig\SystemConfigLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Redis\RedisStub;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\SystemSqlFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class SystemSqlContractTest extends TestCase
{
    public const CHANNEL = '0000000000000000000000000000ff01';
    public const CUSTOMER = '0000000000000000000000000000ff02';
    public const RANGE = '0000000000000000000000000000ff03';

    private SystemSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->fixture = new SystemSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-12 12:13:14.123 UTC');
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testNumberReservationPreviewStartIncreaseAndStorageMigration(): void
    {
        $storage = new IncrementSqlStorage($this->connection, $this->clock);
        $config = ['id' => self::RANGE, 'pattern' => '{n}', 'start' => 100];
        static::assertSame(100, $storage->preview($config));
        static::assertSame(100, $storage->reserve($config));
        static::assertSame(101, $storage->reserve($config));
        static::assertSame(102, $storage->preview($config));
        static::assertSame(500, $storage->reserve(['id' => self::RANGE, 'pattern' => '{n}', 'start' => 500]));
        $storage->increaseToAtLeast(self::RANGE, 20);
        static::assertSame([self::RANGE => 500], $storage->list());
        $storage->increaseToAtLeast(self::RANGE, 800);
        static::assertSame(801, $storage->reserve($config));
        $repository = StaticEntityRepository::of(NumberRangeCollection::class, [[self::RANGE], [self::RANGE], [self::RANGE]]);
        $target = new IncrementRedisStorage(new RedisStub(), new LockFactory(new FlockStore()), $repository);
        static::assertSame([], $target->list());
        $registry = new IncrementStorageRegistry(new ServiceLocator(['mysql' => static fn () => $storage, 'sql' => static fn () => $target]), 'mysql');
        $command = new CommandTester(new MigrateIncrementStorageCommand($registry));
        $command->setInputs(['yes']);
        $command->execute(['from' => 'mysql', 'to' => 'sql']);
        static::assertSame(0, $command->getStatusCode());
        static::assertSame([self::RANGE => 801], $target->list());
        $target->set(self::RANGE, 42);
        static::assertSame([self::RANGE => 801], $storage->list());
        $registry->migrate('sql', 'mysql');
        static::assertSame(43, $storage->reserve(['id' => self::RANGE, 'pattern' => '{n}', 'start' => 1]));
    }

    public function testNumberGeneratorSelectsAssignedAndGlobalConfigurations(): void
    {
        $global = $this->connection->quoteSingleIdentifier('global');
        $this->fixture->insert('number_range_type', ['id' => self::CUSTOMER, 'technical_name' => 'order', $global => 0]);
        $this->fixture->insert('number_range', ['id' => self::RANGE, 'type_id' => self::CUSTOMER, 'pattern' => 'ORDER-{n}', 'start' => 30, $global => 1]);
        $this->fixture->insert('number_range', ['id' => self::CHANNEL, 'type_id' => self::CUSTOMER, 'pattern' => 'LOCAL-{n}', 'start' => 50, $global => 0]);
        $this->fixture->insert('number_range_sales_channel', ['number_range_id' => self::CHANNEL, 'sales_channel_id' => self::CHANNEL]);
        $generator = new NumberRangeValueGenerator(new ValueGeneratorPatternRegistry([new ValueGeneratorPatternIncrement(new IncrementSqlStorage($this->connection, $this->clock))]), new EventDispatcher(), $this->connection);
        static::assertSame('LOCAL-50', $generator->getValue('order', Context::createDefaultContext(), self::CHANNEL));
        static::assertSame('ORDER-30', $generator->getValue('order', Context::createDefaultContext(), null));
        static::assertSame('LOCAL-51', $generator->previewPatternByNumberRangeId(self::CHANNEL));
    }

    #[DataProvider('reservationStates')]
    public function testConcurrentReservationsWaitForTheSameRowWithoutDuplicates(bool $existing): void
    {
        $storage = new IncrementSqlStorage($this->connection, $this->clock);
        if ($existing) {
            $storage->set(self::RANGE, 100);
        }
        $this->connection->beginTransaction();
        $first = $storage->reserve(['id' => self::RANGE, 'pattern' => '{n}', 'start' => 100]);
        $worker = new Process([\PHP_BINARY, __DIR__ . '/_fixtures/reserve.php', self::RANGE]);
        $worker->setTimeout(15);
        $worker->start();
        $deadline = microtime(true) + 5;
        while (!str_contains($worker->getOutput(), 'started:') && microtime(true) < $deadline) {
            usleep(10000);
        }
        preg_match('/started:(\d+)/', $worker->getOutput(), $waiter);
        static::assertArrayHasKey(1, $waiter, $worker->getErrorOutput());
        $blocker = (int) $this->connection->fetchOne($this->fixture->schema->isPostgreSQL() ? 'SELECT pg_backend_pid()' : 'SELECT CONNECTION_ID()');
        $observer = $this->fixture->schema->isPostgreSQL() ? SystemSqlFixture::connect() : ContractConnectionFactory::create('DATABASE_FUNCTION_INSTALL_URL');
        $sql = $this->fixture->schema->isPostgreSQL()
            ? 'SELECT COUNT(*) FROM unnest(pg_blocking_pids(:waiter)) blocker(id) WHERE id = :blocker'
            : ($observer->getDatabasePlatform() instanceof MariaDBPlatform
                ? 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id WHERE r.trx_mysql_thread_id = :waiter AND b.trx_mysql_thread_id = :blocker'
                : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID JOIN information_schema.INNODB_TRX b ON b.TRX_ID = w.BLOCKING_ENGINE_TRANSACTION_ID WHERE r.PROCESSLIST_ID = :waiter AND b.TRX_MYSQL_THREAD_ID = :blocker');
        $waiting = 0;
        try {
            while ($waiting === 0 && microtime(true) < $deadline) {
                $waiting = (int) $observer->fetchOne($sql, ['waiter' => (int) $waiter[1], 'blocker' => $blocker], ['waiter' => ParameterType::INTEGER, 'blocker' => ParameterType::INTEGER]);
                usleep(10000);
            }
            static::assertGreaterThan(0, $waiting, 'Separate worker must be waiting for the reservation lock');
            $this->connection->commit();
            $worker->wait();
            static::assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            preg_match('/reserved:(\d+)/', $worker->getOutput(), $number);
            static::assertArrayHasKey(1, $number);
            static::assertSame($first + 1, (int) $number[1]);
            static::assertSame([self::RANGE => $first + 1], $storage->list());
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $worker->stop();
            $observer->close();
        }
    }

    public static function reservationStates(): \Generator
    {
        yield 'first insert conflicts atomically' => [false];
        yield 'existing row increments atomically' => [true];
    }

    public function testContextRoundTripBothConflictsAndTransactionRecovery(): void
    {
        $persister = $this->persister();
        $persister->save('customer', ['nested' => ['old' => true], 'permissions' => ['read' => true]], self::CHANNEL, self::CUSTOMER);
        $persister->save('anonymous', ['guest' => true], self::CHANNEL);
        $this->connection->beginTransaction();
        $persister->save('anonymous', ['nested' => ['new' => true], 'permissions' => []], self::CHANNEL, self::CUSTOMER);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_api_context'));
        $loaded = $persister->load('anonymous', self::CHANNEL, self::CUSTOMER);
        static::assertSame(self::CUSTOMER, $loaded['customerId']);
        static::assertEquals(['old' => true, 'new' => true], $loaded['nested']);
        static::assertArrayNotHasKey('permissions', $loaded);
        static::assertSame('anonymous', $loaded['token']);
        $this->connection->commit();
        $persister->save('other-channel', ['other' => true], self::RANGE);
        $persister->save('anonymous', ['moved' => true], self::RANGE);
        static::assertSame([], $persister->load('anonymous', self::CHANNEL));
        static::assertTrue($persister->load('anonymous', self::RANGE)['moved']);
        $this->fixture->insert('sales_channel_api_context', ['token' => 'nullable-channel', 'payload' => '{"valid":true}', 'sales_channel_id' => null, 'updated_at' => '2026-10-12 12:13:14.123']);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sales_channel_api_context WHERE sales_channel_id IS NULL'));
        $persister->save('nullable-channel', ['changed' => true], self::CHANNEL);
        static::assertTrue($persister->load('nullable-channel', self::CHANNEL)['changed']);
        $persister->delete('nullable-channel', self::CHANNEL);
        static::assertSame([], $persister->load('nullable-channel', self::CHANNEL));
    }

    public function testContextCustomerSelectionExpiryRevokeAndTokenReplacement(): void
    {
        $persister = $this->persister();
        $persister->save('authenticated', ['billingAddressId' => self::RANGE], self::CHANNEL, self::CUSTOMER);
        $persister->save('anonymous', ['guest' => true], self::CHANNEL);
        static::assertSame('authenticated', $persister->load('anonymous', self::CHANNEL, self::CUSTOMER)['token']);
        $this->clock->sleep(86401);
        static::assertTrue($persister->load('authenticated', self::CHANNEL, self::CUSTOMER)['expired']);
        static::assertSame(['expired' => true, 'token' => 'anonymous'], $persister->load('anonymous', self::CHANNEL));
        $persister->revokeAllCustomerTokens(self::CUSTOMER, 'authenticated');
        static::assertSame(self::CUSTOMER, $persister->load('authenticated', self::CHANNEL)['customerId']);
        $persister->revokeAllCustomerTokens(self::CUSTOMER);
        static::assertArrayNotHasKey('customerId', $persister->load('authenticated', self::CHANNEL));
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setId(self::CHANNEL);
        $newToken = $persister->replace('missing', $context);
        static::assertSame($newToken, $context->getToken());
        static::assertSame($newToken, $persister->load($newToken, self::CHANNEL)['token']);
        $nextToken = $persister->replace($newToken, $context);
        static::assertSame([], $persister->load($newToken, self::CHANNEL));
        static::assertSame($nextToken, $persister->load($nextToken, self::CHANNEL)['token']);
    }

    public function testConfigurationPreservesChannelOverridesAndUpdatesBinaryIds(): void
    {
        $kernel = static::createStub(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn(new StaticKernelPluginLoader(new ClassLoader()));
        $dispatcher = new EventDispatcher();
        $loader = new SystemConfigLoader($this->connection, $kernel);
        $service = new SystemConfigService($this->connection, static::createStub(ConfigReader::class), $loader, $dispatcher, new SymfonySystemConfigService([]), new CacheTagCollector(new RequestStack(), $dispatcher), $this->clock);
        $this->fixture->insert('app', ['id' => self::CUSTOMER, 'name' => 'contract', 'active' => 1]);
        $service->setMultiple(['Contract.config.value' => 'global', 'Contract.config.flag' => true, 'Contract.config.zero' => 10, 'Contract.config.empty' => 'inherited']);
        $service->setMultiple(['Contract.config.value' => ['nested' => ['number' => 2, 'false' => false]], 'Contract.config.flag' => false, 'Contract.config.zero' => 0, 'Contract.config.empty' => ''], self::CHANNEL);
        static::assertEquals(['nested' => ['number' => 2, 'false' => false]], $service->get('Contract.config.value', self::CHANNEL));
        static::assertFalse($service->get('Contract.config.flag', self::CHANNEL));
        static::assertSame(0, $service->get('Contract.config.zero', self::CHANNEL));
        static::assertSame('', $service->get('Contract.config.empty', self::CHANNEL));
        static::assertSame(0, $service->getDomain('Contract.config', self::CHANNEL, true)['Contract.config.zero']);
        $service->set('Contract.config.value', 'updated', self::CHANNEL);
        static::assertSame('updated', $service->get('Contract.config.value', self::CHANNEL));
        static::assertSame('global', $service->get('Contract.config.value'));
        $service->deleteExtensionConfiguration('Contract', [['elements' => [['name' => 'value'], ['name' => 'flag'], ['name' => 'zero'], ['name' => 'empty']]]]);
        static::assertSame([], $service->getDomain('Contract.config', self::CHANNEL, true));
    }

    public function testLanguageLoadingAndContextFactoryPreservePublicCamelCaseKeys(): void
    {
        $this->fixture->insert('locale', ['id' => self::RANGE, 'code' => 'en-GB']);
        $this->fixture->insert('language', ['id' => Defaults::LANGUAGE_SYSTEM, 'parent_id' => null, 'locale_id' => self::RANGE, 'translation_code_id' => self::RANGE]);
        $this->fixture->insert('language', ['id' => self::CUSTOMER, 'parent_id' => null, 'locale_id' => self::RANGE, 'translation_code_id' => self::RANGE]);
        $this->fixture->insert('currency', ['id' => self::RANGE, 'iso_code' => 'EUR', 'factor' => '1.500']);
        $this->fixture->insert('sales_channel', ['id' => self::CHANNEL, 'language_id' => self::CUSTOMER, 'currency_id' => self::RANGE]);
        $this->fixture->insert('sales_channel_language', ['language_id' => self::CUSTOMER, 'sales_channel_id' => self::CHANNEL]);
        $this->fixture->insert('sales_channel_language', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'sales_channel_id' => self::CHANNEL]);
        $languages = (new LanguageLoader($this->connection))->loadLanguages();
        static::assertArrayHasKey('parentId', $languages[self::CUSTOMER]);
        static::assertNull($languages[self::CUSTOMER]['parentId']);
        static::assertArrayHasKey('parentCode', $languages[self::CUSTOMER]);
        static::assertNull($languages[self::CUSTOMER]['parentCode']);
        static::assertArrayNotHasKey('parent_id', $languages[self::CUSTOMER]);
        $loader = new SalesChannelLanguageLoader($this->connection);
        static::assertSame([self::CHANNEL], $loader->loadLanguages()[self::CUSTOMER]);
        $loader->reset();
        static::assertSame([self::CHANNEL], $loader->loadLanguages()[Defaults::LANGUAGE_SYSTEM]);
        $context = (new ContextFactory($this->connection, new EventDispatcher()))->getContext(self::CHANNEL, []);
        static::assertSame(self::RANGE, $context->getCurrencyId());
        static::assertSame(1.5, $context->getCurrencyFactor());
        static::assertSame([self::CUSTOMER, Defaults::LANGUAGE_SYSTEM], $context->getLanguageIdChain());
    }

    private function persister(): SalesChannelContextPersister
    {
        return new SalesChannelContextPersister($this->connection, new EventDispatcher(), static::createStub(AbstractCartPersister::class), $this->clock);
    }
}

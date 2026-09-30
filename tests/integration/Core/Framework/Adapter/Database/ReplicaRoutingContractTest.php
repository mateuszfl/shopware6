<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\PostgreSQLReplayBarrier;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaRoutingContractTest extends TestCase
{
    use EnvTestBehaviour;

    private Connection $primary;

    private Connection $replica;

    private ?Connection $control = null;

    private PrimaryReadReplicaConnection $router;

    private DatabaseEndpoint $primaryEndpoint;

    private DatabaseEndpoint $replicaEndpoint;

    protected function setUp(): void
    {
        $this->primary = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $this->replica = ContractConnectionFactory::create('DATABASE_CONTRACT_REPLICA_URL');
        $this->primaryEndpoint = DatabaseEndpoint::observe($this->primary);
        $this->replicaEndpoint = DatabaseEndpoint::observe($this->replica);
        static::assertNotSame($this->primaryEndpoint->serverId, $this->replicaEndpoint->serverId);
        static::assertFalse($this->primaryEndpoint->standby);

        if ($this->primary->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            static::assertStringStartsWith('18.6 ', $this->primaryEndpoint->version);
            static::assertStringStartsWith('18.6 ', $this->replicaEndpoint->version);
            static::assertTrue($this->replicaEndpoint->standby);
            $this->control = ContractConnectionFactory::create('DATABASE_CONTRACT_CONTROL_URL');
            static::assertSame($this->replicaEndpoint->serverId, DatabaseEndpoint::observe($this->control)->serverId);
        } else {
            static::assertStringStartsWith('8.4.', $this->primaryEndpoint->version);
            static::assertStringStartsWith('8.4.', $this->replicaEndpoint->version);
        }

        $this->primary->executeStatement('DROP TABLE IF EXISTS sw_replica_contract');
        $this->primary->executeStatement('CREATE TABLE sw_replica_contract (id VARCHAR(80) NOT NULL PRIMARY KEY, value INTEGER NOT NULL)');
        $this->primary->executeStatement('INSERT INTO sw_replica_contract (id, value) VALUES (\'initial\', 1)');
        $this->waitForReplica();
        $this->router = $this->createRouter();
        $pid = getmypid();
        static::assertIsInt($pid);
        fwrite(\STDERR, \sprintf(
            "[replica-routing] pid=%d primary=%s replica=%s\n",
            $pid,
            $this->primaryEndpoint->serverId,
            $this->replicaEndpoint->serverId,
        ));
    }

    protected function tearDown(): void
    {
        if (isset($this->router)) {
            if ($this->router->getTransactionNestingLevel() > 0) {
                while ($this->router->getTransactionNestingLevel() > 0) {
                    $this->router->rollBack();
                }
            }
            $this->router->close();
        }
        if (isset($this->primary)) {
            $this->primary->executeStatement('DROP TABLE IF EXISTS sw_replica_contract');
            $this->primary->close();
        }
        if (isset($this->replica)) {
            $this->replica->close();
        }
        $this->control?->close();
    }

    public function testReadWriteReadAndResetUseThePhysicalEndpoints(): void
    {
        // R01, R02 and the read/write part of R09.
        static::assertSame(1, (int) $this->router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        $this->assertRoute($this->replicaEndpoint);

        $this->router->executeStatement('UPDATE sw_replica_contract SET value = 2 WHERE id = \'initial\'');
        static::assertSame(2, (int) $this->router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        $this->assertRoute($this->primaryEndpoint);

        $this->waitForReplica();
        (new ReplicaConnectionResetter($this->router))->reset();
        static::assertSame(2, (int) $this->router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        $this->assertRoute($this->replicaEndpoint);
    }

    public function testNestedTransactionKeepsPrimaryThroughResetAndRollback(): void
    {
        // R03: reset never substitutes for rollback.
        $this->router->beginTransaction();
        $this->router->executeStatement('UPDATE sw_replica_contract SET value = 2 WHERE id = \'initial\'');
        $this->router->beginTransaction();
        $this->router->executeStatement('UPDATE sw_replica_contract SET value = 3 WHERE id = \'initial\'');
        (new ReplicaConnectionResetter($this->router))->reset();
        $this->assertRoute($this->primaryEndpoint);
        static::assertSame(3, (int) $this->router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        $this->router->rollBack();
        static::assertSame(2, (int) $this->router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        $this->router->rollBack();
        static::assertSame(1, (int) $this->primary->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        (new ReplicaConnectionResetter($this->router))->reset();
        $this->assertRoute($this->replicaEndpoint);
    }

    public function testUniqueViolationRequiresRollbackBeforeNextUnit(): void
    {
        // R06: the error is not silently retried; the next unit sees a clean connection.
        $this->router->beginTransaction();
        try {
            $this->router->executeStatement('INSERT INTO sw_replica_contract (id, value) VALUES (\'initial\', 8)');
            static::fail('A duplicate primary key must fail');
        } catch (UniqueConstraintViolationException $exception) {
            static::assertNotSame('', $exception->getSQLState() ?? '');
            static::assertTrue($this->router->isConnectedToPrimary());
        } finally {
            $this->router->rollBack();
        }

        (new ReplicaConnectionResetter($this->router))->reset();
        static::assertSame(1, (int) $this->router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
        $this->assertRoute($this->replicaEndpoint);
        static::assertSame(0, $this->router->getTransactionNestingLevel());
    }

    public function testConfigurationWithoutReplicaUsesPrimary(): void
    {
        // R11: no replica DSN means an ordinary DBAL connection.
        $this->setEnvVars(['DATABASE_REPLICA_0_URL' => '']);
        $connection = DatabaseConnectionFactory::create();
        try {
            static::assertNotInstanceOf(PrimaryReadReplicaConnection::class, $connection);
            static::assertSame($this->primaryEndpoint->serverId, DatabaseEndpoint::observe($connection)->serverId);
        } finally {
            $connection->close();
        }
    }

    public function testKeepReplicaFalseStaysOnPrimaryAfterWrite(): void
    {
        // R11: DBAL deliberately discards the replica after a primary operation.
        $router = $this->createRouter(keepReplica: false);
        try {
            $router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\'');
            $this->assertRoute($this->replicaEndpoint, $router);
            $router->executeStatement('UPDATE sw_replica_contract SET value = 2 WHERE id = \'initial\'');
            (new ReplicaConnectionResetter($router))->reset();
            $this->assertRoute($this->primaryEndpoint, $router);
        } finally {
            $router->close();
        }
    }

    public function testUnavailableReplicaFailsWithoutSilentPrimaryFallback(): void
    {
        // R07 baseline: DBAL must surface the replica connection error within 15 seconds.
        $replicaUrl = getenv('DATABASE_CONTRACT_REPLICA_URL');
        static::assertIsString($replicaUrl);
        $unavailableUrl = preg_replace('/:\d+\//', ':1/', $replicaUrl, 1);
        static::assertIsString($unavailableUrl);
        static::assertNotSame($replicaUrl, $unavailableUrl);
        $this->setEnvVars(['DATABASE_REPLICA_0_URL' => $unavailableUrl]);
        $router = DatabaseConnectionFactory::create();
        static::assertInstanceOf(PrimaryReadReplicaConnection::class, $router);
        $start = microtime(true);
        try {
            $router->fetchOne('SELECT 1');
            static::fail('An unavailable replica must not silently fall back to primary');
        } catch (DriverException $exception) {
            static::assertLessThanOrEqual(15.0, microtime(true) - $start);
            fwrite(\STDERR, \sprintf("[R07-unavailable] platform=%s replica=%s sqlstate=%s elapsed=%.3Fs\n", $this->replicaEndpoint->platform, $this->replicaEndpoint->serverId, $exception->getSQLState() ?? 'none', microtime(true) - $start));
        } finally {
            $router->close();
        }

        $recovered = $this->createRouter();
        try {
            static::assertSame(1, (int) $recovered->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\''));
            $this->assertRoute($this->replicaEndpoint, $recovered);
        } finally {
            $recovered->close();
        }
    }

    public function testMessengerWorkerResetsRoutingBetweenMessagesInOneProcess(): void
    {
        // R02/R09: execute Symfony's Worker and its ResetServicesListener, not a direct reset() call.
        $routes = [];
        $applicationPid = getmypid();
        $router = $this->router;
        $resetter = new class($router) implements ResetInterface {
            public int $calls = 0;

            public function __construct(private readonly PrimaryReadReplicaConnection $connection)
            {
            }

            public function reset(): void
            {
                ++$this->calls;
                (new ReplicaConnectionResetter($this->connection))->reset();
            }
        };
        $handler = function (ReplicaRoutingMessage $message) use (&$routes, $router): void {
            if ($message->id === 2) {
                $router->executeStatement('UPDATE sw_replica_contract SET value = 2 WHERE id = \'initial\'');
            } else {
                if ($message->id === 3) {
                    $this->waitForReplica();
                }
                $router->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\'');
            }
            $routes[$message->id] = [getmypid(), DatabaseEndpoint::observe($router)->serverId];
        };

        $transport = new InMemoryTransport();
        foreach ([1, 2, 3] as $id) {
            $transport->send(new Envelope(new ReplicaRoutingMessage($id)));
        }
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetServicesListener($resetter));
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([ReplicaRoutingMessage::class => [$handler]]))]);
        $worker = new Worker(['contract' => $transport], $bus, $dispatcher);
        $handled = 0;
        $dispatcher->addListener(WorkerMessageHandledEvent::class, static function () use (&$handled, $worker): void {
            if (++$handled === 3) {
                $worker->stop();
            }
        });
        $worker->run(['sleep' => 0, 'time_limit' => 5]);

        static::assertCount(3, $transport->getAcknowledged());
        static::assertCount(0, $transport->getRejected());
        static::assertSame(4, $resetter->calls, 'Three message boundaries and worker stop reset services');
        static::assertSame([
            1 => [$applicationPid, $this->replicaEndpoint->serverId],
            2 => [$applicationPid, $this->primaryEndpoint->serverId],
            3 => [$applicationPid, $this->replicaEndpoint->serverId],
        ], $routes);
    }

    public function testHttpKernelResetsAtStartOfFollowingRequest(): void
    {
        // R02: real Symfony Kernel::handle() is the HTTP reset boundary.
        $router = $this->router;
        $resetter = new class($router) implements ResetInterface {
            public int $calls = 0;

            public function __construct(private readonly PrimaryReadReplicaConnection $connection)
            {
            }

            public function reset(): void
            {
                ++$this->calls;
                (new ReplicaConnectionResetter($this->connection))->reset();
            }
        };
        $httpKernel = new class($router, $this->primaryEndpoint, fn () => $this->waitForReplica()) implements HttpKernelInterface {
            public function __construct(
                private readonly PrimaryReadReplicaConnection $connection,
                private readonly DatabaseEndpoint $primaryEndpoint,
                private readonly \Closure $waitForReplica,
            ) {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                if ($request->getPathInfo() === '/write') {
                    $this->connection->executeStatement('UPDATE sw_replica_contract SET value = 2 WHERE id = \'initial\'');
                } else {
                    if ($request->getPathInfo() === '/read-after-write') {
                        ($this->waitForReplica)();
                    }
                    $this->connection->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\'');
                }

                $endpoint = DatabaseEndpoint::observe($this->connection);
                $role = $endpoint->serverId === $this->primaryEndpoint->serverId ? 'primary' : 'replica';

                return new Response(getmypid() . ':' . $role . ':' . $endpoint->serverId);
            }
        };
        $container = new Container();
        $container->set('services_resetter', $resetter);
        $container->set('http_kernel', $httpKernel);
        $kernel = new class($container) extends Kernel {
            public function __construct(Container $container)
            {
                parent::__construct('test', false);
                $this->container = $container;
                $this->booted = true;
            }

            public function registerBundles(): iterable
            {
                return [];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
            }
        };

        $pid = getmypid();
        static::assertSame($pid . ':replica:' . $this->replicaEndpoint->serverId, $kernel->handle(Request::create('/read'))->getContent());
        static::assertSame(0, $resetter->calls);
        static::assertSame($pid . ':primary:' . $this->primaryEndpoint->serverId, $kernel->handle(Request::create('/write'))->getContent());
        static::assertSame(1, $resetter->calls, 'Only the start of the second request has reset services');
        static::assertSame($pid . ':replica:' . $this->replicaEndpoint->serverId, $kernel->handle(Request::create('/read-after-write'))->getContent());
        static::assertSame(2, $resetter->calls, 'The write is reset at the start of the third request');
        $kernel->shutdown();
    }

    private function createRouter(bool $keepReplica = true): PrimaryReadReplicaConnection
    {
        $primaryUrl = getenv('DATABASE_CONTRACT_URL');
        $replicaUrl = getenv('DATABASE_CONTRACT_REPLICA_URL');
        static::assertIsString($primaryUrl);
        static::assertIsString($replicaUrl);

        $this->setEnvVars([
            'DATABASE_URL' => $primaryUrl . ($keepReplica ? '&keepReplica=1' : '&keepReplica=0'),
            'DATABASE_REPLICA_0_URL' => $replicaUrl,
            'DATABASE_REPLICA_1_URL' => null,
        ]);
        $router = DatabaseConnectionFactory::create();
        static::assertInstanceOf(PrimaryReadReplicaConnection::class, $router);

        return $router;
    }

    private function assertRoute(DatabaseEndpoint $expected, ?PrimaryReadReplicaConnection $router = null): void
    {
        $observed = DatabaseEndpoint::observe($router ?? $this->router);
        static::assertSame($expected->serverId, $observed->serverId);
        static::assertNotSame($expected->backendId, $observed->backendId);
    }

    private function waitForReplica(): void
    {
        if ($this->control instanceof Connection) {
            (new PostgreSQLReplayBarrier($this->primary, $this->control))->wait();

            return;
        }

        $deadline = microtime(true) + 30;
        do {
            try {
                if ((int) $this->replica->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\'') === (int) $this->primary->fetchOne('SELECT value FROM sw_replica_contract WHERE id = \'initial\'')) {
                    return;
                }
            } catch (\Throwable) {
                // The table definition may not yet have reached the replica.
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException('MySQL replica did not replay the committed fixture within 30 seconds');
    }
}

/**
 * @internal
 */
final readonly class ReplicaRoutingMessage
{
    public function __construct(public int $id)
    {
    }
}

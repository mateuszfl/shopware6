<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableTransaction::class)]
class RetryableTransactionTest extends TestCase
{
    public function testRetryableTransactionRetriesOnDeadlock(): void
    {
        $this->expectException(DeadlockException::class);

        $counter = 0;
        $f = static function () use (&$counter): void {
            ++$counter;
            throw new DeadlockException(
                new Exception('Deadlock detected'),
                null,
            );
        };

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('transactional')->willReturnCallback($f);

        try {
            RetryableTransaction::retryable($connection, $f);
        } finally {
            static::assertSame(11, $counter);
        }
    }

    public function testRetriedAttemptDiscardsSuccessEffectsAndRestoresState(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection));
        $attempts = 0;
        $success = 0;
        $error = 0;
        $rollback = 0;
        RetryableTransaction::retryable($connection, static function () use ($connection, &$attempts, &$success, &$error, &$rollback): void {
            ++$attempts;
            RetryableTransaction::onCommit($connection, static function () use (&$success): void { ++$success; }, static function () use (&$error): void { ++$error; });
            RetryableTransaction::onRollback($connection, static function () use (&$rollback): void { ++$rollback; });
            if ($attempts === 1) {
                throw new DeadlockException(new Exception('serialization', '40001'), null);
            }
        });
        static::assertSame(2, $attempts);
        static::assertSame(1, $success);
        static::assertSame(0, $error);
        static::assertSame(1, $rollback);
    }

    public function testFailedTransactionRunsErrorAndRollbackEffectsOnce(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection));
        $success = 0;
        $error = 0;
        $rollback = 0;
        try {
            RetryableTransaction::transactional($connection, static function () use ($connection, &$success, &$error, &$rollback): void {
                RetryableTransaction::onCommit($connection, static function () use (&$success): void { ++$success; }, static function (): void {});
                RetryableTransaction::onError($connection, static function () use (&$error): void { ++$error; });
                RetryableTransaction::onRollback($connection, static function () use (&$rollback): void { ++$rollback; });
                throw new \RuntimeException('failed');
            });
            static::fail('Transaction must fail');
        } catch (\RuntimeException $exception) {
            static::assertSame('failed', $exception->getMessage());
        }
        static::assertSame(0, $success);
        static::assertSame(1, $error);
        static::assertSame(1, $rollback);
    }

    public function testCommittedEffectFailureDoesNotRetryDatabaseWork(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection));
        $attempts = 0;
        try {
            RetryableTransaction::retryable($connection, static function () use ($connection, &$attempts): void {
                ++$attempts;
                RetryableTransaction::onCommit($connection, static function (): void { throw new DeadlockException(new Exception('external effect failed'), null); }, static function (): void {});
            });
            static::fail('The callback exception must propagate');
        } catch (DeadlockException) {
            static::assertSame(1, $attempts);
        }
    }
}

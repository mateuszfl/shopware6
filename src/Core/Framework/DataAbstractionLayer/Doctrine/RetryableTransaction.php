<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\TransactionRolledBack;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\MeterProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

#[Package('framework')]
class RetryableTransaction
{
    /**
     * @var \WeakMap<Connection, array{success: list<\Closure(): void>, error: list<\Closure(): void>, rollback: list<\Closure(): void>}>|null
     */
    private static ?\WeakMap $callbacks = null;

    /**
     * Defer external effects to the outermost transaction controlled by this helper.
     * A rolled-back retry attempt discards its callbacks.
     *
     * @param \Closure(): void $success
     * @param \Closure(): void $error
     */
    public static function onCommit(Connection $connection, \Closure $success, \Closure $error): void
    {
        if (self::$callbacks === null || !isset(self::$callbacks[$connection])) {
            $success();

            return;
        }
        $callbacks = self::$callbacks[$connection];
        $callbacks['success'][] = $success;
        $callbacks['error'][] = $error;
        self::$callbacks[$connection] = $callbacks;
    }

    /**
     * Restore attempt-local mutable state after rollback, including abandoned retries.
     *
     * @param \Closure(): void $rollback
     */
    public static function onRollback(Connection $connection, \Closure $rollback): void
    {
        if (self::$callbacks === null || !isset(self::$callbacks[$connection])) {
            return;
        }
        $callbacks = self::$callbacks[$connection];
        $callbacks['rollback'][] = $rollback;
        self::$callbacks[$connection] = $callbacks;
    }

    /**
     * Report a failed operation only if the enclosing managed transaction finally fails.
     *
     * @param \Closure(): void $error
     */
    public static function onError(Connection $connection, \Closure $error): void
    {
        if (self::$callbacks === null || !isset(self::$callbacks[$connection])) {
            $error();

            return;
        }
        $callbacks = self::$callbacks[$connection];
        $callbacks['error'][] = $error;
        self::$callbacks[$connection] = $callbacks;
    }

    /**
     * Executes the given closure inside a DBAL transaction. In case of a deadlock (RetryableException) the transaction
     * is rolled back and the closure will be retried. Because it may run multiple times the closure should not cause
     * any side effects outside its own scope.
     *
     * @template TReturn of mixed
     *
     * @param \Closure(Connection): TReturn $closure
     *
     * @return TReturn
     */
    public static function retryable(Connection $connection, \Closure $closure)
    {
        return self::withCallbacks($connection, fn () => self::retry($connection, $closure, 0, $connection->getTransactionNestingLevel()));
    }

    /**
     * Executes the given closure inside a DBAL transaction. In case of a deadlock (RetryableException) the transaction
     * is rolled back. There are no retries, and the original exception is re-thrown.
     *
     * @template TReturn of mixed
     *
     * @param \Closure(Connection): TReturn $closure
     *
     * @return TReturn
     */
    public static function transactional(Connection $connection, \Closure $closure)
    {
        return self::withCallbacks($connection, fn () => self::executeTransaction($connection, $closure));
    }

    /**
     * @template TReturn
     *
     * @param \Closure(Connection): TReturn $closure
     *
     * @return TReturn
     */
    private static function executeTransaction(Connection $connection, \Closure $closure)
    {
        $originalNestingLevel = $connection->getTransactionNestingLevel();
        try {
            return $connection->transactional($closure);
        } catch (\Throwable $e) {
            if ($originalNestingLevel > 0) {
                // If this RetryableTransaction was executed inside another transaction, do not retry this nested
                // transaction. Remember that the whole (outermost) transaction was already rolled back by the database
                // when any RetryableException is thrown.
                // Rethrow the exception here so only the outermost transaction is retried which in turn includes this
                // nested transaction.
                throw $e;
            }

            // after failure and rollback in transactional we need to make sure the nesting level
            // is correct (see https://github.com/doctrine/dbal/issues/6651) and transaction is rolled back
            // it's safe to assume that correct nesting level is 0, as we check for transaction nesting level
            // in condition above
            self::fixConnection($connection);

            // we still throw the exception, so it can be handled gracefully by the caller
            // however the transactionNestingLevel is fixed, so this won't cause follow up issues
            throw $e;
        }
    }

    /**
     * @template TReturn of mixed
     *
     * @param \Closure(Connection): TReturn $closure The function to execute transactionally.
     *
     * @return TReturn
     */
    private static function retry(Connection $connection, \Closure $closure, int $counter, int $transactionNestingLevel)
    {
        ++$counter;
        try {
            return $connection->transactional($closure);
        } catch (\Throwable $e) {
            if ($transactionNestingLevel > 0) {
                // If this RetryableTransaction was executed inside another transaction, do not retry this nested
                // transaction. Remember that the whole (outermost) transaction was already rolled back by the database
                // when any RetryableException is thrown.
                // Rethrow the exception here so only the outermost transaction is retried which in turn includes this
                // nested transaction.
                throw $e;
            }

            // after failure and rollback in transactional we need to make sure the nesting level
            // is correct (see https://github.com/doctrine/dbal/issues/6651) and transaction is rolled back
            // it's safe to assume that correct nesting level is 0, as we check for transaction nesting level
            // in condition above
            self::fixConnection($connection);

            self::runRollbackCallbacks($connection);
            $deadlockRelatedException = self::deadlockRelatedException($e);

            if ($deadlockRelatedException) {
                MeterProvider::meter()?->emit(new ConfiguredMetric('database.locks.count', 1));
            }

            if ($counter > 10 || !$deadlockRelatedException) {
                throw $e;
            }

            // Randomize sleep to prevent same execution delay for multiple statements
            usleep(random_int(10, 20));

            if (self::$callbacks !== null && isset(self::$callbacks[$connection])) {
                self::$callbacks[$connection] = ['success' => [], 'error' => [], 'rollback' => []];
            }

            return self::retry($connection, $closure, $counter, $transactionNestingLevel);
        }
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $operation
     *
     * @return TReturn
     */
    private static function withCallbacks(Connection $connection, \Closure $operation)
    {
        $scopes = self::$callbacks ??= new \WeakMap();
        if (isset($scopes[$connection])) {
            $checkpoint = $scopes[$connection];
            try {
                return $operation();
            } catch (\Throwable $exception) {
                self::runRollbackCallbacks($connection, \count($checkpoint['rollback']));
                $scopes[$connection] = $checkpoint;

                throw $exception;
            }
        }
        if ($connection->getTransactionNestingLevel() > 0) {
            return $operation();
        }
        self::$callbacks[$connection] = ['success' => [], 'error' => [], 'rollback' => []];
        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            self::runRollbackCallbacks($connection);
            $callbacks = self::takeCallbacks($connection, success: false);
            foreach ($callbacks as $callback) {
                $callback();
            }

            throw $exception;
        }
        $callbacks = self::takeCallbacks($connection, success: true);
        foreach ($callbacks as $callback) {
            $callback();
        }

        return $result;
    }

    /**
     * @return list<\Closure(): void>
     */
    private static function takeCallbacks(Connection $connection, bool $success): array
    {
        if (self::$callbacks === null || !isset(self::$callbacks[$connection])) {
            return [];
        }
        $callbacks = self::$callbacks[$connection][$success ? 'success' : 'error'];
        unset(self::$callbacks[$connection]);

        return $callbacks;
    }

    private static function runRollbackCallbacks(Connection $connection, int $offset = 0): void
    {
        if (self::$callbacks === null || !isset(self::$callbacks[$connection])) {
            return;
        }
        $callbacks = self::$callbacks[$connection];
        $rollback = \array_slice($callbacks['rollback'], $offset);
        $callbacks['rollback'] = \array_slice($callbacks['rollback'], 0, $offset);
        self::$callbacks[$connection] = $callbacks;
        foreach (array_reverse($rollback) as $callback) {
            $callback();
        }
    }

    private static function deadlockRelatedException(\Throwable $e): bool
    {
        return
            $e instanceof TransactionRolledBack
            || $e instanceof RetryableException
            // caused by the https://github.com/doctrine/dbal/issues/6651
            || ($e instanceof DriverException && preg_match('/SAVEPOINT [^\s]+ does not exist/', $e->getMessage()))
        ;
    }

    private static function fixConnection(Connection $connection): void
    {
        if ($connection->getTransactionNestingLevel() > 0) {
            $reflectionProperty = new \ReflectionProperty(Connection::class, 'transactionNestingLevel');
            $reflectionProperty->setValue($connection, 1);
            // it could happen that transaction was already rolled back in the transactional method.
            // if case reported - need to catch specific exception
            $connection->rollBack();
        }
    }
}

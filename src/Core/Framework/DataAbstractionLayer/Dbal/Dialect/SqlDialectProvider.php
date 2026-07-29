<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Shopware\Core\Framework\Log\Package;

/**
 * Static bridge that exposes the active {@see AbstractSqlDialect} to code paths that cannot receive
 * it via dependency injection (most notably the static {@see EntityDefinitionQueryHelper::escape()}
 * and other static SQL-building helpers).
 *
 * The dialect is configured once at boot (see the framework bundle boot hook). Until it is set, the
 * provider defaults to the MySQL dialect, which guarantees that any early or DI-less call path keeps
 * producing exactly today's MySQL SQL (backwards-compatibility keystone).
 *
 * New code should inject {@see AbstractSqlDialect} directly and use this holder only as a bridge for
 * unavoidable static contexts.
 *
 * @internal
 */
#[Package('framework')]
final class SqlDialectProvider
{
    private static ?AbstractSqlDialect $dialect = null;

    /**
     * @var (\Closure(): AbstractSqlDialect)|null
     */
    private static ?\Closure $resolver = null;

    /**
     * Set the dialect explicitly. Mostly useful in tests.
     */
    public static function set(AbstractSqlDialect $dialect): void
    {
        self::$dialect = $dialect;
    }

    /**
     * Register a lazy resolver, invoked on first {@see get()}. This keeps dialect resolution (which
     * needs the database platform and therefore a live connection) out of the boot path, so commands
     * that never touch the database do not open a connection just because they were booted.
     *
     * @param \Closure(): AbstractSqlDialect $resolver
     */
    public static function setResolver(\Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function get(): AbstractSqlDialect
    {
        if (self::$dialect !== null) {
            return self::$dialect;
        }

        if (self::$resolver !== null) {
            try {
                return self::$dialect = (self::$resolver)();
            } catch (\Throwable) {
                // The platform is not resolvable yet (e.g. no database during installation). Fall
                // back to the MySQL identity behaviour without caching, so resolution is retried
                // once a connection becomes available.
                return new MySqlDialect();
            }
        }

        return self::$dialect = new MySqlDialect();
    }

    /**
     * Reset the holder. Intended for test teardown so a dialect never leaks between test runs.
     */
    public static function reset(): void
    {
        self::$dialect = null;
        self::$resolver = null;
    }
}

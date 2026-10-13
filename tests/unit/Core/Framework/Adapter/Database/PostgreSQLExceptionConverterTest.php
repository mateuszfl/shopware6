<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\PostgreSQLExceptionConverter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PostgreSQLExceptionConverter::class)]
class PostgreSQLExceptionConverterTest extends TestCase
{
    public function testRestrictViolationsUseTheForeignKeyExceptionContract(): void
    {
        $error = new \PDOException('restricted');
        $error->errorInfo = ['23001', 7, 'restricted'];
        $converted = (new PostgreSQLExceptionConverter(new ExceptionConverter()))->convert(Exception::new($error), null);
        static::assertInstanceOf(ForeignKeyConstraintViolationException::class, $converted);
        static::assertSame('23001', $converted->getSQLState());
    }

    /**
     * @param class-string<DriverException> $expected
     */
    #[DataProvider('nativeConversions')]
    public function testExistingConversionsRemainDelegatedToDbal(string $state, string $expected): void
    {
        $error = new \PDOException('duplicate');
        $error->errorInfo = [$state, 7, 'duplicate'];
        $converted = (new PostgreSQLExceptionConverter(new ExceptionConverter()))->convert(Exception::new($error), null);
        static::assertInstanceOf($expected, $converted);
    }

    public static function nativeConversions(): \Generator
    {
        yield 'ordinary foreign key violation' => ['23503', ForeignKeyConstraintViolationException::class];
        yield 'unique violation' => ['23505', UniqueConstraintViolationException::class];
        yield 'deadlock' => ['40P01', DeadlockException::class];
    }
}

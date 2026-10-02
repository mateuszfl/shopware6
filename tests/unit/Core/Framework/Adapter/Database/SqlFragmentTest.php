<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialectException;
use Shopware\Core\Framework\Adapter\Database\SqlFragment;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SqlFragment::class)]
class SqlFragmentTest extends TestCase
{
    public function testNamedTransportKeepsValuesTypesAndLiteralQuestionMarks(): void
    {
        $fragment = new SqlFragment('SELECT \'?\' AS literal, ?::text, ?', ['a', 7], [ParameterType::STRING, ParameterType::INTEGER]);
        $named = $fragment->named();
        static::assertSame(['a', 7], array_values($named->parameters));
        static::assertSame([ParameterType::STRING, ParameterType::INTEGER], array_values($named->types));
        static::assertStringContainsString('\'?\' AS literal', $named->sql);
        static::assertSame($named->sql, $fragment->named()->sql);
        static::assertSame($named, $named->named());
        static::assertSame('SELECT 1', (new SqlFragment('SELECT 1'))->named()->sql);
    }

    public function testMismatchIsRejectedBeforeExecution(): void
    {
        $this->expectExceptionObject(DatabaseDialectException::invalidOperation('fragment placeholder count does not match its parameters'));
        (new SqlFragment('SELECT ?, ?', ['only one']))->named();
    }

    public function testLegacyInlineEncodingDoesNotExposeSqlOrPathInput(): void
    {
        $fragment = new SqlFragment('SELECT ?', ['\'\\\'; SELECT 1 --']);
        foreach ([['pdo_mysql', '8.4'], ['pdo_pgsql', '18.6']] as [$driver, $version]) {
            $connection = DriverManager::getConnection(['driver' => $driver, 'serverVersion' => $version]);
            $sql = $fragment->inline($connection);
            static::assertStringNotContainsString('\'\\\'; SELECT 1 --', $sql);
            static::assertStringContainsString(bin2hex('\'\\\'; SELECT 1 --'), $sql);
        }
    }
}

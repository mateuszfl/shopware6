<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\JsonExtractType;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\MySqlDialect;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * These are characterization tests: every expected string is the literal Shopware emitted before the
 * dialect abstraction was introduced. If one changes, MySQL behaviour changed and the change is not
 * backwards compatible.
 */
#[Package('framework')]
#[CoversClass(MySqlDialect::class)]
class MySqlDialectTest extends TestCase
{
    private MySqlDialect $dialect;

    protected function setUp(): void
    {
        $this->dialect = new MySqlDialect();
    }

    public function testSupportsMysqlFamilyOnly(): void
    {
        static::assertTrue($this->dialect->supports(new MySQLPlatform()));
        static::assertTrue($this->dialect->supports(new MariaDBPlatform()));
        static::assertFalse($this->dialect->supports(new PostgreSQLPlatform()));
    }

    public function testGetName(): void
    {
        static::assertSame('mysql', $this->dialect->getName());
    }

    public function testQuoteIdentifier(): void
    {
        static::assertSame('`foo`', $this->dialect->quoteIdentifier('foo'));
    }

    public function testQuoteIdentifierRejectsBacktick(): void
    {
        $this->expectException(\Throwable::class);
        $this->dialect->quoteIdentifier('fo`o');
    }

    public function testToHex(): void
    {
        static::assertSame('LOWER(HEX(a.id))', $this->dialect->toHex('a.id'));
    }

    public function testFromHex(): void
    {
        static::assertSame('UNHEX(:id)', $this->dialect->fromHex(':id'));
    }

    public function testJsonExtract(): void
    {
        static::assertSame(
            "JSON_EXTRACT(`root`.`field`, '$.a.b')",
            $this->dialect->jsonExtract('`root`.`field`', "'$.a.b'")
        );
    }

    public function testJsonExtractTypedString(): void
    {
        static::assertSame(
            'IF(JSON_TYPE(JSON_EXTRACT(`t`.`c`, \'$.x\')) != "NULL", '
            . 'CONVERT(JSON_UNQUOTE(JSON_EXTRACT(`t`.`c`, \'$.x\')) USING "utf8mb4") COLLATE utf8mb4_unicode_ci, NULL)',
            $this->dialect->jsonExtractTyped('`t`.`c`', "'$.x'", JsonExtractType::STRING)
        );
    }

    public function testJsonExtractTypedInt(): void
    {
        static::assertSame(
            'IF(JSON_TYPE(JSON_EXTRACT(`t`.`c`, \'$.x\')) != "NULL", JSON_UNQUOTE(JSON_EXTRACT(`t`.`c`, \'$.x\')) + 0.0, NULL)',
            $this->dialect->jsonExtractTyped('`t`.`c`', "'$.x'", JsonExtractType::INT)
        );
    }

    public function testJsonExtractTypedBool(): void
    {
        static::assertSame(
            'IF(JSON_TYPE(JSON_EXTRACT(`t`.`c`, \'$.x\')) != "NULL", '
            . 'IF(JSON_UNQUOTE(JSON_EXTRACT(`t`.`c`, \'$.x\')) != "true" AND JSON_UNQUOTE(JSON_EXTRACT(`t`.`c`, \'$.x\')) = 0, 0, 1), NULL)',
            $this->dialect->jsonExtractTyped('`t`.`c`', "'$.x'", JsonExtractType::BOOL)
        );
    }

    public function testJsonExtractTypedDateTime(): void
    {
        static::assertSame(
            'IF(JSON_TYPE(JSON_EXTRACT(`t`.`c`, \'$.x\')) != "NULL", CAST(JSON_UNQUOTE(JSON_EXTRACT(`t`.`c`, \'$.x\')) AS datetime(3)), NULL)',
            $this->dialect->jsonExtractTyped('`t`.`c`', "'$.x'", JsonExtractType::DATETIME)
        );
    }

    public function testJsonExtractTypedDate(): void
    {
        static::assertSame(
            'IF(JSON_TYPE(JSON_EXTRACT(`t`.`c`, \'$.x\')) != "NULL", CAST(JSON_UNQUOTE(JSON_EXTRACT(`t`.`c`, \'$.x\')) AS DATE), NULL)',
            $this->dialect->jsonExtractTyped('`t`.`c`', "'$.x'", JsonExtractType::DATE)
        );
    }

    public function testIfNull(): void
    {
        static::assertSame('IFNULL(a, b)', $this->dialect->ifNull('a', 'b'));
    }

    public function testGroupConcat(): void
    {
        static::assertSame("GROUP_CONCAT(x SEPARATOR ',')", $this->dialect->groupConcat('x'));
        static::assertSame("GROUP_CONCAT(DISTINCT x SEPARATOR '||')", $this->dialect->groupConcat('x', '||', true));
    }

    public function testJsonObject(): void
    {
        static::assertSame("JSON_OBJECT('k', v, 'k2', v2)", $this->dialect->jsonObject(['k' => 'v', 'k2' => 'v2']));
    }

    public function testJsonObjectAgg(): void
    {
        static::assertSame(
            "CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT('k', v)), ']')",
            $this->dialect->jsonObjectAgg(['k' => 'v'])
        );
    }

    public function testInsertIgnore(): void
    {
        static::assertSame('INSERT IGNORE', $this->dialect->insertIgnoreKeyword());
        static::assertSame('', $this->dialect->insertIgnoreSuffix());
    }

    public function testBuildUpsertSuffix(): void
    {
        static::assertSame('', $this->dialect->buildUpsertSuffix([]));
        static::assertSame(
            ' ON DUPLICATE KEY UPDATE `a` = VALUES(`a`), `b` = VALUES(`b`)',
            $this->dialect->buildUpsertSuffix(['a', 'b'])
        );
    }

    public function testSupportsReplaceInto(): void
    {
        static::assertTrue($this->dialect->supportsReplaceInto());
    }

    public function testLikeEscapeClause(): void
    {
        static::assertSame('', $this->dialect->likeEscapeClause());
    }

    public function testNullSafeEquals(): void
    {
        static::assertSame('a <=> b', $this->dialect->nullSafeEquals('a', 'b'));
    }

    public function testDateHistogramFormat(): void
    {
        static::assertSame("DATE_FORMAT(c, '%Y-%m')", $this->dialect->dateHistogramFormat('c', DateHistogramAggregation::PER_MONTH));
        static::assertSame(
            "CONCAT(DATE_FORMAT(c, '%Y'), '-', QUARTER(c))",
            $this->dialect->dateHistogramFormat('c', DateHistogramAggregation::PER_QUARTER)
        );
    }
}

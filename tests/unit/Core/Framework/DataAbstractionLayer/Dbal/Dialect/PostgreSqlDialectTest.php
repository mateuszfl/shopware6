<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\JsonExtractType;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect\PostgreSqlDialect;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PostgreSqlDialect::class)]
class PostgreSqlDialectTest extends TestCase
{
    private PostgreSqlDialect $dialect;

    protected function setUp(): void
    {
        $this->dialect = new PostgreSqlDialect();
    }

    public function testSupportsPostgresOnly(): void
    {
        static::assertTrue($this->dialect->supports(new PostgreSQLPlatform()));
        static::assertFalse($this->dialect->supports(new MySQLPlatform()));
    }

    public function testGetName(): void
    {
        static::assertSame('postgresql', $this->dialect->getName());
    }

    public function testQuoteIdentifier(): void
    {
        static::assertSame('"foo"', $this->dialect->quoteIdentifier('foo'));
    }

    public function testQuoteIdentifierRejectsDoubleQuote(): void
    {
        $this->expectException(\Throwable::class);
        $this->dialect->quoteIdentifier('fo"o');
    }

    public function testToHex(): void
    {
        static::assertSame("encode(a.id, 'hex')", $this->dialect->toHex('a.id'));
    }

    public function testFromHex(): void
    {
        static::assertSame("decode(:id, 'hex')", $this->dialect->fromHex(':id'));
    }

    public function testJsonExtractTranslatesPath(): void
    {
        static::assertSame("(t.c #>> '{a,b}')", $this->dialect->jsonExtract('t.c', "'$.a.b'"));
    }

    public function testJsonExtractTypedCoercions(): void
    {
        static::assertSame("((t.c #>> '{x}'))::bigint", $this->dialect->jsonExtractTyped('t.c', "'$.x'", JsonExtractType::INT));
        static::assertSame("((t.c #>> '{x}'))::double precision", $this->dialect->jsonExtractTyped('t.c', "'$.x'", JsonExtractType::FLOAT));
        static::assertSame("((t.c #>> '{x}'))::boolean", $this->dialect->jsonExtractTyped('t.c', "'$.x'", JsonExtractType::BOOL));
        static::assertSame("((t.c #>> '{x}'))::timestamp(3)", $this->dialect->jsonExtractTyped('t.c', "'$.x'", JsonExtractType::DATETIME));
        static::assertSame("((t.c #>> '{x}'))::date", $this->dialect->jsonExtractTyped('t.c', "'$.x'", JsonExtractType::DATE));
        static::assertSame("(t.c #>> '{x}')", $this->dialect->jsonExtractTyped('t.c', "'$.x'", JsonExtractType::STRING));
    }

    public function testIfNull(): void
    {
        static::assertSame('COALESCE(a, b)', $this->dialect->ifNull('a', 'b'));
    }

    public function testGroupConcat(): void
    {
        static::assertSame("string_agg(x, ',')", $this->dialect->groupConcat('x'));
        static::assertSame("string_agg(DISTINCT x, '||')", $this->dialect->groupConcat('x', '||', true));
    }

    public function testJsonObjectHelpers(): void
    {
        static::assertSame("json_build_object('k', v)", $this->dialect->jsonObject(['k' => 'v']));
        static::assertSame(
            "COALESCE(json_agg(DISTINCT json_build_object('k', v))::text, '[]')",
            $this->dialect->jsonObjectAgg(['k' => 'v'])
        );
    }

    public function testInsertIgnore(): void
    {
        static::assertSame('INSERT', $this->dialect->insertIgnoreKeyword());
        static::assertSame(' ON CONFLICT DO NOTHING', $this->dialect->insertIgnoreSuffix());
    }

    public function testBuildUpsertSuffix(): void
    {
        static::assertSame('', $this->dialect->buildUpsertSuffix([]));
        static::assertSame(
            ' ON CONFLICT ("id") DO UPDATE SET "a" = EXCLUDED."a", "b" = EXCLUDED."b"',
            $this->dialect->buildUpsertSuffix(['a', 'b'], ['id'])
        );
    }

    public function testBuildUpsertSuffixRequiresConflictColumns(): void
    {
        $this->expectException(DataAbstractionLayerException::class);
        $this->dialect->buildUpsertSuffix(['a'], []);
    }

    public function testSupportsReplaceInto(): void
    {
        static::assertFalse($this->dialect->supportsReplaceInto());
    }

    public function testLikeEscapeClause(): void
    {
        static::assertSame(" ESCAPE '\\'", $this->dialect->likeEscapeClause());
    }

    public function testNullSafeEquals(): void
    {
        static::assertSame('a IS NOT DISTINCT FROM b', $this->dialect->nullSafeEquals('a', 'b'));
    }

    public function testDateHistogramFormat(): void
    {
        static::assertSame("to_char(c, 'YYYY-MM')", $this->dialect->dateHistogramFormat('c', DateHistogramAggregation::PER_MONTH));
        static::assertSame(
            "(to_char(c, 'YYYY') || '-' || EXTRACT(QUARTER FROM c)::int)",
            $this->dialect->dateHistogramFormat('c', DateHistogramAggregation::PER_QUARTER)
        );
    }
}

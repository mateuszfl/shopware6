<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialectException;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonPath::class)]
class JsonPathTest extends TestCase
{
    public function testLiteralMembersPreserveDotsQuotesBackslashesAndUnicode(): void
    {
        $members = ['with.dot', 'with-hyphen', '"\\żółć', '\' OR 1=1 --', 'items ? (@ > 0)', ''];
        $path = JsonPath::members($members);
        $suffix = implode('', array_map(static fn (string $member): string => '.' . json_encode($member, \JSON_THROW_ON_ERROR), $members));
        static::assertSame($path->steps, JsonPath::parse($suffix)->steps);
        static::assertFalse($path->hasWildcard());
        static::assertSame('$', JsonPath::parse('')->expression());
    }

    public function testIndexesAndWildcardsAreDistinctFromLiteralStarMembers(): void
    {
        $path = JsonPath::parse('.items[1].*');
        static::assertSame('$."items"[1].*', $path->expression());
        static::assertTrue($path->hasWildcard());
        static::assertTrue(JsonPath::parse('.items[*]')->hasWildcard());
        static::assertFalse(JsonPath::parse('."*"')->hasWildcard());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'negative index' => ['.items[-1]'];
        yield 'unterminated member' => ['."unfinished'];
        yield 'empty unquoted member' => ['.items..name'];
        yield 'unclosed index' => ['.items[0'];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsNonDalPaths(string $path): void
    {
        $this->expectExceptionObject(DatabaseDialectException::invalidOperation('invalid JSON accessor path'));
        JsonPath::parse($path);
    }
}

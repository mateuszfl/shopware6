<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class SharedRuntimeSqlFragmentRegressionTest extends TestCase
{
    public const DIALECT_FUNCTION_PATTERN = '~\\b(?:GROUP_CONCAT|UNHEX|HEX|IFNULL|JSON_(?:EXTRACT|CONTAINS|CONTAINS_PATH|SEARCH|KEY|VALUE|LENGTH|TYPE|VALID|QUOTE|UNQUOTE|ARRAY|OBJECT|SET|INSERT|REPLACE|REMOVE|MERGE|MERGE_PATCH|MERGE_PRESERVE|OVERLAPS|DEPTH|PRETTY|STORAGE_SIZE|ARRAYAGG|OBJECTAGG|TABLE|QUERY|KEYS|ARRAY_APPEND|ARRAY_INSERT|ARRAY_INTERSECT|ARRAY_DELETE|ARRAY_COMPACT|ARRAY_UNION|ARRAY_EXCEPT|ARRAY_LENGTH|ARRAY_SORT|ARRAY_REVERSE|ARRAY_INDEX|ARRAY_POSITION|ARRAY_REMOVE|ARRAY_SLICE)|FIND_IN_SET|DATE_FORMAT|DATE_ADD|DATE_SUB|DATEDIFF|TIMESTAMPDIFF|FROM_UNIXTIME|UNIX_TIMESTAMP|FIELD|BIN_TO_UUID|UUID_TO_BIN|LAST_INSERT_ID|GET_LOCK|RELEASE_LOCK)\\s*\\(~i';
    public const DIALECT_SQL_PATTERN = '~\\b(?:ON\\s+DUPLICATE\\s+KEY|INSERT\\s+IGNORE|REPLACE\\s+INTO|SQL_CALC_FOUND_ROWS|FOREIGN_KEY_CHECKS|AUTO_INCREMENT|SHOW\\s+(?:TABLES|COLUMNS|INDEX|CREATE)|information_schema)\\b|@@~i';
    public const SQL_CONTEXT_PATTERN = '~^\\s*(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|REPLACE|WITH|SHOW|WHERE|JOIN|FROM)\\b|^\\s*SET\\s+[a-z_]+\\s*=~i';

    public function testSharedRuntimeDoesNotGainUnreviewedMySqlSqlLiterals(): void
    {
        $root = \dirname(__DIR__, 6);
        $baselinePath = $root . '/dev-tools/database/runtime-sql-baseline.json';
        $baseline = json_decode((string) file_get_contents($baselinePath), true, 512, \JSON_THROW_ON_ERROR);
        $approved = array_map(
            static fn (array $entry): string => $entry['path'] . ':' . $entry['line'] . ':' . $entry['sha256'],
            $baseline,
        );
        sort($approved);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $actual = [];

        foreach (Finder::create()->files()->name('*.php')->in($root . '/src') as $file) {
            $relativePath = 'src/' . $file->getRelativePathname();
            if (str_contains($relativePath, '/Migration/') || str_contains($relativePath, '/Test/') || preg_match('~/(?:MySQL|MariaDB)[^/]*\.php$~', $relativePath) === 1) {
                continue;
            }

            foreach (self::collectDialectFragments($parser->parse($file->getContents()) ?? []) as $fragment) {
                $actual[] = $relativePath . ':' . $fragment['line'] . ':' . hash('sha256', $fragment['value']);
            }
        }

        sort($actual);
        static::assertSame($approved, $actual, 'A new or changed MySQL SQL literal must receive a reviewed per-occurrence baseline entry and dual-engine coverage.');
    }

    public function testCollectorFindsDynamicSqlFragmentsAndIgnoresSharedUuidHexFunction(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse('<?php $field = "id"; $a = "SELECT IFNULL(value, 0) FROM t"; $b = "JSON_EXTRACT(data, \'$.x\')"; $c = "JSON_CONTAINS(data, \'1\')"; $d = "JSON_SET(data, \'$.x\', 1)"; $e = "SELECT sw_uuid_hex(id) FROM t"; $f = "SELECT {$field}, IFNULL(value, 0) FROM t"; $g = "JSON_ARRAYAGG(value)";');
        $fragments = self::collectDialectFragments($nodes ?? []);

        static::assertCount(6, $fragments);
        $all = implode("\n", array_column($fragments, 'value'));
        static::assertStringContainsString('IFNULL', $all);
        static::assertStringContainsString('JSON_EXTRACT', $all);
        static::assertStringContainsString('JSON_CONTAINS', $all);
        static::assertStringContainsString('JSON_SET', $all);
        static::assertStringContainsString('JSON_ARRAYAGG', $all);
    }

    /**
     * @param array<Node> $nodes
     *
     * @return list<array{line: int, value: string}>
     */
    private static function collectDialectFragments(array $nodes): array
    {
        $collector = new class extends NodeVisitorAbstract {
            /**
             * @var list<array{line: int, value: string}>
             */
            public array $fragments = [];

            public function enterNode(Node $node): ?int
            {
                if (!$node instanceof Node\Scalar\String_ && !$node instanceof Node\Scalar\EncapsedStringPart) {
                    return null;
                }

                $hasSqlContext = preg_match(SharedRuntimeSqlFragmentRegressionTest::SQL_CONTEXT_PATTERN, $node->value) === 1;
                $fragment = $hasSqlContext ? $node->value : preg_replace('~`[^`]*`~', '', $node->value);
                $dialectFunction = preg_match(SharedRuntimeSqlFragmentRegressionTest::DIALECT_FUNCTION_PATTERN, $node->value) === 1;
                $dialectSql = $hasSqlContext && \is_string($fragment) && preg_match(SharedRuntimeSqlFragmentRegressionTest::DIALECT_SQL_PATTERN, $fragment) === 1;
                $mysqlQuotedSql = $hasSqlContext && preg_match('~`[^`]+`~', $node->value) === 1;
                if ($dialectFunction || $dialectSql || $mysqlQuotedSql) {
                    $this->fragments[] = ['line' => $node->getStartLine(), 'value' => $node->value];
                }

                return null;
            }
        };
        (new NodeTraverser($collector))->traverse($nodes);

        return $collector->fragments;
    }
}

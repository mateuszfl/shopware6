<?php declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php dev-tools/postgres-migration-translator.php <input.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Cannot read input file\n");
    exit(2);
}

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($source);
if ($ast === null) {
    fwrite(STDERR, "Input contains no PHP AST\n");
    exit(2);
}

$unsupported = [];
$translatedSql = 0;
$traverser = new NodeTraverser();
$traverser->addVisitor(new class($unsupported, $translatedSql) extends NodeVisitorAbstract {
    /** @var list<string> */
    public array $unsupported = [];
    public int $translatedSql = 0;

    public function __construct(array &$unsupported, int &$translatedSql)
    {
        $this->unsupported =& $unsupported;
        $this->translatedSql =& $translatedSql;
    }

    public function enterNode(Node $node): ?Node
    {
        if (!$node instanceof Node\Scalar\String_) {
            return null;
        }

        $sql = $node->value;
        if (!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|RENAME)\b/i', $sql)) {
            return null;
        }

        $unsupportedPatterns = [
            'binary' => '/\bBINARY\s*\(/i',
            'json' => '/\bJSON\b|JSON_(?:EXTRACT|UNQUOTE|VALID)/i',
            'tinyint' => '/\bTINYINT\b/i',
            'enum' => '/\bENUM\s*\(/i',
            'mysql alter syntax' => '/\b(?:CHANGE|MODIFY)\s+(?:COLUMN\s+)?/i',
            'generated or virtual column' => '/\b(?:VIRTUAL|GENERATED\s+ALWAYS)\b/i',
            'trigger' => '/\bTRIGGER\b/i',
            'mysql collation' => '/\bCOLLATE\s+utf8mb4?_[a-z0-9_]+/i',
        ];
        foreach ($unsupportedPatterns as $reason => $pattern) {
            if (preg_match($pattern, $sql)) {
                $this->unsupported[] = $reason;
            }
        }

        $converted = preg_replace('/`([^`]+)`/', '"$1"', $sql);
        $converted = preg_replace('/\b(INT|INTEGER)\s*\(\d+\)/i', '$1', $converted);
        $converted = preg_replace('/\s+AFTER\s+"?[_a-z0-9]+"?/i', '', $converted);
        $converted = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $converted);
        $converted = preg_replace('/\bCREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $converted);

        if ($converted !== $sql) {
            ++$this->translatedSql;
            $node->value = $converted;
        }

        return $node;
    }
});
$translated = $traverser->traverse($ast);
$eligible = $translatedSql > 0 && $unsupported === [];
if ($eligible) {
    $output = (new Standard())->prettyPrintFile($translated);
    if (file_put_contents($argv[2], $output . "\n") === false) {
        fwrite(STDERR, "Cannot write output file\n");
        exit(2);
    }
}

echo json_encode([
    'output' => $eligible ? $argv[2] : null,
    'eligible' => $eligible,
    'translated_sql_literals' => $translatedSql,
    'unsupported_shapes' => array_values(array_unique($unsupported)),
], JSON_THROW_ON_ERROR) . "\n";

if (!$eligible) {
    exit(3);
}

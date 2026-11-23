<?php declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc === 5 && $argv[1] === '--inventory') {
    $sourceDirectory = rtrim($argv[2], '/');
    $variantDirectory = rtrim($argv[3], '/');
    $outputDirectory = rtrim($argv[4], '/');
    if (!is_dir($sourceDirectory) || !is_dir($variantDirectory) || !is_dir($outputDirectory)) {
        fwrite(STDERR, "Source, variant, and output directories must exist\n");
        exit(2);
    }

    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $printer = new Standard();
    $report = [];
    foreach (glob($sourceDirectory . '/Migration*.php') ?: [] as $path) {
        $name = basename($path);
        if (is_file($variantDirectory . '/' . $name)) {
            $report[] = ['class_file' => $name, 'source' => $path, 'status' => 'existing_variant', 'blocker_category' => null, 'variant' => $variantDirectory . '/' . $name];
            continue;
        }
        $source = file_get_contents($path);
        $entry = ['class_file' => $name, 'source' => $path, 'status' => 'rejected', 'unsupported_shapes' => [], 'translated_sql_literals' => 0];
        if ($source === false) {
            $entry['unsupported_shapes'][] = 'source unreadable';
            $report[] = $entry;
            continue;
        }
        try {
            $ast = $parser->parse($source);
        } catch (Throwable $e) {
            $entry['unsupported_shapes'][] = 'PHP parse error: ' . $e->getMessage();
            $report[] = $entry;
            continue;
        }
        if ($ast === null) {
            $entry['unsupported_shapes'][] = 'empty PHP AST';
            $report[] = $entry;
            continue;
        }

        $visitor = new class extends NodeVisitorAbstract {
            public array $unsupported = [];
            public int $translated = 0;
            private const SQL = '/\\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|RENAME)\\b/i';

            public function enterNode(Node $node): ?Node
            {
                if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && strtolower($node->name->toString()) === 'sprintf') {
                    $format = $node->args[0]->value ?? null;
                    if ($format instanceof Node\Scalar\String_ && preg_match(self::SQL, $format->value)) {
                        $this->unsupported[] = 'formatted dynamic SQL';
                    }
                }
                if ($node instanceof Node\Expr\BinaryOp\Concat) {
                    $strings = [];
                    $node->getAttribute('parent');
                    foreach ($node->getSubNodeNames() as $subName) {
                        $part = $node->$subName;
                        if ($part instanceof Node\Scalar\String_) {
                            $strings[] = $part->value;
                        }
                    }
                    if (array_filter($strings, fn (string $value): bool => preg_match(self::SQL, $value) === 1)) {
                        $this->unsupported[] = 'dynamic SQL concatenation';
                    }
                    return null;
                }
                if ($node instanceof Node\Scalar\Encapsed) {
                    foreach ($node->parts as $part) {
                        if ($part instanceof Node\Scalar\EncapsedStringPart && preg_match(self::SQL, $part->value)) {
                            $this->unsupported[] = 'interpolated SQL string';
                        }
                    }
                    return null;
                }
                if (!$node instanceof Node\Scalar\String_ || !preg_match(self::SQL, $node->value)) {
                    return null;
                }

                $sql = $node->value;
                $patterns = [
                    'binary type/function' => '/\\bBINARY\\s*\\(/i',
                    'json type/function' => '/\\bJSON\\b|JSON_(?:EXTRACT|UNQUOTE|VALID)/i',
                    'tinyint type' => '/\\bTINYINT\\b/i',
                    'enum type' => '/\\bENUM\\s*\\(/i',
                    'mysql alter syntax' => '/\\b(?:CHANGE|MODIFY)\\s+(?:COLUMN\\s+)?/i',
                    'generated or virtual column' => '/\\b(?:VIRTUAL|GENERATED\\s+ALWAYS)\\b/i',
                    'trigger syntax' => '/\\bTRIGGER\\b/i',
                    'mysql collation' => '/\\bCOLLATE\\s+utf8mb4?_[a-z0-9_]+/i',
                ];
                foreach ($patterns as $reason => $pattern) {
                    if (preg_match($pattern, $sql)) {
                        $this->unsupported[] = $reason;
                    }
                }
                $converted = preg_replace('/`([^`]+)`/', '"$1"', $sql);
                $converted = preg_replace('/\\b(INT|INTEGER)\\s*\\(\\d+\\)/i', '$1', $converted);
                $converted = preg_replace('/\\s+AFTER\\s+"?[_a-z0-9]+"?/i', '', $converted);
                $converted = preg_replace('/\\bIFNULL\\s*\\(/i', 'COALESCE(', $converted);
                $converted = preg_replace('/\\bCREATE\\s+TABLE\\s+(?!IF\\s+NOT\\s+EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $converted);
                if ($converted !== $sql) {
                    ++$this->translated;
                    $node->value = $converted;
                }
                return null;
            }
        };
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $translated = $traverser->traverse($ast);
        $entry['translated_sql_literals'] = $visitor->translated;
        $entry['unsupported_shapes'] = array_values(array_unique($visitor->unsupported));
        if ($entry['unsupported_shapes'] !== []) {
            $entry['status'] = 'rejected_unsupported';
            $entry['blocker_category'] = 'unsupported SQL syntax rule';
        } elseif ($visitor->translated > 0) {
            $entry['status'] = 'converted';
            $entry['blocker_category'] = 'candidate; semantic migration review still required';
            $target = $outputDirectory . '/' . $name;
            if (file_put_contents($target, $printer->prettyPrintFile($translated) . "\n") === false) {
                $entry['status'] = 'rejected_write_error';
                $entry['unsupported_shapes'][] = 'output write failed';
            } else {
                $entry['output'] = $target;
            }
        } else {
            $entry['status'] = 'no_supported_transform';
            $entry['blocker_category'] = 'runtime/helper/data migration review';
            $entry['unsupported_shapes'][] = 'no changed static SQL literal; manual/runtime analysis required';
        }
        $report[] = $entry;
    }
    echo json_encode(['source_directory' => $sourceDirectory, 'variant_directory' => $variantDirectory, 'count' => count($report), 'summary' => array_count_values(array_column($report, 'status')), 'blocker_summary' => array_count_values(array_filter(array_column($report, 'blocker_category'))), 'classes' => $report], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    exit(array_filter($report, static fn (array $entry): bool => $entry['status'] !== 'converted') === [] ? 0 : 3);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php dev-tools/postgres-migration-translator.php --inventory <source-dir> <existing-variant-dir> <existing-output-dir>\n");
    exit(2);
}

fwrite(STDERR, "Single-file mode is unavailable; use --inventory to guarantee complete reports.\n");
exit(2);

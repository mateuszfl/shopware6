<?php declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc === 5 && $argv[1] === '--inventory') {
    $sourceDirectory = rtrim($argv[2], '/');
    $variantDirectory = rtrim($argv[3], '/');
    $outputDirectory = rtrim($argv[4], '/');
    if (!is_dir($sourceDirectory) || !is_dir($variantDirectory) || !is_dir($outputDirectory)) {
        fwrite(STDERR, "Source, variant, and output directories must exist\n");
        exit(2);
    }
    if (glob($outputDirectory . '/Migration*.php') !== []) {
        fwrite(STDERR, "Output directory must be empty to prevent stale candidate files\n");
        exit(2);
    }

    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $printer = new Standard();
    $registry = new MigrationVariantRegistry();
    $report = [];
    foreach (glob($sourceDirectory . '/Migration*.php') ?: [] as $path) {
        $name = basename($path);
        $logicalClass = 'Shopware\\Core\\Migration\\V6_3\\' . pathinfo($name, PATHINFO_FILENAME);
        if (!class_exists($logicalClass)) {
            $report[] = ['class_file' => $name, 'source' => $path, 'status' => 'source_class_not_autoloadable', 'blocker_category' => 'inventory error'];
            continue;
        }
        $logicalMigration = new $logicalClass();
        $timestamp = $logicalMigration->getCreationTimestamp();
        try {
            $implementation = $registry->resolve($logicalClass, $timestamp);
            $isPortableMapping = $implementation::class === $logicalClass;
            $report[] = [
                'class_file' => $name,
                'source' => $path,
                'status' => $isPortableMapping ? 'registered_portable_mapping' : 'registered_variant',
                'blocker_category' => null,
                'implementation' => $implementation::class,
                'variant_file_present' => is_file($variantDirectory . '/' . $name),
            ];
            continue;
        } catch (Throwable $exception) {
            $missingVariantMessage = 'No PostgreSQL migration variant for ' . $logicalClass . ' (timestamp ' . $timestamp . ').';
            if (!$exception instanceof Shopware\Core\Framework\Migration\MigrationException
                || !str_contains($exception->getMessage(), $missingVariantMessage)) {
                $report[] = [
                    'class_file' => $name,
                    'source' => $path,
                    'status' => 'registry_resolution_error',
                    'blocker_category' => 'migration registry inconsistency',
                    'error' => $exception->getMessage(),
                ];
                continue;
            }
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
            public int $sqlLiterals = 0;
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
                ++$this->sqlLiterals;

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
                    'mysql storage or charset clause' => '/\\b(?:ENGINE\\s*=|DEFAULT\\s+CHARSET|CHARSET\\s*=)/i',
                    'mysql foreign key alter syntax' => '/\\bDROP\\s+FOREIGN\\s+KEY\\b/i',
                    'mysql key/index alter syntax' => '/\\bALTER\\s+TABLE\\b[\\s\\S]*?\\b(?:ADD\\s+(?:(?:UNIQUE|FULLTEXT|SPATIAL)\\s+)?(?:INDEX|KEY)\\b|ADD\\s+UNIQUE\\b|DROP\\s+INDEX\\b|DROP\\s+PRIMARY\\s+KEY\\b)/i',
                    'unsigned numeric type' => '/\\bUNSIGNED\\b/i',
                    'auto increment type' => '/\\bAUTO_INCREMENT\\b/i',
                    'on update column clause' => '/\\bON\\s+UPDATE\\s+CURRENT_TIMESTAMP/i',
                    'mysql text/blob type' => '/\\b(?:TINYTEXT|MEDIUMTEXT|LONGTEXT|TINYBLOB|MEDIUMBLOB|LONGBLOB)\\b/i',
                    'mysql datetime or medium integer type' => '/\\b(?:DATETIME(?:\\s*\\(\\d+\\))?|MEDIUMINT|YEAR)\\b/i',
                    'mysql collation with quoted name' => "~\\bCOLLATE\\s+['\"]?utf8(?:mb4)?_[a-z0-9_]+['\"]?~i",
                    'double-quoted SQL token is dialect-ambiguous' => '/"[^"\\r\\n]*"/',
                    'MySQL AFTER column placement changes schema order' => '/\\bAFTER\\s+[`"a-z0-9_]+/i',
                    'CREATE TABLE idempotency semantics need review' => '/\\bCREATE\\s+TABLE\\s+(?!IF\\s+NOT\\s+EXISTS)/i',
                    'mixed-case MySQL identifier quoting needs review' => '/`[^`]*[A-Z][^`]*`/',
                    'quoted UPDATE value may be a MySQL string' => '/\\bUPDATE\\b[\\s\\S]*?\\bSET\\b[\\s\\S]*?=\\s*`[^`]+`/i',
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
        if (preg_match('/\\bUuid::(?:randomBytes|fromHexToBytes)\\s*\\(/', $source)
            && preg_match('/(?:->(?:insert|update|delete|executeStatement|executeQuery|addInsert)\\s*\\(|\\bMultiInsertQueryQueue\\b|\\bStatementHelper::)/', $source)) {
            $visitor->unsupported[] = 'binary UUID values passed through DBAL need explicit PostgreSQL binding review';
        }
        $namespaceCount = 0;
        foreach ($translated as $statement) {
            if (!$statement instanceof Node\Stmt\Namespace_) {
                continue;
            }

            ++$namespaceCount;
            $statement->name = new Node\Name('Shopware\\Core\\Migration\\PostgreSQL\\V6_3');
        }
        $entry['translated_sql_literals'] = $visitor->translated;
        $entry['static_sql_literals'] = $visitor->sqlLiterals;
        $entry['unsupported_shapes'] = array_values(array_unique($visitor->unsupported));
        $entry['logical_class'] = $logicalClass;
        $entry['proposed_implementation'] = 'Shopware\\Core\\Migration\\PostgreSQL\\V6_3\\' . pathinfo($name, PATHINFO_FILENAME);
        $entry['proposed_timestamp'] = $timestamp;
        $entry['registry_entry_proposal'] = 'Core63\\' . pathinfo($name, PATHINFO_FILENAME) . '::class, ' . $timestamp . ', PostgreSQL63\\' . pathinfo($name, PATHINFO_FILENAME) . '::class';
        if ($namespaceCount !== 1) {
            $entry['status'] = 'rejected_namespace_shape';
            $entry['blocker_category'] = 'PHP namespace shape';
            $entry['unsupported_shapes'][] = 'expected exactly one namespace declaration';
        } elseif ($entry['unsupported_shapes'] !== []) {
            $entry['status'] = 'custom_semantic_review';
            $entry['blocker_category'] = 'custom SQL semantics or runtime-generated SQL';
        } elseif ($visitor->translated > 0) {
            $entry['status'] = 'basic_sql_rewrite_candidate';
            $entry['blocker_category'] = 'SQL syntax rewrite; semantic review still required';
            $target = $outputDirectory . '/' . $name;
            if (file_put_contents($target, $printer->prettyPrintFile($translated) . "\n") === false) {
                $entry['status'] = 'rejected_write_error';
                $entry['unsupported_shapes'][] = 'output write failed';
            } else {
                $entry['output'] = $target;
            }
        } else {
            if ($visitor->sqlLiterals > 0) {
                $entry['status'] = 'static_sql_no_rewrite_review';
                $entry['blocker_category'] = 'static SQL portability review';
                $entry['unsupported_shapes'][] = 'static SQL has no recognized rewrite; portability not proven';
            } else {
                $entry['status'] = 'runtime_helper_data_review';
                $entry['blocker_category'] = 'runtime/helper/data migration review';
                $entry['unsupported_shapes'][] = 'no static SQL literal; helper/data behavior requires review';
            }
        }
        $report[] = $entry;
    }
    echo json_encode(['source_directory' => $sourceDirectory, 'variant_directory' => $variantDirectory, 'count' => count($report), 'summary' => array_count_values(array_column($report, 'status')), 'blocker_summary' => array_count_values(array_filter(array_column($report, 'blocker_category'))), 'classes' => $report], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    exit(array_filter($report, static fn (array $entry): bool => !str_starts_with($entry['status'], 'registered_') && $entry['status'] !== 'basic_sql_rewrite_candidate') === [] ? 0 : 3);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php dev-tools/postgres-migration-translator.php --inventory <source-dir> <existing-variant-dir> <existing-output-dir>\n");
    exit(2);
}

fwrite(STDERR, "Single-file mode is unavailable; use --inventory to guarantee complete reports.\n");
exit(2);

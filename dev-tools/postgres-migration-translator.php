<?php declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;

require dirname(__DIR__) . '/vendor/autoload.php';

$diagnosticMode = ($argv[1] ?? '') === '--diagnostic-inventory';
if ($argc === 5 && \in_array($argv[1], ['--inventory', '--diagnostic-inventory'], true)) {
    $sourceDirectory = rtrim($argv[2], '/');
    $variantDirectory = rtrim($argv[3], '/');
    $outputDirectory = rtrim($argv[4], '/');
    if (!is_dir($sourceDirectory) || !is_dir($variantDirectory) || !is_dir($outputDirectory)) {
        fwrite(STDERR, "Source, variant, and output directories must exist\n");
        exit(2);
    }
    if (!$diagnosticMode && glob($outputDirectory . '/Migration*.php') !== []) {
        fwrite(STDERR, "Output directory must be empty to prevent stale candidate files\n");
        exit(2);
    }
    if ($diagnosticMode && (glob($outputDirectory . '/Migration*.php') !== [] || is_file($outputDirectory . '/registry-manifest.json') || is_file($outputDirectory . '/registry-entries.php'))) {
        fwrite(STDERR, "Diagnostic output directory must be fresh and empty\n");
        exit(2);
    }

    $version = $diagnosticMode ? basename($sourceDirectory) : 'V6_3';
    if (!preg_match('/\AV6_[0-9]+\z/', $version)) {
        fwrite(\STDERR, "Diagnostic source directory must be named V6_<minor>\n");
        exit(2);
    }

    $logicalNamespace = 'Shopware\\Core\\Migration\\' . $version;
    $implementationNamespace = 'Shopware\\Core\\Migration\\PostgreSQL\\' . $version;
    $registryAliasSuffix = str_replace('_', '', substr($version, 1));
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $printer = new Standard();
    $registry = new MigrationVariantRegistry();
    $report = [];
    foreach (glob($sourceDirectory . '/Migration*.php') ?: [] as $path) {
        $name = basename($path);
        $className = pathinfo($name, \PATHINFO_FILENAME);
        $logicalClass = $logicalNamespace . '\\' . $className;
        if (!class_exists($logicalClass)) {
            $report[] = ['class_file' => $name, 'source' => $path, 'status' => 'source_class_not_autoloadable', 'blocker_category' => 'inventory error'];
            continue;
        }
        $logicalMigration = new $logicalClass();
        $timestamp = $logicalMigration->getCreationTimestamp();
        try {
            $implementation = $registry->resolve($logicalClass, $timestamp);
            $isPortableMapping = $implementation::class === $logicalClass;
            $variantPath = $variantDirectory . '/' . $name;
            $diagnosticImplementation = !$isPortableMapping && is_file($variantPath)
                && str_contains((string) file_get_contents($variantPath), 'Diagnostic best-effort PostgreSQL variant');
            if ($diagnosticMode && $diagnosticImplementation) {
                // Fall through and regenerate this diagnostic-only variant from current source.
            } else {
                $report[] = [
                    'class_file' => $name,
                    'source' => $path,
                    'status' => $isPortableMapping ? 'registered_portable_mapping' : 'registered_variant',
                    'blocker_category' => null,
                    'logical_class' => $logicalClass,
                    'creation_timestamp' => $timestamp,
                    'implementation' => $implementation::class,
                    'variant_file_present' => is_file($variantPath),
                ];
                if ($diagnosticMode && $implementation::class !== $logicalClass && !is_file($variantDirectory . '/' . $name)) {
                    $report[array_key_last($report)]['status'] = 'registered_variant_file_missing';
                    $report[array_key_last($report)]['blocker_category'] = 'registry points to a missing implementation file';
                }
                continue;
            }
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

        $visitor = new class($diagnosticMode, $name) extends NodeVisitorAbstract {
            public array $unsupported = [];
            public int $translated = 0;
            public int $sqlLiterals = 0;
            public array $appliedRules = [];
            public bool $diagnosticMode = false;
            private string $classFile;
            /** @var array<string, list<string>> */
            private array $methodArrayKeys = [];
            /** @var array<string, bool> */
            private array $methodUuidVariables = [];
            /** @var array<string, list<string>> */
            private array $methodUuidRowColumns = [];
            /** @var array<string, bool> */
            private array $methodUuidArrays = [];
            private const SQL = '/\\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|RENAME)\\b/i';

            private function isUuidBytesExpression(Node\Expr $expression): bool
            {
                if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
                    return $this->methodUuidVariables[$expression->name] ?? false;
                }
                if ($expression instanceof Node\Expr\ArrayDimFetch
                    && $expression->var instanceof Node\Expr\Variable
                    && is_string($expression->var->name)
                    && $expression->dim instanceof Node\Scalar\String_) {
                    return in_array($expression->dim->value, $this->methodUuidRowColumns[$expression->var->name] ?? [], true);
                }
                if (!$expression instanceof Node\Expr\StaticCall
                    || !$expression->class instanceof Node\Name
                    || !$expression->name instanceof Node\Identifier
                    || !in_array($expression->name->toString(), ['fromHexToBytes', 'randomBytes'], true)) {
                    return false;
                }

                return $expression->class->getLast() === 'Uuid';
            }

            /** @return array<string, string> parameter name => binary|array_binary */
            private function getUuidParameterTypes(Node\Expr $expression): array
            {
                if (!$expression instanceof Node\Expr\Array_) {
                    return [];
                }

                $types = [];
                foreach ($expression->items as $item) {
                    if (!$item?->key instanceof Node\Scalar\String_) {
                        continue;
                    }
                    if ($this->isUuidBytesExpression($item->value)) {
                        $types[$item->key->value] = 'ParameterType';
                    } elseif ($item->value instanceof Node\Expr\Variable
                        && is_string($item->value->name)
                        && ($this->methodUuidArrays[$item->value->name] ?? false)) {
                        $types[$item->key->value] = 'ArrayParameterType';
                    }
                }

                return $types;
            }

            /** @return list<string> */
            private function getUuidSelectColumns(Node\Expr $expression): array
            {
                if (!$expression instanceof Node\Expr\MethodCall
                    || !$expression->name instanceof Node\Identifier
                    || !in_array($expression->name->toString(), ['fetchOne', 'fetchAllAssociative'], true)
                    || !isset($expression->args[0])
                    || !$expression->args[0]->value instanceof Node\Scalar\String_
                    || !preg_match('/\\bSELECT\\s+([\\s\\S]+?)\\s+FROM\\b/i', $expression->args[0]->value->value, $match)) {
                    return [];
                }

                $columns = [];
                $selections = explode(',', $match[1]);
                if ($expression->name->toString() === 'fetchOne') {
                    $selections = array_slice($selections, 0, 1);
                }
                foreach ($selections as $selection) {
                    $selection = preg_replace('/\\s+AS\\s+.+$/i', '', trim($selection)) ?? trim($selection);
                    $parts = preg_split('/\\s*\\.\\s*/', $selection) ?: [];
                    $column = trim((string) end($parts), " `\\\"");
                    if ($column === 'id' || str_ends_with($column, '_id')) {
                        $columns[] = $column;
                    }
                }

                return array_values(array_unique($columns));
            }

            private function addBinaryTypesToQueryCall(Node\Expr\MethodCall $node): void
            {
                if (!isset($node->args[1]) || !$node->args[1]->value instanceof Node\Expr\Array_) {
                    return;
                }

                $binaryTypes = $this->getUuidParameterTypes($node->args[1]->value);
                if ($binaryTypes === []) {
                    return;
                }

                if (!isset($node->args[2])) {
                    $node->args[2] = new Node\Arg(new Node\Expr\Array_());
                } elseif (!$node->args[2]->value instanceof Node\Expr\Array_) {
                    return;
                }

                $existingKeys = [];
                foreach ($node->args[2]->value->items as $item) {
                    if ($item?->key instanceof Node\Scalar\String_) {
                        $existingKeys[] = $item->key->value;
                    }
                }

                foreach (array_diff_key($binaryTypes, array_flip($existingKeys)) as $key => $typeClass) {
                    $node->args[2]->value->items[] = new Node\Expr\ArrayItem(
                        new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified('Doctrine\\DBAL\\' . $typeClass), 'BINARY'),
                        new Node\Scalar\String_($key)
                    );
                    $this->appliedRules['diagnostic_query_uuid_parameters_bind_as_binary'] = ($this->appliedRules['diagnostic_query_uuid_parameters_bind_as_binary'] ?? 0) + 1;
                }
            }

            /** @return list<string> */
            private function getInsertDataKeys(Node\Expr $expression): array
            {
                if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
                    return $this->methodArrayKeys[$expression->name] ?? [];
                }
                if (!$expression instanceof Node\Expr\Array_) {
                    return [];
                }

                $keys = [];
                $nestedKeys = [];
                foreach ($expression->items as $item) {
                    if ($item === null) {
                        continue;
                    }
                    if ($item->key instanceof Node\Scalar\String_) {
                        $keys[] = $item->key->value;
                        continue;
                    }
                    if ($item->value instanceof Node\Expr\Array_) {
                        foreach ($item->value->items as $childItem) {
                            if ($childItem?->key instanceof Node\Scalar\String_) {
                                $nestedKeys[] = $childItem->key->value;
                            }
                        }
                    }
                }

                return array_values(array_unique($keys !== [] ? $keys : $nestedKeys));
            }

            /** @param list<string> $keys */
            private function getBinaryInsertKeys(array $keys): array
            {
                return array_values(array_filter($keys, static fn (string $key): bool => $key === 'id' || str_ends_with($key, '_id')));
            }

            public function __construct(bool $diagnosticMode, string $classFile)
            {
                $this->diagnosticMode = $diagnosticMode;
                $this->classFile = $classFile;
            }

            private function replace(string $rule, string $pattern, string|callable $replacement, string &$sql): void
            {
                $count = 0;
                $converted = is_callable($replacement)
                    ? preg_replace_callback($pattern, $replacement, $sql, -1, $count)
                    : preg_replace($pattern, $replacement, $sql, -1, $count);
                if ($converted === null || $count === 0) {
                    return;
                }

                $sql = $converted;
                $this->appliedRules[$rule] = ($this->appliedRules[$rule] ?? 0) + $count;
            }

            private function splitSqlStatements(string $sql): array
            {
                $statements = [];
                $start = 0;
                $quote = null;
                $length = strlen($sql);

                for ($index = 0; $index < $length; ++$index) {
                    $character = $sql[$index];
                    $next = $sql[$index + 1] ?? '';

                    if ($quote !== null) {
                        if ($character === '\\' && $quote !== '`' && $next !== '') {
                            ++$index;

                            continue;
                        }
                        if ($character === $quote) {
                            if ($next === $quote) {
                                ++$index;

                                continue;
                            }
                            $quote = null;
                        }

                        continue;
                    }
                    if (($character === '-' && $next === '-') || ($character === '/' && $next === '*')) {
                        $endMarker = $character === '-' ? "\n" : '*/';
                        $end = strpos($sql, $endMarker, $index + 2);
                        if ($end === false) {
                            break;
                        }
                        $index = $end + strlen($endMarker) - 1;

                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;

                        continue;
                    }
                    if ($character === ';') {
                        $statements[] = substr($sql, $start, $index - $start + 1);
                        $start = $index + 1;
                    }
                }

                $statements[] = substr($sql, $start);

                return $statements;
            }

            private function rewriteCreateTable(string $sql): string
            {
                if (!preg_match('/^\\s*CREATE\\s+TABLE\\b/i', $sql)) {
                    return $sql;
                }

                $rewritten = '';
                foreach ($this->splitSqlStatements($sql) as $statement) {
                    if (!preg_match('/^\\s*CREATE\\s+TABLE\\b/i', $statement)) {
                        $rewritten .= $statement;

                        continue;
                    }

                    $indexes = [];
                    if ($this->diagnosticMode) {
                        [$statement, $indexes] = $this->extractTableLevelKeys($statement);
                    }

                    if (preg_match('/\\bCOLLATE\\s*=\\s*utf8mb4?_[A-Za-z0-9_]+/i', $statement)) {
                        $this->replace(
                            'create_table_default_collation_to_sw_type_ci',
                            '/(?m)^([ \\t]*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)[ \\t]+(?:VARCHAR|CHAR|TINYTEXT|MEDIUMTEXT|LONGTEXT|TEXT)(?:[ \\t]*\\([0-9]+\\))?)(?![^\\r\\n]*\\bCOLLATE\\b)/i',
                            '$1 COLLATE "sw_type_ci"',
                            $statement
                        );
                    }
                    if (preg_match('/\\bCOLLATE\\s*=\\s*(?!utf8mb4?_)[A-Za-z0-9_]+/i', $statement)) {
                        $this->unsupported[] = 'unsupported MySQL table default collation needs explicit mapping';
                    }

                    $mysqlTableOptions = 'ENGINE\\s*=\\s*[A-Za-z0-9_]+|DEFAULT\\s+CHARSET\\s*=\\s*[A-Za-z0-9_]+|DEFAULT\\s+CHARACTER\\s+SET\\s*=?\\s*[A-Za-z0-9_]+|CHARSET\\s*=\\s*[A-Za-z0-9_]+|COLLATE\\s*=\\s*utf8mb4?_[A-Za-z0-9_]+';
                    if ($this->diagnosticMode) {
                        $mysqlTableOptions .= '|AUTO_INCREMENT\\s*=\\s*[0-9]+';
                    }
                    $this->replace(
                        'create_table_mysql_options_removed',
                        '/(?:\\s+(?:' . $mysqlTableOptions . '))+(?=\\s*(?:;|$))/i',
                        '',
                        $statement
                    );
                    $this->replace(
                        'create_table_binary16_to_bytea_with_length_check',
                        '/(?m)^([ \\t]*)(?:"(?<double>[^"\\r\\n]+)"|`(?<tick>[^`\\r\\n]+)`|(?<plain>[A-Za-z_][A-Za-z0-9_$]*))([ \\t]+)BINARY\\s*\\(\\s*16\\s*\\)/i',
                        static function (array $matches): string {
                            $identifier = $matches['double'] !== '' ? '"' . $matches['double'] . '"' : ($matches['tick'] !== '' ? '"' . $matches['tick'] . '"' : $matches['plain']);

                            return $matches[1] . $identifier . $matches[5] . 'BYTEA CHECK (octet_length(' . $identifier . ') = 16)';
                        },
                        $statement
                    );
                    $this->replace('create_table_tinyint_to_smallint', '/\\bTINYINT(?:\\s*\\(\\s*\\d+\\s*\\))?(?![A-Za-z0-9_])/i', 'SMALLINT', $statement);
                    $this->replace(
                        'create_table_datetime_to_timestamp_without_time_zone',
                        '/\\bDATETIME(?:\\s*\\(\\s*(\\d+)\\s*\\))?/i',
                        static fn (array $matches): string => 'TIMESTAMP' . (($matches[1] ?? '') === '' ? '' : '(' . $matches[1] . ')') . ' WITHOUT TIME ZONE',
                        $statement
                    );
                    $this->replace(
                        'create_table_utf8mb4_collation_to_sw_type_ci',
                        '/\\bCOLLATE\\s+[" ]?utf8mb4?_[A-Za-z0-9_]+[" ]?/i',
                        'COLLATE sw_type_ci',
                        $statement
                    );

                    if ($indexes !== [] && !preg_match('/;\s*$/', $statement)) {
                        $statement = rtrim($statement) . ';';
                    }

                    $rewritten .= $statement;
                    foreach ($indexes as [$name, $table, $columns]) {
                        $rewritten .= "\nCREATE INDEX " . $name . ' ON ' . $table . ' (' . $columns . ');';
                        $this->appliedRules['create_table_key_to_create_index'] = ($this->appliedRules['create_table_key_to_create_index'] ?? 0) + 1;
                    }
                }

                return $rewritten;
            }

            /** @return array{string, list<array{string, string, string}>} */
            private function extractTableLevelKeys(string $statement): array
            {
                if (!preg_match('/^\\s*CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?("[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\\s*\\(/i', $statement, $tableMatch, PREG_OFFSET_CAPTURE)) {
                    return [$statement, []];
                }

                $table = $tableMatch[1][0];
                $open = strpos($statement, '(', $tableMatch[0][1]);
                $depth = 0;
                $quote = null;
                $close = null;
                for ($index = $open; $index < strlen($statement); ++$index) {
                    $character = $statement[$index];
                    if ($quote !== null) {
                        if ($character === $quote) {
                            if (($statement[$index + 1] ?? '') === $quote) { ++$index; continue; }
                            $quote = null;
                        }
                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') { $quote = $character; continue; }
                    if ($character === '(') { ++$depth; continue; }
                    if ($character === ')' && --$depth === 0) { $close = $index; break; }
                }
                if ($close === null) { return [$statement, []]; }

                $body = substr($statement, $open + 1, $close - $open - 1);
                $clauses = [];
                $start = 0;
                $depth = 0;
                $quote = null;
                for ($index = 0; $index < strlen($body); ++$index) {
                    $character = $body[$index];
                    if ($quote !== null) {
                        if ($character === $quote) {
                            if (($body[$index + 1] ?? '') === $quote) { ++$index; continue; }
                            $quote = null;
                        }
                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') { $quote = $character; continue; }
                    if ($character === '(') { ++$depth; continue; }
                    if ($character === ')') { --$depth; continue; }
                    if ($character === ',' && $depth === 0) { $clauses[] = substr($body, $start, $index - $start); $start = $index + 1; }
                }
                $clauses[] = substr($body, $start);
                $indexes = [];
                $kept = [];
                $changed = false;
                foreach ($clauses as $clause) {
                    if (preg_match('/^\\s*UNIQUE\\s+(?:(?:KEY|INDEX)\\s+)?("[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\\s*\\((.*)\\)\\s*$/is', $clause, $uniqueMatch)) {
                        $columns = trim($uniqueMatch[2]);
                        if (preg_match('/^(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)(?:\\s*,\\s*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*))*$/', $columns)) {
                            $kept[] = 'CONSTRAINT ' . $uniqueMatch[1] . ' UNIQUE (' . $columns . ')';
                            $this->appliedRules['create_table_unique_key_to_constraint'] = ($this->appliedRules['create_table_unique_key_to_constraint'] ?? 0) + 1;
                            $changed = true;
                        } else {
                            $this->unsupported[] = 'table-level UNIQUE KEY has unsupported column expression';
                            $kept[] = $clause;
                        }
                        continue;
                    }
                    if (!preg_match('/^\\s*KEY\\s+("[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\\s*\\((.*)\\)\\s*$/is', $clause, $match)) {
                        $kept[] = $clause;
                        continue;
                    }
                    $columns = trim($match[2]);
                    if (!preg_match('/^(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)(?:\\s*\\(\\s*\\d+\\s*\\))?(?:\\s*,\\s*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)(?:\\s*\\(\\s*\\d+\\s*\\))?)*$/', $columns)) {
                        $this->unsupported[] = 'table-level KEY has unsupported column expression';
                        $kept[] = $clause;
                        continue;
                    }
                    $indexes[] = [$match[1], $table, $columns];
                    $changed = true;
                }
                if (!$changed) { return [$statement, []]; }
                return [substr($statement, 0, $open + 1) . implode(',', $kept) . substr($statement, $close), $indexes];
            }

            private function rewriteSingleDropForeignKey(string $sql): string
            {
                $rewritten = '';
                foreach ($this->splitSqlStatements($sql) as $statement) {
                    $this->replace(
                        'alter_drop_foreign_key_to_drop_constraint',
                        '/^(\\s*ALTER\\s+TABLE\\s+)(`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)(\\s+DROP\\s+)FOREIGN\\s+KEY(\\s+)(`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)(\\s*;?\\s*)$/i',
                        static function (array $matches): string {
                            return $matches[1] . $matches[2] . $matches[3] . 'CONSTRAINT' . $matches[4] . $matches[5] . $matches[6];
                        },
                        $statement
                    );
                    $rewritten .= $statement;
                }

                return $rewritten;
            }

            private function rewriteAlterTable(string $sql): string
            {
                $rewritten = '';
                foreach ($this->splitSqlStatements($sql) as $statement) {
                    if (!preg_match('/^\s*ALTER\s+TABLE\b/i', $statement)) {
                        $rewritten .= $statement;

                        continue;
                    }

                    $identifier = '(?:"[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                    $this->replace(
                        'alter_add_unique_to_constraint',
                        '/\bADD\s+UNIQUE\s+(?:(?:KEY|INDEX)\s+)?' . $identifier . '\s*\(\s*' . $identifier . '(?:\s*,\s*' . $identifier . ')*\s*\)/i',
                        static function (array $matches) use ($identifier): string {
                            preg_match('/\bADD\s+UNIQUE\s+(?:(?:KEY|INDEX)\s+)?(' . $identifier . ')\s*\((.*)\)/i', $matches[0], $parts);

                            return 'ADD CONSTRAINT ' . $parts[1] . ' UNIQUE (' . $parts[2] . ')';
                        },
                        $statement
                    );
                    $this->replace(
                        'alter_modify_binary16_to_bytea_with_length_check',
                        '/\bMODIFY(?:\s+COLUMN)?\s+(' . $identifier . ')\s+BINARY\s*\(\s*16\s*\)\s+(NULL|NOT\s+NULL)(?:\s+AFTER\s+' . $identifier . ')?(?=\s*(?:;|$))/i',
                        static function (array $matches): string {
                            $column = str_starts_with($matches[1], '`') ? '"' . trim($matches[1], '`') . '"' : $matches[1];
                            $nullability = strtoupper(preg_replace('/\s+/', ' ', $matches[2]) ?? $matches[2]) === 'NULL' ? 'DROP NOT NULL' : 'SET NOT NULL';

                            return 'ALTER COLUMN ' . $column . ' TYPE BYTEA, ALTER COLUMN ' . $column . ' ' . $nullability . ', ADD CHECK (octet_length(' . $column . ') = 16)';
                        },
                        $statement
                    );

                    $this->replace(
                        'alter_binary16_to_bytea_with_length_check',
                        '/\b(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?)\s+' . $identifier . '\s+BINARY\s*\(\s*16\s*\)/i',
                        static function (array $matches): string {
                            preg_match('/(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?)\s+("[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]+)/i', $matches[0], $columnMatch);
                            $column = str_starts_with($columnMatch[1], '`') ? '"' . trim($columnMatch[1], '`') . '"' : $columnMatch[1];

                            return preg_replace('/\bBINARY\s*\(\s*16\s*\)$/i', 'BYTEA CHECK (octet_length(' . $column . ') = 16)', $matches[0]) ?? $matches[0];
                        },
                        $statement
                    );
                    $this->replace(
                        'alter_binary16_to_bytea_with_length_check',
                        '/\bCHANGE(?:\s+COLUMN)?\s+' . $identifier . '\s+' . $identifier . '\s+BINARY\s*\(\s*16\s*\)/i',
                        static function (array $matches): string {
                            preg_match('/\bCHANGE(?:\s+COLUMN)?\s+(?:"[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]+)\s+("[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]+)/i', $matches[0], $columnMatch);
                            $column = str_starts_with($columnMatch[1], '`') ? '"' . trim($columnMatch[1], '`') . '"' : $columnMatch[1];

                            return preg_replace('/\bBINARY\s*\(\s*16\s*\)$/i', 'BYTEA CHECK (octet_length(' . $column . ') = 16)', $matches[0]) ?? $matches[0];
                        },
                        $statement
                    );
                    $this->replace(
                        'alter_datetime_to_timestamp_without_time_zone',
                        '/(\b(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?|CHANGE(?:\s+COLUMN)?)\s+' . $identifier . '(?:\s+' . $identifier . ')?\s+)DATETIME(?:\s*\(\s*(\d+)\s*\))?/i',
                        static fn (array $matches): string => $matches[1] . 'TIMESTAMP' . (($matches[2] ?? '') === '' ? '' : '(' . $matches[2] . ')') . ' WITHOUT TIME ZONE',
                        $statement
                    );
                    $this->replace(
                        'alter_double_to_double_precision',
                        '/(\b(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?|CHANGE(?:\s+COLUMN)?)\s+' . $identifier . '(?:\s+' . $identifier . ')?\s+)DOUBLE(?!\s+PRECISION)(?=\s+(?:NULL|NOT\s+NULL|DEFAULT|UNSIGNED|COLLATE|COMMENT|AFTER)\b|\s*[,;]|\s*$)/i',
                        '$1DOUBLE PRECISION',
                        $statement
                    );
                    $this->replace('alter_tinyint_to_smallint', '/\bTINYINT(?:\s*\(\s*\d+\s*\))?(?![A-Za-z0-9_])/i', 'SMALLINT', $statement);
                    // Only match types immediately following an ALTER column operation.
                    // Keeping the operation and column identifiers in the pattern avoids
                    // rewriting the same words in literals, functions, or expressions.
                    $this->replace(
                        'alter_text_type_to_postgresql_type',
                        '/(\b(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?|CHANGE(?:\s+COLUMN)?)\s+' . $identifier . '(?:\s+' . $identifier . ')?\s+)(?:TINYTEXT|MEDIUMTEXT|LONGTEXT|TEXT)(?=\s+(?:NULL|NOT\s+NULL|DEFAULT|COLLATE|COMMENT|AFTER)\b|\s*[,;]|\s*$)/i',
                        static fn (array $matches): string => $matches[1] . 'TEXT',
                        $statement
                    );
                    $this->replace(
                        'alter_blob_type_to_bytea',
                        '/(\b(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?|CHANGE(?:\s+COLUMN)?)\s+' . $identifier . '(?:\s+' . $identifier . ')?\s+)(?:TINYBLOB|MEDIUMBLOB|LONGBLOB|BLOB)(?=\s+(?:NULL|NOT\s+NULL|DEFAULT|COLLATE|COMMENT|AFTER)\b|\s*[,;]|\s*$)/i',
                        static fn (array $matches): string => $matches[1] . 'BYTEA',
                        $statement
                    );
                    // Match collation only after an ALTER column operation and a textual type.
                    $this->replace(
                        'alter_column_utf8_collation_to_sw_type_ci',
                        '/(\b(?:ADD(?:\s+COLUMN)?|MODIFY(?:\s+COLUMN)?|CHANGE(?:\s+COLUMN)?)\s+' . $identifier . '(?:\s+' . $identifier . ')?\s+(?:VARCHAR\s*\(\s*\d+\s*\)|CHAR(?:ACTER)?(?:\s+VARYING)?\s*\(\s*\d+\s*\)|TEXT)\s+COLLATE\s+)["\'`]?utf8mb4?_[A-Za-z0-9_]+["\'`]?/i',
                        '$1sw_type_ci',
                        $statement
                    );
                    $this->replace('alter_unsigned_removed', '/\s+UNSIGNED\b/i', '', $statement);
                    $this->replace('alter_after_column_placement_removed', '/\s+AFTER\s+(?:"[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*)/i', '', $statement);
                    // PostgreSQL uses ALTER COLUMN TYPE and separate clauses for
                    // nullability/defaults. Keep this deliberately limited to a
                    // single, simple MODIFY definition; more complex MySQL forms
                    // stay in the inventory as unsupported syntax.
                    $this->replace(
                        'alter_modify_column_to_alter_column',
                        '/\bMODIFY(?:\s+COLUMN)?\s+("[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\s+(VARCHAR\s*\(\s*\d+\s*\)|CHARACTER\s+VARYING\s*\(\s*\d+\s*\)|DOUBLE(?:\s+PRECISION)?|FLOAT|INTEGER|INT|SMALLINT|BIGINT|BYTEA|DECIMAL\s*\(\s*\d+\s*,\s*\d+\s*\))\s+(NULL|NOT\s+NULL)(?:\s+DEFAULT\s+(NULL|[-+]?[0-9]+(?:\.[0-9]+)?|\'[^\']*(?:\'\'[^\']*)*\'))?(?:\s+AFTER\s+(?:"[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*))?(?=\s*(?:,|;|$))/i',
                        static function (array $matches): string {
                            $column = str_starts_with($matches[1], '`') ? '"' . trim($matches[1], '`') . '"' : $matches[1];
                            $type = preg_replace('/^INT$/i', 'INTEGER', $matches[2]) ?? $matches[2];
                            $type = preg_replace('/^DOUBLE$/i', 'DOUBLE PRECISION', $type) ?? $type;
                            $clauses = ['ALTER COLUMN ' . $column . ' TYPE ' . $type];
                            $clauses[] = strtoupper(preg_replace('/\s+/', ' ', $matches[3]) ?? $matches[3]) === 'NULL'
                                ? 'ALTER COLUMN ' . $column . ' DROP NOT NULL'
                                : 'ALTER COLUMN ' . $column . ' SET NOT NULL';
                            if (($matches[4] ?? '') !== '') {
                                $clauses[] = strtoupper($matches[4]) === 'NULL'
                                    ? 'ALTER COLUMN ' . $column . ' DROP DEFAULT'
                                    : 'ALTER COLUMN ' . $column . ' SET DEFAULT ' . $matches[4];
                            }

                            return implode(', ', $clauses);
                        },
                        $statement
                    );
                    // CHANGE can also be used as a MySQL spelling of MODIFY when
                    // the old and new column names are identical. Keep renames
                    // untouched because they need separate PostgreSQL handling.
                    $this->replace(
                        'alter_change_same_column_to_alter_column',
                        '/\\bCHANGE(?:\\s+COLUMN)?\\s+(`[^`\\r\\n]+`|"[^"\\r\\n]+"|[A-Za-z_][A-Za-z0-9_$]*)\\s+(`[^`\\r\\n]+`|"[^"\\r\\n]+"|[A-Za-z_][A-Za-z0-9_$]*)\\s+(VARCHAR\\s*\\(\\s*\\d+\\s*\\)|CHARACTER\\s+VARYING\\s*\\(\\s*\\d+\\s*\\)|DOUBLE(?:\\s+PRECISION)?|FLOAT|INTEGER|INT|SMALLINT|BIGINT|BYTEA|DECIMAL\\s*\\(\\s*\\d+\\s*,\\s*\\d+\\s*\\))\\s+(NULL|NOT\\s+NULL)(?:\\s+DEFAULT\\s+(NULL|[-+]?[0-9]+(?:\\.[0-9]+)?|\'[^\']*(?:\'\'[^\']*)*\'))?(?:\\s+AFTER\\s+(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*))?(?=\\s*(?:,|;|$))/i',
                        static function (array $matches): string {
                            $oldColumn = trim($matches[1], '`"');
                            $newColumn = trim($matches[2], '`"');
                            if ($oldColumn !== $newColumn) {
                                return $matches[0];
                            }

                            $column = str_starts_with($matches[2], '`') ? '"' . $newColumn . '"' : $matches[2];
                            $type = preg_replace('/^INT$/i', 'INTEGER', $matches[3]) ?? $matches[3];
                            $type = preg_replace('/^DOUBLE$/i', 'DOUBLE PRECISION', $type) ?? $type;
                            $clauses = ['ALTER COLUMN ' . $column . ' TYPE ' . $type];
                            $clauses[] = strtoupper(preg_replace('/\\s+/', ' ', $matches[4]) ?? $matches[4]) === 'NULL'
                                ? 'ALTER COLUMN ' . $column . ' DROP NOT NULL'
                                : 'ALTER COLUMN ' . $column . ' SET NOT NULL';
                            if (($matches[5] ?? '') !== '') {
                                $clauses[] = strtoupper($matches[5]) === 'NULL'
                                    ? 'ALTER COLUMN ' . $column . ' DROP DEFAULT'
                                    : 'ALTER COLUMN ' . $column . ' SET DEFAULT ' . $matches[5];
                            }

                            return implode(', ', $clauses);
                        },
                        $statement
                    );

                    $rewritten .= $statement;
                }

                return $rewritten;
            }

            private function hasMultiActionDropForeignKey(string $sql): bool
            {
                foreach ($this->splitSqlStatements($sql) as $statement) {
                    $visibleSql = '';
                    $quote = null;
                    $length = strlen($statement);

                    for ($index = 0; $index < $length; ++$index) {
                        $character = $statement[$index];
                        $next = $statement[$index + 1] ?? '';
                        if ($quote !== null) {
                            $visibleSql .= ' ';
                            if ($character === '\\' && $quote !== '`' && $next !== '') {
                                $visibleSql .= ' ';
                                ++$index;

                                continue;
                            }
                            if ($character === $quote) {
                                if ($next === $quote) {
                                    $visibleSql .= ' ';
                                    ++$index;

                                    continue;
                                }
                                $quote = null;
                            }

                            continue;
                        }
                        if (($character === '-' && $next === '-') || ($character === '/' && $next === '*')) {
                            $endMarker = $character === '-' ? "\n" : '*/';
                            $end = strpos($statement, $endMarker, $index + 2);
                            if ($end === false) {
                                break;
                            }
                            $visibleSql .= str_repeat(' ', $end + strlen($endMarker) - $index);
                            $index = $end + strlen($endMarker) - 1;

                            continue;
                        }
                        if ($character === "'" || $character === '"' || $character === '`') {
                            $quote = $character;
                            $visibleSql .= ' ';

                            continue;
                        }
                        $visibleSql .= $character;
                    }

                    if (preg_match('/\\bALTER\\s+TABLE\\b[\\s\\S]*?\\bDROP\\s+FOREIGN\\s+KEY\\b[^;]*,\\s*DROP\\s+FOREIGN\\s+KEY\\b/i', $visibleSql)) {
                        return true;
                    }
                }

                return false;
            }

            public function enterNode(Node $node): ?Node
            {
                if ($this->diagnosticMode && $node instanceof Node\Stmt\ClassMethod) {
                    $this->methodArrayKeys = [];
                    $this->methodUuidVariables = [];
                    $this->methodUuidRowColumns = [];
                    $this->methodUuidArrays = [];
                }
                if ($this->diagnosticMode && $node instanceof Node\Expr\Assign
                    && $node->var instanceof Node\Expr\Variable
                    && is_string($node->var->name)) {
                    $this->methodArrayKeys[$node->var->name] = $this->getInsertDataKeys($node->expr);
                    $uuidColumns = $this->getUuidSelectColumns($node->expr);
                    $this->methodUuidVariables[$node->var->name] = $this->isUuidBytesExpression($node->expr)
                        || ($node->expr instanceof Node\Expr\MethodCall && $node->expr->name instanceof Node\Identifier
                            && $node->expr->name->toString() === 'fetchOne' && isset($uuidColumns[0]));
                    $this->methodUuidRowColumns[$node->var->name] = $node->expr instanceof Node\Expr\MethodCall
                        && $node->expr->name instanceof Node\Identifier && $node->expr->name->toString() === 'fetchAllAssociative'
                        ? $uuidColumns
                        : [];
                    $this->methodUuidArrays[$node->var->name] = false;
                    if ($node->expr instanceof Node\Expr\FuncCall && $node->expr->name instanceof Node\Name
                        && strtolower($node->expr->name->toString()) === 'array_column'
                        && isset($node->expr->args[0], $node->expr->args[1])
                        && $node->expr->args[0]->value instanceof Node\Expr\Variable
                        && is_string($node->expr->args[0]->value->name)
                        && $node->expr->args[1]->value instanceof Node\Scalar\String_) {
                        $sourceName = $node->expr->args[0]->value->name;
                        $column = $node->expr->args[1]->value->value;
                        $this->methodUuidArrays[$node->var->name] = in_array($column, $this->methodUuidRowColumns[$sourceName] ?? [], true);
                    }
                }
                if ($this->diagnosticMode && $node instanceof Node\Stmt\Foreach_
                    && $node->valueVar instanceof Node\Expr\Variable
                    && is_string($node->valueVar->name)) {
                    $this->methodArrayKeys[$node->valueVar->name] = $this->getInsertDataKeys($node->expr);
                    if ($node->expr instanceof Node\Expr\Variable && is_string($node->expr->name)) {
                        $this->methodUuidRowColumns[$node->valueVar->name] = $this->methodUuidRowColumns[$node->expr->name] ?? [];
                    }
                }
                if ($this->diagnosticMode && $node instanceof Node\Expr\MethodCall
                    && $node->var instanceof Node\Expr\Variable
                    && $node->var->name === 'connection'
                    && $node->name instanceof Node\Identifier) {
                    $methodName = $node->name->toString();
                    if ($methodName === 'insert' && isset($node->args[1]) && !isset($node->args[2])) {
                        $keys = $this->getBinaryInsertKeys($this->getInsertDataKeys($node->args[1]->value));
                        if ($keys !== []) {
                            $typeItems = [];
                            foreach ($keys as $key) {
                                $typeItems[] = new Node\Expr\ArrayItem(
                                    new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified('Doctrine\\DBAL\\ParameterType'), 'BINARY'),
                                    new Node\Scalar\String_($key)
                                );
                            }
                            $node->args[] = new Node\Arg(new Node\Expr\Array_($typeItems));
                            $this->appliedRules['diagnostic_insert_uuid_columns_bind_as_binary'] = ($this->appliedRules['diagnostic_insert_uuid_columns_bind_as_binary'] ?? 0) + count($keys);
                        }
                    }
                    if (in_array($methodName, ['executeQuery', 'executeStatement', 'fetchAllAssociative', 'fetchAssociative', 'fetchOne', 'fetchFirstColumn'], true)) {
                        $this->addBinaryTypesToQueryCall($node);
                    }
                }
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1576590301FixSalesChannelDomainLanguageFk.php'
                    && $node instanceof Node\Stmt\Expression
                    && $node->expr instanceof Node\Expr\MethodCall
                    && $node->expr->name instanceof Node\Identifier
                    && $node->expr->name->toString() === 'executeStatement'
                    && isset($node->expr->args[0])
                    && $node->expr->args[0]->value instanceof Node\Scalar\String_
                    && preg_match('/^\s*ALTER\s+TABLE\s+`sales_channel_domain`\s+DROP\s+INDEX\s+`fk\.sales_channel_domain\.language_id`\s*;?\s*$/i', $node->expr->args[0]->value->value)) {
                    $this->appliedRules['redundant_mysql_implicit_foreign_key_index_drop_removed'] = 1;

                    return new Node\Stmt\Nop();
                }
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

                $originalSql = $node->value;
                $sql = $this->rewriteCreateTable($originalSql);
                if ($this->diagnosticMode) {
                    $sql = $this->rewriteAlterTable($sql);
                }
                $sql = $this->rewriteSingleDropForeignKey($sql);
                if ($this->diagnosticMode) {
                    $this->replace(
                        'check_json_valid_to_native_json_validation',
                        '/\bCHECK\s*\(\s*JSON_VALID\s*\(\s*(?:"[^"\r\n]+"|`[^`\r\n]+`)\s*\)\s*\)/i',
                        'CHECK (TRUE)',
                        $sql
                    );
                    $this->replace(
                        'json_config_like_cast_to_text',
                        '/(?<![A-Za-z0-9_])(?:"config"|`config`|config)(?![A-Za-z0-9_])\\s+LIKE\\s+(\'(?:\'\'|[^\'])*\')/i',
                        static fn (array $matches): string => 'CAST("config" AS TEXT) LIKE ' . $matches[1],
                        $sql
                    );
                }
                $this->replace(
                    'comparison_double_quoted_literal_to_single_quoted',
                    '/(\s*(?:=|<>|!=|<=|>=|<|>)\s*)"((?:[^"]|"")*)"/',
                    static fn (array $match): string => $match[1] . "'" . str_replace("'", "''", str_replace('""', '"', $match[2])) . "'",
                    $sql
                );
                if ($this->diagnosticMode) {
                    $this->replace(
                        'update_target_column_qualification_removed',
                        '/(\\bUPDATE\\s+("[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\\s+SET\\s+)([\\s\\S]*?)(?=\\s+WHERE\\b|;?\\s*$)/i',
                        static function (array $matches): string {
                            $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                            $table = preg_quote($matches[2], '/');
                            $assignments = preg_replace('/(^|,\\s*)' . $table . '\\s*\\.\\s*(' . $identifier . ')\\s*=/i', '$1$2 =', $matches[3]) ?? $matches[3];

                            return $matches[1] . $assignments;
                        },
                        $sql
                    );
                    $this->replace(
                        'mysql_order_currency_multi_table_update_to_postgresql_update_from',
                        '/^\s*UPDATE\s+("order"|`order`|order)\s*,\s*("currency"|`currency`|currency)\s+SET\s+(.+?)\s+WHERE\s+(.+?)\s*;?\s*$/is',
                        static function (array $matches): string {
                            if (!preg_match('/^(?:"order"|`order`|order)\s*\.\s*["`]?currency_id["`]?\s*=\s*(?:"currency"|`currency`|currency)\s*\.\s*["`]?id["`]?$/i', trim($matches[4]))) {
                                return $matches[0];
                            }

                            $assignments = preg_split('/\s*,\s*(?=(?:"order"|`order`|order)\s*\.)/i', trim($matches[3])) ?: [];
                            if ($assignments === []) {
                                return $matches[0];
                            }

                            $translatedAssignments = [];
                            foreach ($assignments as $assignment) {
                                if (!preg_match('/^(?:"order"|`order`|order)\s*\.\s*["`]?([A-Za-z_][A-Za-z0-9_$]*)["`]?\s*=\s*(?:"currency"|`currency`|currency)\s*\.\s*["`]?([A-Za-z_][A-Za-z0-9_$]*)["`]?$/i', trim($assignment), $columns)) {
                                    return $matches[0];
                                }

                                $translatedAssignments[] = '"' . $columns[1] . '" = "currency"."' . $columns[2] . '"';
                            }

                            return 'UPDATE "order" SET ' . implode(', ', $translatedAssignments) . ' FROM "currency" WHERE "order"."currency_id" = "currency"."id"';
                        },
                        $sql
                    );
                }
                if ($this->hasMultiActionDropForeignKey($sql)) {
                    $this->unsupported[] = 'multi-action ALTER TABLE DROP FOREIGN KEY grouping is retained; splitting changes statement atomicity';
                }
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
                    'mysql text/blob type' => '/\\b(?:TINYTEXT|MEDIUMTEXT|LONGTEXT|TINYBLOB|MEDIUMBLOB|LONGBLOB|BLOB)\\b/i',
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
                $converted = preg_replace('/\\bIFNULL\\s*\\(/i', 'COALESCE(', $converted);
                $converted = preg_replace('/\\bCREATE\\s+TABLE\\s+(?!IF\\s+NOT\\s+EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $converted);
                if ($converted !== $sql || $sql !== $originalSql) {
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
            $statement->name = new Node\Name($implementationNamespace);
        }
        if ($diagnosticMode && preg_match('/\\b__DIR__\\b/', $source)) {
            $dirTraverser = new NodeTraverser();
            $dirTraverser->addVisitor(new class($version) extends NodeVisitorAbstract {
                public function __construct(private readonly string $version)
                {
                }

                public function enterNode(Node $node): ?Node
                {
                    if (!$node instanceof Node\Scalar\MagicConst\Dir) {
                        return null;
                    }

                    return new Node\Expr\BinaryOp\Concat(
                        new Node\Expr\FuncCall(new Node\Name('dirname'), [
                            new Node\Arg(new Node\Scalar\MagicConst\File()),
                            new Node\Arg(new Node\Scalar\LNumber(2)),
                        ]),
                        new Node\Scalar\String_('/' . $this->version)
                    );
                }
            });
            $translated = $dirTraverser->traverse($translated);
            $entry['source_dir_references_rebased'] = true;
        }
        $entry['translated_sql_literals'] = $visitor->translated;
        $entry['static_sql_literals'] = $visitor->sqlLiterals;
        $entry['applied_rules'] = $visitor->appliedRules;
        $entry['unsupported_shapes'] = array_values(array_unique($visitor->unsupported));
        $entry['logical_class'] = $logicalClass;
        $entry['proposed_implementation'] = $implementationNamespace . '\\' . $className;
        $entry['proposed_timestamp'] = $timestamp;
        $entry['registry_entry_proposal'] = 'Core' . $registryAliasSuffix . '\\' . $className . '::class, ' . $timestamp . ', PostgreSQL' . $registryAliasSuffix . '\\' . $className . '::class';
        if ($namespaceCount !== 1) {
            $entry['status'] = 'rejected_namespace_shape';
            $entry['blocker_category'] = 'PHP namespace shape';
            $entry['unsupported_shapes'][] = 'expected exactly one namespace declaration';
        } elseif ($diagnosticMode) {
            $entry['status'] = $entry['unsupported_shapes'] === [] ? 'diagnostic_emitted' : 'diagnostic_emitted_with_unhandled_shapes';
            $entry['blocker_category'] = $entry['unsupported_shapes'] === [] ? null : 'diagnostic output retains unhandled SQL/runtime shapes';
            $entry['unhandled_semantics'] = 'Diagnostic best effort only. SQLGlot is unavailable in this environment; PostgreSQL syntax and semantics remain unverified.';
            $target = $outputDirectory . '/' . $name;
            if (is_file($target)) {
                $entry['status'] = 'rejected_output_collision';
                $entry['blocker_category'] = 'diagnostic output collision';
                $entry['unsupported_shapes'][] = 'refusing to overwrite an existing PostgreSQL migration file';
            } else {
                $diagnosticOutput = $printer->prettyPrintFile($translated);
                $diagnosticOutput = preg_replace(
                    '/^<\\?php\\b/',
                    "<?php\n\n// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.",
                    $diagnosticOutput,
                    1
                ) ?? $diagnosticOutput;
                if (file_put_contents($target, $diagnosticOutput . "\n") === false) {
                $entry['status'] = 'rejected_write_error';
                $entry['blocker_category'] = 'diagnostic output write error';
                } else {
                    $entry['output'] = $target;
                }
            }
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
    $registryManifest = [];
    foreach ($report as $entry) {
        if (isset($entry['implementation'], $entry['logical_class'], $entry['creation_timestamp'])) {
            $registryManifest[] = [
                'logical_class' => $entry['logical_class'],
                'timestamp' => $entry['creation_timestamp'],
                'implementation' => $entry['implementation'],
            ];
            continue;
        }
        if (isset($entry['proposed_implementation'], $entry['logical_class'], $entry['proposed_timestamp'])
            && str_starts_with($entry['status'], 'diagnostic_emitted')) {
            $registryManifest[] = [
                'logical_class' => $entry['logical_class'],
                'timestamp' => $entry['proposed_timestamp'],
                'implementation' => $entry['proposed_implementation'],
            ];
        }
    }
    if ($diagnosticMode) {
        $manifestPath = $outputDirectory . '/registry-manifest.json';
        file_put_contents($manifestPath, json_encode($registryManifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        $registryPatchLines = [];
        foreach ($report as $entry) {
            if (!isset($entry['logical_class'], $entry['proposed_implementation'], $entry['proposed_timestamp'])
                || !str_starts_with($entry['status'], 'diagnostic_emitted')) {
                continue;
            }

            $registryPatchLines[] = '    [' . $entry['logical_class'] . '::class, ' . $entry['proposed_timestamp'] . ', ' . $entry['proposed_implementation'] . '::class],';
        }
        $registryEntries = "<?php declare(strict_types=1);\n\nreturn [\n" . implode("\n", $registryPatchLines) . "\n];\n";
        file_put_contents($outputDirectory . '/registry-entries.php', $registryEntries);
    }
    echo json_encode([
        'mode' => $diagnosticMode ? 'diagnostic' : 'fail_closed',
        'source_directory' => $sourceDirectory,
        'variant_directory' => $variantDirectory,
        'count' => count($report),
        'sql_transpiler' => 'PHP AST literal substitutions; SQLGlot not invoked',
        'registry_manifest_count' => count($registryManifest),
        'registry_manifest' => $registryManifest,
        'summary' => array_count_values(array_column($report, 'status')),
        'blocker_summary' => array_count_values(array_filter(array_column($report, 'blocker_category'))),
        'classes' => $report,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    if ($diagnosticMode) {
        $completedDiagnosticStatuses = ['registered_variant', 'registered_portable_mapping', 'diagnostic_variant_unhandled', 'diagnostic_emitted', 'diagnostic_emitted_with_unhandled_shapes'];

        exit(array_filter($report, static fn (array $entry): bool => !\in_array($entry['status'], $completedDiagnosticStatuses, true)) === [] ? 0 : 4);
    }

    exit(array_filter($report, static fn (array $entry): bool => !str_starts_with($entry['status'], 'registered_') && $entry['status'] !== 'basic_sql_rewrite_candidate') === [] ? 0 : 3);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php dev-tools/postgres-migration-translator.php --inventory|--diagnostic-inventory <source-dir> <existing-variant-dir> <existing-output-dir>\n");
    exit(2);
}

fwrite(STDERR, "Single-file mode is unavailable; use --inventory to guarantee complete reports.\n");
exit(2);

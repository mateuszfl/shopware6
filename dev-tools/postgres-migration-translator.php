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
            private ?string $currentMethodName = null;
            /** @var array<string, list<string>> */
            private array $methodArrayKeys = [];
            /** @var array<string, list<string>> */
            private array $methodPropertyArrayKeys = [];
            /** @var array<string, bool> */
            private array $methodUuidVariables = [];
            /** @var array<string, list<string>> */
            private array $methodUuidRowColumns = [];
            /** @var array<string, bool> */
            private array $methodUuidArrays = [];
            private const SQL = '/\\b(SELECT|SHOW|INSERT|REPLACE|UPDATE|DELETE|CREATE|ALTER|DROP|RENAME)\\b/i';

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

            private function rewriteReplaceIntoSet(string $sql): string
            {
                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                if (!preg_match('/^\\s*REPLACE\\s+INTO\\s+' . $identifier . '\\s+SET\\b/i', $sql)) {
                    return $sql;
                }
                if (!preg_match('/^\\s*REPLACE\\s+INTO\\s+(' . $identifier . ')\\s+SET\\s+([\\s\\S]*?)\\s*(;?)\\s*$/i', $sql, $matches)) {
                    $this->unsupported[] = 'REPLACE INTO SET has unsupported target or suffix syntax';

                    return $sql;
                }

                $assignments = [];
                $start = 0;
                $depth = 0;
                $quote = null;
                $value = $matches[2];
                for ($index = 0; $index < strlen($value); ++$index) {
                    $character = $value[$index];
                    $next = $value[$index + 1] ?? '';
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
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;

                        continue;
                    }
                    if ($character === '(') {
                        ++$depth;

                        continue;
                    }
                    if ($character === ')') {
                        --$depth;

                        continue;
                    }
                    if ($character === ',' && $depth === 0) {
                        $assignments[] = substr($value, $start, $index - $start);
                        $start = $index + 1;
                    }
                }
                $assignments[] = substr($value, $start);

                $columns = [];
                $values = [];
                foreach ($assignments as $assignment) {
                    if (!preg_match('/^\\s*(' . $identifier . ')\\s*=\\s*([\\s\\S]*?)\\s*$/', $assignment, $parts) || $parts[2] === '') {
                        $this->unsupported[] = 'REPLACE INTO SET contains an unsupported assignment';

                        return $sql;
                    }
                    $columns[] = $parts[1];
                    $values[] = $parts[2];
                }

                $this->unsupported[] = 'REPLACE INTO SET translated as INSERT; duplicate-key replacement semantics are not represented';
                $this->appliedRules['diagnostic_replace_into_set_to_insert'] = ($this->appliedRules['diagnostic_replace_into_set_to_insert'] ?? 0) + 1;

                return 'INSERT INTO ' . $matches[1] . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')' . $matches[3];
            }

            private function rewriteReplaceIntoValues(string $sql): string
            {
                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                if (!preg_match('/^\\s*REPLACE\\s+INTO\\s+(' . $identifier . ')\\s*\\(/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
                    return $sql;
                }

                $table = $matches[1][0];
                $baseTable = strtolower(trim($table, '`"'));
                $openColumns = strpos($sql, '(', $matches[0][1]);
                $closeColumns = $openColumns === false ? null : $this->findClosingParenthesis($sql, $openColumns);
                if ($closeColumns === null) {
                    $this->unsupported[] = 'REPLACE INTO has an unterminated column list';

                    return $sql;
                }

                $tail = substr($sql, $closeColumns + 1);
                if (!preg_match('/^\\s*VALUES\\s*\\(/i', $tail, $valuesStart)) {
                    $this->unsupported[] = 'REPLACE INTO supports only a single VALUES tuple';

                    return $sql;
                }
                $openValues = strpos($tail, '(', $valuesStart[0] === '' ? 0 : strlen($valuesStart[0]) - 1);
                $closeValues = $openValues === false ? null : $this->findClosingParenthesis($tail, $openValues);
                if ($closeValues === null || !preg_match('/^\\s*(;?)\\s*$/', substr($tail, $closeValues + 1), $suffix)) {
                    $this->unsupported[] = 'REPLACE INTO has a complex VALUES suffix';

                    return $sql;
                }

                $columnParts = $this->splitSqlCommaList(substr($sql, $openColumns + 1, $closeColumns - $openColumns - 1));
                $valueParts = $this->splitSqlCommaList(substr($tail, $openValues + 1, $closeValues - $openValues - 1));
                $columns = [];
                foreach ($columnParts as $columnPart) {
                    if (!preg_match('/^\\s*(' . $identifier . ')\\s*$/', $columnPart, $columnMatch)) {
                        $this->unsupported[] = 'REPLACE INTO has a complex column list';

                        return $sql;
                    }
                    $columns[] = $columnMatch[1];
                }
                if (count($columns) !== count($valueParts) || in_array('', array_map('trim', $valueParts), true)) {
                    $this->unsupported[] = 'REPLACE INTO column and value counts do not match';

                    return $sql;
                }

                $knownConflictKeys = [
                    'state_machine_state_translation' => ['language_id', 'state_machine_state_id'],
                    'state_machine_transition' => ['id'],
                ];
                if (!isset($knownConflictKeys[$baseTable])) {
                    $this->unsupported[] = 'REPLACE INTO VALUES has no known conflict key for this table';

                    return $sql;
                }

                $columnNames = array_map(static fn (string $column): string => strtolower(trim($column, '`"')), $columns);
                $conflictNames = $knownConflictKeys[$baseTable];
                if (array_diff($conflictNames, $columnNames) !== []) {
                    $this->unsupported[] = 'REPLACE INTO omits a known conflict key';

                    return $sql;
                }

                $conflictColumns = [];
                $updates = [];
                foreach ($columns as $index => $column) {
                    if (in_array($columnNames[$index], $conflictNames, true)) {
                        $conflictColumns[] = $column;

                        continue;
                    }
                    $updates[] = $column . ' = EXCLUDED.' . $column;
                }
                if ($updates === []) {
                    $this->unsupported[] = 'REPLACE INTO has no non-key columns to update';

                    return $sql;
                }

                $this->appliedRules['diagnostic_replace_into_known_key_upsert'] = ($this->appliedRules['diagnostic_replace_into_known_key_upsert'] ?? 0) + 1;

                return 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $valueParts) . ') ON CONFLICT (' . implode(', ', $conflictColumns) . ') DO UPDATE SET ' . implode(', ', $updates) . $suffix[1];
            }

            /** @return list<string> */
            private function splitSqlCommaList(string $value): array
            {
                $parts = [];
                $start = 0;
                $depth = 0;
                $quote = null;
                for ($index = 0; $index < strlen($value); ++$index) {
                    $character = $value[$index];
                    $next = $value[$index + 1] ?? '';
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
                        $end = strpos($value, $endMarker, $index + 2);
                        if ($end === false) {
                            return [];
                        }
                        $index = $end + strlen($endMarker) - 1;

                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;

                        continue;
                    }
                    if ($character === '(') {
                        ++$depth;
                    } elseif ($character === ')') {
                        --$depth;
                    } elseif ($character === ',' && $depth === 0) {
                        $parts[] = substr($value, $start, $index - $start);
                        $start = $index + 1;
                    }
                }
                $parts[] = substr($value, $start);

                return $parts;
            }

            private function rewriteInsertIgnore(string $sql): string
            {
                if (!preg_match('/^\\s*INSERT\\s+IGNORE\\s+INTO\\b/i', $sql)) {
                    return $sql;
                }

                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                if (preg_match('/^\\s*INSERT\\s+IGNORE\\s+INTO\\s+(' . $identifier . ')\\s+SET\\s+([\\s\\S]*?)\\s*(;?)\\s*$/i', $sql, $setMatch)) {
                    $columns = [];
                    $values = [];
                    foreach ($this->splitSqlCommaList($setMatch[2]) as $assignment) {
                        if (!preg_match('/^\\s*(' . $identifier . ')\\s*=\\s*([\\s\\S]*?)\\s*$/', $assignment, $parts) || $parts[2] === '') {
                            $this->unsupported[] = 'INSERT IGNORE INTO SET contains an unsupported assignment';

                            return $sql;
                        }
                        $columns[] = $parts[1];
                        $values[] = $parts[2];
                    }
                    $this->unsupported[] = 'INSERT IGNORE translated as ON CONFLICT DO NOTHING; MySQL warning semantics for other invalid values are not represented';
                    $this->appliedRules['diagnostic_insert_ignore_set_to_on_conflict'] = ($this->appliedRules['diagnostic_insert_ignore_set_to_on_conflict'] ?? 0) + 1;

                    return 'INSERT INTO ' . $setMatch[1] . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ') ON CONFLICT DO NOTHING' . $setMatch[3];
                }

                $startPattern = '/^\\s*INSERT\\s+IGNORE\\s+INTO\\s+(' . $identifier . ')\\s*\\(/i';
                if (!preg_match($startPattern, $sql, $insertMatch, PREG_OFFSET_CAPTURE)) {
                    $this->unsupported[] = 'INSERT IGNORE uses an unsupported form';

                    return $sql;
                }
                $table = $insertMatch[1][0];
                $openColumns = strpos($sql, '(', $insertMatch[0][1]);
                $closeColumns = $openColumns === false ? null : $this->findClosingParenthesis($sql, $openColumns);
                if ($closeColumns === null) {
                    $this->unsupported[] = 'INSERT IGNORE has an unterminated column list';

                    return $sql;
                }
                $columnList = trim(substr($sql, $openColumns + 1, $closeColumns - $openColumns - 1));
                $tail = substr($sql, $closeColumns + 1);
                if (preg_match('/^\\s*SELECT\\b/i', $tail)) {
                    $columnParts = $this->splitSqlCommaList($columnList);
                    foreach ($columnParts as $columnPart) {
                        if (!preg_match('/^\\s*' . $identifier . '\\s*$/', $columnPart)) {
                            $this->unsupported[] = 'INSERT IGNORE SELECT contains a complex column list';

                            return $sql;
                        }
                    }
                    $statements = array_values(array_filter($this->splitSqlStatements($sql), static fn (string $statement): bool => trim($statement) !== ''));
                    if (count($statements) !== 1) {
                        $this->unsupported[] = 'INSERT IGNORE SELECT contains multiple statements';

                        return $sql;
                    }

                    $rewritten = preg_replace('/^(\\s*)INSERT\\s+IGNORE\\s+INTO\\b/i', '$1INSERT INTO', $sql, 1);
                    if ($rewritten === null) {
                        $this->unsupported[] = 'INSERT IGNORE SELECT could not be rewritten';

                        return $sql;
                    }
                    if (preg_match('/;(\\s*)$/', $rewritten, $semicolon, PREG_OFFSET_CAPTURE)) {
                        $offset = $semicolon[0][1];
                        $rewritten = substr_replace($rewritten, ' ON CONFLICT DO NOTHING;', $offset, strlen($semicolon[0][0]));
                    } else {
                        $rewritten = rtrim($rewritten) . ' ON CONFLICT DO NOTHING';
                    }
                    $this->appliedRules['diagnostic_insert_ignore_select_to_on_conflict'] = ($this->appliedRules['diagnostic_insert_ignore_select_to_on_conflict'] ?? 0) + 1;

                    return $rewritten;
                }
                if (!preg_match('/^\\s*VALUES\\s*\\(/i', $tail, $valuesStart)) {
                    $this->unsupported[] = 'INSERT IGNORE supports only a simple VALUES tuple';

                    return $sql;
                }
                $openValues = strpos($tail, '(', $valuesStart[0] === '' ? 0 : strlen($valuesStart[0]) - 1);
                $closeValues = $openValues === false ? null : $this->findClosingParenthesis($tail, $openValues);
                if ($closeValues === null || !preg_match('/^\\s*(;?)\\s*$/', substr($tail, $closeValues + 1), $suffixMatch)) {
                    $this->unsupported[] = 'INSERT IGNORE has a complex VALUES suffix';

                    return $sql;
                }

                $columnParts = $this->splitSqlCommaList($columnList);
                $valueList = substr($tail, $openValues + 1, $closeValues - $openValues - 1);
                $valueParts = $this->splitSqlCommaList($valueList);
                foreach ($columnParts as $columnPart) {
                    if (!preg_match('/^\\s*' . $identifier . '\\s*$/', $columnPart)) {
                        $this->unsupported[] = 'INSERT IGNORE contains a complex column list';

                        return $sql;
                    }
                }
                if ($columnParts === [] || count($columnParts) !== count($valueParts)) {
                    $this->unsupported[] = 'INSERT IGNORE column and value counts do not match';

                    return $sql;
                }

                $this->unsupported[] = 'INSERT IGNORE translated as ON CONFLICT DO NOTHING; MySQL warning semantics for other invalid values are not represented';
                $this->appliedRules['diagnostic_insert_ignore_values_to_on_conflict'] = ($this->appliedRules['diagnostic_insert_ignore_values_to_on_conflict'] ?? 0) + 1;

                return 'INSERT INTO ' . $table . ' (' . $columnList . ') VALUES (' . $valueList . ') ON CONFLICT DO NOTHING' . $suffixMatch[1];
            }

            private function rewriteUpdateIgnore(string $sql): string
            {
                if (!preg_match('/^(\\s*)UPDATE\\s+IGNORE\\b/i', $sql, $matches)) {
                    return $sql;
                }

                $rewritten = preg_replace('/^(\\s*)UPDATE\\s+IGNORE\\b/i', '$1UPDATE', $sql, 1);
                if ($rewritten === null) {
                    return $sql;
                }

                $this->unsupported[] = 'UPDATE IGNORE translated without MySQL invalid-row warning semantics';
                $this->appliedRules['diagnostic_update_ignore_removed'] = ($this->appliedRules['diagnostic_update_ignore_removed'] ?? 0) + 1;

                return $rewritten;
            }

            private function rewriteUnhexCalls(string $sql): string
            {
                $searchOffset = 0;
                while (preg_match('/\\bUNHEX\\s*\\(/i', $sql, $match, PREG_OFFSET_CAPTURE, $searchOffset)) {
                    $start = $match[0][1];
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $start)) {
                        $searchOffset = $start + strlen($match[0][0]);

                        continue;
                    }
                    $open = strpos($sql, '(', $start);
                    $close = $open === false ? null : $this->findClosingParenthesis($sql, $open);
                    if ($close === null) {
                        $searchOffset = $start + strlen($match[0][0]);

                        continue;
                    }
                    $argument = trim(substr($sql, $open + 1, $close - $open - 1));
                    if ($argument === '' || count($this->splitSqlCommaList($argument)) !== 1) {
                        $this->unsupported[] = 'UNHEX call has an unsupported argument list';
                        $searchOffset = $close + 1;

                        continue;
                    }
                    $replacement = "decode(" . $argument . ", 'hex')";
                    $sql = substr_replace($sql, $replacement, $start, $close - $start + 1);
                    $this->appliedRules['unhex_to_decode_hex'] = ($this->appliedRules['unhex_to_decode_hex'] ?? 0) + 1;
                    $searchOffset = $start + strlen($replacement);
                }

                return $sql;
            }

            private function rewriteShowIndexes(string $sql): string
            {
                if (!preg_match('/^\\s*SHOW\\s+(?:INDEXES|INDEX|KEYS)\\s+(?:IN|FROM)\\b/i', $sql)) {
                    return $sql;
                }

                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                if (!preg_match('/^\\s*SHOW\\s+(?:INDEXES|INDEX|KEYS)\\s+(?:IN|FROM)\\s+(' . $identifier . ')\\s+WHERE\\s+(?:"?Key_name"?|`Key_name`|Key_name)\\s*=\\s*\'((?:\'\'|[^\'])*)\'\\s*;?\\s*$/i', $sql, $matches)) {
                    $this->unsupported[] = 'SHOW INDEXES uses an unsupported form';

                    return $sql;
                }

                $table = trim(str_replace('""', '"', str_replace('``', '`', $matches[1])), '`"');
                $index = str_replace("''", "'", $matches[2]);
                $tableLiteral = str_replace("'", "''", $table);
                $indexLiteral = str_replace("'", "''", $index);
                $this->appliedRules['show_indexes_to_pg_indexes_exists'] = ($this->appliedRules['show_indexes_to_pg_indexes_exists'] ?? 0) + 1;

                return "SELECT EXISTS (SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = '" . $tableLiteral . "' AND indexname = '" . $indexLiteral . "')";
            }

            private function rewriteDeleteSelfJoin(string $sql): string
            {
                if (!preg_match('/^\\s*DELETE\\s+\\S+\\s+FROM\\b/i', $sql)) {
                    return $sql;
                }

                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                $pattern = '/^\\s*DELETE\\s+(' . $identifier . ')\\s+FROM\\s+(' . $identifier . ')(?:\\s+AS)?\\s+(' . $identifier . ')\\s+INNER\\s+JOIN\\s+(' . $identifier . ')(?:\\s+AS)?\\s+(' . $identifier . ')\\s+WHERE\\s+([\\s\\S]*?)\\s*(;?)\\s*$/i';
                if (!preg_match($pattern, $sql, $matches)) {
                    $this->unsupported[] = 'multi-table DELETE uses an unsupported form';

                    return $sql;
                }

                $normalize = static fn (string $identifier): string => strtolower(trim($identifier, '`"'));
                if ($normalize($matches[1]) !== $normalize($matches[3]) || $normalize($matches[2]) !== $normalize($matches[4])) {
                    $this->unsupported[] = 'multi-table DELETE is not a simple self-join';

                    return $sql;
                }

                $this->appliedRules['delete_self_join_to_using'] = ($this->appliedRules['delete_self_join_to_using'] ?? 0) + 1;

                return 'DELETE FROM ' . $matches[2] . ' AS ' . $matches[3] . ' USING ' . $matches[4] . ' AS ' . $matches[5] . ' WHERE ' . $matches[6] . $matches[7];
            }

            private function rewriteDerivedUpdateJoin(string $sql): string
            {
                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                $prefixPattern = '/^\\s*UPDATE\\s+(' . $identifier . ')(?:\\s+(?:AS\\s+)?(' . $identifier . '))?\\s+INNER\\s+JOIN\\s*\\(/i';
                if (!preg_match($prefixPattern, $sql, $prefix, PREG_OFFSET_CAPTURE)) {
                    return $sql;
                }

                $open = $prefix[0][1] + strlen($prefix[0][0]) - 1;
                if (($sql[$open] ?? '') !== '(') {
                    $this->unsupported[] = 'derived-table UPDATE JOIN has an invalid subquery start';

                    return $sql;
                }
                $close = $this->findClosingParenthesis($sql, $open);
                if ($close === null) {
                    $this->unsupported[] = 'derived-table UPDATE JOIN has an unterminated subquery';

                    return $sql;
                }

                $suffix = substr($sql, $close + 1);
                if (!preg_match('/^\\s+(?:AS\\s+)?(' . $identifier . ')\\s+ON\\s+([\\s\\S]+?)\\s+SET\\s+([\\s\\S]*?)\\s*(;?)\\s*$/i', $suffix, $parts)) {
                    $this->unsupported[] = 'derived-table UPDATE JOIN uses an unsupported suffix';

                    return $sql;
                }

                $targetTable = $prefix[1][0];
                $targetAlias = $prefix[2][0] ?? '';
                $normalize = static fn (string $name): string => strtolower(trim($name, '`"'));
                $targetNames = [$normalize($targetTable)];
                if ($targetAlias !== '') {
                    $targetNames[] = $normalize($targetAlias);
                }
                $assignments = [];
                foreach ($this->splitSqlCommaList($parts[3]) as $assignment) {
                    $qualifiedIdentifier = $identifier . '(?:\\s*\\.\\s*' . $identifier . ')*';
                    if (!preg_match('/^\\s*(' . $qualifiedIdentifier . ')\\s*=\\s*([\\s\\S]*?)\\s*$/', $assignment, $assignmentParts) || $assignmentParts[2] === '') {
                        $this->unsupported[] = 'derived-table UPDATE JOIN has an unsupported assignment';

                        return $sql;
                    }
                    $leftParts = preg_split('/\\s*\\.\\s*/', trim($assignmentParts[1])) ?: [];
                    if (count($leftParts) > 1) {
                        $qualifier = (string) $leftParts[count($leftParts) - 2];
                        if (!in_array($normalize($qualifier), $targetNames, true)) {
                            $this->unsupported[] = 'derived-table UPDATE JOIN assignment targets a different table';

                            return $sql;
                        }
                    }
                    $column = (string) end($leftParts);
                    $assignments[] = $column . ' = ' . $assignmentParts[2];
                }

                $subquery = trim(substr($sql, $open + 1, $close - $open - 1));
                $target = 'UPDATE ' . $targetTable . ($targetAlias === '' ? '' : ' AS ' . $targetAlias);
                $target = $target . ' SET ' . implode(', ', $assignments);
                $sourceAlias = $parts[1];
                $this->appliedRules['derived_update_join_to_from'] = ($this->appliedRules['derived_update_join_to_from'] ?? 0) + 1;

                return $target . ' FROM (' . $subquery . ') AS ' . $sourceAlias . ' WHERE ' . trim($parts[2]) . $parts[4];
            }

            private function rewriteUpdateInnerJoin(string $sql): string
            {
                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                $pattern = '/^\\s*UPDATE\\s+(' . $identifier . ')(?:\\s+(?:AS\\s+)?(' . $identifier . '))?\\s+INNER\\s+JOIN\\s+(' . $identifier . ')(?:\\s+(?:AS\\s+)?(' . $identifier . '))?\\s+ON\\s+([\\s\\S]*?)\\s+SET\\s+([\\s\\S]*?)(?:\\s+WHERE\\s+([\\s\\S]*?))?\\s*(;?)\\s*$/i';
                if (!preg_match($pattern, $sql, $matches)) {
                    return $sql;
                }

                $targetTable = $matches[1];
                $targetAlias = $matches[2] ?? '';
                $joinedTable = $matches[3];
                $joinedAlias = $matches[4] ?? '';
                $normalize = static fn (string $name): string => strtolower(trim($name, '`"'));
                $targetNames = [$normalize($targetTable)];
                if ($targetAlias !== '') {
                    $targetNames[] = $normalize($targetAlias);
                }

                $assignments = [];
                $qualifiedIdentifier = $identifier . '(?:\\s*\\.\\s*' . $identifier . ')*';
                foreach ($this->splitSqlCommaList($matches[6]) as $assignment) {
                    if (!preg_match('/^\\s*(' . $qualifiedIdentifier . ')\\s*=\\s*([\\s\\S]*?)\\s*$/', $assignment, $parts) || $parts[2] === '') {
                        $this->unsupported[] = 'UPDATE INNER JOIN contains an unsupported assignment';

                        return $sql;
                    }
                    $leftParts = preg_split('/\\s*\\.\\s*/', trim($parts[1])) ?: [];
                    if (count($leftParts) > 1) {
                        $qualifier = (string) $leftParts[count($leftParts) - 2];
                        if (!in_array($normalize($qualifier), $targetNames, true)) {
                            $this->unsupported[] = 'UPDATE INNER JOIN assignment targets a different table';

                            return $sql;
                        }
                    }
                    $assignments[] = (string) end($leftParts) . ' = ' . $parts[2];
                }

                $target = 'UPDATE ' . $targetTable . ($targetAlias === '' ? '' : ' AS ' . $targetAlias);
                $source = $joinedTable . ($joinedAlias === '' ? '' : ' AS ' . $joinedAlias);
                $conditions = ['(' . trim($matches[5]) . ')'];
                if (isset($matches[7]) && trim($matches[7]) !== '') {
                    $conditions[] = '(' . trim($matches[7]) . ')';
                }
                $this->appliedRules['update_inner_join_to_from'] = ($this->appliedRules['update_inner_join_to_from'] ?? 0) + 1;

                return $target . ' SET ' . implode(', ', $assignments) . ' FROM ' . $source . ' WHERE ' . implode(' AND ', $conditions) . ($matches[8] ?? '');
            }

            private function isSqlOffsetOutsideQuotes(string $sql, int $position): bool
            {
                $quote = null;
                for ($index = 0; $index < $position; ++$index) {
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
                        if ($end === false || $end >= $position) {
                            return false;
                        }
                        $index = $end + strlen($endMarker) - 1;

                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;
                    }
                }

                return $quote === null;
            }

            private function rewriteLikeIdentifierOperands(string $sql): string
            {
                $identifierPart = '(?:"(?:[^"\\r\\n]|"")*"|`(?:[^`\\r\\n]|``)*`|[A-Za-z_][A-Za-z0-9_$]*)';
                $identifier = $identifierPart . '(?:\\s*\\.\\s*' . $identifierPart . ')*';
                $string = '\'(?:\'\'|[^\'])*\'';
                $operand = '(?:' . $identifier . '|' . $string . ')';
                $pattern = '/(' . $operand . ')(\\s+)(NOT\\s+)?(LIKE)(\\s+)(' . $operand . ')/i';
                if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    return $sql;
                }

                $changes = [];
                foreach ($matches as $match) {
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $match[4][1])) {
                        continue;
                    }
                    $left = $match[1][0];
                    $right = $match[6][0];
                    $leftIsIdentifier = preg_match('/\\A' . $identifier . '\\z/i', $left) === 1;
                    $rightIsIdentifier = preg_match('/\\A' . $identifier . '\\z/i', $right) === 1;
                    if (!$leftIsIdentifier && !$rightIsIdentifier) {
                        continue;
                    }
                    if ($leftIsIdentifier) {
                        $left = 'CAST(' . $left . ' AS TEXT)';
                    }
                    if ($rightIsIdentifier) {
                        $right = 'CAST(' . $right . ' AS TEXT)';
                    }

                    $replacement = $left . $match[2][0] . $match[3][0] . $match[4][0] . $match[5][0] . $right;
                    $changes[] = [$match[0][1], strlen($match[0][0]), $replacement];
                }
                foreach (array_reverse($changes) as [$offset, $length, $replacement]) {
                    $sql = substr_replace($sql, $replacement, $offset, $length);
                }
                if ($changes !== []) {
                    $this->appliedRules['json_like_identifier_operand_cast_to_text'] = ($this->appliedRules['json_like_identifier_operand_cast_to_text'] ?? 0) + count($changes);
                }

                return $sql;
            }

            private function rewriteJsonEmptyStringComparison(string $sql): string
            {
                $identifierPart = '(?:"(?:[^"\\r\\n]|"")*"|`(?:[^`\\r\\n]|``)*`|[A-Za-z_][A-Za-z0-9_$]*)';
                $identifier = $identifierPart . '(?:\\s*\\.\\s*' . $identifierPart . ')*';
                $pattern = '/(\\b(?:WHERE|AND|OR|ON)\\s+(?:\\(\\s*)*)(' . $identifier . ')(\\s*)(<>|!=|=)(\\s*)(\'\'|"")/i';
                if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    return $sql;
                }

                $changes = [];
                foreach ($matches as $match) {
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $match[4][1])) {
                        continue;
                    }
                    $replacement = $match[1][0] . 'CAST(' . $match[2][0] . ' AS TEXT)' . $match[3][0] . $match[4][0] . $match[5][0] . $match[6][0];
                    $changes[] = [$match[0][1], strlen($match[0][0]), $replacement];
                }
                foreach (array_reverse($changes) as [$offset, $length, $replacement]) {
                    $sql = substr_replace($sql, $replacement, $offset, $length);
                }
                if ($changes !== []) {
                    $this->appliedRules['json_empty_string_comparison_cast_to_text'] = ($this->appliedRules['json_empty_string_comparison_cast_to_text'] ?? 0) + count($changes);
                }

                return $sql;
            }

            private function rewriteQuotedDefaultStrings(string $sql): string
            {
                $pattern = '/(\\bDEFAULT\\s+)"((?:[^"]|"")*)"/i';
                if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    return $sql;
                }

                $changes = [];
                foreach ($matches as $match) {
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $match[1][1])) {
                        continue;
                    }
                    $literal = str_replace('""', '"', $match[2][0]);
                    $replacement = $match[1][0] . "'" . str_replace("'", "''", $literal) . "'";
                    $changes[] = [$match[0][1], strlen($match[0][0]), $replacement];
                }
                foreach (array_reverse($changes) as [$offset, $length, $replacement]) {
                    $sql = substr_replace($sql, $replacement, $offset, $length);
                }
                if ($changes !== []) {
                    $this->appliedRules['mysql_double_quoted_default_to_string_literal'] = ($this->appliedRules['mysql_double_quoted_default_to_string_literal'] ?? 0) + count($changes);
                }

                return $sql;
            }

            private function rewriteLowerHexBinaryIdentifiers(string $sql): string
            {
                $identifierPart = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                $identifier = $identifierPart . '(?:\\s*\\.\\s*' . $identifierPart . ')*';
                $pattern = '/LOWER\\s*\\(\\s*HEX\\s*\\(\\s*(' . $identifier . ')\\s*\\)\\s*\\)/i';
                $changes = [];
                if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($matches as $match) {
                        $offset = $match[0][1];
                        if (!$this->isSqlOffsetOutsideQuotes($sql, $offset)) {
                            continue;
                        }
                        $parts = preg_split('/\\s*\\.\\s*/', trim($match[1][0])) ?: [];
                        $column = strtolower(trim((string) end($parts), '`"'));
                        if ($column !== 'id' && !str_ends_with($column, '_id')) {
                            $this->unsupported[] = 'LOWER(HEX(...)) on a non-UUID-named column needs explicit translation';

                            continue;
                        }
                        $changes[] = [$offset, strlen($match[0][0]), "encode(" . $match[1][0] . ", 'hex')"];
                    }
                }
                foreach (array_reverse($changes) as [$offset, $length, $replacement]) {
                    $sql = substr_replace($sql, $replacement, $offset, $length);
                }
                if ($changes !== []) {
                    $this->appliedRules['lower_hex_uuid_to_encode_hex'] = ($this->appliedRules['lower_hex_uuid_to_encode_hex'] ?? 0) + count($changes);
                }

                $plainPattern = '/\\bHEX\\s*\\(\\s*(' . $identifier . ')\\s*\\)/i';
                $plainChanges = [];
                if (preg_match_all($plainPattern, $sql, $plainMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($plainMatches as $match) {
                        $offset = $match[0][1];
                        if (!$this->isSqlOffsetOutsideQuotes($sql, $offset)) {
                            continue;
                        }
                        $prefix = substr($sql, max(0, $offset - 20), min(20, $offset));
                        if (preg_match('/LOWER\\s*\\(\\s*$/i', $prefix)) {
                            continue;
                        }
                        $parts = preg_split('/\\s*\\.\\s*/', trim($match[1][0])) ?: [];
                        $column = strtolower(trim((string) end($parts), '`"'));
                        if ($column !== 'id' && !str_ends_with($column, '_id')) {
                            $this->unsupported[] = 'HEX(...) on a non-UUID-named column needs explicit translation';

                            continue;
                        }
                        $plainChanges[] = [$offset, strlen($match[0][0]), "encode(" . $match[1][0] . ", 'hex')"];
                    }
                }
                foreach (array_reverse($plainChanges) as [$offset, $length, $replacement]) {
                    $sql = substr_replace($sql, $replacement, $offset, $length);
                }
                if ($plainChanges !== []) {
                    $this->appliedRules['hex_uuid_to_encode_hex'] = ($this->appliedRules['hex_uuid_to_encode_hex'] ?? 0) + count($plainChanges);
                }

                if (preg_match_all('/\\bHEX\\s*\\(/i', $sql, $hexMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($hexMatches[0] as [$hexCall, $offset]) {
                        if ($this->isSqlOffsetOutsideQuotes($sql, $offset)) {
                            $this->unsupported[] = 'HEX(...) form remains unsupported';

                            break;
                        }
                    }
                }

                return $sql;
            }

            private function rewriteInvalidFlowSequenceDistinct(string $sql): string
            {
                if ($this->classFile !== 'Migration1648803451FixInvalidMigrationOfBusinessEventToFlow.php'
                    || !preg_match('/^(\\s*)SELECT\\s+DISTINCT\\s+(HEX\\s*\\(\\s*`flow`\\s*\\.\\s*`id`\\s*\\))\\s*,/i', $sql, $matches)) {
                    return $sql;
                }

                $this->appliedRules['flow_sequence_distinct_to_distinct_on_ids'] = ($this->appliedRules['flow_sequence_distinct_to_distinct_on_ids'] ?? 0) + 1;

                return $matches[1] . 'SELECT DISTINCT ON (`flow`.`id`, `flow_sequence`.`id`) ' . $matches[2] . ',' . substr($sql, strlen($matches[0]));
            }

            private function rewriteJsonReplaceUpdate(string $sql): string
            {
                $identifier = '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)';
                $qualifiedIdentifier = $identifier . '(?:\\s*\\.\\s*' . $identifier . ')*';
                $string = '\'(?:\'\'|[^\'])*\'';
                $pattern = '/^\\s*UPDATE\\s+(' . $identifier . ')\\s+SET\\s+(' . $qualifiedIdentifier . ')\\s*=\\s*REPLACE\\s*\\(\\s*(' . $qualifiedIdentifier . ')\\s*,\\s*(' . $string . ')\\s*,\\s*(' . $string . ')\\s*\\)\\s*(WHERE\\s+[\\s\\S]*?)\\s*(;?)\\s*$/i';
                if (!preg_match($pattern, $sql, $matches)) {
                    return $sql;
                }

                $normalize = static function (string $value): string {
                    $parts = preg_split('/\\s*\\.\\s*/', trim($value)) ?: [];

                    return implode('.', array_map(static fn (string $part): string => strtolower(trim($part, '`"')), $parts));
                };
                $table = $normalize($matches[1]);
                $leftColumn = $normalize($matches[2]);
                $replaceColumn = $normalize($matches[3]);
                $columnParts = explode('.', $leftColumn);
                $column = (string) end($columnParts);
                $jsonColumns = [
                    'product_stream' => ['api_filter'],
                    'category_translation' => ['slot_config'],
                    'cms_slot_translation' => ['config'],
                    'product_sorting' => ['fields'],
                ];
                if ($leftColumn !== $replaceColumn || !in_array($column, $jsonColumns[$table] ?? [], true)) {
                    return $sql;
                }

                $this->appliedRules['json_replace_update_cast_to_text_and_jsonb'] = ($this->appliedRules['json_replace_update_cast_to_text_and_jsonb'] ?? 0) + 1;

                return 'UPDATE ' . $matches[1] . ' SET ' . $matches[2] . ' = REPLACE(CAST(' . $matches[3] . ' AS TEXT), ' . $matches[4] . ', ' . $matches[5] . ')::jsonb ' . $matches[6] . $matches[7];
            }

            private function findClosingParenthesis(string $sql, int $open): ?int
            {
                $depth = 1;
                $quote = null;
                for ($index = $open + 1; $index < strlen($sql); ++$index) {
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
                            return null;
                        }
                        $index = $end + strlen($endMarker) - 1;

                        continue;
                    }
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;

                        continue;
                    }
                    if ($character === '(') {
                        ++$depth;
                    } elseif ($character === ')' && --$depth === 0) {
                        return $index;
                    }
                }

                return null;
            }

            private function convertJsonObjectArgumentStrings(string $arguments): string
            {
                $converted = '';
                $quote = null;
                for ($index = 0; $index < strlen($arguments); ++$index) {
                    $character = $arguments[$index];
                    $next = $arguments[$index + 1] ?? '';
                    if ($quote === "'" || $quote === '`') {
                        $converted .= $character;
                        if ($character === '\\' && $quote !== '`' && $next !== '') {
                            $converted .= $next;
                            ++$index;
                        } elseif ($character === $quote) {
                            if ($next === $quote) {
                                $converted .= $next;
                                ++$index;
                            } else {
                                $quote = null;
                            }
                        }

                        continue;
                    }
                    if ($character === "'" || $character === '`') {
                        $quote = $character;
                        $converted .= $character;

                        continue;
                    }
                    if ($character !== '"') {
                        $converted .= $character;

                        continue;
                    }

                    $literal = '';
                    for (++$index; $index < strlen($arguments); ++$index) {
                        $character = $arguments[$index];
                        $next = $arguments[$index + 1] ?? '';
                        if ($character === '\\' && $next !== '') {
                            $literal .= $next === '"' ? '"' : $character . $next;
                            ++$index;
                        } elseif ($character === '"') {
                            if ($next === '"') {
                                $literal .= '"';
                                ++$index;
                            } else {
                                break;
                            }
                        } else {
                            $literal .= $character;
                        }
                    }
                    $converted .= '\'' . str_replace("'", "''", $literal) . '\'';
                }

                return $converted;
            }

            private function castJsonObjectPlaceholderValues(string $arguments): string
            {
                $parts = [];
                $start = 0;
                $depth = 0;
                $quote = null;
                for ($index = 0; $index < strlen($arguments); ++$index) {
                    $character = $arguments[$index];
                    $next = $arguments[$index + 1] ?? '';
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
                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;

                        continue;
                    }
                    if ($character === '(') {
                        ++$depth;
                    } elseif ($character === ')') {
                        --$depth;
                    } elseif ($character === ',' && $depth === 0) {
                        $parts[] = substr($arguments, $start, $index - $start);
                        $start = $index + 1;
                    }
                }
                $parts[] = substr($arguments, $start);

                foreach ($parts as $index => $part) {
                    if ($index % 2 === 0 || !preg_match('/^(\\s*):([A-Za-z_][A-Za-z0-9_]*)(\\s*)$/', $part, $match)) {
                        continue;
                    }
                    $parts[$index] = $match[1] . 'CAST(:' . $match[2] . ' AS TEXT)' . $match[3];
                }

                return implode(',', $parts);
            }

            private function rewriteJsonObjectCalls(string $sql): string
            {
                $searchOffset = 0;
                while (preg_match('/\\bJSON_OBJECT\\s*\\(/i', $sql, $match, PREG_OFFSET_CAPTURE, $searchOffset)) {
                    $functionOffset = $match[0][1];
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $functionOffset)) {
                        $searchOffset = $functionOffset + strlen($match[0][0]);

                        continue;
                    }

                    $open = strpos($sql, '(', $functionOffset);
                    $close = $open === false ? null : $this->findClosingParenthesis($sql, $open);
                    if ($close === null) {
                        $searchOffset = $functionOffset + strlen($match[0][0]);

                        continue;
                    }

                    $arguments = substr($sql, $open + 1, $close - $open - 1);
                    $arguments = $this->castJsonObjectPlaceholderValues($arguments);
                    $arguments = $this->convertJsonObjectArgumentStrings($arguments);
                    $arguments = $this->rewriteJsonObjectCalls($arguments);
                    $replacement = 'jsonb_build_object(' . $arguments . ')';
                    $sql = substr_replace($sql, $replacement, $functionOffset, $close - $functionOffset + 1);
                    $this->appliedRules['json_object_to_jsonb_build_object'] = ($this->appliedRules['json_object_to_jsonb_build_object'] ?? 0) + 1;
                    $searchOffset = $functionOffset + strlen($replacement);
                }

                return $sql;
            }

            private function rewriteJsonExtractCalls(string $sql): string
            {
                $searchOffset = 0;
                while (preg_match('/\b(?:JSON_UNQUOTE\s*\(\s*)?JSON_EXTRACT\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE, $searchOffset)) {
                    $start = $match[0][1];
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $start)) {
                        $searchOffset = $start + strlen($match[0][0]);

                        continue;
                    }
                    $unquote = preg_match('/^JSON_UNQUOTE/i', $match[0][0]) === 1;
                    $extractOffset = $start + ($unquote ? strpos(strtoupper($match[0][0]), 'JSON_EXTRACT') : 0);
                    $open = strpos($sql, '(', $extractOffset);
                    $close = $open === false ? null : $this->findClosingParenthesis($sql, $open);
                    if ($close === null) {
                        $searchOffset = $start + strlen($match[0][0]);

                        continue;
                    }
                    $end = $close + 1;
                    if ($unquote) {
                        while (isset($sql[$end]) && ctype_space($sql[$end])) {
                            ++$end;
                        }
                        if (($sql[$end] ?? '') !== ')') {
                            $searchOffset = $start + strlen($match[0][0]);

                            continue;
                        }
                        ++$end;
                    }
                    $arguments = $this->splitSqlCommaList(substr($sql, $open + 1, $close - $open - 1));
                    if (count($arguments) !== 2 || !preg_match('/^\s*[\'"]\$(?:\.([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*))?[\'"]\s*$/', $arguments[1], $pathMatch)) {
                        $searchOffset = $end;

                        continue;
                    }
                    $path = ($pathMatch[1] ?? '') === '' ? '' : str_replace('.', ',', $pathMatch[1]);
                    $operator = $unquote ? '#>>' : '#>';
                    $replacement = '(' . trim($arguments[0]) . ' ' . $operator . " '{" . $path . "}')";
                    $sql = substr_replace($sql, $replacement, $start, $end - $start);
                    $rule = $unquote ? 'json_unquote_json_extract_to_text_path' : 'json_extract_to_json_path';
                    $this->appliedRules[$rule] = ($this->appliedRules[$rule] ?? 0) + 1;
                    $searchOffset = $start + strlen($replacement);
                }

                return $sql;
            }

            private function rewriteGroupConcatCalls(string $sql): string
            {
                $searchOffset = 0;
                while (preg_match('/\bGROUP_CONCAT\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE, $searchOffset)) {
                    $start = $match[0][1];
                    if (!$this->isSqlOffsetOutsideQuotes($sql, $start)) {
                        $searchOffset = $start + strlen($match[0][0]);

                        continue;
                    }
                    $open = strpos($sql, '(', $start);
                    $close = $open === false ? null : $this->findClosingParenthesis($sql, $open);
                    if ($close === null) {
                        $searchOffset = $start + strlen($match[0][0]);

                        continue;
                    }
                    $arguments = trim(substr($sql, $open + 1, $close - $open - 1));
                    $distinct = '';
                    if (preg_match('/^DISTINCT\b\s*/i', $arguments, $distinctMatch)) {
                        $distinct = 'DISTINCT ';
                        $arguments = substr($arguments, strlen($distinctMatch[0]));
                    }
                    $delimiter = "','";
                    if (preg_match('/^(.*?)\s+SEPARATOR\s+(\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*")\s*$/is', $arguments, $separatorMatch)) {
                        $arguments = trim($separatorMatch[1]);
                        $delimiter = $separatorMatch[2];
                        if (str_starts_with($delimiter, '"')) {
                            $delimiter = "'" . str_replace("'", "''", str_replace('""', '"', trim($delimiter, '"'))) . "'";
                        }
                    }
                    if ($arguments === '') {
                        $searchOffset = $close + 1;

                        continue;
                    }
                    $replacement = 'string_agg(' . $distinct . $arguments . ', ' . $delimiter . ')';
                    $sql = substr_replace($sql, $replacement, $start, $close - $start + 1);
                    $this->appliedRules['group_concat_to_string_agg'] = ($this->appliedRules['group_concat_to_string_agg'] ?? 0) + 1;
                    $searchOffset = $start + strlen($replacement);
                }

                return $sql;
            }

            private function rewriteProductPricePercentageUpdate(string $sql): string
            {
                if ($this->classFile !== 'Migration1627929168UpdatePriceFieldInProductTable.php') {
                    return $sql;
                }
                if (!preg_match('/^\\s*UPDATE\\s+product\\s+SET\\s+price\\s*=\\s*JSON_SET\\s*\\(/i', $sql)) {
                    $this->unsupported[] = 'product price percentage migration SQL did not match its diagnostic rewrite';

                    return $sql;
                }

                $this->appliedRules['product_price_json_percentage_to_jsonb'] = 1;

                return <<<'SQL'
UPDATE product
SET price = jsonb_set(
    price,
    '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,percentage}'::text[],
    jsonb_build_object(
        'net', COALESCE(
            ROUND(
                CASE
                    WHEN COALESCE((price #>> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,listPrice,net}')::numeric, 0) = 0 THEN 0
                    ELSE 100 - (price #>> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,net}')::numeric
                        / (price #>> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,listPrice,net}')::numeric * 100
                END,
                2
            ),
            0
        ),
        'gross', COALESCE(
            ROUND(
                CASE
                    WHEN COALESCE((price #>> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,listPrice,gross}')::numeric, 0) = 0 THEN 0
                    ELSE 100 - (price #>> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,gross}')::numeric
                        / (price #>> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,listPrice,gross}')::numeric * 100
                END,
                2
            ),
            0
        )
    ),
    true
)
WHERE price #> '{cb7d2554b0ce847cd82f3ac9bd1c0dfca,listPrice}'::text[] IS NOT NULL
SQL;
            }

            /** @return list<string> */
            private function getInsertDataKeys(Node\Expr $expression): array
            {
                if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
                    return $this->methodArrayKeys[$expression->name] ?? [];
                }
                if ($expression instanceof Node\Expr\PropertyFetch
                    && $expression->var instanceof Node\Expr\Variable
                    && $expression->var->name === 'this'
                    && $expression->name instanceof Node\Identifier) {
                    return $this->methodPropertyArrayKeys[$expression->name->toString()] ?? [];
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

                    $hasUtf8TableCollation = preg_match('/\\bCOLLATE\\s*=\\s*utf8mb4?_[A-Za-z0-9_]+/i', $statement) === 1;
                    $hasUnsupportedTableCollation = preg_match('/\\bCOLLATE\\s*=\\s*(?!utf8mb4?_)[A-Za-z0-9_]+/i', $statement) === 1;
                    $mysqlTableOptions = 'ENGINE\\s*=\\s*[A-Za-z0-9_]+|DEFAULT\\s+CHARSET\\s*=\\s*[A-Za-z0-9_]+|DEFAULT\\s+CHARACTER\\s+SET\\s*=?\\s*[A-Za-z0-9_]+|CHARSET\\s*=\\s*[A-Za-z0-9_]+|COLLATE\\s*=\\s*utf8mb4?_[A-Za-z0-9_]+';
                    if ($this->diagnosticMode) {
                        $mysqlTableOptions .= '|AUTO_INCREMENT\\s*=\\s*[0-9]+';
                    }
                    $this->replace(
                        'create_table_mysql_options_removed',
                        '/(\\))\\s*(?:' . $mysqlTableOptions . ')(?:\\s+(?:' . $mysqlTableOptions . '))*\\s*(?=;?\\s*$)/i',
                        '$1',
                        $statement
                    );

                    if ($hasUtf8TableCollation) {
                        $this->replace(
                            'create_table_default_collation_to_sw_type_ci',
                            '/(?m)^([ \\t]*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)[ \\t]+(?:VARCHAR|CHAR|TINYTEXT|MEDIUMTEXT|LONGTEXT|TEXT)(?:[ \\t]*\\([0-9]+\\))?)(?![^\\r\\n]*\\bCOLLATE\\b)/i',
                            '$1 COLLATE "sw_type_ci"',
                            $statement
                        );
                    }
                    if ($hasUnsupportedTableCollation) {
                        $this->unsupported[] = 'unsupported MySQL table default collation needs explicit mapping';
                    }
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
                        'create_table_long_text_to_text',
                        '/(?m)^([ \\t]*' . '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)' . '[ \\t]+)(?:TINYTEXT|MEDIUMTEXT|LONGTEXT)(?=[ \\t]*(?:NULL|NOT[ \\t]+NULL|DEFAULT|COLLATE|COMMENT)\\b|[ \\t]*[,)]|[ \\t]*$)/i',
                        '$1TEXT',
                        $statement
                    );
                    $this->replace(
                        'create_table_blob_to_bytea',
                        '/(?m)^([ \\t]*' . '(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)' . '[ \\t]+)(?:TINYBLOB|MEDIUMBLOB|LONGBLOB|BLOB)(?=[ \\t]*(?:NULL|NOT[ \\t]+NULL|DEFAULT|COLLATE|COMMENT)\\b|[ \\t]*[,)]|[ \\t]*$)/i',
                        '$1BYTEA',
                        $statement
                    );
                    if ($this->diagnosticMode) {
                        $this->replace(
                            'create_table_unsigned_removed',
                            '/(?m)^([ \\t]*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)[ \\t]+(?:TINYINT|SMALLINT|MEDIUMINT|INT(?:EGER)?|BIGINT|DECIMAL|NUMERIC)(?:[ \\t]*\\([0-9]+(?:[ \\t]*,[ \\t]*[0-9]+)?[ \\t]*\\))?)[ \\t]+UNSIGNED\\b',
                            '$1',
                            $statement
                        );
                        $this->replace(
                            'create_table_auto_increment_to_identity',
                            '/(?m)^([ \\t]*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)[ \\t]+)(TINYINT|SMALLINT|MEDIUMINT|INT|INTEGER|BIGINT)(?:[ \\t]*\\(\\s*\\d+\\s*\\))?[ \\t]+(?:(NOT[ \\t]+NULL)[ \\t]+)?AUTO_INCREMENT\\b',
                            static fn (array $matches): string => $matches[1] . (strtoupper($matches[2]) === 'INT' ? 'INTEGER' : strtoupper($matches[2])) . ' GENERATED BY DEFAULT AS IDENTITY' . (($matches[3] ?? '') === '' ? '' : ' ' . preg_replace('/[ \\t]+/', ' ', $matches[3])),
                            $statement
                        );
                        if ($this->classFile === 'Migration1669124190AddDoctrineMessengerTable.php') {
                            $this->replace(
                                'create_table_messenger_auto_increment_to_identity',
                                '/(?m)^([ \\t]*`id`[ \\t]+)(BIGINT)[ \\t]+NOT[ \\t]+NULL[ \\t]+AUTO_INCREMENT\\b/i',
                                '$1BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL',
                                $statement
                            );
                        }
                        if ($this->classFile === 'Migration1635388654CreateIncrementTable.php') {
                            $this->replace(
                                'create_table_increment_bigint_unsigned_removed',
                                '/(\\bBIGINT)\\s+UNSIGNED\\b/i',
                                '$1',
                                $statement
                            );
                        }
                    }
                    $this->replace(
                        'create_table_utf8mb4_collation_to_sw_type_ci',
                        '/\\bCOLLATE\\s+[" ]?utf8mb4?_[A-Za-z0-9_]+["]?([ \\t]*)/i',
                        static fn (array $matches): string => 'COLLATE sw_type_ci' . (($matches[1] ?? '') === '' ? ' ' : $matches[1]),
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
                    if ($this->classFile === 'Migration1669124190AddDoctrineMessengerTable.php'
                        && preg_match('/^\\s*INDEX\\s*\\((.*)\\)\\s*$/is', $clause, $anonymousIndexMatch)) {
                        $columns = trim($anonymousIndexMatch[1]);
                        $columnParts = $this->splitSqlCommaList($columns);
                        if ($columnParts === [] || array_filter($columnParts, static fn (string $column): bool => !preg_match('/^\\s*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\\s*$/', $column)) !== []) {
                            $this->unsupported[] = 'anonymous table-level INDEX has unsupported column expression';
                            $kept[] = $clause;

                            continue;
                        }
                        $columnNames = array_map(static fn (string $column): string => trim(trim($column), '`"'), $columnParts);
                        $indexName = '"' . trim($table, '`"') . '_' . implode('_', $columnNames) . '_idx"';
                        $indexes[] = [$indexName, $table, $columns];
                        $changed = true;

                        continue;
                    }
                    if (!preg_match('/^\\s*(?:KEY|INDEX)\\s+("[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\\s*\\((.*)\\)\\s*$/is', $clause, $match)) {
                        $kept[] = $clause;
                        continue;
                    }
                    $columns = trim($match[2]);
                    if (!preg_match('/^(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)(?:\\s*\\(\\s*\\d+\\s*\\))?(?:\\s*,\\s*(?:"[^"\\r\\n]+"|`[^`\\r\\n]+`|[A-Za-z_][A-Za-z0-9_$]*)(?:\\s*\\(\\s*\\d+\\s*\\))?)*$/', $columns)) {
                        $this->unsupported[] = 'table-level KEY/INDEX has unsupported column expression';
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
                    if ($this->classFile === 'Migration1671003201RemoveDeprecatedColumns.php'
                        && preg_match('/^\s*ALTER\s+TABLE\s+(' . $identifier . ')\s+CHANGE(?:\s+COLUMN)?\s+(' . $identifier . ')\s+\2\s+TINYINT(?:\s*\(\s*1\s*\))?\s+DEFAULT\s+0\s*(;?)\s*$/i', $statement, $changeMatch)) {
                        $column = $changeMatch[2];
                        $rewritten .= 'ALTER TABLE ' . $changeMatch[1] . ' ALTER COLUMN ' . $column . ' TYPE SMALLINT, ALTER COLUMN ' . $column . ' SET DEFAULT 0, ALTER COLUMN ' . $column . ' SET NOT NULL' . $changeMatch[3];
                        $this->appliedRules['diagnostic_same_name_tinyint_change_to_alter_column'] = ($this->appliedRules['diagnostic_same_name_tinyint_change_to_alter_column'] ?? 0) + 1;

                        continue;
                    }
                    $indexColumns = $identifier . '(?:\s*,\s*' . $identifier . ')*';
                    if ($this->diagnosticMode
                        && preg_match('/^(\s*ALTER\s+TABLE\s+' . $identifier . '\s+ADD(?:\s+COLUMN)?\s+' . $identifier . '\s+)(BIGINT|INTEGER|INT|SMALLINT)\s+(NOT\s+NULL\s+)?AUTO_INCREMENT(\s+UNIQUE)?\s*(;?)\s*$/i', $statement, $identityMatch)) {
                        $type = strtoupper($identityMatch[2]) === 'INT' ? 'INTEGER' : strtoupper($identityMatch[2]);
                        $statement = $identityMatch[1] . $type . ' GENERATED BY DEFAULT AS IDENTITY'
                            . (($identityMatch[3] ?? '') === '' ? '' : ' ' . trim($identityMatch[3]))
                            . (($identityMatch[4] ?? '') === '' ? '' : ' UNIQUE')
                            . ($identityMatch[5] ?? '');
                        $this->appliedRules['alter_add_auto_increment_to_identity'] = ($this->appliedRules['alter_add_auto_increment_to_identity'] ?? 0) + 1;
                    }
                    if (preg_match('/^\s*ALTER\s+TABLE\s+(' . $identifier . ')\s+ADD\s+(UNIQUE\s+)?(?:INDEX|KEY)\s+(' . $identifier . ')\s*\(\s*(' . $indexColumns . ')\s*\)\s*;?\s*$/i', $statement, $indexMatch)) {
                        $unique = ($indexMatch[2] ?? '') !== '' ? 'UNIQUE ' : '';
                        $rewritten .= 'CREATE ' . $unique . 'INDEX ' . $indexMatch[3] . ' ON ' . $indexMatch[1] . ' (' . $indexMatch[4] . ');';
                        $this->appliedRules['alter_add_index_to_create_index'] = ($this->appliedRules['alter_add_index_to_create_index'] ?? 0) + 1;

                        continue;
                    }
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
                    $defaultLiteralPattern = "(?:NULL|[-+]?[0-9]+(?:\\.[0-9]+)?|'(?:''|[^'])*'|\"(?:[^\"]|\"\")*\")";
                    $this->replace(
                        'alter_same_name_change_column_to_alter_column',
                        '/\\bCHANGE(?:\\s+COLUMN)?\\s+(' . $identifier . ')\\s+\\1\\s+(VARCHAR\\s*\\(\\s*\\d+\\s*\\)|CHARACTER\\s+VARYING\\s*\\(\\s*\\d+\\s*\\)|CHAR(?:ACTER)?(?:\\s+VARYING)?(?:\\s*\\(\\s*\\d+\\s*\\))?)(?:\\s+COLLATE\\s+(' . $identifier . '))?\\s+(NULL|NOT\\s+NULL)(?:\\s+DEFAULT\\s+(' . $defaultLiteralPattern . '))?(?=\\s*(?:,|;|$))/i',
                        static function (array $matches): string {
                            $column = $matches[1];
                            $type = $matches[2];
                            $collation = ($matches[3] ?? '') === '' ? '' : ' COLLATE ' . $matches[3];
                            $clauses = ['ALTER COLUMN ' . $column . ' TYPE ' . $type . $collation];
                            $clauses[] = strtoupper(preg_replace('/\\s+/', ' ', $matches[4]) ?? $matches[4]) === 'NULL'
                                ? 'ALTER COLUMN ' . $column . ' DROP NOT NULL'
                                : 'ALTER COLUMN ' . $column . ' SET NOT NULL';
                            if (($matches[5] ?? '') !== '') {
                                $default = $matches[5];
                                if (str_starts_with($default, '"')) {
                                    $default = "'" . str_replace("'", "''", str_replace('""', '"', trim($default, '"'))) . "'";
                                }
                                $clauses[] = strtoupper($default) === 'NULL'
                                    ? 'ALTER COLUMN ' . $column . ' DROP DEFAULT'
                                    : 'ALTER COLUMN ' . $column . ' SET DEFAULT ' . $default;
                            }

                            return implode(', ', $clauses);
                        },
                        $statement
                    );
                    $this->replace(
                        'alter_modify_column_to_alter_column',
                        '/\bMODIFY(?:\s+COLUMN)?\s+("[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*)\s+(VARCHAR\s*\(\s*\d+\s*\)|CHARACTER\s+VARYING\s*\(\s*\d+\s*\)|DOUBLE(?:\s+PRECISION)?|FLOAT|INTEGER|INT|SMALLINT|BIGINT|BYTEA|TEXT|JSON|DECIMAL\s*\(\s*\d+\s*,\s*\d+\s*\))(?:\s+COLLATE\s+("[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*))?\s+(NULL|NOT\s+NULL)(?:\s+DEFAULT\s+(NULL|[-+]?[0-9]+(?:\.[0-9]+)?|\'[^\']*(?:\'\'[^\']*)*\'))?(?:\s+AFTER\s+(?:"[^"\r\n]+"|`[^`\r\n]+`|[A-Za-z_][A-Za-z0-9_$]*))?(?=\s*(?:,|;|$))/i',
                        static function (array $matches): string {
                            $column = str_starts_with($matches[1], '`') ? '"' . trim($matches[1], '`') . '"' : $matches[1];
                            $type = preg_replace('/^INT$/i', 'INTEGER', $matches[2]) ?? $matches[2];
                            $type = preg_replace('/^DOUBLE$/i', 'DOUBLE PRECISION', $type) ?? $type;
                            $collation = ($matches[3] ?? '') === '' ? '' : ' COLLATE ' . $matches[3];
                            $clauses = ['ALTER COLUMN ' . $column . ' TYPE ' . $type . $collation];
                            $clauses[] = strtoupper(preg_replace('/\s+/', ' ', $matches[4]) ?? $matches[4]) === 'NULL'
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
                    // CHANGE can also be used as a MySQL spelling of MODIFY when
                    // the old and new column names are identical. Keep renames
                    // untouched because they need separate PostgreSQL handling.
                    $this->replace(
                        'alter_change_same_name_general_to_alter_column',
                        '/\\bCHANGE(?:\\s+COLUMN)?\\s+(' . $identifier . ')\\s+\\1\\s+(VARCHAR\\s*\\(\\s*\\d+\\s*\\)|CHARACTER\\s+VARYING\\s*\\(\\s*\\d+\\s*\\)|DOUBLE(?:\\s+PRECISION)?|FLOAT|INTEGER|INT|SMALLINT|BIGINT|BYTEA|TEXT|JSON|DECIMAL\\s*\\(\\s*\\d+\\s*,\\s*\\d+\\s*\\))(?:\\s+COLLATE\\s+(' . $identifier . '))?(?:\\s+(NULL|NOT\\s+NULL))?(?:\\s+DEFAULT\\s+(' . $defaultLiteralPattern . '))?(?:\\s+AFTER\\s+' . $identifier . ')?(?=\\s*(?:,|;|$))/i',
                        static function (array $matches): string {
                            $column = str_starts_with($matches[1], '`') ? '"' . trim($matches[1], '`') . '"' : $matches[1];
                            $type = preg_replace('/^INT$/i', 'INTEGER', $matches[2]) ?? $matches[2];
                            $type = preg_replace('/^DOUBLE$/i', 'DOUBLE PRECISION', $type) ?? $type;
                            $collation = ($matches[3] ?? '') === '' ? '' : ' COLLATE ' . $matches[3];
                            $clauses = ['ALTER COLUMN ' . $column . ' TYPE ' . $type . $collation];
                            $nullability = strtoupper(preg_replace('/\\s+/', ' ', $matches[4] ?? '') ?? ($matches[4] ?? ''));
                            $clauses[] = $nullability === '' || $nullability === 'NULL'
                                ? 'ALTER COLUMN ' . $column . ' DROP NOT NULL'
                                : 'ALTER COLUMN ' . $column . ' SET NOT NULL';
                            if (($matches[5] ?? '') === '') {
                                $clauses[] = 'ALTER COLUMN ' . $column . ' DROP DEFAULT';
                            } elseif (strtoupper($matches[5]) === 'NULL') {
                                $clauses[] = 'ALTER COLUMN ' . $column . ' DROP DEFAULT';
                            } else {
                                $default = $matches[5];
                                if (str_starts_with($default, '"')) {
                                    $default = "'" . str_replace("'", "''", str_replace('""', '"', trim($default, '"'))) . "'";
                                }
                                $clauses[] = 'ALTER COLUMN ' . $column . ' SET DEFAULT ' . $default;
                            }

                            return implode(', ', $clauses);
                        },
                        $statement
                    );
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

            private function rewriteTemporaryVariantListingGeneratedColumn(string $sql): string
            {
                if ($this->classFile !== 'Migration1656397126AddMainVariantConfiguration.php'
                    || !preg_match('/^\s*(ALTER\s+TABLE\s+`?product`?\s+ADD\s+COLUMN\s+`?variant_listing_config`?\s+JSON)\s+GENERATED\s+ALWAYS\s+AS\s*\([\s\S]*\)\s+VIRTUAL\s*(;?)\s*$/i', $sql, $matches)) {
                    return $sql;
                }

                $this->appliedRules['temporary_variant_listing_generated_column_to_nullable_json'] = ($this->appliedRules['temporary_variant_listing_generated_column_to_nullable_json'] ?? 0) + 1;

                return $matches[1] . ' NULL' . ($matches[2] ?? '');
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
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1654839361ProductDownloadMedia.php'
                    && $node instanceof Node\Stmt\If_
                    && $node->cond instanceof Node\Expr\BooleanNot
                    && $node->cond->expr instanceof Node\Expr\Variable
                    && $node->cond->expr->name === 'folderId') {
                    array_unshift($node->stmts, new Node\Stmt\Expression(new Node\Expr\Assign(
                        new Node\Expr\Variable('folderId'),
                        new Node\Expr\StaticCall(new Node\Name\FullyQualified('Shopware\\Core\\Framework\\Uuid\\Uuid'), 'randomBytes')
                    )));
                    $this->appliedRules['diagnostic_missing_media_folder_id_generated'] = ($this->appliedRules['diagnostic_missing_media_folder_id_generated'] ?? 0) + 1;
                }
                if ($this->diagnosticMode && $node instanceof Node\Stmt\ClassMethod) {
                    $this->currentMethodName = $node->name->toString();
                    $this->methodArrayKeys = [];
                    $this->methodPropertyArrayKeys = [];
                    $this->methodUuidVariables = [];
                    $this->methodUuidRowColumns = [];
                    $this->methodUuidArrays = [];
                }
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1657173907DownloadMailTemplate.php'
                    && $this->currentMethodName === 'fetchLanguageIdByName'
                    && $node instanceof Node\Stmt\Return_
                    && $node->expr instanceof Node\Expr\Cast\String_
                    && $node->expr->expr instanceof Node\Expr\Variable
                    && $node->expr->expr->name === 'result') {
                    $resultForCheck = new Node\Expr\Variable('result');
                    $node->expr = new Node\Expr\Ternary(
                        new Node\Expr\FuncCall(new Node\Name\FullyQualified('is_resource'), [new Node\Arg($resultForCheck)]),
                        new Node\Expr\Cast\String_(new Node\Expr\FuncCall(
                            new Node\Name\FullyQualified('stream_get_contents'),
                            [new Node\Arg(new Node\Expr\Variable('result')), new Node\Arg(new Node\Scalar\Int_(-1)), new Node\Arg(new Node\Scalar\Int_(0))]
                        )),
                        new Node\Expr\Cast\String_(new Node\Expr\Variable('result'))
                    );
                    $this->appliedRules['diagnostic_language_uuid_stream_read_before_cast'] = ($this->appliedRules['diagnostic_language_uuid_stream_read_before_cast'] ?? 0) + 1;
                }
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1659257396DownloadFlow.php'
                    && $this->currentMethodName === 'update'
                    && $node instanceof Node\Stmt\If_
                    && $node->cond instanceof Node\Expr\BinaryOp\Identical
                    && $node->cond->left instanceof Node\Expr\Variable
                    && $node->cond->left->name === 'templateId'
                    && $node->cond->right instanceof Node\Expr\ConstFetch
                    && strtolower($node->cond->right->name->toString()) === 'false') {
                    $result = new Node\Expr\Variable('templateId');
                    $node->cond = new Node\Expr\FuncCall(
                        new Node\Name\FullyQualified('is_resource'),
                        [new Node\Arg($result)]
                    );
                    $node->stmts = [new Node\Stmt\Expression(new Node\Expr\Assign(
                        new Node\Expr\Variable('templateId'),
                        new Node\Expr\Cast\String_(new Node\Expr\FuncCall(
                            new Node\Name\FullyQualified('stream_get_contents'),
                            [new Node\Arg(new Node\Expr\Variable('templateId')), new Node\Arg(new Node\Scalar\Int_(-1)), new Node\Arg(new Node\Scalar\Int_(0))]
                        ))
                    ))];
                    $node->elseifs = [new Node\Stmt\ElseIf_(
                        new Node\Expr\BinaryOp\Identical(new Node\Expr\Variable('templateId'), new Node\Expr\ConstFetch(new Node\Name('false'))),
                        [new Node\Stmt\Expression(new Node\Expr\Assign(new Node\Expr\Variable('templateId'), new Node\Expr\ConstFetch(new Node\Name('null'))))]
                    )];
                    $this->appliedRules['diagnostic_flow_template_uuid_stream_read'] = ($this->appliedRules['diagnostic_flow_template_uuid_stream_read'] ?? 0) + 1;
                }
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1672934282ReviewFormSendFlow.php'
                    && $this->currentMethodName === 'update'
                    && $node instanceof Node\Stmt\If_
                    && $node->cond instanceof Node\Expr\BooleanNot
                    && $node->cond->expr instanceof Node\Expr\Variable
                    && $node->cond->expr->name === 'templateId') {
                    $condition = new Node\Expr\Variable('templateId');
                    $node->cond = new Node\Expr\BinaryOp\BooleanOr(
                        new Node\Expr\FuncCall(new Node\Name\FullyQualified('is_resource'), [new Node\Arg($condition)]),
                        new Node\Expr\BooleanNot(new Node\Expr\Variable('templateId'))
                    );
                    $node->stmts = [new Node\Stmt\Expression(new Node\Expr\Assign(
                        new Node\Expr\Variable('templateId'),
                        new Node\Expr\Ternary(
                            new Node\Expr\FuncCall(new Node\Name\FullyQualified('is_resource'), [new Node\Arg(new Node\Expr\Variable('templateId'))]),
                            new Node\Expr\Cast\String_(new Node\Expr\FuncCall(
                                new Node\Name\FullyQualified('stream_get_contents'),
                                [new Node\Arg(new Node\Expr\Variable('templateId')), new Node\Arg(new Node\Scalar\Int_(-1)), new Node\Arg(new Node\Scalar\Int_(0))]
                            )),
                            new Node\Expr\StaticCall(new Node\Name\FullyQualified('Shopware\\Core\\Framework\\Uuid\\Uuid'), 'randomBytes')
                        )
                    ))];
                    $this->appliedRules['diagnostic_review_flow_template_uuid_stream_read'] = ($this->appliedRules['diagnostic_review_flow_template_uuid_stream_read'] ?? 0) + 1;
                }
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate.php'
                    && $node instanceof Node\Expr\StaticCall
                    && $node->class instanceof Node\Name
                    && $node->class->getLast() === 'Uuid'
                    && $node->name instanceof Node\Identifier
                    && $node->name->toString() === 'fromBytesToHex'
                    && isset($node->args[0])
                    && $node->args[0]->value instanceof Node\Expr\Variable
                    && in_array($node->args[0]->value->name, ['mailTemplateId', 'mailTemplateTypeId'], true)) {
                    $variableName = $node->args[0]->value->name;
                    $result = new Node\Expr\Variable($variableName);
                    $node->args[0]->value = new Node\Expr\Ternary(
                        new Node\Expr\FuncCall(new Node\Name\FullyQualified('is_resource'), [new Node\Arg($result)]),
                        new Node\Expr\Cast\String_(new Node\Expr\FuncCall(
                            new Node\Name\FullyQualified('stream_get_contents'),
                            [new Node\Arg(new Node\Expr\Variable($variableName)), new Node\Arg(new Node\Scalar\Int_(-1)), new Node\Arg(new Node\Scalar\Int_(0))]
                        )),
                        new Node\Expr\Variable($variableName)
                    );
                    $this->appliedRules['diagnostic_flow_template_uuid_bytes_stream_read'] = ($this->appliedRules['diagnostic_flow_template_uuid_bytes_stream_read'] ?? 0) + 1;
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
                if ($this->diagnosticMode && $node instanceof Node\Expr\Assign
                    && $node->var instanceof Node\Expr\ArrayDimFetch
                    && $node->var->var instanceof Node\Expr\PropertyFetch
                    && $node->var->var->var instanceof Node\Expr\Variable
                    && $node->var->var->var->name === 'this'
                    && $node->var->var->name instanceof Node\Identifier
                    && $node->expr instanceof Node\Expr\Array_) {
                    $this->methodPropertyArrayKeys[$node->var->var->name->toString()] = $this->getInsertDataKeys($node->expr);
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
                    if (in_array($methodName, ['executeQuery', 'executeStatement', 'fetchAllAssociative', 'fetchAllKeyValue', 'fetchAssociative', 'fetchOne', 'fetchFirstColumn'], true)) {
                        $this->addBinaryTypesToQueryCall($node);
                    }
                }
                if ($this->diagnosticMode
                    && $node instanceof Node\Expr\MethodCall
                    && $node->name instanceof Node\Identifier
                    && $node->name->toString() === 'addColumn') {
                    $typeArgument = null;
                    foreach ($node->args as $index => $argument) {
                        if (($argument->name?->toString() === 'type') || ($argument->name === null && $index === 3)) {
                            $typeArgument = $argument;

                            break;
                        }
                    }
                    if ($typeArgument?->value instanceof Node\Scalar\String_
                        && preg_match('/^\\s*(?:VAR)?BINARY\\s*\\(\\s*\\d+\\s*\\)\\s*$/i', $typeArgument->value->value)) {
                        $typeArgument->value->value = 'BYTEA';
                        $this->appliedRules['diagnostic_add_column_binary_type_to_bytea'] = ($this->appliedRules['diagnostic_add_column_binary_type_to_bytea'] ?? 0) + 1;
                    }
                }
                if ($this->diagnosticMode && $node instanceof Node\Expr\MethodCall
                    && $node->name instanceof Node\Identifier
                    && $node->name->toString() === 'addInsert'
                    && isset($node->args[1])
                    && !isset($node->args[2])) {
                    $keys = match ($this->classFile) {
                        'Migration1625583619MoveDataFromEventActionToFlow.php' => ['id', 'flow_id', 'rule_id', 'parent_id', 'sales_channel_id'],
                        'Migration1659257296GenerateFlowTemplateDataFromEventAction.php' => ['id'],
                        default => $this->getBinaryInsertKeys($this->getInsertDataKeys($node->args[1]->value)),
                    };
                    if ($keys !== []) {
                        $typeItems = [];
                        foreach ($keys as $key) {
                            $typeItems[] = new Node\Expr\ArrayItem(
                                new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified('Doctrine\\DBAL\\ParameterType'), 'BINARY'),
                                new Node\Scalar\String_($key)
                            );
                        }
                        $node->args[] = new Node\Arg(new Node\Expr\Array_($typeItems));
                        $this->appliedRules['diagnostic_multi_insert_uuid_columns_bind_as_binary'] = ($this->appliedRules['diagnostic_multi_insert_uuid_columns_bind_as_binary'] ?? 0) + count($keys);
                    }
                }
                if ($this->diagnosticMode
                    && $this->classFile === 'Migration1625583619MoveDataFromEventActionToFlow.php'
                    && $node instanceof Node\Expr\New_
                    && $node->class instanceof Node\Name
                    && $node->class->getLast() === 'RetryableQuery'
                    && count($node->args) === 2) {
                    $typeItems = [];
                    foreach (['flowId', 'eventActionId'] as $key) {
                        $typeItems[] = new Node\Expr\ArrayItem(
                            new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified('Doctrine\\DBAL\\ParameterType'), 'BINARY'),
                            new Node\Scalar\String_($key)
                        );
                    }
                    $node->args[] = new Node\Arg(new Node\Expr\Array_($typeItems));
                    $this->appliedRules['diagnostic_retryable_flow_uuid_parameters_bind_as_binary'] = ($this->appliedRules['diagnostic_retryable_flow_uuid_parameters_bind_as_binary'] ?? 0) + 2;
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
                    $sql = $this->rewriteProductPricePercentageUpdate($sql);
                    $sql = $this->rewriteTemporaryVariantListingGeneratedColumn($sql);
                    $sql = $this->rewriteAlterTable($sql);
                    $sql = $this->rewriteShowIndexes($sql);
                    $sql = $this->rewriteDeleteSelfJoin($sql);
                    $sql = $this->rewriteUpdateInnerJoin($sql);
                    $sql = $this->rewriteDerivedUpdateJoin($sql);
                    $sql = $this->rewriteReplaceIntoSet($sql);
                    $sql = $this->rewriteReplaceIntoValues($sql);
                    $sql = $this->rewriteInsertIgnore($sql);
                    $sql = $this->rewriteUpdateIgnore($sql);
                    $sql = $this->rewriteJsonReplaceUpdate($sql);
                    $sql = $this->rewriteJsonObjectCalls($sql);
                    $sql = $this->rewriteJsonExtractCalls($sql);
                    $sql = $this->rewriteGroupConcatCalls($sql);
                    $sql = $this->rewriteUnhexCalls($sql);
                    $sql = $this->rewriteInvalidFlowSequenceDistinct($sql);
                    $sql = $this->rewriteLowerHexBinaryIdentifiers($sql);
                    $sql = $this->rewriteLikeIdentifierOperands($sql);
                    $sql = $this->rewriteJsonEmptyStringComparison($sql);
                    $sql = $this->rewriteQuotedDefaultStrings($sql);
                }
                $sql = $this->rewriteSingleDropForeignKey($sql);
                if ($this->diagnosticMode) {
                    $this->replace(
                        'check_json_valid_to_native_json_validation',
                        '/\bCHECK\s*\(\s*JSON_VALID\s*\(\s*(?:"[^"\r\n]+"|`[^`\r\n]+`)\s*\)\s*\)/i',
                        'CHECK (TRUE)',
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

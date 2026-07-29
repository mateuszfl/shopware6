<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * MySQL/MariaDB dialect. This is the "identity" implementation: every method returns exactly
 * the SQL fragment Shopware hard-coded before the dialect abstraction was introduced, so that
 * existing behaviour and the whole test suite stay byte-for-byte unchanged.
 *
 * @internal
 */
#[Package('framework')]
class MySqlDialect extends AbstractSqlDialect
{
    public function supports(AbstractPlatform $platform): bool
    {
        // AbstractMySQLPlatform covers both MySQLPlatform and MariaDBPlatform.
        return $platform instanceof AbstractMySQLPlatform;
    }

    public function getName(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '`')) {
            throw DataAbstractionLayerException::invalidIdentifier($identifier);
        }

        return '`' . $identifier . '`';
    }

    public function toHex(string $expression): string
    {
        return \sprintf('LOWER(HEX(%s))', $expression);
    }

    public function fromHex(string $expression): string
    {
        return \sprintf('UNHEX(%s)', $expression);
    }

    public function jsonExtract(string $column, string $path): string
    {
        return \sprintf('JSON_EXTRACT(%s, %s)', $column, $path);
    }

    public function jsonExtractTyped(string $column, string $path, JsonExtractType $type): string
    {
        $value = $this->jsonExtract($column, $path);

        $accessor = match ($type) {
            JsonExtractType::INT, JsonExtractType::FLOAT => \sprintf('JSON_UNQUOTE(%s) + 0.0', $value),
            JsonExtractType::BOOL => \sprintf('IF(JSON_UNQUOTE(%s) != "true" AND JSON_UNQUOTE(%s) = 0, 0, 1)', $value, $value),
            JsonExtractType::DATETIME => \sprintf('CAST(JSON_UNQUOTE(%s) AS datetime(3))', $value),
            JsonExtractType::DATE => \sprintf('CAST(JSON_UNQUOTE(%s) AS DATE)', $value),
            // The CONVERT is required for mariadb support (mysqls JSON_UNQUOTE returns utf8mb4)
            JsonExtractType::STRING => \sprintf('CONVERT(JSON_UNQUOTE(%s) USING "utf8mb4") COLLATE utf8mb4_unicode_ci', $value),
        };

        /*
         * Values extracted from json have distinct json types, that are different from normal value types.
         * We need to convert json nulls into sql nulls.
         */
        return \sprintf('IF(JSON_TYPE(%s) != "NULL", %s, NULL)', $value, $accessor);
    }

    public function ifNull(string $expression, string $default): string
    {
        return \sprintf('IFNULL(%s, %s)', $expression, $default);
    }

    public function groupConcat(string $expression, string $separator = ',', bool $distinct = false): string
    {
        return \sprintf(
            'GROUP_CONCAT(%s%s SEPARATOR %s)',
            $distinct ? 'DISTINCT ' : '',
            $expression,
            $this->quoteString($separator)
        );
    }

    public function jsonObject(array $keyExpressionMap): string
    {
        return \sprintf('JSON_OBJECT(%s)', implode(', ', $this->flattenJsonObjectArgs($keyExpressionMap)));
    }

    public function jsonObjectAgg(array $keyExpressionMap): string
    {
        return \sprintf("CONCAT('[', GROUP_CONCAT(DISTINCT %s), ']')", $this->jsonObject($keyExpressionMap));
    }

    public function insertIgnoreKeyword(): string
    {
        return 'INSERT IGNORE';
    }

    public function insertIgnoreSuffix(): string
    {
        return '';
    }

    public function buildUpsertSuffix(array $updateColumns, array $conflictColumns = []): string
    {
        if ($updateColumns === []) {
            return '';
        }

        $parts = [];
        foreach ($updateColumns as $column) {
            $quoted = $this->quoteIdentifier($column);
            // see https://stackoverflow.com/a/2714653/10064036
            $parts[] = \sprintf('%s = VALUES(%s)', $quoted, $quoted);
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $parts);
    }

    public function supportsReplaceInto(): bool
    {
        return true;
    }

    public function likeEscapeClause(): string
    {
        return '';
    }

    public function nullSafeEquals(string $left, string $right): string
    {
        return \sprintf('%s <=> %s', $left, $right);
    }

    public function dateHistogramFormat(string $accessor, string $interval): string
    {
        return match ($interval) {
            DateHistogramAggregation::PER_MINUTE => 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d %H:%i\')',
            DateHistogramAggregation::PER_HOUR => 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d %H\')',
            DateHistogramAggregation::PER_DAY => 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d\')',
            DateHistogramAggregation::PER_WEEK => 'DATE_FORMAT(' . $accessor . ', \'%Y-%v\')',
            DateHistogramAggregation::PER_MONTH => 'DATE_FORMAT(' . $accessor . ', \'%Y-%m\')',
            DateHistogramAggregation::PER_QUARTER => 'CONCAT(DATE_FORMAT(' . $accessor . ', \'%Y\'), \'-\', QUARTER(' . $accessor . '))',
            DateHistogramAggregation::PER_YEAR => 'DATE_FORMAT(' . $accessor . ', \'%Y\')',
            default => 'DATE_FORMAT(' . $accessor . ', \'%Y-%m-%d %H:%i:%s\')',
        };
    }

    /**
     * @param array<string, string> $keyExpressionMap
     *
     * @return list<string>
     */
    private function flattenJsonObjectArgs(array $keyExpressionMap): array
    {
        $args = [];
        foreach ($keyExpressionMap as $key => $expression) {
            $args[] = $this->quoteString($key);
            $args[] = $expression;
        }

        return $args;
    }

    private function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

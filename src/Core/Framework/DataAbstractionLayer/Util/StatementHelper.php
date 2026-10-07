<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final class StatementHelper
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, ParameterType> $types
     */
    public static function executeStatement(Statement $stmt, array $parameters = [], array $types = []): int|string
    {
        self::bindParameters($stmt, $parameters, $types);

        return $stmt->executeStatement();
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, ParameterType> $types
     */
    public static function executeQuery(Statement $stmt, array $parameters = [], array $types = []): Result
    {
        self::bindParameters($stmt, $parameters, $types);

        return $stmt->executeQuery();
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, ParameterType> $types
     */
    public static function bindParameters(Statement $stmt, array $parameters, array $types = []): void
    {
        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? ParameterType::STRING);
        }
    }
}

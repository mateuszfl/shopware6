<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;

/**
 * SQL and its bind values must travel together. Identifiers are quoted by the dialect before construction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SqlFragment
{
    /**
     * @param list<mixed> $parameters
     * @param list<ParameterType> $types
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $parameters = [],
        public readonly array $types = [],
    ) {
    }
}

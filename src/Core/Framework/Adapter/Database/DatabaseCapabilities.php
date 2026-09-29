<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class DatabaseCapabilities
{
    public function __construct(
        public readonly bool $supportsReturning,
        public readonly bool $supportsConflictTarget,
        public readonly bool $supportsJsonb,
        public readonly bool $supportsUpdateFrom,
    ) {
    }
}

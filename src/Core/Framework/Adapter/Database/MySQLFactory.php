<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Shopware\Core\Framework\Log\Package;

/**
 * Compatibility entry point for extensions creating DBAL connections.
 *
 * @internal
 */
#[Package('framework')]
class MySQLFactory extends DatabaseConnectionFactory
{
}

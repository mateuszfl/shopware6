<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Shopware\Core\Framework\Log\Package;

/**
 * A separate contract for gateway operations whose join layout differs by platform.
 * Conditions are gateway-owned SQL with values passed separately.
 *
 * @internal
 */
#[Package('framework')]
interface DatabaseQueryShape
{
    /**
     * @param array<string, mixed> $assignments Target column => value
     * @param list<mixed> $conditionParameters
     */
    public function updateJoined(string $table, string $joinedTable, string $condition, array $assignments, array $conditionParameters = []): SqlFragment;

    /**
     * @param list<mixed> $conditionParameters
     */
    public function deleteJoined(string $table, string $joinedTable, string $condition, array $conditionParameters = []): SqlFragment;
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1000000002Second extends MigrationStep
{
    public static bool $fail = false;

    public static bool $typeError = false;

    public static bool $commitInside = false;

    public function getCreationTimestamp(): int
    {
        return 1000000002;
    }

    public function update(Connection $connection): void
    {
        // MySQL can retain partial autocommit writes; a retry is explicitly idempotent.
        $connection->delete('migration_contract_event', ['step' => 'second']);
        $connection->insert('migration_contract_event', ['step' => 'second', 'engine' => $this->engine()]);
        if (self::$commitInside) {
            $connection->commit();
        }
        if (self::$fail) {
            $connection->executeStatement('INSERT INTO migration_contract_missing_table (id) VALUES (1)');
        }
        if (self::$typeError) {
            throw new \TypeError('migration contract type error: żółć');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        if (self::$fail) {
            $connection->executeStatement('INSERT INTO migration_contract_missing_table (id) VALUES (1)');
        }
        $connection->insert('migration_contract_event', ['step' => 'second-destructive', 'engine' => $this->engine()]);
    }

    protected function engine(): string
    {
        return 'mysql';
    }
}

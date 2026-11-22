<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1565346846Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565346846;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'promotion',
            'use_setgroups',
            'SMALLINT CONSTRAINT chk_sw_promotion_use_setgroups_bool CHECK (use_setgroups IN (0, 1))',
            nullable: false,
            default: '0'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

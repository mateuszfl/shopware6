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
class Migration1559134989Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559134989;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'promotion',
            'use_individual_codes',
            'SMALLINT CONSTRAINT chk_sw_promotion_use_individual_codes_bool CHECK (use_individual_codes IN (0, 1))',
            nullable: false,
            default: '0'
        );
        $this->addColumn($connection, 'promotion', 'individual_code_pattern', 'VARCHAR(255) COLLATE sw_type_ci');
        $connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS promotion__individual_code_pattern ON promotion (individual_code_pattern)');
    }
}

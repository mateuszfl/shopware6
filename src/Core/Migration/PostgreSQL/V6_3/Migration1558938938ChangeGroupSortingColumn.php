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
class Migration1558938938ChangeGroupSortingColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558938938;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product', 'configurator_group_config', 'JSONB');
        $this->dropColumnIfExists($connection, 'product', 'configurator_group_sorting');
        $this->addColumn(
            $connection,
            'product',
            'display_in_listing',
            'SMALLINT CONSTRAINT chk_sw_product_display_in_listing_bool CHECK (display_in_listing IN (0, 1))',
            default: '1'
        );
    }
}

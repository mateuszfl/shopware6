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
class Migration1561377793AddAvailableAsShippingCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561377793;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'country',
            'shipping_available',
            'SMALLINT CONSTRAINT chk_sw_country_shipping_available_bool CHECK (shipping_available IN (0, 1))',
            nullable: false,
            default: '1'
        );
    }
}

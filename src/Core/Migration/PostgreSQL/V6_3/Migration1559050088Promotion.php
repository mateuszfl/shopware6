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
class Migration1559050088Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559050088;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'promotion',
            'customer_restriction',
            'SMALLINT CONSTRAINT chk_sw_promotion_customer_restriction_bool CHECK (customer_restriction IN (0, 1))',
            nullable: false,
            default: '0'
        );
    }
}

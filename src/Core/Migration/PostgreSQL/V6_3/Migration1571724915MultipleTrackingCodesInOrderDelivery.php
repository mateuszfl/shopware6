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
class Migration1571724915MultipleTrackingCodesInOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571724915;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_delivery', 'tracking_codes', 'JSON');

        $constraintExists = $connection->fetchOne(
            'SELECT 1 FROM pg_constraint WHERE conname = :name AND conrelid = to_regclass(:table)',
            ['name' => 'json.order_delivery.tracking_codes', 'table' => 'order_delivery']
        );
        if ($constraintExists === false) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE order_delivery
                ADD CONSTRAINT "json.order_delivery.tracking_codes"
                CHECK (tracking_codes IS NULL OR jsonb_typeof(tracking_codes::jsonb) IS NOT NULL)
                SQL);
        }

        if ($this->columnExists($connection, 'order_delivery', 'tracking_code')) {
            $connection->executeStatement(<<<'SQL'
                UPDATE order_delivery
                SET tracking_codes = CASE
                    WHEN tracking_code IS NULL OR tracking_code = '' THEN '[]'::json
                    ELSE json_build_array(tracking_code)::json
                END
                WHERE tracking_codes IS NULL
                SQL);
        }

        $connection->executeStatement('ALTER TABLE order_delivery ALTER COLUMN tracking_codes SET NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'order_delivery', 'tracking_code');
    }
}

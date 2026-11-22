<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1561712450NewSystemConfigsAndDefaultValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561712450;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE system_config IN SHARE ROW EXCLUSIVE MODE');

            $defaults = [
                'core.cart.maxQuantity' => '100',
                'core.loginRegistration.passwordMinLength' => '8',
                'core.address.showZipcodeInFrontOfCity' => true,
            ];
            foreach ($defaults as $key => $value) {
                $scope = $key === 'core.cart.maxQuantity' ? ' AND sales_channel_id IS NULL' : '';
                if ($connection->fetchOne('SELECT 1 FROM system_config WHERE configuration_key = ?' . $scope, [$key]) !== false) {
                    continue;
                }

                $connection->insert('system_config', [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => $key,
                    'configuration_value' => json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
            }
        });
    }
}

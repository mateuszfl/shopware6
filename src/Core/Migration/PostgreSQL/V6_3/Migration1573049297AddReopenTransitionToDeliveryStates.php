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
class Migration1573049297AddReopenTransitionToDeliveryStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1573049297;
    }

    public function update(Connection $connection): void
    {
        $stateMachineId = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = ?', ['order_delivery.state']);
        if (\is_resource($stateMachineId)) {
            $stateMachineId = stream_get_contents($stateMachineId);
        }
        if (!\is_string($stateMachineId)) {
            return;
        }

        $states = $connection->fetchAllAssociative(
            'SELECT id, technical_name FROM state_machine_state WHERE state_machine_id = :stateMachineId AND technical_name IN (\'open\', \'cancelled\')',
            ['stateMachineId' => $stateMachineId],
            ['stateMachineId' => ParameterType::BINARY],
        );
        $stateIds = [];
        foreach ($states as $state) {
            $stateId = $state['id'];
            if (\is_resource($stateId)) {
                $stateId = stream_get_contents($stateId);
            }
            if (\is_string($stateId)) {
                $stateIds[$state['technical_name']] = $stateId;
            }
        }
        if (!isset($stateIds['open'], $stateIds['cancelled'])) {
            return;
        }

        $exists = $connection->fetchOne(
            'SELECT 1 FROM state_machine_transition WHERE state_machine_id = :machineId AND action_name = :action AND from_state_id = :fromId AND to_state_id = :toId',
            ['machineId' => $stateMachineId, 'action' => 'reopen', 'fromId' => $stateIds['cancelled'], 'toId' => $stateIds['open']],
            ['machineId' => ParameterType::BINARY, 'fromId' => ParameterType::BINARY, 'toId' => ParameterType::BINARY],
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('state_machine_transition', [
            'id' => Uuid::randomBytes(),
            'state_machine_id' => $stateMachineId,
            'action_name' => 'reopen',
            'from_state_id' => $stateIds['cancelled'],
            'to_state_id' => $stateIds['open'],
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1591361320ChargebackAndAuthorized extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591361320;
    }

    public function update(Connection $connection): void
    {
        $machineId = $this->getMachineId($connection, 'order_transaction.state');
        if ($machineId === null) {
            return;
        }

        $state = [
            'technical_name' => 'authorized',
            'translations' => ['en-GB' => 'Authorized', 'de-DE' => 'Autorisiert'],
            'transition' => 'authorize',
            'from' => $this->getStateIds($connection, $machineId, ['open', 'in_progress', 'reminded']),
            'to' => [
                'paid' => $this->getStateId($connection, $machineId, 'paid'),
                'paid_partially' => $this->getStateId($connection, $machineId, 'paid_partially'),
                'fail' => $this->getStateId($connection, $machineId, 'failed'),
                'cancel' => $this->getStateId($connection, $machineId, 'cancelled'),
            ],
        ];
        $this->insertState($connection, $state, $machineId);

        $state = [
            'technical_name' => 'chargeback',
            'translations' => ['en-GB' => 'Chargeback', 'de-DE' => 'Rückbuchung'],
            'transition' => 'chargeback',
            'from' => $this->getStateIds($connection, $machineId, ['paid', 'paid_partially']),
            'to' => [
                'paid' => $this->getStateId($connection, $machineId, 'paid'),
                'paid_partially' => $this->getStateId($connection, $machineId, 'paid_partially'),
                'cancel' => $this->getStateId($connection, $machineId, 'cancelled'),
            ],
        ];
        $this->insertState($connection, $state, $machineId);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param array<string, mixed> $state
     */
    private function insertState(Connection $connection, array $state, string $machineId): void
    {
        $stateId = Uuid::randomBytes();
        try {
            $connection->executeStatement(
                'INSERT INTO "state_machine_state" ("id", "technical_name", "state_machine_id", "created_at")
                 VALUES (:id, :technical_name, :state_machine_id, :created_at)',
                [
                    'id' => $stateId,
                    'technical_name' => $state['technical_name'],
                    'state_machine_id' => Uuid::fromHexToBytes($machineId),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                ['id' => ParameterType::BINARY, 'state_machine_id' => ParameterType::BINARY]
            );
        } catch (UniqueConstraintViolationException) {
            // don't add states if they already exist
            return;
        }

        $stateIdHex = Uuid::fromBytesToHex($stateId);
        $languages = array_unique(array_filter([$this->getLanguageId('en-GB', $connection), Defaults::LANGUAGE_SYSTEM]));
        foreach ($languages as $language) {
            $this->insertTranslation($stateIdHex, $state['translations']['en-GB'], $language, $connection);
        }

        foreach (array_filter([$this->getLanguageId('de-DE', $connection)]) as $language) {
            $this->insertTranslation($stateIdHex, $state['translations']['de-DE'], $language, $connection);
        }

        foreach ($state['from'] as $fromId) {
            if ($fromId !== null) {
                $this->insertTransition($state['transition'], $machineId, $fromId, $stateIdHex, $connection);
            }
        }

        foreach ($state['to'] as $action => $toId) {
            if ($toId !== null) {
                $this->insertTransition($action, $machineId, $stateIdHex, $toId, $connection);
            }
        }
    }

    private function insertTransition(string $action, string $machineId, string $from, string $to, Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO "state_machine_transition" ("id", "action_name", "state_machine_id", "from_state_id", "to_state_id", "created_at")
             VALUES (:id, :action_name, :state_machine_id, :from_state_id, :to_state_id, :created_at)
             ON CONFLICT ("action_name", "state_machine_id", "from_state_id", "to_state_id") DO NOTHING',
            [
                'id' => Uuid::randomBytes(),
                'action_name' => $action,
                'state_machine_id' => Uuid::fromHexToBytes($machineId),
                'from_state_id' => Uuid::fromHexToBytes($from),
                'to_state_id' => Uuid::fromHexToBytes($to),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => ParameterType::BINARY,
                'state_machine_id' => ParameterType::BINARY,
                'from_state_id' => ParameterType::BINARY,
                'to_state_id' => ParameterType::BINARY,
            ]
        );
    }

    private function insertTranslation(string $stateId, string $name, string $languageId, Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO "state_machine_state_translation" ("language_id", "state_machine_state_id", "name", "created_at")
             VALUES (:language_id, :state_machine_state_id, :name, :created_at)
             ON CONFLICT ("language_id", "state_machine_state_id") DO NOTHING',
            [
                'language_id' => Uuid::fromHexToBytes($languageId),
                'state_machine_state_id' => Uuid::fromHexToBytes($stateId),
                'name' => $name,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['language_id' => ParameterType::BINARY, 'state_machine_state_id' => ParameterType::BINARY]
        );
    }

    private function getLanguageId(string $locale, Connection $connection): ?string
    {
        return $this->normalizeHexUuid($connection->fetchOne(
            'SELECT LOWER(encode("language"."id", \'hex\'))
             FROM "language"
             INNER JOIN "locale" ON "locale"."id" = "language"."translation_code_id"
                 AND "locale"."code" = :locale',
            ['locale' => $locale]
        ));
    }

    private function getMachineId(Connection $connection, string $name): ?string
    {
        return $this->normalizeHexUuid($connection->fetchOne(
            'SELECT LOWER(encode("id", \'hex\')) FROM "state_machine" WHERE "technical_name" = :name',
            ['name' => $name]
        ));
    }

    private function getStateId(Connection $connection, string $machineId, string $name): ?string
    {
        return $this->normalizeHexUuid($connection->fetchOne(
            'SELECT LOWER(encode("id", \'hex\')) FROM "state_machine_state"
             WHERE "technical_name" = :name AND "state_machine_id" = :id',
            ['name' => $name, 'id' => Uuid::fromHexToBytes($machineId)],
            ['id' => ParameterType::BINARY]
        ));
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function getStateIds(Connection $connection, string $machineId, array $names): array
    {
        $ids = $connection->fetchFirstColumn(
            'SELECT LOWER(encode("id", \'hex\')) FROM "state_machine_state"
             WHERE "technical_name" IN (:name) AND "state_machine_id" = :id',
            ['name' => $names, 'id' => Uuid::fromHexToBytes($machineId)],
            ['name' => ArrayParameterType::STRING, 'id' => ParameterType::BINARY]
        );

        return array_values(array_filter(array_map(fn (mixed $id): ?string => $this->normalizeHexUuid($id), $ids)));
    }

    private function normalizeHexUuid(mixed $value): ?string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }
}

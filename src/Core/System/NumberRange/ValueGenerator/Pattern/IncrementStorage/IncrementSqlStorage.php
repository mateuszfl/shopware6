<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\System\NumberRange\ValueGenerator\IncrementSqlStorageTest
 */
#[Package('framework')]
class IncrementSqlStorage extends AbstractIncrementStorage
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public function reserve(array $config): int
    {
        $start = $config['start'] ?? 1;
        if ((new DatabasePlatformResolver($this->connection))->capabilities()->supportsReturning) {
            return (int) $this->connection->fetchOne(
                'INSERT INTO number_range_state (id, last_value, number_range_id, created_at)
                 VALUES (:stateId, :value, :id, :createdAt)
                 ON CONFLICT (number_range_id) DO UPDATE
                 SET last_value = GREATEST(number_range_state.last_value + 1, EXCLUDED.last_value)
                 RETURNING last_value',
                [
                    'stateId' => Uuid::randomBytes(),
                    'value' => $start,
                    'id' => Uuid::fromHexToBytes($config['id']),
                    'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                ['stateId' => ParameterType::BINARY, 'id' => ParameterType::BINARY, 'value' => ParameterType::INTEGER]
            );
        }

        $varname = Uuid::randomHex();
        $stateId = Uuid::randomBytes();
        $this->connection->executeStatement(
            'INSERT `number_range_state` (`id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = @nr' . $varname . ' := IF(`last_value`+1 > :value, `last_value`+1, :value)',
            [
                'value' => $start,
                'id' => Uuid::fromHexToBytes($config['id']),
                'stateId' => $stateId,
                'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['stateId' => ParameterType::BINARY, 'id' => ParameterType::BINARY, 'value' => ParameterType::INTEGER]
        );

        $result = $this->connection->executeQuery('SELECT @nr' . $varname);

        $lastNumber = $result->fetchOne();

        if (!$lastNumber) {
            return $start;
        }

        return (int) $lastNumber;
    }

    public function preview(array $config): int
    {
        $result = $this->connection->executeQuery(
            'SELECT ' . $this->connection->quoteSingleIdentifier('last_value') . ' FROM number_range_state WHERE number_range_id = :id',
            [
                'id' => Uuid::fromHexToBytes($config['id']),
            ],
            ['id' => ParameterType::BINARY]
        );
        $lastNumber = $result->fetchOne();

        $start = (int) ($config['start'] ?? 1);

        if ($lastNumber === false || (int) $lastNumber < $start) {
            $nextNumber = $start;
        } else {
            $nextNumber = $lastNumber + 1;
        }

        return $nextNumber;
    }

    public function list(): array
    {
        /** @var array<string, string> $states */
        $states = $this->connection->fetchAllKeyValue('
            SELECT sw_uuid_hex(number_range_id), ' . $this->connection->quoteSingleIdentifier('last_value') . '
            FROM number_range_state
        ');

        return array_map(static fn ($state) => (int) $state, $states);
    }

    public function set(string $configurationId, int $value): void
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $query = $dialect->upsert('number_range_state', [
            'id' => Uuid::randomBytes(),
            'last_value' => $value,
            'number_range_id' => Uuid::fromHexToBytes($configurationId),
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], ['number_range_id'], ['last_value'], [
            'id' => ParameterType::BINARY,
            'number_range_id' => ParameterType::BINARY,
            'last_value' => ParameterType::INTEGER,
        ]);
        $this->connection->executeStatement($query->sql, $query->parameters, $query->types);
    }

    public function increaseToAtLeast(string $configurationId, int $value): void
    {
        $lastValue = $this->connection->quoteSingleIdentifier('last_value');
        $conflict = (new DatabasePlatformResolver($this->connection))->capabilities()->supportsConflictTarget
            ? 'ON CONFLICT (number_range_id) DO UPDATE SET last_value = GREATEST(number_range_state.last_value, EXCLUDED.last_value)'
            : 'ON DUPLICATE KEY UPDATE ' . $lastValue . ' = GREATEST(' . $lastValue . ', :value)';
        $this->connection->executeStatement(
            'INSERT INTO number_range_state (id, ' . $lastValue . ', number_range_id, created_at) VALUES (:stateId, :value, :id, :createdAt) ' . $conflict,
            [
                'value' => $value,
                'id' => Uuid::fromHexToBytes($configurationId),
                'stateId' => Uuid::randomBytes(),
                'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['value' => ParameterType::INTEGER, 'id' => ParameterType::BINARY, 'stateId' => ParameterType::BINARY],
        );
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @final
 */
#[Package('framework')]
class IteratorFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry
    ) {
    }

    /**
     * @param array{offset: int|null}|null $lastId
     */
    public function createIterator(string|EntityDefinition $definition, ?array $lastId = null, int $limit = 50, ?string $versionId = null): IterableQuery
    {
        if (\is_string($definition)) {
            $definition = $this->registry->getByEntityName($definition);
        }

        $entity = $definition->getEntityName();

        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $escaped = EntityDefinitionQueryHelper::escape($entity, $this->connection);
        $id = $dialect->hex([$entity, 'id']);
        $query = new QueryBuilder($this->connection);
        $query->from($escaped);
        $query->setMaxResults($limit);

        if ($definition->isVersionAware() && $versionId !== null) {
            $query->andWhere($escaped . '.version_id = :versionId');
            $query->setParameter('versionId', Uuid::fromHexToBytes($versionId), ParameterType::BINARY);
        }

        if ($definition->hasAutoIncrement()) {
            $query->select($escaped . '.auto_increment', $id . ' as id');
            $query->andWhere($escaped . '.auto_increment > :lastId');
            $query->addOrderBy($escaped . '.auto_increment');
            $query->setParameter('lastId', 0, ParameterType::INTEGER);

            if ($lastId !== null) {
                $query->setParameter('lastId', $lastId['offset'], ParameterType::INTEGER);
            }

            return new LastIdQuery($query);
        }

        $key = $escaped . '.id';
        if ($definition->isVersionAware() && $versionId === null) {
            // Without a version filter the iterator covers physical versions. Keep both rows in a batch even when
            // they have the same entity ID; consumers use the values while the composite key advances the offset.
            $key = $this->connection->getDatabasePlatform()->getConcatExpression($id, $dialect->hex([$entity, 'version_id']));
        }
        $query->select($key, $id);
        $query->addOrderBy($escaped . '.id');
        if ($definition->isVersionAware()) {
            $query->addOrderBy($escaped . '.version_id');
        }
        $query->setFirstResult(0);
        if ($lastId !== null) {
            $query->setFirstResult((int) $lastId['offset']);
        }

        return new OffsetQuery($query);
    }
}

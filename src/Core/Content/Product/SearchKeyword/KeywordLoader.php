<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class KeywordLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<array{normal: list<string>, reversed: list<string>}> $tokenSlops
     *
     * @return list<list<string>>
     */
    public function fetch(array $tokenSlops, Context $context): array
    {
        if ($tokenSlops === []) {
            return [];
        }

        $query = new QueryBuilder($this->connection);
        $query->select('keyword');
        $query->from('product_keyword_dictionary');

        $query->setTitle('search::detect-keywords');

        $counter = 0;
        $wheres = [];
        $index = 0;

        foreach ($tokenSlops as $slops) {
            $slopsWheres = [];
            foreach ($slops['normal'] as $slop) {
                ++$counter;
                $slopsWheres[] = 'keyword LIKE :reg' . $counter;
                $query->setParameter('reg' . $counter, $slop);
            }
            foreach ($slops['reversed'] as $slop) {
                ++$counter;
                $slopsWheres[] = 'reversed LIKE :reg' . $counter;
                $query->setParameter('reg' . $counter, $slop);
            }
            $query->addSelect('CASE WHEN ' . ($slopsWheres === [] ? '1 = 0' : implode(' OR ', $slopsWheres)) . ' THEN 1 ELSE 0 END AS ' . $this->connection->quoteSingleIdentifier((string) $index++));
            $wheres = array_merge($wheres, $slopsWheres);
        }

        $query->andWhere('language_id = :language');
        $query->andWhere('(' . ($wheres === [] ? '1 = 0' : implode(' OR ', $wheres)) . ')');
        $query->addOrderBy('keyword', 'ASC');

        $query->setParameter('language', Uuid::fromHexToBytes($context->getLanguageId()), ParameterType::BINARY);

        return $query->executeQuery()->fetchAllNumeric();
    }
}

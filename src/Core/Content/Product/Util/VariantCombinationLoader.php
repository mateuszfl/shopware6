<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\IndexingSql;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class VariantCombinationLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<array<string>>
     */
    public function load(string $productId, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(IndexingSql::hex($this->connection, 'product.id'), 'product.option_ids as options', 'product.product_number as ' . $this->connection->quoteSingleIdentifier('productNumber'), 'product.type as type');

        if (!Feature::isActive('v6.8.0.0')) {
            $query->addSelect('product.states as ' . $this->connection->quoteSingleIdentifier('productStates'));
        }

        $query->from('product');
        $query->where('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->setParameter('id', Uuid::fromHexToBytes($productId), ParameterType::BINARY);
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()), ParameterType::BINARY);
        $query->andWhere('product.option_ids IS NOT NULL');

        $combinations = $query->executeQuery()->fetchAllAssociative();
        $combinations = FetchModeHelper::groupUnique($combinations);
        /** @var array<string, array{options: string, productNumber: string, type: string, productStates?: string}> $combinations */
        foreach ($combinations as &$combination) {
            $combination['options'] = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $combinations;
    }
}

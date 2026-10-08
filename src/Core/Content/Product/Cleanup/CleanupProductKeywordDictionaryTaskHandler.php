<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: CleanupProductKeywordDictionaryTask::class)]
final class CleanupProductKeywordDictionaryTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly Connection $connection
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        do {
            $result = RetryableQuery::retryable(
                $this->connection,
                fn (): int => (int) $this->connection->executeStatement(
                    'DELETE FROM product_keyword_dictionary WHERE id IN (SELECT id FROM (SELECT id FROM product_keyword_dictionary WHERE keyword NOT IN (SELECT DISTINCT keyword FROM product_search_keyword) ORDER BY id LIMIT 1000) unused_keywords)',
                )
            );
        } while ($result > 0);
    }
}

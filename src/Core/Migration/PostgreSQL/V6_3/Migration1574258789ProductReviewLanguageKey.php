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
class Migration1574258789ProductReviewLanguageKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574258789;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE product_review DROP CONSTRAINT IF EXISTS "fk.product_review.language_id"');
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE product_review
            ADD CONSTRAINT "fk.product_review.language_id" FOREIGN KEY (language_id)
            REFERENCES language (id) ON DELETE RESTRICT ON UPDATE CASCADE
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

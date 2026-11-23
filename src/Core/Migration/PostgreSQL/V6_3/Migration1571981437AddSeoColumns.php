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
class Migration1571981437AddSeoColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571981437;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_translation', 'meta_description', 'VARCHAR(255) COLLATE sw_type_ci');
        if ($this->columnExists($connection, 'product_translation', 'additional_text')) {
            $connection->executeStatement('UPDATE product_translation SET meta_description = additional_text WHERE meta_description IS NULL');
        }

        $this->addColumn($connection, 'category_translation', 'meta_title', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'category_translation', 'meta_description', 'VARCHAR(255) COLLATE sw_type_ci');
        $this->addColumn($connection, 'category_translation', 'keywords', 'VARCHAR(255) COLLATE sw_type_ci');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product_translation', 'additional_text');
    }
}

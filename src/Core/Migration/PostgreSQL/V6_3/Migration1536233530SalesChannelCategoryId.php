<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536233530SalesChannelCategoryId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233530;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'category',
            'cms_page_id',
            'BYTEA CONSTRAINT chk_sw_category_cms_page_id_uuid CHECK (octet_length(cms_page_id) = 16)'
        );

        if (!TableHelper::foreignKeyExists($connection, 'category', 'fk.category.cms_page_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE category
                    ADD CONSTRAINT "fk.category.cms_page_id" FOREIGN KEY (cms_page_id)
                    REFERENCES cms_page (id) ON DELETE RESTRICT ON UPDATE CASCADE
                SQL);
        }
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "category__fk.category.cms_page_id" ON category (cms_page_id)');

        $this->addColumn($connection, 'category_translation', 'slot_config', 'JSONB');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE sales_channel
                DROP CONSTRAINT IF EXISTS "fk.sales_channel.navigation_id",
                DROP COLUMN IF EXISTS navigation_id,
                DROP COLUMN IF EXISTS navigation_version_id
            SQL);
        $connection->executeStatement('DROP TABLE IF EXISTS navigation_translation');
        $connection->executeStatement('DROP TABLE IF EXISTS navigation');
    }
}

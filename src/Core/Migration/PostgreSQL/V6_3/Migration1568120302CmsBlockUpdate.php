<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1568120302CmsBlockUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568120302;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'cms_block',
            'cms_section_id',
            'BYTEA CONSTRAINT chk_sw_cms_block_cms_section_id_uuid CHECK (octet_length(cms_section_id) = 16)',
        );
        $this->addColumn($connection, 'cms_block', 'section_position', 'VARCHAR(50) COLLATE sw_type_ci', default: '\'main\'');

        foreach ($connection->fetchFirstColumn('SELECT id FROM cms_page') as $pageId) {
            if (\is_resource($pageId)) {
                $pageId = stream_get_contents($pageId);
            }

            $sectionId = $connection->fetchOne(
                'SELECT id FROM cms_section WHERE cms_page_id = :pageId ORDER BY "position", id LIMIT 1',
                ['pageId' => $pageId],
                ['pageId' => ParameterType::BINARY]
            );
            if (\is_resource($sectionId)) {
                $sectionId = stream_get_contents($sectionId);
            }

            if (!\is_string($sectionId)) {
                $sectionId = Uuid::randomBytes();
                $connection->insert('cms_section', [
                    'id' => $sectionId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'cms_page_id' => $pageId,
                    'locked' => 0,
                    'position' => 0,
                    'type' => 'default',
                    'sizing_mode' => 'boxed',
                    'mobile_behavior' => 'wrap',
                ], [
                    'id' => ParameterType::BINARY,
                    'created_at' => ParameterType::STRING,
                    'cms_page_id' => ParameterType::BINARY,
                    'locked' => ParameterType::INTEGER,
                    'position' => ParameterType::INTEGER,
                    'type' => ParameterType::STRING,
                    'sizing_mode' => ParameterType::STRING,
                    'mobile_behavior' => ParameterType::STRING,
                ]);
            }

            $connection->executeStatement(
                'UPDATE cms_block SET cms_section_id = :sectionId WHERE cms_page_id = :pageId AND cms_section_id IS NULL',
                ['sectionId' => $sectionId, 'pageId' => $pageId],
                ['sectionId' => ParameterType::BINARY, 'pageId' => ParameterType::BINARY]
            );
        }

        $connection->executeStatement('ALTER TABLE cms_block ALTER COLUMN cms_page_id DROP NOT NULL');
        $this->dropForeignKeyIfExists($connection, 'cms_block', 'fk.cms_block.cms_page_id');

        if (!TableHelper::foreignKeyExists($connection, 'cms_block', 'fk.cms_block.cms_section_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE cms_block ADD CONSTRAINT "fk.cms_block.cms_section_id" FOREIGN KEY (cms_section_id)
                    REFERENCES cms_section (id) ON DELETE CASCADE ON UPDATE CASCADE
                SQL);
        }

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "cms_block__fk.cms_block.cms_section_id" ON cms_block (cms_section_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'cms_block', 'cms_page_id');
        $this->dropColumnIfExists($connection, 'cms_block', 'sizing_mode');
    }
}

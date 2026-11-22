<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1570459127AddCmsSidebarLayout extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570459127;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
            $languageDe = $this->getLanguageDeDeId($connection, $languageEn);
            $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

            $this->addFilterPanelToDefault($connection, $createdAt, $languageEn, $versionId);
            $this->addDefaultLayoutWithSidebar($connection, $createdAt, $languageEn, $languageDe, $versionId);
        });
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addFilterPanelToDefault(Connection $connection, string $createdAt, string $languageEn, string $versionId): void
    {
        $cmsPageId = $this->findDefaultLayoutId($connection);
        if ($cmsPageId === null) {
            return;
        }

        $sectionId = $connection->fetchOne(
            'SELECT id FROM cms_section WHERE cms_page_id = :pageId ORDER BY "position", id LIMIT 1',
            ['pageId' => $cmsPageId],
            ['pageId' => ParameterType::BINARY]
        );
        $sectionId = $this->normalizeBinaryValue($sectionId);
        if ($sectionId === null) {
            return;
        }

        $filterBlockId = $this->id(1);
        $blockExists = $connection->fetchOne('SELECT 1 FROM cms_block WHERE id = ?', [$filterBlockId], [ParameterType::BINARY]) !== false;
        if (!$blockExists) {
            $connection->executeStatement(
                'UPDATE cms_block SET position = position + 1 WHERE cms_section_id = :sectionId AND position > 0',
                ['sectionId' => $sectionId],
                ['sectionId' => ParameterType::BINARY]
            );
            $this->insertIfMissing($connection, 'cms_block', [
                'id' => $filterBlockId,
                'cms_section_id' => $sectionId,
                'position' => 1,
                'locked' => 1,
                'type' => 'sidebar-filter',
                'name' => 'Filter',
                'created_at' => $createdAt,
            ], ['id'], [
                'id' => ParameterType::BINARY,
                'cms_section_id' => ParameterType::BINARY,
                'position' => ParameterType::INTEGER,
                'locked' => ParameterType::INTEGER,
                'type' => ParameterType::STRING,
                'name' => ParameterType::STRING,
                'created_at' => ParameterType::STRING,
            ]);
        }

        $filterSlotId = $this->id(2);
        $this->insertIfMissing($connection, 'cms_slot', [
            'id' => $filterSlotId,
            'locked' => 1,
            'cms_block_id' => $filterBlockId,
            'type' => 'sidebar-filter',
            'slot' => 'content',
            'created_at' => $createdAt,
            'version_id' => $versionId,
        ], ['id', 'version_id'], [
            'id' => ParameterType::BINARY,
            'locked' => ParameterType::INTEGER,
            'cms_block_id' => ParameterType::BINARY,
            'type' => ParameterType::STRING,
            'slot' => ParameterType::STRING,
            'created_at' => ParameterType::STRING,
            'version_id' => ParameterType::BINARY,
        ]);
        $this->insertIfMissing($connection, 'cms_slot_translation', [
            'cms_slot_id' => $filterSlotId,
            'cms_slot_version_id' => $versionId,
            'language_id' => $languageEn,
            'created_at' => $createdAt,
            'config' => null,
        ], ['cms_slot_id', 'cms_slot_version_id', 'language_id'], [
            'cms_slot_id' => ParameterType::BINARY,
            'cms_slot_version_id' => ParameterType::BINARY,
            'language_id' => ParameterType::BINARY,
            'created_at' => ParameterType::STRING,
            'config' => ParameterType::NULL,
        ]);
    }

    private function findDefaultLayoutId(Connection $connection): ?string
    {
        return $this->normalizeBinaryValue($connection->fetchOne(<<<'SQL'
            SELECT cms_page.id
            FROM cms_page_translation
            INNER JOIN cms_page ON cms_page.id = cms_page_translation.cms_page_id
            WHERE cms_page.locked = 1 AND cms_page_translation.name = :name
            LIMIT 1
            SQL, ['name' => 'Default category layout']));
    }

    private function addDefaultLayoutWithSidebar(Connection $connection, string $createdAt, string $languageEn, ?string $languageDe, string $versionId): void
    {
        $pageId = $this->id(3);
        $this->insertIfMissing($connection, 'cms_page', [
            'id' => $pageId,
            'type' => 'product_list',
            'locked' => 1,
            'created_at' => $createdAt,
        ], ['id'], [
            'id' => ParameterType::BINARY,
            'type' => ParameterType::STRING,
            'locked' => ParameterType::INTEGER,
            'created_at' => ParameterType::STRING,
        ]);
        $this->insertIfMissing($connection, 'cms_page_translation', [
            'cms_page_id' => $pageId,
            'language_id' => $languageEn,
            'name' => 'Default category layout with sidebar',
            'created_at' => $createdAt,
        ], ['cms_page_id', 'language_id'], [
            'cms_page_id' => ParameterType::BINARY,
            'language_id' => ParameterType::BINARY,
            'name' => ParameterType::STRING,
            'created_at' => ParameterType::STRING,
        ]);
        if ($languageDe !== null) {
            $this->insertIfMissing($connection, 'cms_page_translation', [
                'cms_page_id' => $pageId,
                'language_id' => $languageDe,
                'name' => 'Standard Kategorie-Layout mit Sidebar',
                'created_at' => $createdAt,
            ], ['cms_page_id', 'language_id'], [
                'cms_page_id' => ParameterType::BINARY,
                'language_id' => ParameterType::BINARY,
                'name' => ParameterType::STRING,
                'created_at' => ParameterType::STRING,
            ]);
        }

        $topSectionId = $this->id(4);
        $sidebarSectionId = $this->id(5);
        foreach ([[$topSectionId, 0, 'default'], [$sidebarSectionId, 1, 'sidebar']] as [$sectionId, $position, $type]) {
            $this->insertIfMissing($connection, 'cms_section', [
                'id' => $sectionId,
                'cms_page_id' => $pageId,
                'position' => $position,
                'type' => $type,
                'created_at' => $createdAt,
            ], ['id'], [
                'id' => ParameterType::BINARY,
                'cms_page_id' => ParameterType::BINARY,
                'position' => ParameterType::INTEGER,
                'type' => ParameterType::STRING,
                'created_at' => ParameterType::STRING,
            ]);
        }

        $blocks = [
            [
                'id' => $this->id(6), 'cms_section_id' => $topSectionId, 'position' => 0, 'type' => 'image-text', 'name' => 'Category info',
                'section_position' => 'main', 'margin_top' => '20px', 'margin_bottom' => '20px', 'margin_left' => '20px', 'margin_right' => '20px',
            ],
            [
                'id' => $this->id(7), 'cms_section_id' => $sidebarSectionId, 'position' => 1, 'type' => 'category-navigation', 'name' => 'Sidebar navigation',
                'section_position' => 'sidebar', 'margin_bottom' => '30px',
            ],
            [
                'id' => $this->id(8), 'cms_section_id' => $sidebarSectionId, 'position' => 2, 'type' => 'sidebar-filter', 'name' => 'Sidebar filter',
                'section_position' => 'sidebar',
            ],
            [
                'id' => $this->id(9), 'cms_section_id' => $sidebarSectionId, 'position' => 2, 'type' => 'product-listing', 'name' => 'Category listing',
                'section_position' => 'main', 'margin_top' => '20px', 'margin_bottom' => '20px', 'margin_left' => '20px', 'margin_right' => '20px',
            ],
        ];
        foreach ($blocks as $block) {
            $this->insertIfMissing($connection, 'cms_block', [
                'id' => $block['id'],
                'created_at' => $createdAt,
                'cms_section_id' => $block['cms_section_id'],
                'section_position' => $block['section_position'],
                'locked' => 1,
                'position' => $block['position'],
                'type' => $block['type'],
                'name' => $block['name'],
                'margin_top' => $block['margin_top'] ?? null,
                'margin_bottom' => $block['margin_bottom'] ?? null,
                'margin_left' => $block['margin_left'] ?? null,
                'margin_right' => $block['margin_right'] ?? null,
                'background_media_mode' => 'cover',
            ], ['id'], [
                'id' => ParameterType::BINARY,
                'created_at' => ParameterType::STRING,
                'cms_section_id' => ParameterType::BINARY,
                'section_position' => ParameterType::STRING,
                'locked' => ParameterType::INTEGER,
                'position' => ParameterType::INTEGER,
                'type' => ParameterType::STRING,
                'name' => ParameterType::STRING,
                'margin_top' => ParameterType::STRING,
                'margin_bottom' => ParameterType::STRING,
                'margin_left' => ParameterType::STRING,
                'margin_right' => ParameterType::STRING,
                'background_media_mode' => ParameterType::STRING,
            ]);
        }

        $slots = [
            ['id' => $this->id(10), 'cms_block_id' => $blocks[0]['id'], 'type' => 'image', 'slot' => 'left'],
            ['id' => $this->id(11), 'cms_block_id' => $blocks[0]['id'], 'type' => 'text', 'slot' => 'right'],
            ['id' => $this->id(12), 'cms_block_id' => $blocks[1]['id'], 'type' => 'category-navigation', 'slot' => 'content'],
            ['id' => $this->id(13), 'cms_block_id' => $blocks[2]['id'], 'type' => 'sidebar-filter', 'slot' => 'content'],
            ['id' => $this->id(14), 'cms_block_id' => $blocks[2]['id'], 'type' => 'sidebar-filter', 'slot' => 'content'],
            ['id' => $this->id(15), 'cms_block_id' => $blocks[3]['id'], 'type' => 'product-listing', 'slot' => 'content'],
        ];
        foreach ($slots as $slot) {
            $this->insertIfMissing($connection, 'cms_slot', [
                ...$slot,
                'locked' => 1,
                'created_at' => $createdAt,
                'version_id' => $versionId,
            ], ['id', 'version_id'], [
                'id' => ParameterType::BINARY,
                'cms_block_id' => ParameterType::BINARY,
                'type' => ParameterType::STRING,
                'slot' => ParameterType::STRING,
                'locked' => ParameterType::INTEGER,
                'created_at' => ParameterType::STRING,
                'version_id' => ParameterType::BINARY,
            ]);
        }

        $slotConfigurations = [
            10 => ['media' => ['source' => 'mapped', 'value' => 'category.media'], 'displayMode' => ['source' => 'static', 'value' => 'cover'], 'url' => ['source' => 'static', 'value' => null], 'newTab' => ['source' => 'static', 'value' => false], 'minHeight' => ['source' => 'static', 'value' => '320px']],
            11 => ['content' => ['source' => 'mapped', 'value' => 'category.description']],
            12 => null,
            13 => ['boxLayout' => ['source' => 'static', 'value' => 'standard']],
        ];
        $languages = $languageDe === null ? [$languageEn] : [$languageEn, $languageDe];
        foreach ($slotConfigurations as $slotNumber => $configuration) {
            foreach ($languages as $languageId) {
                $this->insertIfMissing($connection, 'cms_slot_translation', [
                    'cms_slot_id' => $this->id($slotNumber),
                    'cms_slot_version_id' => $versionId,
                    'language_id' => $languageId,
                    'created_at' => $createdAt,
                    'config' => $configuration === null ? null : json_encode($configuration, \JSON_THROW_ON_ERROR),
                ], ['cms_slot_id', 'cms_slot_version_id', 'language_id'], [
                    'cms_slot_id' => ParameterType::BINARY,
                    'cms_slot_version_id' => ParameterType::BINARY,
                    'language_id' => ParameterType::BINARY,
                    'created_at' => ParameterType::STRING,
                    'config' => $configuration === null ? ParameterType::NULL : ParameterType::STRING,
                ]);
            }
        }
    }

    private function getLanguageDeDeId(Connection $connection, string $languageEn): ?string
    {
        $languageDe = $this->normalizeBinaryValue($connection->fetchOne(
            'SELECT language.id FROM language INNER JOIN locale ON locale.id = language.translation_code_id WHERE locale.code = :code',
            ['code' => 'de-DE']
        ));

        return $languageDe === null || $languageDe === $languageEn ? null : $languageDe;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $conflictColumns
     * @param array<string, ParameterType> $types
     */
    private function insertIfMissing(Connection $connection, string $table, array $data, array $conflictColumns, array $types): void
    {
        $platform = $connection->getDatabasePlatform();
        $quote = $platform->quoteSingleIdentifier(...);
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $quote($table)
            . ' (' . implode(', ', array_map($quote, $columns)) . ') VALUES ('
            . implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns))
            . ') ON CONFLICT (' . implode(', ', array_map($quote, $conflictColumns)) . ') DO NOTHING';
        $connection->executeStatement($sql, $data, $types);
    }

    private function id(int $number): string
    {
        return Uuid::fromHexToBytes(\sprintf('157045914000400080000000%08x', $number));
    }

    private function normalizeBinaryValue(mixed $value): ?string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }
}

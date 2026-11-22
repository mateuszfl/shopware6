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
class Migration1563180880AddDefaultThumbnailSizes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563180880;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE media_thumbnail_size IN SHARE ROW EXCLUSIVE MODE');
            $thumbnailSizes = $this->insertMissingThumbnailSizes($connection);
            $foldersWithDefaultThumbnails = ['Product Media', 'Category Media', 'Cms Page Media'];

            foreach ($foldersWithDefaultThumbnails as $folderName) {
                $configurationId = $connection->fetchOne(
                    'SELECT media_folder_configuration_id FROM media_folder WHERE name = :name',
                    ['name' => $folderName],
                );
                if (\is_resource($configurationId)) {
                    $configurationId = stream_get_contents($configurationId);
                }
                if (!$configurationId) {
                    continue;
                }

                foreach ($thumbnailSizes as $thumbnailSize) {
                    $connection->executeStatement(<<<'SQL'
                        INSERT INTO media_folder_configuration_media_thumbnail_size
                            (media_folder_configuration_id, media_thumbnail_size_id)
                        VALUES (?, ?)
                        ON CONFLICT (media_folder_configuration_id, media_thumbnail_size_id) DO NOTHING
                        SQL, [$configurationId, $thumbnailSize['id']], [ParameterType::BINARY, ParameterType::BINARY]);
                }
            }
        });
    }

    /**
     * @return list<array{id: string, width: int, height: int}>
     */
    private function insertMissingThumbnailSizes(Connection $connection): array
    {
        $sizes = [];
        foreach ([
            ['width' => 400, 'height' => 400],
            ['width' => 800, 'height' => 800],
            ['width' => 1920, 'height' => 1920],
        ] as $thumbnailSize) {
            $existingId = $connection->fetchOne(
                'SELECT id FROM media_thumbnail_size WHERE width = :width AND height = :height',
                ['width' => $thumbnailSize['width'], 'height' => $thumbnailSize['height']],
            );
            if ($existingId) {
                continue;
            }

            $id = Uuid::randomBytes();
            $connection->insert('media_thumbnail_size', [
                'id' => $id,
                'width' => $thumbnailSize['width'],
                'height' => $thumbnailSize['height'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING]);

            $sizes[] = ['id' => $id, ...$thumbnailSize];
        }

        return $sizes;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationDatabaseHelper;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
trait EnsureThumbnailSizesTrait
{
    /**
     * @param list<array{width: int, height: int}> $thumbnailSizes
     *
     * @return list<string>
     */
    final protected function ensureThumbnailSizes(array $thumbnailSizes, Connection $connection): array
    {
        /** @var list<array{id: string|resource, width: string, height: string}> $allSizes */
        $allSizes = $connection->fetchAllAssociative(
            'SELECT id, width, height FROM media_thumbnail_size'
        );
        foreach ($allSizes as &$size) {
            $size['id'] = MigrationDatabaseHelper::normalizeBinaryId($size['id']);
        }
        unset($size);

        $insertStatement = $connection->prepare('
                INSERT INTO media_thumbnail_size (id, width, height, created_at)
                VALUES (:id, :width, :height, :createdAt)
            ');

        $sizes = [];
        foreach ($thumbnailSizes as $thumbnailSize) {
            $result = array_filter($allSizes, static function ($var) use ($thumbnailSize) {
                return (int) $var['width'] === $thumbnailSize['width'] && (int) $var['height'] === $thumbnailSize['height'];
            });

            if ($result !== []) {
                $id = reset($result)['id'];
                $sizes[] = $id;

                continue;
            }

            $id = Uuid::randomBytes();
            StatementHelper::executeStatement($insertStatement, [
                'id' => $id,
                'width' => $thumbnailSize['width'],
                'height' => $thumbnailSize['height'],
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], ['id' => ParameterType::BINARY]);

            $sizes[] = $id;
        }

        return $sizes;
    }
}

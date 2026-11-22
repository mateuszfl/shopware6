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
class Migration1561370284AddImportExportProductProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561370284;
    }

    public function update(Connection $connection): void
    {
        $fields = [
            'name.de-DE',
            'name.en-GB',
            'stock',
            'manufacturer.id',
            'tax.id',
            'price.net',
            'price.gross',
            'price.linked',
            'productNumber',
            'releaseDate',
            'categories.id',
        ];
        $mapping = [];
        foreach ($fields as $fieldName) {
            $mapping[] = [
                'fileField' => $fieldName,
                'entityField' => $fieldName,
                'valueSubstitutions' => [],
            ];
        }

        $connection->transactional(function (Connection $connection) use ($mapping): void {
            $connection->executeStatement('LOCK TABLE import_export_profile IN SHARE ROW EXCLUSIVE MODE');
            if ($connection->fetchOne('SELECT 1 FROM import_export_profile WHERE source_entity = \'product\' AND system_default = 1') !== false) {
                return;
            }

            $connection->insert('import_export_profile', [
                'id' => Uuid::randomBytes(),
                'name' => 'Default product',
                'system_default' => 1,
                'source_entity' => 'product',
                'file_type' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
                'mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY]);
        });
    }
}

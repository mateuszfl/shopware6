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
class Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572273565;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            WITH ranked_document_types AS (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY technical_name
                    ORDER BY created_at ASC, id ASC
                ) AS row_number
                FROM document_type
            )
            DELETE FROM document_type
            WHERE id IN (SELECT id FROM ranked_document_types WHERE row_number > 1)
            SQL);

        $constraintExists = $connection->fetchOne(
            'SELECT 1 FROM pg_constraint WHERE conname = :name AND conrelid = to_regclass(:table)',
            ['name' => 'uniq.document_type.technical_name', 'table' => 'document_type']
        );
        if ($constraintExists === false) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE document_type
                ADD CONSTRAINT "uniq.document_type.technical_name" UNIQUE (technical_name)
                SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

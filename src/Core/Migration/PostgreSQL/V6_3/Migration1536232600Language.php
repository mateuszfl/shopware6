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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536232600Language extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232600;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS language (
                id BYTEA NOT NULL CONSTRAINT chk_sw_language_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(50) COLLATE sw_type_ci NOT NULL,
                parent_id BYTEA NULL CONSTRAINT chk_sw_language_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                locale_id BYTEA NOT NULL CONSTRAINT chk_sw_language_locale_id_uuid CHECK (octet_length(locale_id) = 16),
                translation_code_id BYTEA NULL CONSTRAINT chk_sw_language_translation_code_id_uuid CHECK (octet_length(translation_code_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "language__uniq.translation_code_id" UNIQUE (translation_code_id),
                CONSTRAINT "fk.language.parent_id" FOREIGN KEY (parent_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "language__idx.language.translation_code_id" ON language (translation_code_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "language__idx.language.language_id_parent_language_id" ON language (id, parent_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "language__fk.language.parent_id" ON language (parent_id)');
    }
}

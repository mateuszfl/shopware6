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
class Migration1536232750Snippet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232750;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS snippet (
                id BYTEA NOT NULL CONSTRAINT chk_sw_snippet_id_uuid CHECK (octet_length(id) = 16),
                translation_key VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                "value" TEXT COLLATE sw_type_ci NOT NULL,
                author VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                snippet_set_id BYTEA NOT NULL CONSTRAINT chk_sw_snippet_snippet_set_id_uuid CHECK (octet_length(snippet_set_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "snippet__uniq.snippet_set_id_translation_key" UNIQUE (snippet_set_id, translation_key),
                CONSTRAINT "fk.snippet.snippet_set_id" FOREIGN KEY (snippet_set_id) REFERENCES snippet_set (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);
    }
}

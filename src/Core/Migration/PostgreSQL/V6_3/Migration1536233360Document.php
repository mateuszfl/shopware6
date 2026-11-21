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
class Migration1536233360Document extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233360;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS document_type (
                id BYTEA NOT NULL CONSTRAINT chk_sw_document_type_id_uuid CHECK (octet_length(id) = 16),
                technical_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS document_type_translation (
                document_type_id BYTEA NOT NULL CONSTRAINT chk_sw_document_type_translation_document_type_id_uuid CHECK (octet_length(document_type_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_document_type_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (document_type_id, language_id),
                CONSTRAINT "fk.document_type_translation.document_type_id" FOREIGN KEY (document_type_id)
                    REFERENCES document_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.document_type_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document_type_translation__fk.document_type_tr_c7036700db454902" ON document_type_translation (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS document (
                id BYTEA NOT NULL CONSTRAINT chk_sw_document_id_uuid CHECK (octet_length(id) = 16),
                document_type_id BYTEA NOT NULL CONSTRAINT chk_sw_document_document_type_id_uuid CHECK (octet_length(document_type_id) = 16),
                referenced_document_id BYTEA NULL CONSTRAINT chk_sw_document_referenced_document_id_uuid CHECK (octet_length(referenced_document_id) = 16),
                file_type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                order_id BYTEA NOT NULL CONSTRAINT chk_sw_document_order_id_uuid CHECK (octet_length(order_id) = 16),
                order_version_id BYTEA NOT NULL CONSTRAINT chk_sw_document_order_version_id_uuid CHECK (octet_length(order_version_id) = 16),
                config JSONB NULL,
                sent SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_document_sent_bool CHECK (sent IN (0, 1)),
                static SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_document_static_bool CHECK (static IN (0, 1)),
                deep_link_code VARCHAR(32) COLLATE sw_type_ci NOT NULL,
                document_media_file_id BYTEA NULL CONSTRAINT chk_sw_document_document_media_file_id_uuid CHECK (octet_length(document_media_file_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "document__document__uniq.deep_link_code" UNIQUE (deep_link_code),
                CONSTRAINT "fk.document.document_type_id" FOREIGN KEY (document_type_id)
                    REFERENCES document_type (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.document.referenced_document_id" FOREIGN KEY (referenced_document_id)
                    REFERENCES document (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.document.order_id" FOREIGN KEY (order_id,order_version_id)
                    REFERENCES "order" (id,version_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.document.document_media_file_id" FOREIGN KEY (document_media_file_id)
                    REFERENCES media (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document__fk.document.order_id" ON document (order_id,order_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document__fk.document.document_type_id" ON document (document_type_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document__fk.document.referenced_document_id" ON document (referenced_document_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "document__fk.document.document_media_file_id" ON document (document_media_file_id)');
    }
}

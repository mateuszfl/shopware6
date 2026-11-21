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
class Migration1536232850Media extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232850;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media (
                id BYTEA NOT NULL CONSTRAINT chk_sw_media_id_uuid CHECK (octet_length(id) = 16),
                user_id BYTEA NULL CONSTRAINT chk_sw_media_user_id_uuid CHECK (octet_length(user_id) = 16),
                media_folder_id BYTEA NULL CONSTRAINT chk_sw_media_media_folder_id_uuid CHECK (octet_length(media_folder_id) = 16),
                mime_type VARCHAR(255) COLLATE sw_type_ci NULL,
                file_extension VARCHAR(50) COLLATE sw_type_ci NULL,
                file_size BIGINT NULL CONSTRAINT chk_sw_media_file_size_unsigned CHECK (file_size >= 0 AND file_size <= '4294967295'::bigint),
                meta_data JSONB NULL,
                file_name TEXT COLLATE sw_type_ci NULL,
                media_type BYTEA NULL,
                thumbnails_ro BYTEA NULL,
                private SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_media_private_bool CHECK (private IN (0, 1)),
                uploaded_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.media.user_id" FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT "fk.media.media_folder_id" FOREIGN KEY (media_folder_id)
                    REFERENCES media_folder (id) ON DELETE SET NULL
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media__fk.media.user_id" ON media (user_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media__fk.media.media_folder_id" ON media (media_folder_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media_translation (
                media_id BYTEA NOT NULL CONSTRAINT chk_sw_media_translation_media_id_uuid CHECK (octet_length(media_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_media_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                alt VARCHAR(255) COLLATE sw_type_ci,
                title VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (media_id, language_id),
                CONSTRAINT "fk.media_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.media_translation.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "media_translation__fk.media_translation.language_id" ON media_translation (language_id)');

        if (!TableHelper::foreignKeyExists($connection, 'user', 'fk.user.avatar_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE "user" ADD CONSTRAINT "fk.user.avatar_id" FOREIGN KEY (avatar_id)
                    REFERENCES media (id) ON DELETE SET NULL
                SQL);
        }

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "user__fk.user.avatar_id" ON "user" (avatar_id)');
    }
}

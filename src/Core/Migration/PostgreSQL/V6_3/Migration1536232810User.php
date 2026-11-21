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
class Migration1536232810User extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232810;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS "user" (
                id BYTEA NOT NULL CONSTRAINT chk_sw_user_id_uuid CHECK (octet_length(id) = 16),
                username VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                password VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                first_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                last_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                email VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                active SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_user_active_bool CHECK (active IN (0, 1)),
                avatar_id BYTEA NULL CONSTRAINT chk_sw_user_avatar_id_uuid CHECK (octet_length(avatar_id) = 16),
                locale_id BYTEA NOT NULL CONSTRAINT chk_sw_user_locale_id_uuid CHECK (octet_length(locale_id) = 16),
                store_token VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "user__uniq.user.email" UNIQUE (email),
                CONSTRAINT "user__uniq.user.username" UNIQUE (username),
                CONSTRAINT "fk.user.locale_id" FOREIGN KEY (locale_id)
                    REFERENCES locale (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "user__fk.user.locale_id" ON "user" (locale_id)');
    }
}

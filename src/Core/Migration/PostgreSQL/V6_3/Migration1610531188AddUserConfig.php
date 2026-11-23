<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare (strict_types=1);
namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
/**
 * @internal
 */
#[Package('framework')]
class Migration1610531188AddUserConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610531188;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "user_config" (
                "id"                    BYTEA CHECK (octet_length("id") = 16)      NOT NULL,
                "user_id"               BYTEA CHECK (octet_length("user_id") = 16)      NOT NULL,
                "key"                   VARCHAR(255) COLLATE "sw_type_ci"    NOT NULL,
                "value"                 JSON            NULL,
                "created_at"            TIMESTAMP(3) WITHOUT TIME ZONE     NOT NULL,
                "updated_at"            TIMESTAMP(3) WITHOUT TIME ZONE     NULL,
                PRIMARY KEY ("id"),
                UNIQUE "uniq.user_id_key" ("user_id", "key"),
                CONSTRAINT "json.user_config.value" CHECK (JSON_VALID("value")),
                CONSTRAINT "fk.user_config.user_id" FOREIGN KEY ("user_id")
                    REFERENCES "user" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

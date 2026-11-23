<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1597391970App extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597391970;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "app" (
                "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL,
                "name" VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
                "path" VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
                "author" VARCHAR(255) COLLATE "sw_type_ci" NULL,
                "copyright" VARCHAR(255) COLLATE "sw_type_ci" NULL,
                "license" VARCHAR(255) COLLATE "sw_type_ci" NULL,
                "privacy" VARCHAR(255) COLLATE "sw_type_ci" NULL,
                "version" VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
                "active" SMALLINT DEFAULT 0 NOT NULL,
                "icon" MEDIUMBLOB NULL,
                "app_secret" VARCHAR(255) COLLATE "sw_type_ci" NULL,
                "modules" JSON NULL,
                "integration_id" BYTEA CHECK (octet_length("integration_id") = 16) NOT NULL,
                "acl_role_id" BYTEA CHECK (octet_length("acl_role_id") = 16) NOT NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("id"),
                UNIQUE KEY "uniq.name" ("name"),
                CONSTRAINT "json.app.modules" CHECK (TRUE),
                CONSTRAINT "fk.app.integration_id" FOREIGN KEY ("integration_id") REFERENCES "integration" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.app.acl_role_id" FOREIGN KEY ("acl_role_id") REFERENCES "acl_role" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "app_translation" (
                "app_id" BYTEA CHECK (octet_length("app_id") = 16) NOT NULL,
                "language_id" BYTEA CHECK (octet_length("language_id") = 16) NOT NULL,
                "label" VARCHAR(255) COLLATE "sw_type_ci" NOT NULL,
                "description" LONGTEXT COLLATE "sw_type_ci" NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("app_id","language_id"),
                KEY "fk.app_translation.app_id" ("app_id"),
                KEY "fk.app_translation.language_id" ("language_id"),
                CONSTRAINT "fk.app_translation.app_id" FOREIGN KEY ("app_id") REFERENCES "app" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.app_translation.language_id" FOREIGN KEY ("language_id") REFERENCES "language" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

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
class Migration1597657689ActionButton extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597657689;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "app_action_button" (
                "id" BYTEA CHECK (octet_length("id") = 16) NOT NULL,
                "entity" VARCHAR(255) NOT NULL,
                "view" VARCHAR(255) NOT NULL,
                "url" VARCHAR(255) NOT NULL,
                "action" VARCHAR(255) NOT NULL,
                "open_new_tab" SMALLINT NOT NULL DEFAULT \'0\',
                "app_id" BYTEA CHECK (octet_length("app_id") = 16) NOT NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("id"),
                KEY "fk.app_action_button.app_id" ("app_id"),
                CONSTRAINT "fk.app_action_button.app_id" FOREIGN KEY ("app_id") REFERENCES "app" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "uniq.app_action_button.action" UNIQUE ("action", "app_id")
            );
        ');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "app_action_button_translation" (
                "label" VARCHAR(255) NOT NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                "app_action_button_id" BYTEA CHECK (octet_length("app_action_button_id") = 16) NOT NULL,
                "language_id" BYTEA CHECK (octet_length("language_id") = 16) NOT NULL,
                PRIMARY KEY ("app_action_button_id","language_id"),
                KEY "fk.app_action_button_translation.app_action_button_id" ("app_action_button_id"),
                KEY "fk.app_action_button_translation.language_id" ("language_id"),
                CONSTRAINT "fk.app_action_button_translation.app_action_button_id" FOREIGN KEY ("app_action_button_id") REFERENCES "app_action_button" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.app_action_button_translation.language_id" FOREIGN KEY ("language_id") REFERENCES "language" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}

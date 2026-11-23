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
class Migration1583844433AddRefreshTokenTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583844433;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "refresh_token" (
              "id"                  BYTEA CHECK (octet_length("id") = 16)                              NOT NULL,
              "user_id"             BYTEA CHECK (octet_length("user_id") = 16)                              NOT NULL,
              "token_id"            VARCHAR(80) COLLATE "sw_type_ci"                             NOT NULL,
              "issued_at"           TIMESTAMP(3) WITHOUT TIME ZONE                             NOT NULL,
              "expires_at"          TIMESTAMP(3) WITHOUT TIME ZONE                             NOT NULL,
              PRIMARY KEY ("id"),
              UNIQUE "uniq.token_id" (token_id)
            );
        ');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

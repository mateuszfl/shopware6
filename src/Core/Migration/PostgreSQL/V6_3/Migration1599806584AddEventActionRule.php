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
class Migration1599806584AddEventActionRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599806584;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "event_action_rule" (
              "event_action_id" BYTEA CHECK (octet_length("event_action_id") = 16) NOT NULL,
              "rule_id" BYTEA CHECK (octet_length("rule_id") = 16) NOT NULL,
              PRIMARY KEY ("event_action_id","rule_id"),
              CONSTRAINT "fk.event_action_rule.event_action_id" FOREIGN KEY ("event_action_id") REFERENCES "event_action" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT "fk.event_action_rule.rule_id" FOREIGN KEY ("rule_id") REFERENCES "rule" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            )

CREATE INDEX "rule_id" ON "event_action_rule" ("rule_id");');
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}

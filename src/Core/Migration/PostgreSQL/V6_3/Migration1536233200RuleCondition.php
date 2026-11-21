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
class Migration1536233200RuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233200;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rule_condition (
                id BYTEA NOT NULL CONSTRAINT chk_sw_rule_condition_id_uuid CHECK (octet_length(id) = 16),
                type VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                rule_id BYTEA NOT NULL CONSTRAINT chk_sw_rule_condition_rule_id_uuid CHECK (octet_length(rule_id) = 16),
                parent_id BYTEA NULL CONSTRAINT chk_sw_rule_condition_parent_id_uuid CHECK (octet_length(parent_id) = 16),
                "value" JSONB NULL,
                "position" INTEGER DEFAULT 0 NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.rule_condition.rule_id" FOREIGN KEY (rule_id)
                    REFERENCES rule (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.rule_condition.parent_id" FOREIGN KEY (parent_id)
                    REFERENCES rule_condition (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "rule_condition__fk.rule_condition.rule_id" ON rule_condition (rule_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "rule_condition__fk.rule_condition.parent_id" ON rule_condition (parent_id)');
    }
}

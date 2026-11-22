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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1565270366PromotionSetGroupRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565270366;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_setgroup_rule (
                setgroup_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_setgroup_rule_setgroup_id_uuid CHECK (octet_length(setgroup_id) = 16),
                rule_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_setgroup_rule_rule_id_uuid CHECK (octet_length(rule_id) = 16),
                PRIMARY KEY (setgroup_id, rule_id),
                CONSTRAINT "fk.promotion_setgroup_rule.setgroup_id" FOREIGN KEY (setgroup_id)
                    REFERENCES promotion_setgroup (id) ON DELETE CASCADE,
                CONSTRAINT "fk.promotion_setgroup_rule.rule_id" FOREIGN KEY (rule_id)
                    REFERENCES rule (id) ON DELETE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_setgroup_rule__fk.promotion_setgroup_rule.rule_id" ON promotion_setgroup_rule (rule_id)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

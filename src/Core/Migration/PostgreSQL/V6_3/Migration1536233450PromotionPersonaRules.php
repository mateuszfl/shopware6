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
class Migration1536233450PromotionPersonaRules extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233450;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS promotion_persona_rule (
                promotion_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_persona_rule_promotion_id_uuid CHECK (octet_length(promotion_id) = 16),
                rule_id BYTEA NOT NULL CONSTRAINT chk_sw_promotion_persona_rule_rule_id_uuid CHECK (octet_length(rule_id) = 16),
                PRIMARY KEY (promotion_id, rule_id),
                CONSTRAINT "fk.promotion_persona_rule.promotion_id" FOREIGN KEY (promotion_id)
                    REFERENCES promotion (id) ON DELETE CASCADE,
                CONSTRAINT "fk.promotion_persona_rule.rule_id" FOREIGN KEY (rule_id)
                    REFERENCES rule (id) ON DELETE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "promotion_persona_rule__fk.promotion_persona_rule.rule_id" ON promotion_persona_rule (rule_id)');
    }
}

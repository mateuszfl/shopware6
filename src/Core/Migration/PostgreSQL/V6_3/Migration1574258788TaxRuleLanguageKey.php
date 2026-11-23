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
class Migration1574258788TaxRuleLanguageKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574258788;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE tax_rule_type_translation DROP CONSTRAINT IF EXISTS "fk.tax_rule_type_translation.language_id"');
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE tax_rule_type_translation
            ADD CONSTRAINT "fk.tax_rule_type_translation.language_id" FOREIGN KEY (language_id)
            REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

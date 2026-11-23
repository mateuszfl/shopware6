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
class Migration1572421282AddDoubleOptInRegistration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572421282;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'customer', 'doubleOptInRegistration', 'SMALLINT', false, '0');
        $connection->executeStatement(<<<'SQL'
            DO $$ BEGIN
                ALTER TABLE customer ADD CONSTRAINT chk_sw_customer_double_opt_in_registration_bool
                    CHECK ("doubleOptInRegistration" IN (0, 1));
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$
            SQL);
        $this->addColumn($connection, 'customer', 'doubleOptInEmailSentDate', 'TIMESTAMP(3) WITHOUT TIME ZONE');
        $this->addColumn($connection, 'customer', 'doubleOptInConfirmDate', 'TIMESTAMP(3) WITHOUT TIME ZONE');
        $this->addColumn($connection, 'customer', 'hash', 'VARCHAR(255) COLLATE sw_type_ci');
        $connection->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS hash ON customer (hash)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

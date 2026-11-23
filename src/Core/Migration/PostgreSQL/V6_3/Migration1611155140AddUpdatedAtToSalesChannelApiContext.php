<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1611155140AddUpdatedAtToSalesChannelApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1611155140;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE sales_channel_api_context
                ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE OR REPLACE FUNCTION sales_channel_api_context_set_updated_at() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                NEW.updated_at := statement_timestamp();
                RETURN NEW;
            END;
            $$
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE OR REPLACE TRIGGER sales_channel_api_context_set_updated_at
                BEFORE UPDATE ON sales_channel_api_context
                FOR EACH ROW
                WHEN (NEW.updated_at IS NOT DISTINCT FROM OLD.updated_at AND NEW.* IS DISTINCT FROM OLD.*)
                EXECUTE FUNCTION sales_channel_api_context_set_updated_at()
            SQL);
    }
}

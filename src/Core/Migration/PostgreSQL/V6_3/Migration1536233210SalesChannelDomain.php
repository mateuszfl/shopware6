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
class Migration1536233210SalesChannelDomain extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233210;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS sales_channel_domain (
                id BYTEA NOT NULL PRIMARY KEY CONSTRAINT chk_sw_sales_channel_domain_id_uuid CHECK (octet_length(id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_domain_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_domain_language_id_uuid CHECK (octet_length(language_id) = 16),
                url VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                currency_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_domain_currency_id_uuid CHECK (octet_length(currency_id) = 16),
                snippet_set_id BYTEA NOT NULL CONSTRAINT chk_sw_sales_channel_domain_snippet_set_id_uuid CHECK (octet_length(snippet_set_id) = 16),
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                CONSTRAINT "fk.sales_channel_domain.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_domain.language_id" FOREIGN KEY (sales_channel_id, language_id)
                    REFERENCES sales_channel_language (sales_channel_id, language_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_domain.currency_id" FOREIGN KEY (currency_id)
                    REFERENCES currency (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "fk.sales_channel_domain.snippet_set_id" FOREIGN KEY (snippet_set_id)
                    REFERENCES snippet_set (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT "sales_channel_domain__uniq.sales_channel_domain.url" UNIQUE (url)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_domain__fk.sales_channel_domain.language_id" ON sales_channel_domain (sales_channel_id, language_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_domain__fk.sales_channel_domain.currency_id" ON sales_channel_domain (currency_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "sales_channel_domain__fk.sales_channel_domain.snippet_set_id" ON sales_channel_domain (snippet_set_id)');
    }
}

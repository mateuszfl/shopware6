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
class Migration1536233540ProductSearchKeyword extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233540;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_search_keyword (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_search_keyword_id_uuid CHECK (octet_length(id) = 16),
                version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_search_keyword_version_id_uuid CHECK (octet_length(version_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_product_search_keyword_language_id_uuid CHECK (octet_length(language_id) = 16),
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_search_keyword_product_id_uuid CHECK (octet_length(product_id) = 16),
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_search_keyword_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                keyword VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                ranking DOUBLE PRECISION NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id,version_id,language_id),
                CONSTRAINT "fk.product_search_keyword.product_id" FOREIGN KEY (product_id,product_version_id)
                    REFERENCES product (id,version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_search_keyword.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE RESTRICT ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_search_keyword__idx.product_search_keyword.product_id" ON product_search_keyword (product_id,product_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_search_keyword__idx.product_search_keyword.language_id" ON product_search_keyword (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS product_keyword_dictionary (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_keyword_dictionary_id_uuid CHECK (octet_length(id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_product_keyword_dictionary_language_id_uuid CHECK (octet_length(language_id) = 16),
                keyword VARCHAR(500) COLLATE sw_type_ci NOT NULL,
                reversed VARCHAR(500) COLLATE sw_type_ci GENERATED ALWAYS AS (REVERSE(keyword)) STORED,
                PRIMARY KEY (id,language_id),
                CONSTRAINT "fk.product_keyword_dictionary.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_keyword_dictionary__idx.product_keywor_992c24a6e2399d4f" ON product_keyword_dictionary (language_id)');
    }
}

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
class Migration1554900301AddReviewTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554900301;
    }

    public function update(Connection $connection): void
    {
        $this->dropTableIfExists($connection, 'product_review');
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE product_review (
                id BYTEA NOT NULL CONSTRAINT chk_sw_product_review_id_uuid CHECK (octet_length(id) = 16),
                product_id BYTEA NOT NULL CONSTRAINT chk_sw_product_review_product_id_uuid CHECK (octet_length(product_id) = 16),
                customer_id BYTEA NULL CONSTRAINT chk_sw_product_review_customer_id_uuid CHECK (octet_length(customer_id) = 16),
                sales_channel_id BYTEA NULL CONSTRAINT chk_sw_product_review_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                language_id BYTEA NULL CONSTRAINT chk_sw_product_review_language_id_uuid CHECK (octet_length(language_id) = 16),
                external_user VARCHAR(255) COLLATE sw_type_ci NULL,
                external_email VARCHAR(255) COLLATE sw_type_ci NULL,
                title VARCHAR(255) COLLATE sw_type_ci NULL,
                content TEXT COLLATE sw_type_ci NULL,
                points DOUBLE PRECISION NULL,
                status SMALLINT NULL DEFAULT 0 CONSTRAINT chk_sw_product_review_status_bool CHECK (status IN (0, 1)),
                comment TEXT COLLATE sw_type_ci NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                product_version_id BYTEA NOT NULL CONSTRAINT chk_sw_product_review_product_version_id_uuid CHECK (octet_length(product_version_id) = 16),
                PRIMARY KEY (id),
                CONSTRAINT "fk.product_review.product_id" FOREIGN KEY (product_id,product_version_id) REFERENCES product (id,version_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.product_review.customer_id" FOREIGN KEY (customer_id) REFERENCES customer (id),
                CONSTRAINT "fk.product_review.sales_channel_id" FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id),
                CONSTRAINT "fk.product_review.language_id" FOREIGN KEY (language_id) REFERENCES language (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_review__fk.product_review.product_id" ON product_review (product_id,product_version_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_review__fk.product_review.customer_id" ON product_review (customer_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_review__fk.product_review.sales_channel_id" ON product_review (sales_channel_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "product_review__fk.product_review.language_id" ON product_review (language_id)');
    }
}

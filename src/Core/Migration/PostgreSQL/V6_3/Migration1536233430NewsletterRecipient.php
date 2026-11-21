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
class Migration1536233430NewsletterRecipient extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233430;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS newsletter_recipient (
                id BYTEA NOT NULL CONSTRAINT chk_sw_newsletter_recipient_id_uuid CHECK (octet_length(id) = 16),
                email VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                title VARCHAR(255) COLLATE sw_type_ci NULL,
                first_name VARCHAR(255) COLLATE sw_type_ci NULL,
                last_name VARCHAR(255) COLLATE sw_type_ci NULL,
                zip_code VARCHAR(255) COLLATE sw_type_ci NULL,
                city VARCHAR(255) COLLATE sw_type_ci NULL,
                street VARCHAR(255) COLLATE sw_type_ci NULL,
                status VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                hash VARCHAR(255) COLLATE sw_type_ci NOT NULL UNIQUE,
                salutation_id BYTEA NULL CONSTRAINT chk_sw_newsletter_recipient_salutation_id_uuid CHECK (octet_length(salutation_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_newsletter_recipient_language_id_uuid CHECK (octet_length(language_id) = 16),
                sales_channel_id BYTEA NOT NULL CONSTRAINT chk_sw_newsletter_recipient_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                custom_fields JSONB NULL,
                confirmed_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.newsletter_recipient.salutation_id" FOREIGN KEY (salutation_id)
                    REFERENCES salutation (id) ON DELETE RESTRICT,
                CONSTRAINT "fk.newsletter_recipient.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE RESTRICT,
                CONSTRAINT "fk.newsletter_recipient.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE RESTRICT
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "newsletter_recipient__fk.newsletter_recipient.salutation_id" ON newsletter_recipient (salutation_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "newsletter_recipient__fk.newsletter_recipient.language_id" ON newsletter_recipient (language_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "newsletter_recipient__fk.newsletter_recipient.sales_channel_id" ON newsletter_recipient (sales_channel_id)');
    }
}

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
class Migration1536233330MailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233330;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_template_type (
                id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_type_id_uuid CHECK (octet_length(id) = 16),
                technical_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                available_entities TEXT COLLATE sw_type_ci NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "mail_template_type__uniq.mail_template_type.technical_name" UNIQUE (technical_name)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_template_type_translation (
                mail_template_type_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_type_translation_mail_tem_02916813413a3797 CHECK (octet_length(mail_template_type_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_type_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (mail_template_type_id, language_id),
                CONSTRAINT "fk.mail_template_type_translation.mail_template_type_id" FOREIGN KEY (mail_template_type_id)
                    REFERENCES mail_template_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.mail_template_type_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template_type_translation__fk.mail_templa_ad1b8a093cec5d56" ON mail_template_type_translation (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_template (
                id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_id_uuid CHECK (octet_length(id) = 16),
                mail_template_type_id BYTEA NULL CONSTRAINT chk_sw_mail_template_mail_template_type_id_uuid CHECK (octet_length(mail_template_type_id) = 16),
                system_default SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_mail_template_system_default_bool CHECK (system_default IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE,
                PRIMARY KEY (id),
                CONSTRAINT "fk.mail_template.mail_template_type_id" FOREIGN KEY (mail_template_type_id)
                    REFERENCES mail_template_type (id) ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template__fk.mail_template.mail_template_type_id" ON mail_template (mail_template_type_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_template_translation (
                mail_template_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_translation_mail_template_id_uuid CHECK (octet_length(mail_template_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                sender_name VARCHAR(255) COLLATE sw_type_ci DEFAULT NULL,
                subject VARCHAR(255) COLLATE sw_type_ci DEFAULT NULL,
                description TEXT COLLATE sw_type_ci DEFAULT NULL,
                content_html TEXT COLLATE sw_type_ci DEFAULT NULL,
                content_plain TEXT COLLATE sw_type_ci DEFAULT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (mail_template_id, language_id),
                CONSTRAINT "fk.mail_template_translation.mail_template_id" FOREIGN KEY (mail_template_id)
                    REFERENCES mail_template (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.mail_template_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template_translation__fk.mail_template_tr_2362eb32b91bbbd4" ON mail_template_translation (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_template_sales_channel (
                id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_sales_channel_id_uuid CHECK (octet_length(id) = 16),
                mail_template_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_sales_channel_mail_template_id_uuid CHECK (octet_length(mail_template_id) = 16),
                mail_template_type_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_sales_channel_mail_template_type_id_uuid CHECK (octet_length(mail_template_type_id) = 16),
                sales_channel_id BYTEA NULL CONSTRAINT chk_sw_mail_template_sales_channel_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT mail_template_sales_channel__mail_template_sal_7a7e2fb1b0d2b101 UNIQUE (mail_template_id, sales_channel_id),
                CONSTRAINT "fk.mail_template_sales_channel.mail_template_id" FOREIGN KEY (mail_template_id)
                    REFERENCES mail_template (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.mail_template_sales_channel.mail_template_type_id" FOREIGN KEY (mail_template_type_id)
                    REFERENCES mail_template_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.mail_template_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template_sales_channel__fk.mail_template__0acaffdf5c7eeccb" ON mail_template_sales_channel (mail_template_type_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template_sales_channel__fk.mail_template__1a679ec367335ca3" ON mail_template_sales_channel (sales_channel_id)');
    }
}

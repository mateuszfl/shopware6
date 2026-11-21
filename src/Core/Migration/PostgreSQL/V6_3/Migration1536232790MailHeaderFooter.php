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
class Migration1536232790MailHeaderFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232790;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_header_footer (
                id BYTEA NOT NULL CONSTRAINT chk_sw_mail_header_footer_id_uuid CHECK (octet_length(id) = 16),
                system_default SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_mail_header_footer_system_default_bool CHECK (system_default IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_header_footer_translation (
                mail_header_footer_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_header_footer_translation_mail_hea_c1a8ec6944e3b859 CHECK (octet_length(mail_header_footer_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_header_footer_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                header_html TEXT COLLATE sw_type_ci NULL,
                header_plain TEXT COLLATE sw_type_ci NULL,
                footer_html TEXT COLLATE sw_type_ci NULL,
                footer_plain TEXT COLLATE sw_type_ci NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (mail_header_footer_id, language_id),
                CONSTRAINT "fk.mail_header_footer_translation.mail_header_footer_id" FOREIGN KEY (mail_header_footer_id)
                    REFERENCES mail_header_footer (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.mail_header_footer_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_header_footer_translation__fk.mail_header_08407c3b6b5d6178" ON mail_header_footer_translation (language_id)');
    }
}

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
class Migration1536233400MailTemplateMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_template_media (
                id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_media_id_uuid CHECK (octet_length(id) = 16),
                mail_template_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_media_mail_template_id_uuid CHECK (octet_length(mail_template_id) = 16),
                media_id BYTEA NOT NULL CONSTRAINT chk_sw_mail_template_media_media_id_uuid CHECK (octet_length(media_id) = 16),
                "position" INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                CONSTRAINT "fk.mail_template_media.mail_template_id" FOREIGN KEY (mail_template_id)
                    REFERENCES mail_template (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.mail_template_media.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template_media__fk.mail_template_media.mail_template_id" ON mail_template_media (mail_template_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "mail_template_media__fk.mail_template_media.media_id" ON mail_template_media (media_id)');
    }
}

<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1585056571AddLanguageToMailTemplateMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585056571;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
                    ALTER TABLE "mail_template_media" ADD "language_id" BINARY(16) NULL,
                    ADD CONSTRAINT "fk.mail_template_media.language_id" FOREIGN KEY ("language_id")
                     REFERENCES "language" ("id") ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\InitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1536232610Locale extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232610;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS locale (
                id BYTEA NOT NULL CONSTRAINT chk_sw_locale_id_uuid CHECK (octet_length(id) = 16),
                code VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "locale__uniq.code" UNIQUE (code)
            )
            SQL);

        if (!TableHelper::foreignKeyExists($connection, 'language', 'fk.language.locale_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE language ADD CONSTRAINT "fk.language.locale_id" FOREIGN KEY (locale_id)
                REFERENCES locale (id) ON DELETE CASCADE ON UPDATE CASCADE
                SQL);
        }

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "language__fk.language.locale_id" ON language (locale_id)');

        if (!TableHelper::foreignKeyExists($connection, 'language', 'fk.language.translation_code_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE language ADD CONSTRAINT "fk.language.translation_code_id" FOREIGN KEY (translation_code_id)
                REFERENCES locale (id) ON DELETE CASCADE ON UPDATE CASCADE
                SQL);
        }

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS locale_translation (
                locale_id BYTEA NOT NULL CONSTRAINT chk_sw_locale_translation_locale_id_uuid CHECK (octet_length(locale_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_locale_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                territory VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (locale_id, language_id),
                CONSTRAINT "fk.locale_translation.language_id" FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.locale_translation.locale_id" FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "locale_translation__fk.locale_translation.language_id" ON locale_translation (language_id)');
    }
}

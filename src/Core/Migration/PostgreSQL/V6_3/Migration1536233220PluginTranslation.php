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
class Migration1536233220PluginTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233220;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS plugin_translation (
                plugin_id BYTEA NOT NULL CONSTRAINT chk_sw_plugin_translation_plugin_id_uuid CHECK (octet_length(plugin_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_plugin_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                label VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                manufacturer_link TEXT COLLATE sw_type_ci NULL,
                support_link TEXT COLLATE sw_type_ci NULL,
                changelog JSONB NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (plugin_id, language_id),
                CONSTRAINT "fk.plugin_translation.plugin_id" FOREIGN KEY (plugin_id)
                    REFERENCES plugin (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.plugin_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "plugin_translation__fk.plugin_translation.language_id" ON plugin_translation (language_id)');
    }
}

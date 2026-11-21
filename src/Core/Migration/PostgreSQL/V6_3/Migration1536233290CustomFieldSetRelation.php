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
class Migration1536233290CustomFieldSetRelation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233290;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS custom_field_set_relation (
                id BYTEA NOT NULL CONSTRAINT chk_sw_custom_field_set_relation_id_uuid CHECK (octet_length(id) = 16),
                set_id BYTEA NOT NULL CONSTRAINT chk_sw_custom_field_set_relation_set_id_uuid CHECK (octet_length(set_id) = 16),
                entity_name VARCHAR(64) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY(id),
                CONSTRAINT "custom_field_set_relation__uniq.custom_field_s_7ea6ce69263c9a19" UNIQUE (set_id, entity_name),
                CONSTRAINT "fk.custom_field_set_relation.set_id" FOREIGN KEY (set_id)
                    REFERENCES custom_field_set (id) ON UPDATE CASCADE ON DELETE CASCADE
            )
            SQL);
    }
}

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
class Migration1536233340NumberRange extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233340;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS number_range (
                id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_id_uuid CHECK (octet_length(id) = 16),
                type_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_type_id_uuid CHECK (octet_length(type_id) = 16),
                global SMALLINT NOT NULL CONSTRAINT chk_sw_number_range_global_bool CHECK (global IN (0, 1)),
                pattern VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                start INTEGER NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS number_range_translation (
                number_range_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_translation_number_range_id_uuid CHECK (octet_length(number_range_id) = 16),
                name VARCHAR(64) COLLATE sw_type_ci NULL,
                description VARCHAR(255) COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (number_range_id, language_id),
                CONSTRAINT "fk.number_range_translation.number_range_id" FOREIGN KEY (number_range_id)
                    REFERENCES number_range (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.number_range_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "number_range_translation__fk.number_range_tran_646aa541f571d38f" ON number_range_translation (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS number_range_state (
                id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_state_id_uuid CHECK (octet_length(id) = 16),
                number_range_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_state_number_range_id_uuid CHECK (octet_length(number_range_id) = 16),
                last_value INTEGER NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (number_range_id),
                CONSTRAINT "number_range_state__number_range_state__uniq.id" UNIQUE (id)
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "number_range_state__idx.number_range_id" ON number_range_state (number_range_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS number_range_type (
                id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_type_id_uuid CHECK (octet_length(id) = 16),
                technical_name VARCHAR(64) COLLATE sw_type_ci,
                global SMALLINT NOT NULL CONSTRAINT chk_sw_number_range_type_global_bool CHECK (global IN (0, 1)),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "number_range_type__number_range_type__uniq.technical_name" UNIQUE (technical_name)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS number_range_type_translation (
                number_range_type_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_type_translation_number_range_type_id_uuid CHECK (octet_length(number_range_type_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_type_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                type_name VARCHAR(64) COLLATE sw_type_ci NOT NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (number_range_type_id, language_id),
                CONSTRAINT "fk.number_range_type_translation.number_range_type_id" FOREIGN KEY (number_range_type_id)
                    REFERENCES number_range_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.number_range_type_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "number_range_type_translation__fk.number_range_667a7edd5d223374" ON number_range_type_translation (language_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS number_range_sales_channel (
                id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_sales_channel_id_uuid CHECK (octet_length(id) = 16),
                number_range_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_sales_channel_number_range_id_uuid CHECK (octet_length(number_range_id) = 16),
                sales_channel_id BYTEA NULL CONSTRAINT chk_sw_number_range_sales_channel_sales_channel_id_uuid CHECK (octet_length(sales_channel_id) = 16),
                number_range_type_id BYTEA NOT NULL CONSTRAINT chk_sw_number_range_sales_channel_number_range_type_id_uuid CHECK (octet_length(number_range_type_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT number_range_sales_channel__number_range_sales_e20e806a5d905af6 UNIQUE (number_range_id, sales_channel_id),
                CONSTRAINT "fk.number_range_sales_channel.number_range_id" FOREIGN KEY (number_range_id)
                    REFERENCES number_range (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.number_range_sales_channel.sales_channel_id" FOREIGN KEY (sales_channel_id)
                    REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.number_range_sales_channel.number_range_type_id" FOREIGN KEY (number_range_type_id)
                    REFERENCES number_range_type (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "number_range_sales_channel__fk.number_range_sa_a6130d378e98659f" ON number_range_sales_channel (sales_channel_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "number_range_sales_channel__fk.number_range_sa_98ed2f89948d6239" ON number_range_sales_channel (number_range_type_id)');
    }
}

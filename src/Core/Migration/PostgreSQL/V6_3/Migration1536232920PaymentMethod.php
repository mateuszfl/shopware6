<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
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
class Migration1536232920PaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232920;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(\sprintf(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payment_method (
                id BYTEA NOT NULL CONSTRAINT chk_sw_payment_method_id_uuid CHECK (octet_length(id) = 16),
                handler_identifier VARCHAR(255) COLLATE sw_type_ci NOT NULL DEFAULT %s,
                "position" INTEGER NOT NULL DEFAULT 1,
                active SMALLINT NOT NULL DEFAULT 0 CONSTRAINT chk_sw_payment_method_active_bool CHECK (active IN (0, 1)),
                availability_rule_id BYTEA NULL CONSTRAINT chk_sw_payment_method_availability_rule_id_uuid CHECK (octet_length(availability_rule_id) = 16),
                plugin_id BYTEA NULL CONSTRAINT chk_sw_payment_method_plugin_id_uuid CHECK (octet_length(plugin_id) = 16),
                media_id BYTEA NULL CONSTRAINT chk_sw_payment_method_media_id_uuid CHECK (octet_length(media_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id),
                CONSTRAINT "fk.payment_method.availability_rule_id" FOREIGN KEY (availability_rule_id)
                    REFERENCES rule (id) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT "fk.payment_method.plugin_id" FOREIGN KEY (plugin_id)
                    REFERENCES plugin (id) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT "fk.payment_method.media_id" FOREIGN KEY (media_id)
                    REFERENCES media (id) ON DELETE SET NULL ON UPDATE CASCADE
            )
            SQL, $connection->getDatabasePlatform()->quoteStringLiteral(DefaultPayment::class)));

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "payment_method__fk.payment_method.availability_rule_id" ON payment_method (availability_rule_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "payment_method__fk.payment_method.plugin_id" ON payment_method (plugin_id)');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "payment_method__fk.payment_method.media_id" ON payment_method (media_id)');

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payment_method_translation (
                payment_method_id BYTEA NOT NULL CONSTRAINT chk_sw_payment_method_translation_payment_method_id_uuid CHECK (octet_length(payment_method_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_payment_method_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NULL,
                description TEXT COLLATE sw_type_ci NULL,
                custom_fields JSONB NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (payment_method_id, language_id),
                CONSTRAINT "fk.payment_method_translation.language_id" FOREIGN KEY (language_id)
                    REFERENCES language (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.payment_method_translation.payment_method_id" FOREIGN KEY (payment_method_id)
                    REFERENCES payment_method (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "payment_method_translation__fk.payment_method__a32b01b5860dfa71" ON payment_method_translation (language_id)');
    }
}

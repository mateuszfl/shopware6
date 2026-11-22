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
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1565079228AddAclStructure extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565079228;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS acl_role (
                id BYTEA NOT NULL CONSTRAINT chk_sw_acl_role_id_uuid CHECK (octet_length(id) = 16),
                name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS acl_resource (
                resource VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                privilege VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                acl_role_id BYTEA NOT NULL CONSTRAINT chk_sw_acl_resource_acl_role_id_uuid CHECK (octet_length(acl_role_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (resource, privilege, acl_role_id),
                CONSTRAINT "fk.acl_resource.acl_role_id" FOREIGN KEY (acl_role_id)
                    REFERENCES acl_role (id) ON DELETE CASCADE
            )
            SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS acl_user_role (
                user_id BYTEA NOT NULL CONSTRAINT chk_sw_acl_user_role_user_id_uuid CHECK (octet_length(user_id) = 16),
                acl_role_id BYTEA NOT NULL CONSTRAINT chk_sw_acl_user_role_acl_role_id_uuid CHECK (octet_length(acl_role_id) = 16),
                created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (user_id, acl_role_id),
                CONSTRAINT "fk.acl_user_role.user_id" FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT "fk.acl_user_role.acl_role_id" FOREIGN KEY (acl_role_id)
                    REFERENCES acl_role (id) ON DELETE CASCADE
            )
            SQL);

        $this->addColumn($connection, 'user', 'admin', 'SMALLINT');

        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "acl_resource__fk.acl_resource.acl_role_id" ON acl_resource (acl_role_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS "acl_user_role__fk.acl_user_role.acl_role_id" ON acl_user_role (acl_role_id)');

        if (!TableHelper::foreignKeyExists($connection, 'acl_resource', 'fk.acl_resource.acl_role_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE acl_resource ADD CONSTRAINT "fk.acl_resource.acl_role_id" FOREIGN KEY (acl_role_id)
                    REFERENCES acl_role (id) ON DELETE CASCADE
                SQL);
        }
        if (!TableHelper::foreignKeyExists($connection, 'acl_user_role', 'fk.acl_user_role.user_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE acl_user_role ADD CONSTRAINT "fk.acl_user_role.user_id" FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE
                SQL);
        }
        if (!TableHelper::foreignKeyExists($connection, 'acl_user_role', 'fk.acl_user_role.acl_role_id')) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE acl_user_role ADD CONSTRAINT "fk.acl_user_role.acl_role_id" FOREIGN KEY (acl_role_id)
                    REFERENCES acl_role (id) ON DELETE CASCADE
                SQL);
        }

        $connection->executeStatement('UPDATE "user" SET admin = 1');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

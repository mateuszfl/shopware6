<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Service\WebhookLoaderTest
 */
#[Package('framework')]
class WebhookLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $roleIds
     *
     * @return array<string, AclPrivilegeCollection>
     */
    public function getPrivilegesForRoles(array $roleIds): array
    {
        $roles = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT id, privileges
                FROM acl_role
                WHERE id IN (:aclRoleIds)
            SQL,
            ['aclRoleIds' => Uuid::fromHexToBytesList($roleIds)],
            ['aclRoleIds' => ArrayParameterType::BINARY],
        );

        if (!$roles) {
            return [];
        }

        $privileges = [];
        foreach ($roles as $privilege) {
            $privileges[Uuid::fromBytesToHex($privilege['id'])]
                = new AclPrivilegeCollection(json_decode((string) $privilege['privileges'], true, 512, \JSON_THROW_ON_ERROR));
        }

        return $privileges;
    }

    /**
     * @return list<Webhook>
     */
    public function getWebhooks(): array
    {
        $sql = <<<'SQL'
            SELECT
                sw_uuid_hex(w.id) AS webhook_id,
                w.name AS webhook_name,
                w.event_name AS event_name,
                w.url AS webhook_url,
                w.only_live_version AS only_live_version,
                sw_uuid_hex(a.id) AS app_id,
                a.name AS app_name,
                a.active AS app_active,
                a.source_type AS app_source_type,
                a.version AS app_version,
                a.app_secret AS app_secret,
                sw_uuid_hex(a.acl_role_id) AS app_acl_role_id
            FROM webhook w
            LEFT JOIN app a ON (a.id = w.app_id)
            WHERE w.active = 1
        SQL;

        $webhooks = $this->connection->fetchAllAssociative($sql);

        return array_map(
            static fn (array $webhook) => new Webhook(
                $webhook['webhook_id'],
                $webhook['webhook_name'],
                $webhook['event_name'],
                $webhook['webhook_url'],
                (bool) $webhook['only_live_version'],
                $webhook['app_id'],
                $webhook['app_name'],
                $webhook['app_source_type'],
                (bool) $webhook['app_active'],
                $webhook['app_version'],
                $webhook['app_secret'],
                $webhook['app_acl_role_id']
            ),
            $webhooks
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\App;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class ActiveAdminAppLoader
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{name: string, active: int, integrationId: string, baseUrl: string, version: string, sourceType: string, privileges: array<string, list<string>>}>
     */
    public function getActiveAdminApps(): array
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        /** @var list<array{name: string, active: int, integrationId: string, baseUrl: string, version: string, sourceType: string, privileges: ?string}> $apps */
        $apps = $this->connection->fetchAllAssociative('SELECT
    app.name,
    app.active,
    ' . $dialect->hex(['app', 'integration_id']) . ' as ' . $dialect->quoteIdentifier('integrationId') . ',
    app.base_app_url as ' . $dialect->quoteIdentifier('baseUrl') . ',
    app.version,
    app.source_type as ' . $dialect->quoteIdentifier('sourceType') . ',
    ar.privileges as privileges
FROM app
LEFT JOIN acl_role ar on app.acl_role_id = ar.id
WHERE app.active = TRUE AND app.base_app_url is not null');

        return array_map(static function (array $item) {
            $item['active'] = (int) $item['active'];
            $privileges = $item['privileges'] ? json_decode($item['privileges'], true, 512, \JSON_THROW_ON_ERROR) : [];

            $item['privileges'] = [];

            foreach ($privileges as $privilege) {
                if (substr_count($privilege, ':') !== 1) {
                    $item['privileges']['additional'][] = $privilege;

                    continue;
                }

                [$entity, $key] = \explode(':', $privilege);
                $item['privileges'][$key][] = $entity;
            }

            return $item;
        }, $apps);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
#[Package('framework')]
class DbalKernelPluginLoader extends KernelPluginLoader
{
    public function __construct(
        ClassLoader $classLoader,
        ?string $pluginDir,
        private readonly Connection $connection
    ) {
        parent::__construct($classLoader, $pluginDir);
    }

    protected function loadPluginInfos(): void
    {
        $sql = <<<'SQL'
        -- dbal-plugin-loader
        SELECT name, base_class, path, version, autoload, managed_by_composer, composer_name,
               CASE WHEN active AND installed_at IS NOT NULL THEN 1 ELSE 0 END AS active
        FROM plugin
        ORDER BY installed_at
        SQL;

        $plugins = $this->connection->executeQuery($sql)->fetchAllAssociative();

        $result = [];

        /** @var array{name: string, base_class: string, active: int|bool, path: string, version: string|null, autoload: string, managed_by_composer: int|bool, composer_name: string} $plugin */
        foreach ($plugins as $plugin) {
            $result[] = [
                'baseClass' => $plugin['base_class'],
                'name' => $plugin['name'],
                'active' => (bool) $plugin['active'],
                'path' => $plugin['path'],
                'version' => $plugin['version'],
                'autoload' => json_decode((string) $plugin['autoload'], true, 512, \JSON_THROW_ON_ERROR),
                'managedByComposer' => (bool) $plugin['managed_by_composer'],
                'composerName' => $plugin['composer_name'],
            ];
        }

        $this->pluginInfos = $result;
    }
}

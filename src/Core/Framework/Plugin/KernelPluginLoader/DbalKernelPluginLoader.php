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
        $platform = $this->connection->getDatabasePlatform();
        $name = $platform->quoteSingleIdentifier('name');
        $baseClass = $platform->quoteSingleIdentifier('base_class');
        $active = $platform->quoteSingleIdentifier('active');
        $installedAt = $platform->quoteSingleIdentifier('installed_at');
        $path = $platform->quoteSingleIdentifier('path');
        $version = $platform->quoteSingleIdentifier('version');
        $autoload = $platform->quoteSingleIdentifier('autoload');
        $managedByComposer = $platform->quoteSingleIdentifier('managed_by_composer');
        $composerName = $platform->quoteSingleIdentifier('composer_name');
        $pluginTable = $platform->quoteSingleIdentifier('plugin');
        $baseClassAlias = $platform->quoteSingleIdentifier('baseClass');
        $managedByComposerAlias = $platform->quoteSingleIdentifier('managedByComposer');
        $composerNameAlias = $platform->quoteSingleIdentifier('composerName');

        $sql = <<<SQL
        -- dbal-plugin-loader
        SELECT
               {$name},
               {$baseClass} AS {$baseClassAlias},
               CASE WHEN {$active} AND {$installedAt} IS NOT NULL THEN 1 ELSE 0 END AS {$active},
               {$path},
               {$version},
               {$autoload},
               {$managedByComposer} AS {$managedByComposerAlias},
               {$composerName} AS {$composerNameAlias}
        FROM {$pluginTable}
        ORDER BY {$installedAt};
SQL;

        $plugins = $this->connection->executeQuery($sql)->fetchAllAssociative();

        $result = [];

        /** @var array{baseClass: string, name: string, active: int, path: string, version: string|null, autoload: string, managedByComposer: int, composerName: string } $plugin */
        foreach ($plugins as $plugin) {
            $result[] = [
                'baseClass' => $plugin['baseClass'],
                'name' => $plugin['name'],
                'active' => (bool) $plugin['active'],
                'path' => $plugin['path'],
                'version' => $plugin['version'],
                'autoload' => json_decode((string) $plugin['autoload'], true, 512, \JSON_THROW_ON_ERROR),
                'managedByComposer' => (bool) $plugin['managedByComposer'],
                'composerName' => $plugin['composerName'],
            ];
        }

        $this->pluginInfos = $result;
    }
}

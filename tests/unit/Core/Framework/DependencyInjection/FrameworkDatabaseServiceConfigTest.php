<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class FrameworkDatabaseServiceConfigTest extends TestCase
{
    public function testDatabaseAliasesResolveLegacySqlImplementationsAndTags(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'monolog';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $loader = new PhpFileLoader($container, new FileLocator());
        foreach (['services', 'cache', 'increment', 'message-queue', 'webhook'] as $file) {
            $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/' . $file . '.php');
        }
        static::assertSame($container->findDefinition(AbstractKeyValueStorage::class), $container->findDefinition('shopware.key_value_storage.database'));
        static::assertSame(MySQLIncrementer::class, $container->findDefinition('shopware.increment.gateway.database')->getClass());
        static::assertSame($container->findDefinition('shopware.increment.gateway.mysql'), $container->findDefinition('shopware.increment.gateway.database'));
        static::assertSame(MySQLStatsRepository::class, (string) $container->getAlias('shopware.message_queue.stats.database'));
        static::assertSame(MySQLWebhookReceiver::class, (string) $container->getAlias('shopware.webhook.receiver.database'));
        $invalidator = $container->findDefinition('shopware.cache.invalidator.storage.mysql');
        static::assertSame(MySQLInvalidatorStorage::class, $invalidator->getClass());
        static::assertSame([['storage' => 'mysql'], ['storage' => 'database']], $invalidator->getTag('shopware.cache.invalidator.storage'));
    }
}

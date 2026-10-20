<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use Psr\Log\AbstractLogger;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
final class DatabaseContractQueryLogger extends AbstractLogger
{
    public function __construct(private readonly string $path, private readonly Filesystem $filesystem = new Filesystem())
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!isset($context['sql']) || !\is_string($context['sql'])) {
            return;
        }

        $sql = preg_replace('~(?:\$\$.*?\$\$|\$([a-zA-Z_][a-zA-Z0-9_]*)\$.*?\$\1\$|(?:[Ee])?\'(?:\'\'|\\\\.|[^\'\\\\])*\')~s', '?', $context['sql']);
        if (!\is_string($sql)) {
            return;
        }

        $this->filesystem->appendToFile($this->path, preg_replace('/\s+/', ' ', trim($sql)) . "\n", true);
    }
}

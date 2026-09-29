<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Database\DatabasePlatformResolverTest
 */
#[Package('framework')]
final class DatabaseDialectException extends AdapterException
{
    public static function invalidIdentifier(string $identifier): self
    {
        return new self(Response::HTTP_BAD_REQUEST, 'FRAMEWORK__INVALID_DATABASE_IDENTIFIER', 'Invalid database identifier "{{ identifier }}".', ['identifier' => $identifier]);
    }

    public static function invalidOperation(string $reason): self
    {
        return new self(Response::HTTP_BAD_REQUEST, 'FRAMEWORK__INVALID_DATABASE_OPERATION', 'Invalid database operation: {{ reason }}.', ['reason' => $reason]);
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__UNSUPPORTED_DATABASE_DRIVER', 'Unsupported database driver "{{ driver }}".', ['driver' => $driver]);
    }

    public static function unsupportedPlatform(string $platform): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__UNSUPPORTED_DATABASE_PLATFORM', 'Unsupported database platform "{{ platform }}".', ['platform' => $platform]);
    }
}

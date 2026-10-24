<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Exception\DriverException;

/**
 * @internal
 */
class DummyDoctrineException extends DriverException
{
    public function __construct(
        int $errorCode,
        string $message = '',
        private readonly ?string $sqlState = null
    ) {
        $this->code = $errorCode;
        $this->message = $message;
    }

    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}

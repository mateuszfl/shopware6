<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Query;
use Shopware\Core\Framework\Log\Package;

/**
 * PostgreSQL reports RESTRICT violations as 23001; DBAL 4.4 only maps 23503.
 *
 * @internal
 */
#[Package('framework')]
final class PostgreSQLExceptionConverter implements ExceptionConverter
{
    public function __construct(private readonly ExceptionConverter $converter)
    {
    }

    public function convert(Exception $exception, ?Query $query): DriverException
    {
        if ($exception->getSQLState() === '23001') {
            return new ForeignKeyConstraintViolationException($exception, $query);
        }

        return $this->converter->convert($exception, $query);
    }
}

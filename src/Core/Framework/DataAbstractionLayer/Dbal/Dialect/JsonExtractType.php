<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect;

use Shopware\Core\Framework\Log\Package;

/**
 * Describes the target PHP/SQL type a value extracted from a JSON column should be coerced to.
 * The concrete SQL differs per database engine, so the mapping lives in the dialects.
 *
 * @internal
 */
#[Package('framework')]
enum JsonExtractType
{
    case STRING;
    case INT;
    case FLOAT;
    case BOOL;
    case DATETIME;
    case DATE;
}

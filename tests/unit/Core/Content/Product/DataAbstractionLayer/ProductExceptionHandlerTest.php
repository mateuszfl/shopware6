<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductExceptionHandler;
use Shopware\Core\Content\Product\Exception\DuplicateProductNumberException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductExceptionHandler::class)]
class ProductExceptionHandlerTest extends TestCase
{
    public function testPostgreSqlDuplicateNumberRetainsCommaAndHyphen(): void
    {
        $exception = new UniqueConstraintViolationException(new PdoException('duplicate key value violates unique constraint "uniq.product.product_number__version_id" DETAIL: Key (product_number, version_id)=(SW-1, literal, \x00000000000000000000000000000001) already exists.', '23505'), null);
        static::assertEquals(new DuplicateProductNumberException('SW-1, literal', $exception), (new ProductExceptionHandler())->matchException($exception));
    }

    public function testPostgreSqlUnrelatedUniqueIndexIsNotMapped(): void
    {
        $exception = new UniqueConstraintViolationException(new PdoException('duplicate key value violates unique constraint "other"', '23505'), null);
        static::assertNull((new ProductExceptionHandler())->matchException($exception));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\TechnicalNameExceptionHandler;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TechnicalNameExceptionHandler::class)]
class TechnicalNameExceptionHandlerTest extends TestCase
{
    public function testPriority(): void
    {
        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, (new TechnicalNameExceptionHandler())->getPriority());
    }

    public function testPaymentException(): void
    {
        $dbalE = new \Exception(
            'An exception occurred while executing a query: SQLSTATE[23000]: '
            . 'Integrity constraint violation: '
            . '1062 Duplicate entry \'payment_test\' for key \'payment_method.uniq.technical_name\''
        );

        $handler = new TechnicalNameExceptionHandler();
        $e = $handler->matchException($dbalE);

        static::assertInstanceOf(PaymentException::class, $e);
        static::assertSame(PaymentException::PAYMENT_METHOD_DUPLICATE_TECHNICAL_NAME, $e->getErrorCode());
        static::assertSame('The technical name "payment_test" is not unique.', $e->getMessage());
    }

    public function testShippingException(): void
    {
        $dbalE = new \Exception(
            'An exception occurred while executing a query: SQLSTATE[23000]: '
            . 'Integrity constraint violation: '
            . '1062 Duplicate entry \'shipping_test\' for key \'shipping_method.uniq.technical_name\''
        );

        $handler = new TechnicalNameExceptionHandler();
        $e = $handler->matchException($dbalE);

        static::assertInstanceOf(ShippingException::class, $e);
        static::assertSame(ShippingException::SHIPPING_METHOD_DUPLICATE_TECHNICAL_NAME, $e->getErrorCode());
        static::assertSame('The technical name "shipping_test" is not unique.', $e->getMessage());
    }

    public function testImportExportException(): void
    {
        $dbalE = new \Exception(
            'An exception occurred while executing a query: SQLSTATE[23000]: '
            . 'Integrity constraint violation: '
            . '1062 Duplicate entry \'import_export_test\' for key \'import_export_profile.uniq.import_export_profile.technical_name\''
        );

        $handler = new TechnicalNameExceptionHandler();
        $e = $handler->matchException($dbalE);

        static::assertInstanceOf(ImportExportException::class, $e);
        static::assertSame(ImportExportException::DUPLICATE_TECHNICAL_NAME, $e->getErrorCode());
        static::assertSame('The technical name "import_export_test" is not unique.', $e->getMessage());
    }

    #[DataProvider('postgresqlConstraints')]
    public function testPostgreSqlUniqueConstraintsUseDomainExceptions(string $table, string $constraint, string $expectedCode): void
    {
        $driver = new PdoException('duplicate key value violates unique constraint "' . $constraint . '"
DETAIL: Key (technical_name)=(literal, name) already exists.', '23505');
        $exception = new UniqueConstraintViolationException($driver, new Query('INSERT INTO "' . $table . '" VALUES (?)', [], []));
        $mapped = (new TechnicalNameExceptionHandler())->matchException($exception);
        static::assertNotNull($mapped);
        static::assertInstanceOf(HttpException::class, $mapped);
        static::assertSame($expectedCode, $mapped->getErrorCode());
        static::assertSame('The technical name "literal, name" is not unique.', $mapped->getMessage());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function postgresqlConstraints(): iterable
    {
        yield 'payment index scoped by table' => ['payment_method', 'uniq.technical_name', PaymentException::PAYMENT_METHOD_DUPLICATE_TECHNICAL_NAME];
        yield 'shipping index scoped by table' => ['shipping_method', 'uniq.technical_name', ShippingException::SHIPPING_METHOD_DUPLICATE_TECHNICAL_NAME];
        yield 'import profile named index' => ['import_export_profile', 'uniq.import_export_profile.technical_name', ImportExportException::DUPLICATE_TECHNICAL_NAME];
    }

    public function testUnrelatedException(): void
    {
        $dbalE = new \Exception(
            'An exception occurred while executing a query: SQLSTATE[23000]: '
            . 'Integrity constraint violation: 1451 Cannot delete or update a parent row: '
            . 'a foreign key constraint fails '
            . '(`shopware`.`theme_media`, CONSTRAINT `fk.theme_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE)'
        );

        $handler = new TechnicalNameExceptionHandler();
        $e = $handler->matchException($dbalE);

        static::assertNull($e);
    }
}

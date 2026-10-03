<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class TechnicalNameExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if ($e instanceof UniqueConstraintViolationException && $e->getSQLState() === '23505'
            && preg_match('/unique constraint "(?<constraint>[^"\n]+)".*?Key \(technical_name\)=\((?<technicalName>.*)\) already exists/s', $e->getMessage(), $matches)
        ) {
            return match ($matches['constraint']) {
                'uniq.technical_name' => match (true) {
                    str_contains($e->getQuery()?->getSQL() ?? '', '"payment_method"') => PaymentException::duplicateTechnicalName($matches['technicalName']),
                    str_contains($e->getQuery()?->getSQL() ?? '', '"shipping_method"') => ShippingException::duplicateTechnicalName($matches['technicalName']),
                    default => null,
                },
                'uniq.payment_method.technical_name' => PaymentException::duplicateTechnicalName($matches['technicalName']),
                'uniq.shipping_method.technical_name' => ShippingException::duplicateTechnicalName($matches['technicalName']),
                'uniq.import_export_profile.technical_name' => ImportExportException::duplicateTechnicalName($matches['technicalName']),
                default => null,
            };
        }

        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'payment_method.uniq\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            return PaymentException::duplicateTechnicalName($matches['technicalName']);
        }

        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'shipping_method.uniq\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            return ShippingException::duplicateTechnicalName($matches['technicalName']);
        }

        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'import_export_profile\.uniq\.import_export_profile\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            return ImportExportException::duplicateTechnicalName($matches['technicalName']);
        }

        return null;
    }
}

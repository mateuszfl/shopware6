<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNonExistentExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if ($e instanceof ForeignKeyConstraintViolationException && $e->getSQLState() === '23503'
            && str_contains($e->getMessage(), 'foreign key constraint "fk.category.after_category_id"')
        ) {
            return CategoryException::afterCategoryNotFound();
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1452 Cannot add or update a child row: a foreign key constraint fails.*category\.after_category_id/', $e->getMessage())) {
            return CategoryException::afterCategoryNotFound();
        }

        return null;
    }
}

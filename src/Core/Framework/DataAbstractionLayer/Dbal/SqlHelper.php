<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @description This class is used to build a select part of a SQL.
 *
 * @final
 */
#[Package('framework')]
class SqlHelper
{
    /**
     * @description This function is used to build the json_object of the select part of a query.
     *
     * @param array<string, string> $fields The key is the alias of the column, the value is the column itself
     *
     * @example
     *
     * $fields = [ 'id' => 'product.id', 'name' => 'product.name' ];
     * $select = EntityDefinitionQueryHelper::object($fields, 'product');
     * // $select = "JSON_OBJECT('id', product.id, 'name', product.name) as product";
     */
    public static function object(array $fields, string $alias, ?Connection $connection = null, ?QueryBuilder $query = null): string
    {
        if ($connection !== null) {
            return self::build($fields, $alias, $connection, $query, aggregate: false);
        }

        EntityDefinitionQueryHelper::escape($alias);
        $columnSelectSql = '';

        foreach ($fields as $columnAlias => $column) {
            $columnSelectSql .= \sprintf('\'%s\', %s,', str_replace(['\\', '\''], ['\\\\', '\'\''], $columnAlias), $column);
        }

        $columnSelectSql = rtrim($columnSelectSql, ',');

        $sql = 'JSON_OBJECT(%s) as %s';

        return \sprintf($sql, $columnSelectSql, $alias);
    }

    /**
     * @description This function is used to build the json_array of the select part of a query.
     *
     * @param array<int|string, string> $fields The key is the alias of the column, the value is the column itself
     *
     * @example
     *
     * $fields = [ 'id' => 'tags.id', 'name' => 'tags.name' ];
     * $select = EntityDefinitionQueryHelper::object($fields, 'tags');
     * // $select = "CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT('id', tags.id, 'name', tags.name)), ']') as tags";
     */
    public static function objectArray(array $fields, string $alias, ?Connection $connection = null, ?QueryBuilder $query = null): string
    {
        if ($connection !== null) {
            return self::build($fields, $alias, $connection, $query, aggregate: true);
        }

        EntityDefinitionQueryHelper::escape($alias);
        $columnSelectSql = '';

        foreach ($fields as $columnAlias => $column) {
            $columnSelectSql .= \sprintf('\'%s\', %s,', str_replace(['\\', '\''], ['\\\\', '\'\''], (string) $columnAlias), $column);
        }

        $columnSelectSql = rtrim($columnSelectSql, ',');

        $sql = <<<EOF
CONCAT(
    '[',
         GROUP_CONCAT(DISTINCT
             JSON_OBJECT(
                %s
             )
         ),
    ']'
) as %s
EOF;

        return \sprintf($sql, $columnSelectSql, $alias);
    }

    /**
     * @param array<int|string, string> $fields
     */
    private static function build(array $fields, string $alias, Connection $connection, ?QueryBuilder $query, bool $aggregate): string
    {
        $fragment = (new DatabasePlatformResolver($connection))->dialect()->jsonObjectExpressions($fields, $aggregate);
        if ($query === null) {
            return $fragment->inline($connection) . ' AS ' . EntityDefinitionQueryHelper::escape($alias, $connection);
        }
        foreach ($fragment->parameters as $key => $value) {
            $query->setParameter((string) $key, $value, $fragment->types[$key] ?? ParameterType::STRING);
        }

        return $fragment->sql . ' AS ' . EntityDefinitionQueryHelper::escape($alias, $connection);
    }
}

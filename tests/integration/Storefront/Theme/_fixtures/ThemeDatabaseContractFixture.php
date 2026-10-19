<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\_fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;

/**
 * @internal
 */
final class ThemeDatabaseContractFixture
{
    private const TABLES = ['theme_runtime_config', 'theme_child', 'theme_sales_channel', 'sales_channel_translation', 'sales_channel_domain', 'snippet_set', 'product_visibility', 'product', 'cms_page', 'category_translation', 'product_search_config', 'category', 'language', 'locale', 'sales_channel', 'theme'];

    private bool $persistentTables = false;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function setUp(bool $persistentTables = false): void
    {
        $this->persistentTables = $persistentTables;
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $schemaPrefix = $postgres ? 'pg_temp.' : '';
        $drop = $persistentTables || $postgres ? 'DROP TABLE IF EXISTS ' : 'DROP TEMPORARY TABLE IF EXISTS ';
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement($drop . $schemaPrefix . $table);
        }

        (new DatabaseFunctionInstaller())->install($this->connection);

        $binary = $postgres ? 'BYTEA' : 'BINARY(16)';
        $json = $postgres ? 'JSONB' : 'JSON';
        $temporary = $persistentTables ? 'CREATE TABLE ' : 'CREATE TEMPORARY TABLE ';
        $this->connection->executeStatement($temporary . "theme (
            id $binary PRIMARY KEY,
            parent_theme_id $binary NULL,
            technical_name VARCHAR(255) NULL,
            base_config $json NULL
        )");
        $this->connection->executeStatement("$temporary sales_channel (
            id $binary PRIMARY KEY,
            type_id $binary NOT NULL,
            active BOOLEAN NOT NULL,
            maintenance BOOLEAN NULL,
            maintenance_ip_allowlist TEXT NULL,
            maintenance_ip_whitelist TEXT NULL,
            navigation_category_id $binary NULL,
            navigation_category_version_id $binary NULL
        )");
        $this->connection->executeStatement("$temporary snippet_set (id $binary PRIMARY KEY, iso VARCHAR(16) NOT NULL)");
        $this->connection->executeStatement("$temporary sales_channel_domain (
            id $binary PRIMARY KEY,
            url VARCHAR(255) NOT NULL,
            sales_channel_id $binary NOT NULL,
            snippet_set_id $binary NOT NULL,
            currency_id $binary NOT NULL,
            language_id $binary NOT NULL
        )");
        $this->connection->executeStatement("$temporary sales_channel_translation (sales_channel_id $binary NOT NULL, name VARCHAR(255) NOT NULL)");
        $this->connection->executeStatement("$temporary product (id $binary PRIMARY KEY, version_id $binary NOT NULL, active BOOLEAN NOT NULL, stock INT NOT NULL)");
        $this->connection->executeStatement("$temporary product_visibility (product_id $binary NOT NULL, product_version_id $binary NOT NULL, sales_channel_id $binary NOT NULL)");
        $this->connection->executeStatement("$temporary cms_page (id $binary NOT NULL, version_id $binary NOT NULL, type VARCHAR(32) NOT NULL)");
        $this->connection->executeStatement("$temporary category (
            id $binary NOT NULL, version_id $binary NOT NULL, parent_id $binary NULL,
            active BOOLEAN NOT NULL, cms_page_id $binary NULL, path TEXT NULL
        )");
        $this->connection->executeStatement("$temporary category_translation (category_id $binary NOT NULL, link_type VARCHAR(32) NOT NULL, internal_link $binary NOT NULL)");
        $this->connection->executeStatement("$temporary product_search_config (id $binary NULL, language_id $binary NOT NULL, min_search_length INT NOT NULL, excluded_terms TEXT NULL)");
        $this->connection->executeStatement("$temporary locale (id $binary NOT NULL, code VARCHAR(16) NOT NULL)");
        $this->connection->executeStatement("$temporary language (id $binary NOT NULL, translation_code_id $binary NOT NULL)");
        $this->connection->executeStatement("$temporary theme_sales_channel (
            theme_id $binary NOT NULL,
            sales_channel_id $binary NOT NULL,
            PRIMARY KEY (theme_id, sales_channel_id)
        )");
        $this->connection->executeStatement("$temporary theme_child (
            child_id $binary NOT NULL,
            parent_id $binary NOT NULL,
            PRIMARY KEY (child_id, parent_id)
        )");
        $this->connection->executeStatement("$temporary theme_runtime_config (
            theme_id $binary PRIMARY KEY,
            technical_name VARCHAR(255) NULL UNIQUE,
            resolved_config TEXT NOT NULL CHECK (resolved_config <> '{\"key\":\"failure\"}'),
            view_inheritance TEXT NOT NULL,
            script_files TEXT NOT NULL,
            icon_sets TEXT NOT NULL,
            import_map TEXT NULL,
            updated_at VARCHAR(32) NOT NULL
        )");
    }

    public function tearDown(): void
    {
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        $schemaPrefix = $postgres ? 'pg_temp.' : '';
        $drop = $this->persistentTables || $postgres ? 'DROP TABLE IF EXISTS ' : 'DROP TEMPORARY TABLE IF EXISTS ';
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement($drop . $schemaPrefix . $table);
        }
    }
}

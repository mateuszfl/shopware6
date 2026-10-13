<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Contract\_fixtures;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;
use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Adapter\Database\PostgreSQLSessionMiddleware;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;

/**
 * @internal
 */
final class SystemSqlFixture
{
    public readonly Connection $connection;

    public readonly DatabaseSchema $schema;

    private bool $ownsTables = false;

    public function __construct()
    {
        $this->connection = self::connect();
        $this->schema = new DatabaseSchema($this->connection);
    }

    public function setUp(): void
    {
        if ($this->schema->isPostgreSQL()) {
            $this->connection->executeStatement('CREATE SCHEMA sw_system_contract');
            $this->ownsTables = true;
            $this->connection->executeStatement('SET search_path TO sw_system_contract');
        } else {
            foreach ($this->tables() as $table) {
                if (TableHelper::tableExists($this->connection, $table)) {
                    throw new \RuntimeException('System fixture requires absent owned tables: ' . $table);
                }
            }
        }
        $this->ownsTables = true;
        (new DatabaseFunctionInstaller())->install($this->connection);
        foreach ($this->schema->prerequisites() as $query) {
            $this->connection->executeStatement($query);
        }
        $binary = $this->schema->isPostgreSQL() ? 'BYTEA' : 'BINARY(16)';
        $json = $this->schema->isPostgreSQL() ? 'JSONB' : 'JSON';
        $date = $this->schema->isPostgreSQL() ? 'TIMESTAMP(3)' : 'DATETIME(3)';
        $user = $this->connection->quoteSingleIdentifier('user');
        $global = $this->connection->quoteSingleIdentifier('global');
        $lastValue = $this->connection->quoteSingleIdentifier('last_value');
        $this->connection->executeStatement("CREATE TABLE number_range_state (id $binary PRIMARY KEY, number_range_id $binary NOT NULL UNIQUE, $lastValue BIGINT NOT NULL, created_at $date NOT NULL)");
        $this->connection->executeStatement("CREATE TABLE number_range_type (id $binary PRIMARY KEY, technical_name VARCHAR(255), $global SMALLINT)");
        $this->connection->executeStatement("CREATE TABLE number_range (id $binary PRIMARY KEY, type_id $binary, pattern VARCHAR(255), start BIGINT, $global SMALLINT)");
        $this->connection->executeStatement("CREATE TABLE number_range_sales_channel (number_range_id $binary, sales_channel_id $binary)");
        $this->connection->executeStatement("CREATE TABLE sales_channel_api_context (token VARCHAR(255) PRIMARY KEY, payload $json NOT NULL, sales_channel_id $binary, customer_id $binary, updated_at $date NOT NULL, UNIQUE (sales_channel_id, customer_id))");
        $this->connection->executeStatement("CREATE TABLE system_config (id $binary PRIMARY KEY, configuration_key VARCHAR(255) NOT NULL, configuration_value $json, sales_channel_id $binary, created_at $date NOT NULL, updated_at $date, UNIQUE(configuration_key, sales_channel_id))");
        $this->connection->executeStatement("CREATE TABLE app (id $binary PRIMARY KEY, name VARCHAR(255), active SMALLINT, acl_role_id $binary)");
        $this->connection->executeStatement("CREATE TABLE custom_entity (id $binary PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, fields $json NOT NULL, flags $json NOT NULL, app_id $binary, plugin_id $binary, custom_fields_aware SMALLINT, label_property VARCHAR(255), deleted_at $date, created_at $date NOT NULL, updated_at $date)");
        $this->connection->executeStatement("CREATE TABLE language (id $binary PRIMARY KEY, parent_id $binary, locale_id $binary, translation_code_id $binary)");
        $this->connection->executeStatement("CREATE TABLE locale (id $binary PRIMARY KEY, code VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE currency (id $binary PRIMARY KEY, iso_code VARCHAR(255), factor DECIMAL(10,3))");
        $this->connection->executeStatement("CREATE TABLE sales_channel (id $binary PRIMARY KEY, type_id $binary, language_id $binary, currency_id $binary)");
        $this->connection->executeStatement("CREATE TABLE sales_channel_language (language_id $binary, sales_channel_id $binary)");
        $this->connection->executeStatement("CREATE TABLE $user (id $binary PRIMARY KEY, username VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE consent_state (id $binary PRIMARY KEY, name VARCHAR(255), identifier VARCHAR(255), state VARCHAR(255), actor VARCHAR(255), revision VARCHAR(255), updated_at $date, UNIQUE(name,identifier))");
        $this->connection->executeStatement("CREATE TABLE consent_log (consent_name VARCHAR(255), timestamp $date, message $json)");
        $this->connection->executeStatement("CREATE TABLE snippet_set (id $binary PRIMARY KEY, iso VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE snippet (id $binary PRIMARY KEY, snippet_set_id $binary, translation_key VARCHAR(255), value TEXT, author VARCHAR(255), custom_fields $json, created_at $date, UNIQUE(snippet_set_id,translation_key))");
        $this->connection->executeStatement("CREATE TABLE acl_role (id $binary PRIMARY KEY, privileges $json)");
        $this->connection->executeStatement("CREATE TABLE salutation (id $binary PRIMARY KEY, salutation_key VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE tax (id $binary PRIMARY KEY, tax_rate DECIMAL(10,3))");
        $this->connection->executeStatement("CREATE TABLE state_machine (id $binary PRIMARY KEY, technical_name VARCHAR(255), initial_state_id $binary)");
        $this->connection->executeStatement("CREATE TABLE state_machine_transition (id $binary PRIMARY KEY, state_machine_id $binary, from_state_id $binary, to_state_id $binary, action_name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE sales_channel_domain (sales_channel_id $binary, language_id $binary, snippet_set_id $binary)");
        $this->connection->executeStatement("CREATE TABLE tag (id $binary PRIMARY KEY, name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE contract_tag_mapping (tag_id $binary, usage_related_id $binary)");
        $this->connection->executeStatement("CREATE TABLE sales_channel_currency (sales_channel_id $binary, currency_id $binary)");
        $this->connection->executeStatement("CREATE TABLE custom_field (id $binary, name VARCHAR(255), store_api_aware SMALLINT, set_id $binary)");
        $this->connection->executeStatement("CREATE TABLE custom_field_set_relation (set_id $binary, entity_name VARCHAR(255))");
        $this->connection->executeStatement("CREATE TABLE custom_field_set (id $binary, name VARCHAR(255), app_id $binary, extension_name VARCHAR(255))");
        $blob = $this->schema->isPostgreSQL() ? 'BYTEA' : 'LONGBLOB';
        $this->connection->executeStatement("CREATE TABLE usage_item (id $binary, version_id $binary, name VARCHAR(255), body $blob, created_at $date, updated_at $date, PRIMARY KEY(id,version_id))");
        $this->connection->executeStatement("CREATE TABLE usage_related (id $binary PRIMARY KEY)");
        $this->connection->executeStatement("CREATE TABLE usage_mapping (usage_item_id $binary, usage_item_version_id $binary, usage_related_id $binary, PRIMARY KEY(usage_item_id,usage_item_version_id,usage_related_id))");
        $this->connection->executeStatement("CREATE TABLE usage_data_entity_deletion (id $binary PRIMARY KEY, entity_name VARCHAR(255), entity_ids $json, deleted_at $date)");
    }

    public function tearDown(): void
    {
        if (!$this->ownsTables) {
            $this->connection->close();

            return;
        }
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        if ($this->schema->isPostgreSQL()) {
            $this->connection->executeStatement('DROP SCHEMA sw_system_contract CASCADE');
        } else {
            foreach ($this->tables() as $table) {
                $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $this->connection->quoteSingleIdentifier($table));
            }
        }
        $this->connection->close();
    }

    public static function connect(): Connection
    {
        $initial = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $connection = DriverManager::getConnection($initial->getParams(), (new Configuration())->setMiddlewares([new PostgreSQLSessionMiddleware()]));
        $initial->close();
        if ((new DatabaseSchema($connection))->isPostgreSQL()) {
            $connection->executeStatement('SET search_path TO sw_system_contract');
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): void
    {
        $types = [];
        foreach ($data as $key => $value) {
            if ($value !== null && ($key === 'id' || str_ends_with($key, '_id'))) {
                $data[$key] = Uuid::fromHexToBytes($value);
                $types[$key] = ParameterType::BINARY;
            }
        }
        $this->connection->insert($this->connection->quoteSingleIdentifier($table), $data, $types);
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return ['custom_field_set', 'custom_field_set_relation', 'custom_field', 'sales_channel_currency', 'contract_tag_mapping', 'tag', 'sales_channel_domain', 'state_machine_transition', 'state_machine', 'tax', 'salutation', 'acl_role', 'usage_mapping', 'usage_related', 'usage_item', 'ce_system_translation', 'ce_system_related', 'ce_system', 'usage_data_entity_deletion', 'snippet', 'snippet_set', 'consent_log', 'consent_state', 'user', 'sales_channel_language', 'sales_channel', 'currency', 'locale', 'language', 'custom_entity', 'app', 'system_config', 'sales_channel_api_context', 'number_range_sales_channel', 'number_range', 'number_range_type', 'number_range_state'];
    }
}

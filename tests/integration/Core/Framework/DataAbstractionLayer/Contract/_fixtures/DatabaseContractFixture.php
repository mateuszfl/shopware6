<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;

/**
 * Small, explicit schema used before the production migration history is portable.
 * It is always created through the physical primary connection.
 *
 * @internal
 */
final class DatabaseContractFixture
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'sw_contract_product_tag',
        'sw_contract_product_translation',
        'sw_contract_tag',
        'sw_contract_product',
        'sw_contract_language',
    ];

    public function __construct(private readonly Connection $primary)
    {
    }

    public function setUp(): void
    {
        $endpoint = DatabaseEndpoint::observe($this->primary);
        if ($endpoint->standby) {
            throw new \RuntimeException('Database contract fixtures may only be created on the primary endpoint');
        }

        $this->tearDown();

        foreach ($this->schemaStatements() as $statement) {
            $this->primary->executeStatement($statement);
        }
    }

    public function tearDown(): void
    {
        $dialect = (new DatabasePlatformResolver($this->primary))->dialect();
        foreach (self::TABLES as $table) {
            $this->primary->executeStatement('DROP TABLE IF EXISTS ' . $dialect->quoteIdentifier($table));
        }
    }

    /**
     * @return list<string>
     */
    private function schemaStatements(): array
    {
        if ($this->primary->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return [
                <<<'SQL'
                    CREATE TABLE sw_contract_language (
                        id BYTEA NOT NULL CHECK (octet_length(id) = 16),
                        name VARCHAR(255) NOT NULL,
                        PRIMARY KEY (id)
                    )
                    SQL,
                <<<'SQL'
                    CREATE TABLE sw_contract_product (
                        id BYTEA NOT NULL CHECK (octet_length(id) = 16),
                        version_id BYTEA NOT NULL CHECK (octet_length(version_id) = 16),
                        parent_id BYTEA NULL CHECK (parent_id IS NULL OR octet_length(parent_id) = 16),
                        parent_version_id BYTEA NULL CHECK (parent_version_id IS NULL OR octet_length(parent_version_id) = 16),
                        payload JSONB NULL,
                        prices JSONB NOT NULL,
                        active BOOLEAN NOT NULL,
                        created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                        PRIMARY KEY (id, version_id),
                        FOREIGN KEY (parent_id, parent_version_id)
                            REFERENCES sw_contract_product (id, version_id)
                    )
                    SQL,
                <<<'SQL'
                    CREATE TABLE sw_contract_product_translation (
                        product_id BYTEA NOT NULL CHECK (octet_length(product_id) = 16),
                        product_version_id BYTEA NOT NULL CHECK (octet_length(product_version_id) = 16),
                        language_id BYTEA NOT NULL CHECK (octet_length(language_id) = 16),
                        name VARCHAR(255) NOT NULL,
                        description TEXT NULL,
                        payload JSONB NULL,
                        created_at TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                        PRIMARY KEY (product_id, product_version_id, language_id),
                        FOREIGN KEY (product_id, product_version_id)
                            REFERENCES sw_contract_product (id, version_id) ON DELETE CASCADE,
                        FOREIGN KEY (language_id)
                            REFERENCES sw_contract_language (id)
                    )
                    SQL,
                <<<'SQL'
                    CREATE TABLE sw_contract_tag (
                        id BYTEA NOT NULL CHECK (octet_length(id) = 16),
                        name VARCHAR(255) NOT NULL,
                        PRIMARY KEY (id),
                        UNIQUE (name)
                    )
                    SQL,
                <<<'SQL'
                    CREATE TABLE sw_contract_product_tag (
                        product_id BYTEA NOT NULL CHECK (octet_length(product_id) = 16),
                        product_version_id BYTEA NOT NULL CHECK (octet_length(product_version_id) = 16),
                        tag_id BYTEA NOT NULL CHECK (octet_length(tag_id) = 16),
                        PRIMARY KEY (product_id, product_version_id, tag_id),
                        FOREIGN KEY (product_id, product_version_id)
                            REFERENCES sw_contract_product (id, version_id) ON DELETE CASCADE,
                        FOREIGN KEY (tag_id)
                            REFERENCES sw_contract_tag (id) ON DELETE CASCADE
                    )
                    SQL,
            ];
        }

        return [
            <<<'SQL'
                CREATE TABLE sw_contract_language (
                    id BINARY(16) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE sw_contract_product (
                    id BINARY(16) NOT NULL,
                    version_id BINARY(16) NOT NULL,
                    parent_id BINARY(16) NULL,
                    parent_version_id BINARY(16) NULL,
                    payload JSON NULL,
                    prices JSON NOT NULL,
                    active TINYINT(1) NOT NULL,
                    created_at DATETIME(3) NOT NULL,
                    PRIMARY KEY (id, version_id),
                    FOREIGN KEY (parent_id, parent_version_id)
                        REFERENCES sw_contract_product (id, version_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE sw_contract_product_translation (
                    product_id BINARY(16) NOT NULL,
                    product_version_id BINARY(16) NOT NULL,
                    language_id BINARY(16) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    payload JSON NULL,
                    created_at DATETIME(3) NOT NULL,
                    PRIMARY KEY (product_id, product_version_id, language_id),
                    FOREIGN KEY (product_id, product_version_id)
                        REFERENCES sw_contract_product (id, version_id) ON DELETE CASCADE,
                    FOREIGN KEY (language_id)
                        REFERENCES sw_contract_language (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE sw_contract_tag (
                    id BINARY(16) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE sw_contract_product_tag (
                    product_id BINARY(16) NOT NULL,
                    product_version_id BINARY(16) NOT NULL,
                    tag_id BINARY(16) NOT NULL,
                    PRIMARY KEY (product_id, product_version_id, tag_id),
                    FOREIGN KEY (product_id, product_version_id)
                        REFERENCES sw_contract_product (id, version_id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id)
                        REFERENCES sw_contract_tag (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
        ];
    }
}

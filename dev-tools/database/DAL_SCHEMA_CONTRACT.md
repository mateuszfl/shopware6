# DAL schema contract

`database-dal-schema` verifies schema generation and introspection against
PostgreSQL 18.6 and MySQL 8.4.11. It uses owned PostgreSQL schemas and the
dedicated MySQL `shopware_json_test` database. It does not install Shopware or
run production migrations.

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-dal-schema
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-dal-schema
```

The same URLs support the combined regression suites:
`database-contract,database-dal-reader,database-dal-json,database-dal-write,database-dal-search,database-dal-schema`.

## Physical schema and introspection

`SchemaBuilder` accepts the connection used to generate the schema. Its
connection-free construction retains MySQL declarations. Core's
`DatabaseSchema` delegates tables, primary keys, foreign keys, indexes and
ordinary defaults to the platform's SchemaManager and comparator. Its
PostgreSQL catalog queries complement metadata absent from DBAL 4.4:
checks, timestamp precision, generated expressions and the physical JSON type.

The mappings follow [TYPE_CONTRACT.md](TYPE_CONTRACT.md): 16-byte binary
identifiers become `bytea` with length checks; blobs retain arbitrary bytes;
booleans use checked `smallint`; unsigned 32-bit integers use checked `bigint`;
JSON uses `jsonb`; datetimes use `timestamp(3) without time zone`; decimal
precision and scale are retained; auto-increment uses identity. Definitions
containing `AutoIncrementField` receive the required unique index. Existing
equivalent unique indexes retain their names.

`TableHelper` distinguishes checked binary identifiers from blobs and restores
the boolean and unsigned integer metadata for helper-generated columns.
It reports `jsonb` as the physical JSON type. Raw DBAL introspection retains
DBAL's physical type mapping. Introspected helper-generated schemas can be
prepared and compared again without generating DDL.

Schema prerequisites create `sw_type_ci` in `current_schema()`, making it
available in the application's search path. An existing collation must use
nondeterministic ICU `und-u-ks-level1`. PostgreSQL records the ICU version in
its catalog; operators must review and rebuild affected indexes after an ICU
version change. Text fields inherit this collation; explicit column collations
are preserved. Password, timezone, cron/date interval, remote address and enum
fields use the deterministic `C` collation. Technical strings declared through
generic `StringField` require an explicit schema collation when appropriate;
field names do not determine their comparison policy.

PostgreSQL table and column identifiers are quoted and rejected above 63 bytes
instead of being silently truncated. Generated FK/check/index names retain a
readable prefix and a deterministic SHA-256 suffix when shortening is needed.
Index names include the table name because PostgreSQL indexes share a schema
namespace. Custom foreign key names retain `fk_ce_` for lifecycle cleanup.
MySQL/MariaDB retain their existing naming and type declarations.

## Diffs and lifecycle

The fixture covers create, introspection and repeated no-op generation; versioned
PK/FK relations and cascade deletion; ordinary indexes, unique indexes and
explicit unique constraints; defaults, nullable columns, decimal precision,
timestamp precision, checked values and changed/removed managed checks.
It checks real data after applying generated DDL.

PostgreSQL schema definitions can supply trusted `shopware_checks` and
`shopware_generated` table options. Helper-owned checks use the `chk_sw_`
prefix. Generated definitions specify `expression` and `stored`. Existing
stored or virtual expressions can be retained; stored expression and type
changes are tested. Changing between stored/virtual columns or converting
a regular column to generated requires an explicit migration. A definition
that does not model a retained generated column preserves its generation
metadata. Generated columns not present in the target schema are removed
by the ordinary column diff.

Expression comparison preserves grouping and string literals. When PostgreSQL
deparsing adds grouping or casts, read-only `EXPLAIN ... LIMIT 0` compares
the parsed forms without executing the expression against rows. References
to columns added by the pending diff cause the expression to be treated as
changed; a savepoint protects an existing transaction from parser errors.

Custom entity tests exercise create, translation and versioned mapping tables,
core table extensions, an added field and deletion. Repeated updates execute
zero `CREATE`, `ALTER` or `DROP` statements, measured through DBAL logging
middleware. Existing data, core indexes, JSON, generated columns and unrelated
checks remain intact. PostgreSQL executes the DDL transactionally with foreign
keys enforced. MySQL retains its foreign-key-check workaround and restores the
session setting in `finally`; schema locks are released in `finally` on both
platforms. A rejected custom association releases the real lock and allows
the corrected schema update to be retried.

## Remaining boundary

This fixture proves the DAL schema boundary, not a complete PostgreSQL shop.
Custom entity persistence, domain services, index producers, installation and
production migration equivalents belong to their subsequent tasks. MariaDB
uses the unchanged MySQL platform path but was not an acceptance server for
this suite. Expression parsing and schema generation add catalog/planner
queries; production-scale schema performance still needs measurement.

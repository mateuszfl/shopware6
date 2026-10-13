# System SQL contract

The `database-system` suite exercises production system services on disposable
PostgreSQL 18.6, MySQL 8.4.11 and MariaDB 11.8.9 databases. It creates only its
owned tables and, on PostgreSQL, the `sw_system_contract` schema. It refuses
pre-existing owned tables on MySQL/MariaDB. It does not boot the Shopware kernel,
run the installer or execute the migration chain.

Use the test database URLs from [the database fixtures](README.md). MySQL and
MariaDB lock observation also requires a maintenance connection in
`DATABASE_FUNCTION_INSTALL_URL`, with access to the server's lock metadata:

```sh
DATABASE_CONTRACT_URL="$PG_SYSTEM_TEST_URL" vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-system
DATABASE_CONTRACT_URL="$MYSQL_SYSTEM_TEST_URL" DATABASE_FUNCTION_INSTALL_URL="$MYSQL_MAINTENANCE_TEST_URL" vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-system
DATABASE_CONTRACT_URL="$MARIADB_SYSTEM_TEST_URL" DATABASE_FUNCTION_INSTALL_URL="$MARIADB_MAINTENANCE_TEST_URL" vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-system
```

The fixture installs `sw_uuid_hex` and the PostgreSQL ICU collation explicitly.
Runtime requests, factories and reconnects never install SQL functions. Existing
installations must install the shared function through the maintenance procedure
in [README.md](README.md) before deploying code that calls it.

## Number allocation and storage migration

`IncrementSqlStorage` uses one PostgreSQL upsert with `RETURNING` to reserve the
first or next number atomically. MySQL/MariaDB retain their session-variable
allocation path. Both paths respect a raised start value. `preview` does not
reserve, `set` replaces a state and `increaseToAtLeast` cannot lower it. The
`mysql` storage configuration alias continues to select SQL storage, including
on PostgreSQL; Redis is optional.

Separate processes and connections reserve against an absent and an existing
state. The test holds the first transaction open and verifies the second process
is waiting for that exact database lock before releasing it. PostgreSQL uses
`pg_blocking_pids`, MySQL `performance_schema.data_lock_waits`, and MariaDB
`information_schema.INNODB_LOCK_WAITS`. Successful reservations return successive
numbers. Allocation inside a surrounding transaction follows that transaction's
rollback semantics.

`number-range:migrate` is exercised between SQL and the production Redis storage
adapter with the existing in-memory Redis stub, using separate states. Forward
and reverse migration preserve the counter. This tests storage migration without
requiring a Redis server. Migration during live allocation retains the command's
existing warning and requires quiescing allocation for an operational handover.

## Context, configuration and adjacent services

The context schema retains the actual constraints: primary key `token` and unique
`(sales_channel_id, customer_id)`. PostgreSQL updates a token in place. A conflict
with the customer key rolls back its savepoint before replacing every row that
conflicts with either key. This preserves the historical two-row replacement
behavior, including tokens moved between channels and nullable channel rows.
The suite verifies a surrounding transaction remains usable after this conflict.

Public context load/save, recursive payload merging, empty permissions, customer
selection, expiry, revocation and token replacement are covered. Configuration
writes and repeated updates bind binary identities. Global configuration is read
before channel overrides explicitly on both engines; false, zero and empty
string overrides retain their existing loader and domain behavior.

The suite also exercises language/context loading, sync foreign-key resolvers,
custom-field set ownership lookup, snippets and snippet-set fallback, application
configuration privileges, language/channel/state validation, Store API custom
field filtering, and tag filters. Consent acceptance, revocation, revision and
staging cleanup use the production repository, service and audit logger. Usage
data tests execute iteration, dispatch and mapping lookup with live and draft
versions, binary IDs/payloads and deletion records. External dispatch is observed
through a test collaborator; it sends no network requests.

UUID projections use `sw_uuid_hex` and snake_case SQL result names. PHP maps
public camelCase properties explicitly and normalizes remaining BYTEA streams at
the consuming boundary.

## Custom entities and foreign-key failures

The production lifecycle service parses a committed XML fixture and uses the
production metadata persister, `CustomEntityDefinition`, DAL reader/writer and
version manager. Tests cover schema creation, changes preserving data and metadata
identity, repeated schema updates, removal, `keepUserData`, restrict decisions,
generated FK enforcement and recovery after failure. Existing schema contract
coverage additionally proves repeated updates execute no DDL and that translation,
mapping and unrelated core schema objects are retained.

Metadata replacement is transactional. PostgreSQL DDL uses the schema policy from
[DAL_SCHEMA_CONTRACT.md](DAL_SCHEMA_CONTRACT.md), including UUID checks, JSONB,
unsigned ranges, timestamps and generated names. Metadata persistence and the
subsequent DDL update remain separate operations, as on the existing lifecycle;
a failed DDL update must be corrected and retried. The suite does not claim atomic
metadata-plus-DDL behavior on MySQL, where DDL commits implicitly.

DBAL 4.4 does not classify PostgreSQL `23001` RESTRICT failures as foreign-key
violations. The existing PostgreSQL middleware wraps its converter to restore
`ForeignKeyConstraintViolationException`; other classifications delegate to DBAL.
Both runtime and maintenance factories are exercised against a real restrict
constraint, with rollback and subsequent reads. Unit tests verify ordinary FK,
unique and deadlock conversion remains delegated.

Released MySQL migrations are unchanged. PostgreSQL migration equivalents,
full installation, scale/performance measurements, replica/long-running system
service scenarios and final API/storefront E2E belong to the later migration and
acceptance tasks. These fixtures establish service behavior on primary databases,
without claiming completion of those later gates.

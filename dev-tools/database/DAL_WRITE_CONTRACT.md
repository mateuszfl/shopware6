# Production DAL write contract

The `database-dal-write` suite runs the production `EntityWriter`, command
extractor, gateway, result factory, reader, repository, Sync service and
`VersionManager` against PostgreSQL 18.6 and MySQL 8.4.11. It creates portable
fixture definitions and tables, including version commits, inheritance,
parent foreign keys, mapping cascades, locked entities and JSON fields. It
does not install Shopware or run production migrations.

PostgreSQL uses the disposable `sw_write_contract` schema in
`shopware_pg_test`. MySQL requires the separate `shopware_json_test` database
from [JSON_READ_CONTRACT.md](JSON_READ_CONTRACT.md). Tables and the PostgreSQL
schema are removed after each test. Run only one instance of this suite per
database at a time. The concurrency tests themselves create a second PHP
process and database connection.

## Commands

From the repository root, with the topologies from [README.md](README.md)
running and the test databases created:

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json,database-dal-write
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json,database-dal-write
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/unit/Core/Framework/DataAbstractionLayer/Doctrine tests/unit/Core/Framework/DataAbstractionLayer/Dbal/EntityWriteGatewayTest.php tests/unit/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandlerTest.php tests/unit/Core/Content/Product/DataAbstractionLayer/ProductExceptionHandlerTest.php tests/unit/Core/Framework/DataAbstractionLayer/Write/EntityWriteResultFactoryTest.php
composer cs
composer phpstan
```

Use `--testsuite database-dal-write` to run only the write contract. The host
must have `pdo_pgsql` and `pdo_mysql`. The pinned web images provide both
extensions. These credentials are for isolated local development only.

## Conflict and type behavior

| Operation | Contract |
| --- | --- |
| Ordinary batch insert | The whole transaction fails on an invalid row. |
| Ignore duplicates | PostgreSQL uses `ON CONFLICT DO NOTHING`, suppressing uniqueness conflicts only. Existing MySQL `INSERT IGNORE` behavior is retained; MySQL can also suppress an invalid foreign key row. |
| Update on duplicate | PostgreSQL requires `setConflictTarget(table, columns)` naming the actual unique key. Rows execute in input order to allow repeated keys in one batch. MySQL retains `ON DUPLICATE KEY UPDATE`. |
| Mapping replacement | The gateway declares the complete mapping primary key. MySQL retains `REPLACE`; PostgreSQL locks the table and deletes then inserts each row in order. This preserves delete cascades and missing-column defaults. |
| Other replacement callers | PostgreSQL rejects missing targets or unaudited secondary, partial or expression conflicts. Each caller must supply a complete conflict strategy before porting. |
| Missing batch column | Uses SQL `DEFAULT`, including an omitted column on a repeated key. |
| Explicit null | Binds SQL `NULL` independently of a missing column. |
| IDs, references and blobs | Bind as binary, including version/reference-version fields and binary strings containing NUL and non-UTF-8 bytes. PostgreSQL bytea streams are normalized for change sets and plain ID searches. |

The PostgreSQL replacement table lock serializes the delete/insert pair with
ordinary writers, including when no previous row exists. It favors the
existing replacement semantics over update semantics and can reduce write
concurrency on heavily used mapping tables. A table with additional unique
conflicts needs a caller-specific implementation rather than this strategy.

## Partial JSON updates

`JsonUpdateCommand` changes bound top-level members of the stored object
directly in SQL. Member names are literal: dots, quotes and backslashes are
not interpreted as additional path steps. Values are JSON-encoded with
their types, including JSON null, the string `"null"`, booleans, numbers,
lists and objects. Non-finite numbers fail before SQL execution.

SQL null starts an empty object. Existing JSON-null, array and scalar roots
retain their value, matching MySQL `JSON_SET`. Nested objects supplied as
member values replace that member; this command does not recursively merge
them. Updates compose against the current locked row, so simultaneous
changes to different members retain both values. The test forces a second
gateway process to block behind an uncommitted update and checks both
members after commit.

## Transactions, retries and event effects

DBAL `RetryableException` covers PostgreSQL serialization failure `40001`
and deadlock `40P01`, alongside existing MySQL deadlock `40001` handling.
Only the outermost retryable helper retries the transaction, at most eleven
attempts. A connection loss or unknown commit outcome is not retried.
Non-retryable unique, foreign-key and check failures propagate after
rollback. PostgreSQL SQLSTATEs `23505`, `23503` and `23514` are exercised;
product-number and technical-name handlers also translate actual `23505`
errors using the existing named-constraint contracts.

`EntityWriteEvent` and `EntityDeleteEvent` success callbacks run after post
write validation and the outermost transaction controlled by
`RetryableTransaction` commits. A success callback exception propagates
without replaying the committed database transaction. A caught nested
savepoint failure removes callbacks registered within that scope. A retry
discards callbacks from its abandoned attempt; error callbacks run on the
final failed attempt. Pre/post validation subscribers may run again and
must register external effects through the event callbacks.

Completed queue flushes register separate rollback callbacks. Rollback
restores their input rows in original order, including multiple flushes and
rows added between them. A committed flush clears its rows permanently.
Transient driver failures do not poison `WriteContext` with exceptions or
mark commands as failed. The suite verifies these mutable-state contracts
as well as real two-connection deadlock and serialization races, with one
effect and one committed modification per participant.

Raw `Connection::transactional()` or manually opened transactions have no
commit notification hook here. They retain gateway-local callback timing.
Surround DAL writes with `RetryableTransaction::transactional()` or
`retryable()` when external effects depend on the surrounding commit. The
audited DAL gateway, mapping queue, writer, Sync and VersionManager paths
use the managed helpers. Extension-owned raw transactions remain the
extension's responsibility.

## Scope and remaining work

The fixtures prove CRUD, mixed Sync batches, rollback, mapping replacement,
duplicates, binary bindings, parent result resolution, locked write/delete
validation and version create/update/merge/draft deletion on both engines.
Only the plain ID search needed by VersionManager was adjusted; scored,
grouped and full search contracts remain task 010.

Production PostgreSQL schema generation remains task 011, and migration
runtime and migration porting remain tasks 021–031.
Unaudited `MultiInsertQueryQueue(useReplace: true)` callers outside the DAL
mapping gateway include Administration user configuration, custom entities,
system configuration, cart migration, keyword indexers and migrations. They
retain MySQL semantics and fail explicitly on PostgreSQL until their owning
tasks declare and test the conflict contract. Domain-specific exception
handlers outside the product-number and technical-name cases likewise
remain with their domain owners.

This suite does not claim a complete Shopware installation, HTTP/Messenger
worker behavior, or the physical replica/long-running acceptance matrix
R01–R11 (task 032a). Replica routing is covered by the existing independent
replica suites. The developer-facing callback and JSON changes are recorded
in `RELEASE_INFO-6.7.md`; no next-major upgrade entry is needed for these
fixes, since existing code remains valid.

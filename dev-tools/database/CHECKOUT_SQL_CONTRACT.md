# Checkout SQL contract

The `database-checkout` suite runs against disposable PostgreSQL 18.6 and MySQL
8.4.11 databases from [the database fixtures](README.md). It installs the shared
`sw_uuid_hex` prerequisite explicitly in its owned schema. Application connections
and reconnects never create functions.

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-checkout
DATABASE_FUNCTION_INSTALL_URL='mysql://root:root@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-checkout
```

The schema contains binary UUIDs, live and snapshot versions, composite order
references, unique order numbers, promotion codes, cart payloads and dates.
The fixture uses production DAL command extraction, serialization, readers,
writer, gateway, repository, version manager and nested event dispatcher.
`OrderPersister`, `OrderStockSubscriber`, `StockStorage`,
`PromotionIndividualCodeRedeemer`, `PromotionCodeGateway` and
`PromotionRedemptionUpdater` perform the checkout writes. The converter returns
an explicit order payload; language loading and unused aggregation are stubs.
Redis storage migration uses the existing in-memory Redis stub. There are no
external payment handlers or storefront calculation in this contract.

## Persistence and result boundaries

Cart payloads bind as binary data, including compressed NUL and non-UTF-8 bytes.
New carts use a platform conflict clause to update the whole payload, compression
mode, rules and timestamp together. Updates to a deleted persisted cart do not
resurrect it. Pruning selects a bounded deterministic token batch before deletion.
`cart:migrate` upserts by token and retains the existing storage identity instead
of deleting and reinserting a row.

UUID projections use `sw_uuid_hex` on both engines. Static identifiers and aliases
use snake case; camel case result keys are mapped explicitly at the PHP boundary.
Reserved table names and dynamic identifiers retain platform quoting. Parameters
carrying binary UUIDs use DBAL binary types, and raw BYTEA streams are normalized
when a caller still consumes bytes.

The suite checks customer review counts and completed-order totals/date metadata,
payment/shipping/document technical-name references, invoice snapshot matching,
the legacy live fallback and V2's missing-snapshot rejection. V2 partial config
writes retain untouched company and extension JSON, and document type names are
resolved from binary write-command values. Filename validation reads persisted
global and assigned sales-channel configuration with typed binary command IDs,
and detects collisions after inherited infixes are merged. Customer recovery
cleanup deletes all expired batches, including the exact 48-hour threshold,
while retaining newer rows.

Payment handler and refund reads bind binary UUID parameters and normalize refund
transaction/method references at the PHP boundary. A dedicated payment SQL
contract checks core and app handler lookup, missing methods, refund state
rejection and dispatch to a local test handler. It performs no external payments.

## Concurrency and transaction scope

Separate PHP processes with separate DBAL connections execute competing public
operations. A bounded barrier retains the first operation's lock until the second
process is waiting for that lock inside the database operation. PostgreSQL
`pg_blocking_pids` and MySQL `performance_schema.data_lock_waits` confirm the
specific blocker and waiter connection IDs before release. MySQL lock inspection
uses `DATABASE_FUNCTION_INSTALL_URL` maintenance credentials; the checkout workers
use the ordinary application account. The barrier changes lock duration, without
introducing an alternative checkout implementation.

Normal `OrderPersister::persist` opens one raw DBAL transaction around repository
creation and its local write callbacks. It locks referenced promotions in UUID
order before DAL reads, including MySQL repeatable-read snapshots. Stock changes,
code claims and redemption aggregates must succeed with the persisted order.
An individual code uses a conditional claim, rejects another order and accepts a
repeated claim from the same order. Different codes update aggregate counts under
the promotion lock. The suite verifies the losing order leaves no order/customer
rows or stock delta, and failures before validation and after stock/code writes
roll back all database effects. An unexpected database deadlock propagates after
cleanup; the persister does not replay externally observable subscribers.

This boundary applies to the normal standalone persister call. A surrounding
`RetryableTransaction` can defer success callbacks beyond that transaction's
commit. Direct administrative repository writes do not acquire the persister's
whole-order transaction. External side effects emitted by synchronous event
listeners cannot be undone by a database rollback. These paths need their own
transaction and outbox design; the fixture makes no stronger guarantee.

The stock policy continues to permit negative stock: two accepted orders against
one unit leave stock and available stock at -1, sales at 2 and availability false.
This contract establishes atomic deltas and rollback, without changing overselling
policy. Number allocation, full installation/migrations and Store API E2E remain
separate work.

Promotion exclusions are a mirrored graph. The public updater performs its
read, repair and reciprocal append/removal inside one retryable transaction,
locking existing promotions in UUID order. This conservatively serializes graph
writes and can contend with checkout or administrative writers. Nested calls
propagate retryable errors to their transaction owner; a local retry does not
repair a previously committed upstream write or an earlier transaction snapshot.
The suite covers missing/invalid references, NULL arrays, reciprocal removal,
idempotency and concurrent repairs. A one-shot database deadlock trigger checks complete repair
retry; on MySQL its fixture DDL also uses `DATABASE_FUNCTION_INSTALL_URL` maintenance
credentials because binary logging restricts trigger creation. Application operations
still use the normal test user. Measurements and a narrower locking strategy
are subsequent optimization work.

Released MySQL migrations are unchanged. The fixture describes the current cart
storage shape; PostgreSQL schema installation and migration equivalents belong
to the installation/migration tasks. The final Store API E2E belongs to task 032.

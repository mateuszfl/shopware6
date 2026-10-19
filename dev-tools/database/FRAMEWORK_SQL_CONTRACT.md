# Framework SQL contract

The `database-framework` suite runs production Framework adapters against disposable
PostgreSQL 18.6 and MySQL 8.4 databases. It owns `sw_framework_contract` on PostgreSQL
and refuses pre-existing owned tables on MySQL/MariaDB. It does not install Shopware
or execute the historical migrations. See [database setup](README.md) for the test
connections and the required `sw_uuid_hex` function.

```sh
DATABASE_CONTRACT_URL="$FRAMEWORK_TEST_URL" \
DATABASE_FUNCTION_INSTALL_URL="$FRAMEWORK_MAINTENANCE_TEST_URL" \
XDEBUG_MODE=off vendor/bin/phpunit -c phpunit.database-contract.xml.dist \
  --testsuite database-framework --fail-on-warning --fail-on-skipped
```

Both connections must address the same disposable database ending in `_test`.
The maintenance connection is used to observe actual lock waits in the concurrent
increment tests. It needs access to PostgreSQL `pg_blocking_pids`, MySQL
`performance_schema.data_lock_waits`, or MariaDB `INNODB_LOCK_WAITS`.

## Configuration and compatibility

The historical `mysql` selectors and PHP class/service names remain available.
They select the SQL implementation for the connection's platform. New configuration
can use the neutral `database` selector for increment pools and delayed invalidation:

```yaml
shopware:
    increment:
        user_activity:
            type: database
    cache:
        invalidation:
            delay_options:
                storage: database
```

The defaults remain `mysql`. Neutral service aliases are
`shopware.increment.gateway.database`, `shopware.key_value_storage.database`,
`shopware.message_queue.stats.database`, and `shopware.webhook.receiver.database`.
Delayed invalidation registers the `database` storage tag on the existing SQL
service. Redis remains optional; none of these SQL paths depends on Redis.

## Storage, locks and expiry

`MySQLKeyValueStorage` overwrites a key with a platform upsert and retains its
existing string/JSON conversion and per-instance reset behavior. Increment pools
use atomic insert-or-increment, retain nonnegative decrements, and resolve listing
ties consistently. Separate processes prove that both first creation and an
existing counter update wait for the exact earlier transaction before completing.
Counters participate in surrounding transaction rollback.

Delayed invalidation claims rows with `FOR UPDATE SKIP LOCKED` and deletes only
those exact binary IDs, in bounded write batches. A locked row between two claimed
IDs stays available to its owner. Nested calls keep their caller's isolation level
and transaction; standalone calls temporarily use read committed isolation.

OAuth expiry cleanup keeps rows exactly at the cutoff and removes rows before it.
Refresh-token family IDs retain their binary API representation, including when
PDO returns a stream. Revocation and replay remove the intended family/user rows.
MCP toolsets preserve creation time on repeated enable, stay scoped to sessions,
and are reclaimed using the session stores' liveness and TTL across both endpoints.
Tool-result reads remain scoped to their originating session.

Cart cleanup uses an exclusive next-midnight UTC cutoff to preserve the former
whole-day and signed-day behavior. Log retention uses creation time and ID to
resolve ties. A failed log insert is rolled back to its own savepoint before the
fallback write, leaving an outer transaction usable. Message-type statistics sort
by the latest occurrence of each type, with the type name resolving equal dates.

## Messenger and scheduler

The suite builds a real DAL repository for `scheduled_task`. `TaskScheduler` queues
a due task through the Shopware Doctrine transport factory. A real Symfony worker
calls `ScheduledTaskHandler` and `ScheduledTaskExecutor`, records its first failure,
persists a Messenger retry, runs the retry successfully, acknowledges the queue
row and reschedules the task. Registry and health queries use the same SQL tables.
This is a database fixture run without a full kernel or HTTP server.

## Webhook outbox

Event-log creation, delivery sequence allocation and stream creation share one
transaction. Duplicate inserts roll back their own savepoint before returning a
no-op, so PostgreSQL does not leave an outer transaction aborted. IDs, partition
keys and serialized messages are explicitly bound as binary values. JSON request
and response metadata remain JSON; serialized payloads support arbitrary bytes.

Stream claims read candidates in pages and then lock one unique partition key,
rechecking due status, terminal event state and lease eligibility. This avoids
InnoDB locking unrelated filesort input rows before applying `LIMIT`. Never-claimed
streams sort first on both engines. The suite also claims past more than one page
of locked candidates.

Lease ownership, renewal, release, expiry and crash recovery are tested through
the production store and receiver. Recovery locks the delivery/event pair and
updates both rows in one retriable transaction. Attempt and sequence checks reject
stale terminal writers. Future retries remain ineligible until due; terminal event
states prevent redelivery. Broken serialized messages fail without incrementing an
attempt. Legacy backfill creates only the missing queued delivery. Cleanup crosses
the 500-row batch boundary and preserves queued events with a delivery row.

## Apps and plugins

Feature synchronization preserves identity/creation time while updating payloads,
removes stale features, supports rollback, keeps detached user data on uninstall,
and reattaches it by app name on reinstall. App secrets, privileges, webhook
metadata, template timestamps, app-owned flow deactivation, user token invalidation
and plugin metadata are read/written through the production services. Plugin
loading preserves inactive/uninstalled states and Composer/autoload metadata.
Database aliases use snake case and are mapped back to existing PHP contracts.

## Limits of this proof

The fixture covers runtime SQL before the migration port. It does not prove full
installation, every historical DDL statement, external HTTP delivery, complete
App/Plugin installation hooks, production throughput, or replica lifecycle. The
runtime completeness gate, migration acceptance, application acceptance and long
runner/replica work remain separate tasks. Some peripheral DAL-only or demodata
callers have existing unit coverage and a query audit rather than their own full
application scenario. Candidate pagination is best-effort under concurrent stream
changes; a later poll can observe newly eligible streams. Outbox dispatch remains
at-least-once and lease expiry cannot prevent external HTTP side effects from a
worker that has already lost ownership.

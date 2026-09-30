# Replica contract on disposable fixture databases

This is the task 006a fixture result, not acceptance of a fully installed Shopware on PostgreSQL. Use the topology and disposable `*_test` databases from [README.md](README.md). The application uses `DATABASE_URL` for primary and `DATABASE_REPLICA_0_URL` for standby. The tests use separate control connections for WAL replay and backend termination. The application user cannot administer replication.

## Reproduce

Start PostgreSQL primary and standby with `docker compose -f compose.pg-replicated.yaml up -d --wait pg-primary pg-replica-0`, then run:

```sh
docker compose -f compose.pg-replicated.yaml run --rm --no-deps web vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-replica-routing,database-replica-failures
```

Start the second physical standby with `docker compose -f compose.pg-replicated.yaml -f compose.pg-second.yaml --profile second-replica up -d --wait pg-replica-1`, then run:

```sh
docker compose -f compose.pg-replicated.yaml -f compose.pg-second.yaml --profile second-replica run --rm --no-deps web vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-two-replicas
```

The restart test runs on the Docker host so it can restart the two services separately while keeping one PHP client process alive. Give it `DATABASE_CONTRACT_URL` for the published primary port 5434, `DATABASE_CONTRACT_REPLICA_URL` for standby port 5435, and `DATABASE_CONTRACT_CONTROL_URL` for an administrator on the same standby. All three URLs must name `shopware_pg_test`. Then run:

```sh
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-replica-restart-host
```

For the MySQL 8.4 baseline, start both services with `docker compose -f compose.mysql-replicated.yaml up -d --wait mysql-primary mysql-replica-0`, then run:

```sh
docker compose -f compose.mysql-replicated.yaml run --rm --no-deps web vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-replica-routing
```

These suites fail on absent topology instead of skipping. They log application PID, observed physical server identity, backend PID for failures, SQLSTATE and replay LSN without logging credentials. Fixture DDL runs only on primary. Pause always resumes in `finally`; setup and teardown wait for the standby replay barrier. Restart cleanup brings the tested services back to healthy state. Limits are 30 seconds for replay, 15 seconds for injected operation failure, and 120 seconds for restart readiness.

## Coverage and remaining acceptance

| ID | Fixture evidence | Full application follow-up |
| --- | --- | --- |
| R01 | `ReplicaRoutingContractTest::testReadWriteReadAndResetUseThePhysicalEndpoints`: initial read on standby, update and read on primary. | DAL and API paths in 032a after tasks 007–009. |
| R02 | Same test resets routing. A real Symfony Messenger `Worker` consumes three messages in one process with `ResetServicesListener`. A small Symfony HTTP `Kernel::handle(Request)` keeps one router across three requests and proves reset happens at the start of the following request. | Shopware's configured Messenger transport/listeners and a networked supported long-running HTTP runtime in 032a. The fixture test wires the listener and HTTP kernel directly. |
| R03 | Nested DBAL transaction/savepoint remains on primary during reset; rollback precedes reset and standby access. | Production DAL nested operations in 032a. |
| R04 | `pg_wal_replay_pause()` reaches `paused`; a primary commit advances LSN while replay stays behind. Ordinary standby read is stale; explicit primary and read-after-write are current. `finally` resumes and waits for the replay barrier. | Real handlers and API paths in 032a. |
| R05 | Reset during lag returns to a stale standby; explicit primary returns the current value. | Identify every cross-message and cross-request freshness dependency in 032a, especially indexing and checkout. |
| R06 | Unique violation rolls back aborted transaction before reset. Concurrent serializable writers produce SQLSTATE `40001`; a fresh transaction retries deliberately. | Deadlock, message retry/dead-letter, context and session cleanup on the Shopware worker in 032a. |
| R07 | Terminated primary and standby backends report errors; explicit close/reconnect restores endpoint. Separate container restarts retain one client PID and restore fresh backend PIDs, UTC, search path and isolation. Missing standby fails quickly without silent primary fallback on PostgreSQL and MySQL. | Retry budget and business-result accounting across actual application requests/messages in 032a. Promotion and automatic failover are outside this contract. |
| R08 | A second process terminates the primary backend during `INSERT` in a delayed trigger. The client receives an error; independent lookup of the business ID proves rollback before controlled retry. A separate case resolves a committed ID after subsequent connection loss. | Drop the connection in the uncertain COMMIT acknowledgement window and account for business effects after the result is resolved; this precise window is **not verified** by the fixture tests and belongs to 032a. |
| R09 | Three Messenger messages and three HTTP kernel requests share their respective process and router; endpoint state returns to standby. | 10,000 messages over 30 minutes, 1,000 requests on a supported networked HTTP runtime, batched command, RSS/backend/transaction sampling and fault injection in 032a. |
| R10 | Fixture DDL, sequence increment via read-looking `nextval`, and `FOR UPDATE` operate on primary when explicitly forced or transaction-bound. | Reindex, scheduler, order numbering, webhook outbox and installer paths in tasks 012–023 and 032a. |
| R11 | No replica DSN uses a plain connection; `keepReplica=false` stays on primary after write. Both configured PostgreSQL standbys have distinct observed endpoints, replay LSN barriers and router reads. | Full installation with two replicas and configuration permutations in 032a. No round-robin behavior is assumed. |

## Routing audit and task handoff

DBAL routes by method and connection state, not SQL text: `executeQuery()` and `fetch*()` can use standby, while `executeStatement()`, transactions and `prepare()` choose primary. `keepReplica=true` in `DatabaseConnectionFactory` permits the resetter to switch back; `keepReplica=false` keeps primary after a write. `ReplicaConnectionResetter` leaves an open transaction in place, so handlers must roll it back themselves. `ReplicaConnection::ensurePrimary()` appears at DAL repository write methods, `SyncService::doSync()` and `OrderRoute::load()`; it does not cover unrelated low-level callers.

| Code path | Observed risk | Owner before 032a |
| --- | --- | --- |
| `src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php::loadManyToMany` | MySQL `SET @n`/`SET @c` uses `executeQuery()` on a selected connection; session variables and many-to-many query must remain on the same physical endpoint. | 007 |
| `src/Core/Framework/DataAbstractionLayer/Dbal/EntityWriteGateway.php` | Read-looking queries participate in writes and must follow the primary selected by the repository or write transaction. Audit direct gateway callers. | 009 |
| `src/Core/Framework/DataAbstractionLayer/Indexing/ChildCountUpdater.php` and `src/Core/Content/Product/DataAbstractionLayer/ProductStreamUpdater.php` | `SELECT FOR UPDATE` requires a primary transaction, including during indexing and retry. | 012 |
| `src/Core/System/NumberRange/ValueGenerator/Pattern/IncrementStorage/IncrementSqlStorage.php` | `SELECT @nr...` reads session state set by an upsert; the two operations must share the same primary backend. | 015 |
| `src/Core/System/CustomEntity/Schema/CustomEntitySchemaUpdater.php` | `SET FOREIGN_KEY_CHECKS` uses `executeQuery()` even though it changes session state; PostgreSQL dialect and primary DDL need a platform path. | 015 |
| `src/Core/Framework/Webhook/Outbox/StreamLockService.php`, `WebhookOutboxStore.php`, `src/Core/Framework/Adapter/Cache/InvalidatorStorage/MySQLInvalidatorStorage.php`, `src/Core/Framework/Api/OAuth/AuthCodeRepository.php` | Locking reads must be evaluated inside a primary transaction; current callers and retry paths require scenario tests during lag. | 016 |
| `src/Core/Installer/Database/BlueGreenDeploymentService.php` | `CREATE TABLE` and `CREATE TRIGGER` are issued with `executeQuery()` and can route to a read-only standby; SQL also needs a PostgreSQL variant. | 022–023 |

No production behavior changed in task 006a. The suites add internal fixture coverage and configuration for a disposable MySQL replica and second PostgreSQL standby; neither `RELEASE_INFO` nor `UPGRADE` is needed for an external contract.

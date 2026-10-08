# Local database topologies

Run these Compose files **on their own**, without `compose.yaml`. They use separate
project names and never mount the existing `shopware-postgresql_db-data` volume.
The existing default MariaDB service is pinned to MariaDB 11.8.9 (digest in
`compose.yaml`); check the version of any existing MariaDB data before starting
that service. These topologies are disposable development environments, not a
Shopware installation.

| Topology | Start | Application database URLs | Adminer |
| --- | --- | --- | --- |
| PostgreSQL single | `docker compose -f compose.pg-single.yaml up -d --build` | `postgresql://shopware:shopware@pg-single:5432/shopware_pg?serverVersion=18.6&charset=utf8` | http://localhost:9081, server `pg-single` |
| PostgreSQL primary and standby | `docker compose -f compose.pg-replicated.yaml up -d --build` | Primary `pg-primary`, `DATABASE_REPLICA_0_URL` at `pg-replica-0` | http://localhost:9081, server `pg-primary` |
| PostgreSQL with two standbys | `docker compose -f compose.pg-replicated.yaml -f compose.pg-second.yaml --profile second-replica up -d --build` | Adds `DATABASE_REPLICA_1_URL` at `pg-replica-1` | http://localhost:9081 |
| MySQL primary and replica | `docker compose -f compose.mysql-replicated.yaml up -d --build` | Primary `mysql-primary`, `DATABASE_REPLICA_0_URL` at `mysql-replica-0` | http://localhost:9082, server `mysql-primary` |

The PostgreSQL application user is `shopware` and cannot administer replication.
The PostgreSQL control connection is `DATABASE_CONTROL_URL` on the replicated
web service, using the `postgres` user. Do not route ordinary Shopware queries
through it. The single server publishes port 5433; replicated primary and
standbys publish 5434, 5435, and 5436. MySQL publishes 3307 and 3308. These
local examples use matching user names and passwords; replace them before
using them on a shared host.

The independent test databases are `shopware_pg_test`, `shopware_mysql_test`
and the dedicated JSON Criteria database `shopware_json_test`
(setup in [JSON_READ_CONTRACT.md](JSON_READ_CONTRACT.md)). Point destructive tests there, never at an installed
shop. The PostgreSQL image is pinned to `postgres:18.6@sha256:5a5a84b19854a9ffaa54082c166ff4ec27473a361e496e5ea167f298f2da9722`;
both servers use that image. The MySQL image is 8.4.11, pinned by digest in
`compose.mysql-replicated.yaml`. The web image builds on the pinned Shopware
development image with `pdo_pgsql` and the PostgreSQL 18 client. Composer keeps
`ext-pdo_mysql` required for existing supported MySQL/MariaDB installations;
`ext-pdo_pgsql` is suggested for PostgreSQL, and this web image installs both.

The lightweight database contract suite builds only its five fixture tables;
it does not run the Shopware installer or production migrations. Each web
service exposes `DATABASE_CONTRACT_URL` for its test database. The replicated
PostgreSQL service also exposes `DATABASE_CONTRACT_REPLICA_URL` for application
reads and `DATABASE_CONTRACT_CONTROL_URL` as a separate administrative
connection to that standby. Run the shared suite and the replay test with:

```sh
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract-replication
```

The replication suite rejects a primary and replica resolving to the same
server, verifies the control connection is physically separate, and waits for
the standby replay LSN after fixture setup and teardown.

The shared input and assertion contract is exposed through
`DatabaseContractDataAccess`. Task 006 executes it with the portable fixture
gateway. The production DAL reader now uses connection-scoped quoting and UUID
aggregation, with the same fixture boundary verified against both engines.
Bound JSON/config/custom-field/price selectors, list filters, nested aggregation
and cheapest-price reads have their own cross-engine suite. See
[DAL_READ_CONTRACT.md](DAL_READ_CONTRACT.md) and
[JSON_READ_CONTRACT.md](JSON_READ_CONTRACT.md) for the supported behaviors,
commands and remaining work. Production DAL writes, batch conflicts, partial
JSON updates and transaction effects are verified by
[DAL_WRITE_CONTRACT.md](DAL_WRITE_CONTRACT.md). Criteria search and aggregation
behavior is verified by [DAL_SEARCH_CONTRACT.md](DAL_SEARCH_CONTRACT.md).
Schema generation and custom entity schema lifecycle are verified by
[DAL_SCHEMA_CONTRACT.md](DAL_SCHEMA_CONTRACT.md). Content index producers, their
Core helpers, batch removals and version isolation are verified by
[INDEXING_CONTRACT.md](INDEXING_CONTRACT.md). Content cleanup, SEO, flow, stock adapters and remaining Content SQL are verified by
[CONTENT_SQL_CONTRACT.md](CONTENT_SQL_CONTRACT.md).

## Verification

Run the relevant smoke test from the repository root. It checks server
versions, roles, test database, WAL streaming, a committed marker behind a
bounded replay LSN barrier, standby write rejection, and persistence after
restart. The second standby is checked separately. The MySQL test checks its
replication threads and marker replay.

```sh
dev-tools/database/smoke.sh pg-single
dev-tools/database/smoke.sh pg-replicated
dev-tools/database/smoke.sh pg-second
dev-tools/database/smoke.sh mysql
```

The PostgreSQL 18 image declares `PGDATA=/var/lib/postgresql/18/docker` and
`VOLUME /var/lib/postgresql`; each service mounts its named volume at the
latter path, preserving the complete PostgreSQL 18 layout. The volume names
are `shopware-pg-single_pg-single-data`,
`shopware-pg-replicated_pg-primary-data`,
`shopware-pg-replicated_pg-replica-0-data`, and
`shopware-pg-replicated_pg-replica-1-data`. MySQL uses
`shopware-mysql-replicated_mysql-primary-data` and
`shopware-mysql-replicated_mysql-replica-0-data`.

The standby bootstrap takes a physical `pg_basebackup` on an empty volume,
creates `standby.signal` with `-R`, and selects its own permanent physical
slot. Existing PostgreSQL clusters are left in place; a nonempty directory
without `PG_VERSION` fails instead of being overwritten. Both standby slots
are created on the primary's first initialization. `max_slot_wal_keep_size`
limits retained WAL to 256 MB per slot, so a standby left offline too long
may need a fresh base backup.

To rebuild **only a disposable standby** after its slot lost required WAL,
stop that standby, remove its container and named volume, drop its inactive
slot on the primary, recreate the slot, and start it again. For replica 0:

```sh
docker compose -f compose.pg-replicated.yaml stop pg-replica-0
docker compose -f compose.pg-replicated.yaml rm -f pg-replica-0
docker volume rm shopware-pg-replicated_pg-replica-0-data
docker compose -f compose.pg-replicated.yaml exec -T pg-primary psql -U postgres -d shopware_pg -c "SELECT pg_drop_replication_slot('shopware_replica_0')"
docker compose -f compose.pg-replicated.yaml exec -T pg-primary psql -U postgres -d shopware_pg -c "SELECT pg_create_physical_replication_slot('shopware_replica_0')"
docker compose -f compose.pg-replicated.yaml up -d --wait pg-replica-0
dev-tools/database/smoke.sh pg-replicated
```

For replica 1, use the two Compose files and profile, and substitute
`pg-replica-1`, `shopware_replica_1`, and its volume name. Do not remove the
primary volume: it contains the committed data and WAL source.

The topology establishes database infrastructure only. Application routing,
HTTP and Messenger lifecycle, and the long-running R01–R11 matrix belong to
later tasks. This local setup does not change a released Shopware contract, so
it needs no `RELEASE_INFO` or `UPGRADE` entry.

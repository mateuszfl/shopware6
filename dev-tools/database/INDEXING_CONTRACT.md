# Production Content indexer contract

`database-indexers` executes the Content SQL producers and their Core helpers
against PostgreSQL 18.6, MySQL 8.4.11 and MariaDB 11.8.9. It creates only its
own fixture tables; it does not install Shopware or run historical migrations.
PostgreSQL owns schema `sw_indexers_contract` in `shopware_pg_test`. MySQL and
MariaDB require the dedicated `shopware_json_test` database. Run one instance
per database at a time. Each test drops only the objects it created.

## Commands

With the disposable servers from [README.md](README.md):

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-indexers
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-indexers
```

For an independent MariaDB regression, start a disposable container without
mounting an existing shop's database volume:

```sh
docker run --rm -d --name shopware-012-mariadb-contract -p 127.0.0.1:3309:3306 -e MARIADB_ROOT_PASSWORD=contract-root -e MARIADB_DATABASE=shopware_json_test -e MARIADB_USER=shopware -e MARIADB_PASSWORD=shopware mariadb:11.8@sha256:79d59758afc91b89b120b0a8904d637f5a3b3e1c4900f29b740d6d46c72fef68
# Wait for mariadb to accept connections, then:
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3309/shopware_json_test?serverVersion=11.8.9-MariaDB&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-indexers
docker stop shopware-012-mariadb-contract
```

The combined DAL regression uses:

```sh
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json,database-dal-write,database-dal-search,database-dal-schema,database-indexers
```

Set `DATABASE_CONTRACT_URL` to the chosen PostgreSQL or MySQL fixture server.
The existing non-indexer suites retain their own supported-server guards.

## Behaviors exercised

- Real `EntityIndexerRegistry`, `IteratorFactory`, inheritance, ID mapping,
  child counts and tree updates. Full and incremental indexing produce the
  same state, including repeated messages and removal of mappings.
- Product live/draft isolation, referenced parent versions, default/rule
  prices, serialized cheapest-price bytea/blob values and structural JSONB
  accessor equality. Redelivery does not dispatch a price change when only
  JSON object member order differs.
- Deterministic variant SHA256 values and category IDs/paths. PostgreSQL uses
  typed ordered aggregates; MySQL/MariaDB retain their existing dialect.
  Aggregates preserve null/empty behavior and remove duplicate IDs.
- Category paths, breadcrumbs, parent counters and moves over 505 categories;
  503 products with 1006 category mappings; 1001 ordered distinct IDs; 503
  stream mappings; 103 keywords across replacement batches.
- Draft tree updates bind each path field independently, including custom
  ancestor attributes, reserved column names, quoted text and recursive
  children. Updates leave the live version unchanged.
- Stream compilation, deterministic ordering for equal filter positions,
  deleted filters and version-scoped mapping cleanup; newsletter aggregation,
  repeated deletion and customer email/consent changes.
- Rule payload serialization, nested conditions, stable position ties,
  rule areas and plugin reset; media thumbnails/configuration blobs and
  folder inheritance; profile/media validators and legacy product states.
- Keyword dictionary primary and secondary unique conflicts, sequential
  replacement, last-row-wins and whole-batch rollback on foreign-key failure.
  PostgreSQL rejects unaudited, partial, expression and nonunique replacement
  targets rather than silently deleting unrelated rows.
- Legacy statement-helper calls and explicit binary, null and arbitrary
  non-UTF-8 blob bindings. Physical booleans follow the checked-smallint
  contract from [DAL_SCHEMA_CONTRACT.md](DAL_SCHEMA_CONTRACT.md).

The large product test prints elapsed full/incremental time, peak PHP memory
and an `EXPLAIN` plan for the versioned product/category lookup. These local
fixture measurements verify indexed access and bounded batches; they are
not a production throughput benchmark. PostgreSQL replacement takes a table
lock to preserve delete/insert semantics with two unique keys, which can
reduce dictionary write concurrency.

## Boundaries

Repository entities and matching product IDs are controlled fixture inputs;
SQL producers, Core helpers and iterator queries run on the real database.
DAL Criteria/read/write behavior has separate contracts. Minimal definitions
include only the fields each producer needs. Product stock and downstream
SEO/cache/worker lifecycle consumers belong to their separate port tasks.
This contract does not establish PostgreSQL support for a complete Shopware
installation or historical migrations.

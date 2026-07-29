# PostgreSQL portability — RAW SQL inventory & porting plan

> Status: **Phase 1 (foundation)**. This document is the audit that backs the multi-engine effort.
> It inventories the MySQL/MariaDB-specific SQL Shopware emits and maps every construct to the
> dialect abstraction introduced in phase 1. Migrations and `schema.sql` are **explicitly excluded**
> for now (they are phase 4).

Counts below were produced with `ripgrep` over `src/`, excluding `**/Migration/**`, on the branch's
base commit. Treat them as orders of magnitude, not exact contracts — they drift with every merge.

## 1. The dialect abstraction (where the fixes go)

Phase 1 added a small, pure-string dialect layer. All porting work routes through it.

| File | Responsibility |
| --- | --- |
| `src/Core/Framework/DataAbstractionLayer/Dbal/Dialect/AbstractSqlDialect.php` | The contract: one method per portability construct |
| `.../Dbal/Dialect/MySqlDialect.php` | Identity implementation — byte-for-byte today's MySQL SQL |
| `.../Dbal/Dialect/PostgreSqlDialect.php` | PostgreSQL renderings |
| `.../Dbal/Dialect/DatabaseDialectRegistry.php` | Resolves the dialect from `Connection::getDatabasePlatform()` |
| `.../Dbal/Dialect/SqlDialectProvider.php` | Static bridge for DI-less call sites (e.g. `EntityDefinitionQueryHelper::escape()`) |
| `.../Dbal/Dialect/JsonExtractType.php` | Target-type enum for typed JSON extraction |
| `src/Core/Framework/Adapter/Database/ConnectionFactory.php` | Driver-aware connection builder (`pdo_mysql` vs `pdo_pgsql`) |

Engine detection: the source of truth is the platform DBAL negotiated for the live connection, never
the DSN scheme (the scheme is only read inside `ConnectionFactory`, where no connection exists yet).

## 2. MySQL-specific constructs and their dialect mapping

| Construct | occ / files (whole `src`) | outside DAL | Dialect method | MySQL → PostgreSQL |
| --- | --- | --- | --- | --- |
| Binary-id hex read `LOWER(HEX(..))` | 331 / 142 | 315 / 133 | `toHex()` | `LOWER(HEX(x))` → `encode(x,'hex')` |
| Binary-id hex write `UNHEX(..)` | 17 / 12 | 13 / 9 | `fromHex()` | `UNHEX(x)` → `decode(x,'hex')` |
| Backtick identifier quoting | pervasive | pervasive | `quoteIdentifier()` | `` `x` `` → `"x"` |
| Null coalesce `IFNULL(..)` | 70 / 18 | 60 / 11 | `ifNull()` | `IFNULL(a,b)` → `COALESCE(a,b)` |
| Aggregate `GROUP_CONCAT(..)` | 68 / 25 | 59 / 20 | `groupConcat()` / `jsonObjectAgg()` | `GROUP_CONCAT` → `string_agg` / `json_agg` |
| Upsert `ON DUPLICATE KEY UPDATE` | 13 / 11 | 9 / 8 | `buildUpsertSuffix()` / `insertIgnore*()` | `ON DUPLICATE KEY UPDATE` → `ON CONFLICT (..) DO UPDATE` |
| `REPLACE INTO` | 5 / 5 | 5 / 5 | `supportsReplaceInto()` | none in PG — emulate (delete+insert / upsert-all) |
| JSON access `JSON_EXTRACT`/`JSON_UNQUOTE` | 27 / 11, 18 / 7 | — | `jsonExtract()` / `jsonExtractTyped()` | `JSON_EXTRACT(..)` → `#>>` + `::type` casts |
| Null-safe equals `<=>` | see note | — | `nullSafeEquals()` | `a <=> b` → `a IS NOT DISTINCT FROM b` |
| Date histogram `DATE_FORMAT`/`QUARTER` | — | — | `dateHistogramFormat()` | `DATE_FORMAT`/`QUARTER()` → `to_char`/`EXTRACT` |
| Introspection `SHOW COLUMNS`/`SHOW TABLES` | 3 / 2, 4 / 3 | — | (phase 3) | → `information_schema` queries |
| Session vars `SET @..` | 4 / 3 | — | (phase 3) | rewrite as window funcs / CTEs |
| Row locking `FOR UPDATE [SKIP LOCKED]` | 6 / 3 | — | mostly portable | PG 9.5+ supports `FOR UPDATE SKIP LOCKED` |

Note: the `<=>` grep also matches the PHP spaceship operator, so its raw count (≈69/48) overstates
the SQL usage; the real SQL sites live in `Dbal/Search/Parser/SqlQueryParser.php`.

Good news — **not present anywhere in `src`** (verified 0 hits): `INET6_ATON`, MySQL fulltext
`MATCH … AGAINST`, `FORCE INDEX`/`USE INDEX`, `SQL_CALC_FOUND_ROWS`. Shopware's product search uses
its own keyword dictionary, not native MySQL fulltext — a significant portability win.

## 3. Concrete upsert call sites (`ON DUPLICATE KEY UPDATE`)

The central builder is `src/Core/Framework/DataAbstractionLayer/Doctrine/MultiInsertQueryQueue.php`.
Direct call sites outside it:

- `src/Core/Framework/App/DeletedApps/DeletedAppsGateway.php`
- `src/Core/Content/Category/DataAbstractionLayer/CategoryBreadcrumbUpdater.php`
- `src/Core/Framework/Increment/MySQLIncrementer.php`
- `src/Core/Framework/Webhook/Outbox/WebhookOutboxStore.php`
- `src/Core/Maintenance/System/Service/ShopConfigurator.php`
- `src/Core/System/NumberRange/ValueGenerator/Pattern/IncrementStorage/IncrementSqlStorage.php` (also uses `@nr :=` user variables)
- `src/Core/System/Consent/ConsentRepository.php`
- `src/Core/Checkout/Cart/CartPersister.php`

## 4. Raw SQL call-site footprint (outside DAL, excluding migrations)

- **≈733 exec/fetch call sites across ≈318 files** (`executeStatement`, `executeQuery`, `fetchOne`,
  `fetchAssociative`, `fetchAllAssociative`, `fetchAllKeyValue`, `fetchFirstColumn`).
- `RetryableQuery` (MySQL-deadlock-aware retry wrapper): ≈30 files.

Main clusters: Content/Product indexers (`CheapestPriceUpdater`, `ProductIndexer`, …), Content/Media
path builders, Checkout `Cart`/`Document`/`Promotion`, System `SalesChannel`/`NumberRange`/
`CustomEntity`, Framework `OAuth`/`Store`/`Increment`, all Elasticsearch admin indexers
(`*AdminSearchIndexer`, heavy `GROUP_CONCAT`), Storefront `Theme`.

## 5. Centralisation seams (best injection points)

- `Doctrine/MultiInsertQueryQueue.php` — single chokepoint for batch insert / upsert.
- `Dbal/SqlHelper.php` — `GROUP_CONCAT` + `JSON_OBJECT` aggregation helper.
- `Dbal/FieldAccessorBuilder/*` — JSON-path SQL generation (`JsonFieldAccessorBuilder` already wired
  to the dialect in phase 1 as the canary; `Price`/`Config`/`CustomFields`/`CheapestPrice` follow in
  phase 2).
- `Dbal/Search/Parser/SqlQueryParser.php` — Criteria → SQL (JSON, casts, `<=>`, LIKE escaping).
- MySQL-prefixed storages with abstract bases (clean DI seams for PG siblings):
  `Adapter/Cache/InvalidatorStorage/MySQLInvalidatorStorage.php`,
  `Adapter/Storage/MySQLKeyValueStorage.php`, `Increment/MySQLIncrementer.php`,
  `MessageQueue/Stats/MySQLStatsRepository.php`.

## 6. Porting phases

- **Phase 1 (this change):** dialect abstraction, engine detection, `ConnectionFactory`, `escape()`
  routed through the dialect, `JsonFieldAccessorBuilder` wired as canary. MySQL byte-for-byte
  unchanged; dialect unit tests added.
- **Phase 2:** remaining accessor builders; `MultiInsertQueryQueue` upsert/ignore/replace; binary-id
  `toHex`/`fromHex` in reader/searcher and the id/fk/version field serializers; `SqlQueryParser`.
- **Phase 3:** `SqlHelper`, `EntityReader` (`GROUP_CONCAT`, `SET @n`), `EntityAggregator`
  (`dateHistogramFormat`), `SHOW COLUMNS/TABLES` → `information_schema`, and the ≈733 raw SQL sites
  outside the DAL. **Also the `ONLY_FULL_GROUP_BY` problem** (see §7).
- **Phase 4 (deferred):** `SchemaBuilder`, `MigrationQueryGenerator`, `schema.sql`, all migrations,
  and the installer/maintenance DDL (`DatabaseConnectionInformation`, `DatabaseConnectionFactory`,
  `SetupDatabaseAdapter`, `ShopConfigurator`). Until this lands there is **no PostgreSQL schema to run
  against**, so phases 1–3 are validated by unit tests and by MySQL regression, not by a live PG store.

## 7. Hardest problems (honest risk list)

1. **`ONLY_FULL_GROUP_BY` dependence.** `MySQLFactory` strips this mode, so many DAL queries select
   non-aggregated, non-grouped columns — which PostgreSQL rejects outright. This needs query-shape
   changes (extend `GROUP BY`, `DISTINCT ON`, window functions), not string substitution. Largest
   single reason phase 1 is "foundation only".
2. **Binary UUID storage.** Every id is `BINARY(16)`. `toHex`/`fromHex` abstract the *expressions*,
   but the *column type* (`bytea` vs native `uuid`) and PDO binary binding differ; only exercisable
   once a PG schema exists (phase 4).
3. **JSON semantics.** MySQL `JSON_EXTRACT`/`JSON_UNQUOTE` vs PG `#>>`/`jsonb` differ in null
   handling, coercion and collation; needs per-type coverage against a real PG instance.
4. **Upsert conflict targets.** PG `ON CONFLICT` requires explicit conflict columns; `MultiInsertQueryQueue`
   has no notion of the key today. `REPLACE INTO` must be emulated.
5. **Volume.** ≈733 raw SQL sites plus `SET @var`, `SHOW …` — mechanical once the abstraction is
   stable, but a long tail across many bundles.

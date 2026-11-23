# V6_3 PostgreSQL diagnostic batch

**Diagnostic only. This is not a production-ready PostgreSQL migration port.**

The diagnostic run created registry mappings for all 335 Core V6_3 migrations. It retains 170 existing PostgreSQL variants and 9 audited same-class mappings. It generated 156 best-effort diagnostic variants. The generator flagged unhandled SQL or runtime shapes in 149 of those classes; the remaining 7 have no detected flags but are still unverified. SQLGlot was not invoked.

The first observed PostgreSQL run stopped in `Migration1574695657ProductCrossSelling` with SQLSTATE `42601` at `ENGINE=InnoDB`, after 173 migrations had completed. The refreshed diagnostic output applies CREATE TABLE rules: `BINARY(16)` columns become `BYTEA` with an `octet_length(...)=16` check; `TINYINT[(n)]` becomes `SMALLINT`; `DATETIME[(n)]` becomes `TIMESTAMP[(n)] WITHOUT TIME ZONE`; utf8mb4 column collations become `sw_type_ci`; table-level utf8mb4 defaults are copied to text columns as `COLLATE "sw_type_ci"`; and trailing MySQL engine/charset/collation options are removed. This batch copied the default collation to 31 text columns before stripping the MySQL options. It also rewrites standalone `ALTER TABLE ... DROP FOREIGN KEY <name>` statements to `DROP CONSTRAINT`, preserving identifier quoting and statement order; the latest batch matched 19 clauses in 15 classes. Multi-action ALTER statements remain flagged and unchanged. The manifest records per-class applied rule counts and totals.

This refresh addresses the observed DDL syntax blocker in the first emitted migration, but does not establish that the migration batch is runnable. ALTER statements, JSON expressions, dynamic SQL, idempotency behavior, and all other unhandled shapes remain diagnostic flags.

The generated classes and their registry mappings exist only to run one disposable PostgreSQL migration attempt and capture the first failure. The complete class-by-class mapping and unhandled-shape list is in [postgresql-v63-diagnostic-manifest.json](postgresql-v63-diagnostic-manifest.json). Each generated PHP file also carries a diagnostic-only header.

Do not use these mappings for an installed shop. The generator remains fail closed in normal `--inventory` mode; `--diagnostic-inventory` is an explicit best-effort path.

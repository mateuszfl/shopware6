---
title: Introduce SQL dialect abstraction as foundation for PostgreSQL support
author: Crehler
author_github: @crehler
---
# Core
* Added an SQL dialect abstraction under `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Dialect` (`AbstractSqlDialect`, `MySqlDialect`, `PostgreSqlDialect`, `DatabaseDialectRegistry`, `SqlDialectProvider`, `JsonExtractType`) that centralises engine-specific SQL fragments (identifier quoting, binary/UUID hex conversion, JSON extraction, `IFNULL`/`COALESCE`, `GROUP_CONCAT`/`string_agg`, upsert clauses, null-safe equals, date-histogram formatting).
* Changed `EntityDefinitionQueryHelper::escape()` and `JsonFieldAccessorBuilder` to route through the active dialect. The MySQL dialect is byte-for-byte identical to the previous hard-coded SQL, so existing behaviour is unchanged.
* Added `Shopware\Core\Framework\Adapter\Database\ConnectionFactory` — a driver-aware connection builder that selects `pdo_mysql` or `pdo_pgsql` from the `DATABASE_URL` scheme. `MySQLFactory` remains the MySQL/MariaDB bootstrap and is delegated to unchanged. `Kernel`, `KernelFactory` and the test `KernelLifecycleManager` now build the connection through `ConnectionFactory`.
* Added `docs/database/postgresql-raw-sql-inventory.md` documenting the remaining engine-specific SQL and the phased porting plan. Migrations and `schema.sql` are intentionally out of scope for this phase.
# Upgrade Information
## SQL dialect abstraction (foundation for PostgreSQL)
This release introduces the internal scaffolding for supporting database engines other than MySQL/MariaDB. There is no functional change for MySQL/MariaDB installations: the MySQL dialect reproduces the exact SQL emitted before, and MySQL/MariaDB remain the only fully supported engines. PostgreSQL is not yet runnable end-to-end (schema and migrations are still MySQL-only). Third-party code that assembled SQL by hand should, going forward, obtain the active dialect via the injectable `AbstractSqlDialect` service (or `SqlDialectProvider::get()` in static contexts) instead of hard-coding MySQL syntax.

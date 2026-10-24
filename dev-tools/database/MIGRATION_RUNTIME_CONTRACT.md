# Migration runtime contract

The runtime routes PostgreSQL migrations through `MigrationVariantRegistry`.
Each entry binds the original FQCN and its creation timestamp to one implementation.
MySQL/MariaDB execute the original class. The registry validates timestamps and
rejects duplicate or ambiguous identities. A portable step must explicitly map to
itself after an audit; there is no implicit PostgreSQL fallback.

Core mappings belong in `MigrationVariantRegistry::CORE_VARIANTS`, available to
both the container service and the pre-kernel installer. That manifest is empty
until historical migrations are ported. This runtime change does not make a full
PostgreSQL installation available. The base installer schema is separate work.
External plugins without explicit variants are unsupported on PostgreSQL and fail
with the logical FQCN and timestamp. The registry is an internal porting facility,
not a supported third-party registration API.

Keep counterparts outside scanned source directories. Discovery also filters
registered counterpart classes when they share a directory with originals.
Sync stores only the original class and timestamp and preserves existing history.
Success, errors and progress use that logical identity; error logs include the
actual implementation and platform. Missing variants and history timestamp
mismatches reject the entire selected collection before its first execution.
Preflight observes `until` and `limit`, including for destructive steps.

Nested sources retain their order: versioned sources precede the legacy source.
Directories within one source share timestamp ordering. `until` filters timestamps
before source ordering and `limit` applies to the resulting ordered selection.
Destructive source selection still uses the existing `safe`, `blue-green` and
`all` rules. Namespace matching uses escaped SQL `LIKE` and rejects nested and
prefix-collision namespaces, including names containing Unicode or wildcards.

PostgreSQL owns one transaction per step, including its success marker. On any
`Throwable`, it rolls back before recording the error outside that transaction;
previously completed steps remain completed. A retry executes only pending steps.
The runtime rejects an existing caller transaction without rolling it back.
Variants must not commit or roll back the runtime transaction or leave nested
transactions open. An observed boundary change fails the step without a success
marker, but an explicit commit performed by a variant cannot be undone. Such
variants are unsupported and must be fixed before retry. External effects and
manual transaction boundary changes cannot be made atomic by this runtime.
MySQL retains its existing autocommit/DDL behavior and requires idempotent retries
after partial writes. Neither engine wraps the entire historical migration set in
one transaction. PostgreSQL history timestamps use UTC with microsecond precision.

`PluginLifecycleService` and the installer factory both construct the same runtime
path; plugin sync, activation and reinstall use original migration identities.
The pre-kernel factory includes V6_8 alongside the earlier versioned sources.

## Validation boundary

`MigrationPlatformContractTest` uses synthetic committed fixtures and an empty,
disposable `*_test` database through `ContractConnectionFactory`. It does not boot
the application kernel, import a complete schema or execute released migrations.
Run separately on PostgreSQL 18.6 and MySQL 8.4:

```sh
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-migrations
```

Set `DATABASE_CONTRACT_URL` to the appropriate isolated database. Optional
`DATABASE_CONTRACT_QUERY_LOG` captures the executed SQL. The tests create and drop
only their own `migration` and `migration_contract_event` tables and reject a
pre-existing table before mutation. PostgreSQL-only routing/transaction tests are
explicitly skipped on MySQL. The installer now selects its plugin/migration bootstrap schema by platform; see
[installer validation](INSTALLER_CONTRACT.md). Real plugin installation hooks,
historical ports, seed parity and full update/install matrices remain separate
acceptance work; the plugin contract covers the migration collection lifecycle
shared by those hooks.

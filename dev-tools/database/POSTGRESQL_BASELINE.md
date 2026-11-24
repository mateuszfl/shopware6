# PostgreSQL current-trunk baseline

The PostgreSQL installer uses a versioned snapshot of the current trunk schema. The source is the clean MySQL migration result at the commit and migration timestamp recorded in `src/Core/schema.postgresql.version`.

The snapshot is split into ordered installation sections:

1. `schema.postgresql.sql` creates the tables and helper function.
2. `schema.postgresql.collations.sql` applies `sw_type_ci` to columns whose MySQL source used `utf8mb4_unicode_ci`.
3. `schema.postgresql.data.sql` inserts the current-trunk seed data and migration history.
4. `schema.postgresql.checks.sql` enforces 16-byte storage for the source `BINARY(16)` UUID columns.
5. `schema.postgresql.post.sql` creates indexes, keys, defaults, and triggers after the seed rows exist.

To refresh the snapshot, run the official non-destructive and destructive migration commands against an isolated, empty MySQL database for the target trunk commit. Convert that final state to PostgreSQL with the pinned pgloader image used in the baseline-generation record, then export pre-data, data, and post-data sections. Preserve the source types, apply the source collations and UUID checks, and update the version metadata and SHA-256 values. PostgreSQL `JSONB` validates JSON values, so MySQL `JSON_VALID` checks do not need a parallel function.

The baseline supports clean PostgreSQL installs for its recorded trunk snapshot. It does not provide MySQL-to-PostgreSQL data migration or upgrades from an earlier PostgreSQL baseline. New trunk migrations after the recorded timestamp still use the regular PostgreSQL migration runtime and need PostgreSQL variants.

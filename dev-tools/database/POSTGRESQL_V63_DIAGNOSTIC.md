# V6_3 PostgreSQL diagnostic batch

**Diagnostic only. This is not a production-ready PostgreSQL migration port.**

The diagnostic run created registry mappings for all 335 Core V6_3 migrations. It retains 170 existing PostgreSQL variants and 9 audited same-class mappings. It generated 156 best-effort diagnostic variants. The generator flagged unhandled SQL or runtime shapes in 149 of those classes; the remaining 7 have no detected flags but are still unverified. SQLGlot was not invoked.

The generated classes and their registry mappings exist only to run one disposable PostgreSQL migration attempt and capture the first failure. The complete class-by-class mapping and unhandled-shape list is in [postgresql-v63-diagnostic-manifest.json](postgresql-v63-diagnostic-manifest.json). Each generated PHP file also carries a diagnostic-only header.

Do not use these mappings for an installed shop. The generator remains fail closed in normal `--inventory` mode; `--diagnostic-inventory` is an explicit best-effort path.

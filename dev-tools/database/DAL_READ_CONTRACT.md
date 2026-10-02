# DAL entity read contract

The `database-dal-reader` PHPUnit suite uses the production `EntityReader`, `EntityHydrator`, field resolvers, Criteria query builder, serializers, foreign key resolver and iterators against isolated tables. It shares deterministic input data with `DatabaseContractScenario`; it does not boot a kernel or require a Shopware installation. Association serializer stubs cover write-only services which the reader resolves but never calls. Database connections and read operations are real.

## Run

Provide a PostgreSQL 18.6 or reference MySQL 8.4 DSN using `DATABASE_CONTRACT_URL` and run:

```sh
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json
```

The reader suite creates and drops only its `sw_dal_*` tables and an owned PostgreSQL collation schema; the existing database contract uses its own fixture tables. The JSON suite requires the dedicated MySQL `shopware_json_test` database: see [JSON_READ_CONTRACT.md](JSON_READ_CONTRACT.md) for setup and DSNs. Run one suite process per database at a time. PostgreSQL and MySQL can run independently. Tests check the actual server platform/version and reject a standby as the fixture endpoint.

## Supported read behavior

- UUIDs remain lowercase 32-character hexadecimal identifiers. Bytea streams are read once before field decoding. Boolean, date, JSON and typed price values retain their PHP types.
- ID input order, translations with raw/resolved values, language fallback, parent inheritance, draft overrides and missing-draft live fallback are preserved.
- One-to-one and nested to-one joins use version-aware keys and quoted aliases containing dots.
- One-to-many and many-to-many support limit/offset per parent, including offset without a limit. ID is the deterministic final tie breaker.
- Unrestricted many-to-many reads use an ordered hex aggregate from the connection's dialect. Restricted reads bind offset/end and use window functions. A separate window selects the first joined row according to the complete sorting tuple for each `(local_id, reference_id)` before per-parent numbering; a nested to-many join cannot consume multiple slots for one relation.
- Elementary explicit `ScoreQuery` sorting preserves the aggregate expression, uses portable `CASE` and `HAVING` expressions, and keeps grouping when limit is one.
- A version-unfiltered offset iterator enumerates physical versions. Its mapping keys include ID and version so live/draft rows do not overwrite each other; values remain UUIDs. Version-filtered iteration keeps the existing ID keys. Both offset and auto-increment cursors retain count/resume/exhaustion behavior.
- Deprecated metadata helpers use literal table/column names through DBAL SchemaManager.

## Connection scope and compatibility

Services receive their own DBAL `Connection` through DI. `EntityDefinitionQueryHelper::escape($identifier)` keeps its MySQL quoting and validation contract; the optional connection selects that connection's platform. Instance callers in the production read path pass the connection. No global current-platform state is introduced. Changing remaining static callers must happen in their owning gateway/task.

`hexAggregate()` returns a `SqlFragment`: its separator is bound and its UUID ordering is explicit. The reader gives each owned fragment a unique named parameter and transfers parameter types into subqueries. JSON SQL extraction is not part of this hex aggregation operation.

Task 008 adds bound nested JSON/config/custom-field/price accessors, typed scalar extraction, list membership, null-safe filters, scalar null ordering and JSON/price aggregation. The reader contract now also exercises inherited and translated custom-field selectors. Their value, collation, pricing and query-owner contracts are defined in [JSON_READ_CONTRACT.md](JSON_READ_CONTRACT.md).

## Remaining work

| Owner | Remaining behavior | Why outside this read contract |
| --- | --- | --- |
| Write task 009 | Entity write gateways, serializer encode paths, `StatementHelper` parameter types, version cleanup deletes | The fixture seeds rows through DBAL; it does not claim production DAL writes. |
| Search/aggregation task 010 | Full repository search, `JoinGroupBuilder`, search terms/scoring on associated fields, arbitrary grouped filters and remaining non-JSON aggregation SQL | Elementary filters and explicit scalar scoring needed for relation pagination, plus task 008's JSON filtering/sorting/nested aggregation, are covered. The existing inverse N:M relation without `CascadeDelete` collects logical IDs across mapping versions, then target hydration selects the context version; task 010 must evaluate this contract consistently across unrestricted and restricted query shapes. |
| Schema task 011 | SchemaBuilder, definition validation against a complete schema, migration query generation and `sw_type_ci` collation setup | Only deprecated helper metadata lookups and isolated fixture schema are covered. JSON scalar text selectors require the documented collation in the application search path. |
| Indexing task 012 | `ChildCountUpdater`, `InheritanceUpdater`, `ManyToManyIdFieldUpdater`, `TreeUpdater`, cheapest-price producers and consumers' full/incremental index results | These helpers execute denormalizing writes, UPDATE joins and JSON aggregation; iterator and cheapest-price read correctness alone do not prove index correctness. Preserve task 008's cheapest-price shape and rule/currency priority contract. |
| Framework task 016 | Scheduled version cleanup and runtime framework consumers | Lifecycle/queue execution is not part of entity hydration. |
| Elasticsearch/admin indexing task 017 | Search config loading, Elasticsearch indexing and DSL integration | Tokenizers/DSL builders do not execute DAL read SQL; their consuming runtime needs separate validation. |
| Storefront task 018 | Theme indexer `INSERT IGNORE` | It is a Storefront index write. |

The implementation supports the basic read contract, not a full PostgreSQL Shopware installation. Later tasks must finish these paths before runtime completion can be claimed.

## Release documentation decision

This is an internal part of the PostgreSQL port. Released API signatures remain compatible and no extension configuration or upgrade action is required. No `RELEASE_INFO` or `UPGRADE` entry is added; the complete port will document operator setup when it is available.

# DAL Criteria search and aggregation contract

The `database-dal-search` suite executes production `EntitySearcher`,
`EntityAggregator`, `CriteriaQueryBuilder`, `JoinGroupBuilder` and filter parsing
on PostgreSQL 18.6 and MySQL 8.4.11. It creates only its `sw_search_*` tables and
an owned PostgreSQL collation schema. The reader and JSON suites additionally
verify versioned and composite keys, bound JSON grouping and association limits.
These fixtures do not require an installed shop or run production migrations.

## Run

Use disposable databases from [README.md](README.md). Run one process per
database at a time; PostgreSQL and MySQL can run independently. MySQL Criteria
tests explicitly enable `ONLY_FULL_GROUP_BY` and reject a missing mode.

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json,database-dal-write,database-dal-search
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json,database-dal-write,database-dal-search
```

## Verified behavior

- Nested AND/OR, independent association predicates, negations, double negation,
  empty associations, range, contains and typed list filters preserve their
  logic. Aggregation fields at every nested level retain their own association
  joins when a Criteria filter selects parents by that association.
- Searches end sorting with the complete physical primary key. Ascending field
  sorting places nulls first, descending sorting places them last. ID Criteria
  preserve input order when no explicit sorting overrides it. Natural sorting
  measures characters with `CHAR_LENGTH`, including multibyte Unicode.
- Joins and count/scoring queries group by the complete physical primary key.
  Grouped searches rank per entity before choosing a representative: highest
  score, requested sorting tuple, then primary key. JSON grouping aliases retain
  their bound path parameters. Exact totals count the same ranked result; none
  and next-pages modes keep their existing pagination contracts.
- All score queries, including multiple associated score fields, contribute to
  the result. Scores and range bounds are bound values. Fractional scores survive
  aggregation and division. Aggregations honor the same matching entity set.
- Terms preserve native scalar types and column collations when grouping and
  sorting. Native database ranks retain those comparisons through nested bucket
  hydration. Equivalent case/accent spellings use the smallest UTF-8 byte
  spelling across the complete parent bucket. Null and empty-string keys remain
  separate. Bucket-key order resolves equal counts or sort values.
- Limits apply to complete terms buckets at each level, after their full counts
  and children are reconstructed. A leaf terms query retains its SQL limit;
  nested queries fetch all grouped child rows. Their memory and transfer cost
  therefore grows with grouped cardinality. Production cardinality needs separate
  workload measurement; a small fixture does not establish an operating limit.
- Stats and range retain their numeric contracts. Histogram keys represent
  calendar bucket starts, preserve null, and use ISO week years. All seven
  intervals and named-zone spring DST transitions are tested. MySQL named zones
  still require populated time-zone tables, as for existing `CONVERT_TZ` support.
- One-to-many and many-to-many deduplicate joined rows before assigning per-parent
  limit/offset slots. Count sorting and scoring retain complete target primary
  keys. An inverse N:M relation without `CascadeDelete` collects logical target
  IDs across mapping versions; target hydration then chooses the Context version
  or missing-draft live fallback. Unrestricted, sorted, limited, offset, filtered
  and scored inverse shapes share this contract.

## Boundaries

Case/accent comparison uses [TYPE_CONTRACT.md](TYPE_CONTRACT.md): production
schema setup must provide the documented collation. Complete installation,
domain SQL, migrations, index producers and replica lifecycle remain with their
own tasks. No runtime SQL rewriting or global current-platform state is added.

The ordering and bucket fixes are documented in `RELEASE_INFO-6.7.md`. They
require no extension configuration or migration step, so there is no new
`UPGRADE` entry. This partial adapter port does not announce a complete
PostgreSQL Shopware installation.

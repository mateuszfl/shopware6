# DAL JSON and price read contract

The `database-dal-json` suite executes the production JSON, config, custom-field,
price and cheapest-price accessors, filter parser, Criteria query builder and
entity aggregator on real PostgreSQL 18.6 and reference MySQL 8.4.11 connections.
The reader suite additionally checks inherited and translated custom-field
selectors. No kernel or installed shop is required; these tests do not claim
production DAL writes or indexing.

## Run

PostgreSQL uses the disposable `shopware_pg_test` database. The JSON Criteria
fixture needs tables named `currency` and `custom_field`, so MySQL must use the
dedicated empty `shopware_json_test` database rather than a Shopware installation
or the physical-type fixture database. Create it once in the local topology:

```sh
docker compose -f compose.mysql-replicated.yaml exec -T mysql-primary sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS shopware_json_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON shopware_json_test.* TO \"shopware\"@\"%\";"'
```

Run one suite process per database at a time. The two engines may run in
parallel. Fixtures reject a standby and check the actual platform and version.
They drop their own tables and PostgreSQL schemas after execution.

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-contract,database-dal-reader,database-dal-json
```

The task 008 acceptance run has 80 tests and 650 assertions per engine, without
skips or warnings. The focused JSON suite contains extraction and typed scalar
matrices plus real Criteria, sorting, nested aggregation and pricing scenarios.
The original reader hydration contract remains intact.

## Paths and query ownership

`JsonPath` parses dotted members, JSON double-quoted literal members, nonnegative
array indexes, array wildcards and object wildcards. A literal key containing a
dot, quote, backslash, apostrophe or `extensions.` must be quoted as a JSON
string, for example `payload."key.with.dot"`. A quoted `"*"` is a member name;
an unquoted `.*` is a wildcard. Invalid path syntax is rejected.

The connection-scoped dialect returns a `SqlFragment` carrying SQL, values and
parameter types. `EntityDefinitionQueryHelper` transfers those values into the
owning `QueryBuilder` or parser result. Production Criteria filters, sortings,
score accessors and aggregation selectors use this path: user member names
never become SQL text. Deterministic parameter names allow identical expressions
to be reused within a query. `SqlFragment::named()` accepts adapter-owned
templates and uses DBAL's SQL parser; literal question marks do not become bind
slots. Templates containing PostgreSQL's `?` operator must not be passed as
positional fragments.

Existing string-only accessor signatures remain available. Their compatibility
wrapper inlines UTF-8 strings as platform-specific hexadecimal SQL literals;
it does not interpolate raw member text or require opening a database
connection. New query generation must retain bound fragments.
`SqlHelper::object()` and `objectArray()` keep their legacy signatures and accept
an optional connection and query owner for bound JSON member names and
platform-specific aggregation.

## Value semantics

| Operation | Contract |
| --- | --- |
| Raw extraction | SQL `NULL` and a missing path return SQL `NULL`; explicit JSON null remains the JSON value `null`. Boolean, number, string, object and array shapes remain JSON values. |
| Scalar extraction | Missing paths, SQL `NULL` and JSON null become SQL `NULL`. The string `"null"` remains text. Empty objects and arrays remain their JSON text for an unmapped selector. |
| Typed number and boolean | Existing MySQL conversion is retained, including numeric prefixes in strings, non-numeric values becoming numeric zero, and the existing true/numeric boolean rule. List membership candidates retain their scalar types. |
| Typed date and datetime | Serialized calendar dates and timestamps are cast to date or millisecond timestamp. Missing/null values and invalid text produce SQL `NULL`, including boolean, zero, `"0"`, `"infinity"` and `"now"` inputs. |
| Array index | Index zero permits MySQL's scalar/object autowrapping. Out-of-range indexes return SQL `NULL`. A member step does not implicitly traverse an array. |
| Wildcard | Matching values form an array in traversal order; missing members are skipped without suppressing later matches. No matches return SQL `NULL`. Scalars do not match an array wildcard. Object traversal follows stored canonical member order; object key order itself is not a public contract. |
| List membership | `EqualsFilter` and `EqualsAnyFilter` bind a JSON singleton candidate with its original scalar type. Membership descends through nested arrays, ignores values inside objects and distinguishes number, string, boolean and JSON null. Empty `EqualsAnyFilter` is false. `EqualsFilter(field, null)` checks SQL `NULL`; an `EqualsAnyFilter` null candidate checks a JSON-null list member. |
| Objects and object arrays | Member names are bound in the connection-aware API. SQL-null members become JSON null; nested JSON values retain their shapes. Object-array aggregation removes duplicate objects. Aggregate element order is unspecified, as in the existing MySQL operation. |

Mapped `ConfigJsonField` selectors include the `_value` storage prefix.
`CustomFields` selectors obtain scalar types from the actual metadata service,
including literal member names containing dots, quotes or backslashes. Reader
tests cover parent inheritance, explicit child JSON null, translated nested
custom fields and language fallback with the production custom-field serializer.

PostgreSQL scalar text expressions require the existing physical-type collation
`sw_type_ci` from [TYPE_CONTRACT.md](TYPE_CONTRACT.md), with ICU locale
`und-u-ks-level1` and `deterministic = false`. Fixtures create it inside their
owned schemas. Schema/migration task 011 must create it in the application's
search path before these selectors run; no separate JSON collation is required.
The tested case/accent-insensitive subset is preserved for equality and `LIKE`;
this is not a claim of complete Unicode equivalence.

JSON scalar sorting explicitly preserves MySQL's null placement in both
directions. Terms aggregation groups by its selected alias so native prepared
parameters do not turn equivalent selectors into different PostgreSQL grouping
expressions. Nested terms/filter/average aggregation, price statistics and
price range counts execute on both engines. Range counts use portable `CASE`
expressions. These are completed JSON query behaviors, independent of the
broader search task.

## Prices and cheapest-price producer contract

`PriceField` chooses `net` or `gross` from the context, with an explicit selector
overriding the chosen property. `listPrice` and `percentage` address nested
stored values. Context currency reads `c<currency-id>`; an absent or JSON-null
value falls back to `c<default-currency-id>` times the context factor. Zero is
present and never triggers fallback. An explicit currency UUID uses that
currency's binary ID to obtain its factor. If no selected or fallback scalar
exists, the result remains SQL `NULL`.

Values use decimal rounding to the context precision. Cash rounding applies
when precision is two and the context is gross or `roundForNet` is enabled;
intervals 0.01, 0.05 and 0.25 are exercised, as are precision zero and three.
An explicit tax selector changes the selected property; the cash rounding
decision continues to use the context tax state. All stored selectors,
including discount percentage, retain the existing factor and rounding
behavior; a percentage selector exposes `100 - rounded stored discount`.

The cheapest-price read side expects the producer's existing shape:

```json
{
  "ruledefault": {
    "currency<32-character-currency-id>": {
      "gross": 10.025,
      "net": 8.015,
      "listPrice": {"gross": 20, "net": 16},
      "percentage": {"gross": 25, "net": 30}
    }
  }
}
```

Other rules use `rule<32-character-rule-id>`. Rules are examined in context
priority order, capped by `dal.max_rule_prices`, followed by `ruledefault`.
Each rule considers context currency before default-currency fallback;
missing/JSON-null values move to the next candidate while zero remains selected.
The read side applies shared price rounding and percentage behavior before
filtering, sorting or aggregation. It uses context currency; explicit currency
selectors remain a `PriceField` operation. Indexing task 012 must preserve this
shape and priority semantics for parent products and prove full, incremental
and replayed index results. This task does not generate index data.

## Handoffs and limits

| Owner | Remaining work |
| --- | --- |
| Write task 009 | Production serializer encode paths, JSON set/merge/remove operations, missing-parent and shape replacement behavior, concurrent updates, validation of non-finite or out-of-range numbers and write binding. Fixtures seed JSON through DBAL. |
| Search task 010 | Complete repository search, associated scoring, arbitrary grouped filters, remaining non-JSON aggregation SQL such as date histograms, and query-shape performance. The accepted JSON filtering/sorting/nested aggregation behavior above is already implemented and tested. |
| Schema task 011 | Production JSONB columns, required `sw_type_ci` collation in the application search path, migration translation and definition validation. |
| Indexing task 012 | Cheapest-price and other JSON producers, denormalizing writes, full/incremental/replayed indexing and expression/GIN indexes chosen from measured query plans. Nested list membership needs plan-specific review; native JSONB containment alone has different semantics. |
| Other domain tasks | Overlaps, arbitrary document containment, search/remove operations and domain-specific wildcard write consumers remain with their inventory tasks. `jsonListContains` is deliberately a scalar-list operation, not a generic JSON containment API. |

The accessor APIs remain compatible. Release documentation covers externally
visible list type, literal-member and currency lookup fixes. No upgrade action
or released PostgreSQL installation setup is introduced here.

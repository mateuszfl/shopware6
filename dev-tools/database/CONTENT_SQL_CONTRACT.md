# Content SQL contract

`database-content` exercises production Content services against PostgreSQL 18.6 and MySQL 8.4.11 using isolated tables. PostgreSQL uses the `sw_content_contract` schema; MySQL uses the dedicated `shopware_json_test` database. No Shopware installation or migration replay is required.

Run suites sequentially per database. Schema introspection also observes other schemas, so a schema suite must not run concurrently with Content fixtures on the same database.

```sh
DATABASE_CONTRACT_URL='postgresql://shopware:shopware@127.0.0.1:5433/shopware_pg_test?serverVersion=18.6&charset=utf8' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-content,database-dal-search
DATABASE_CONTRACT_URL='mysql://shopware:shopware@127.0.0.1:3307/shopware_json_test?serverVersion=8.4.11&charset=utf8mb4' vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-content,database-dal-search
```

## Verified behavior

| Area | Production entry point and observable contract |
| --- | --- |
| JSON overlap | Dialect `jsonOverlaps`: immediate array values, scalar/array comparisons, shared object pairs, complete object equality inside an array, numeric equivalence, strict string/boolean types, nested arrays, JSON null and SQL NULL. Both engines execute the same 20 cases. |
| CMS media | `UnusedMediaSubscriber::removeUsedMedia`: versioned slot translations, galleries and images, category/product/landing page wildcard overrides, missing/null configurations, multiple candidate IDs and empty events. |
| Custom field media | `CustomFieldsUnusedMediaSubscriber::removeUsedMedia`: literal member names with dots and quotes, translated entities, media fields and single/multiple select fields; scalar/null/array values retain their types. |
| Media purging | `UnusedMediaPurger::getNotUsedMedia` and `deleteNotUsedMedia` use the real DAL reader, searcher and writer. Association usage and CMS usage protect media; folder trees, upload dates and keyset batches retain used media and remove only the three unused fixtures. A repeated purge deletes nothing. |
| UUID ranges | The DAL search suite executes `gt`, `gte`, `lt`, `lte` on IdField, FkField, VersionField, ReferenceVersionField and inherited FK accessors with both hexadecimal and legacy binary UUID bounds. |
| Flow | Payload indexing and loading round-trip serialized binary values. Equal sequence positions use sequence ID; equal priorities use flow ID. State actions select the highest numeric shipping cost, then newest creation date and lowest delivery ID. NULL costs and dates sort last. |
| SEO | Persistence protects manually modified paths, permits explicit overwrite, resolves canonical redirects and deletion, recovers earlier canonical paths after takeover, and handles multiple binary result rows with global and channel paths. Hreflang and placeholders preserve language/channel IDs and base paths. |
| Stock | `StockStorage::alter` preserves draft rows, inherited closeout/minimum purchase settings and availability. `OrderStockSubscriber` reads binary line-item IDs through cancellation/reopening and write success callbacks. `available_stock` is calculated from the updated stock value on both engines. |
| Import/export | Variant cleanup deletes only mappings in the importing version. Number range configuration chooses channel overrides and global fallback. Export scheduling skips missing domains and future work while resetting stale runs. |
| Newsletter/CMS | Default salutation preserves an existing value and tolerates a missing default. CMS validation accepts an existing live page, and slot inheritance merges parent language fields with child overrides. |
| Media paths/thumbnails | SQL location/path adapters preserve missing relations and update binary IDs. The update-path command supplies hexadecimal IDs only for missing paths. Remote thumbnail dimensions work with native integer results and missing folder configurations. |
| Product detail | `ProductDetailRoute::load` preserves a requested child with a binary parent ID and live version through the public route; PostgreSQL bytea results are exposed as hexadecimal IDs before variant selection. |
| Product combinations | Combination loaders preserve active/closeout inheritance, version isolation, inherited visibility from a live parent referenced by a draft child, and product-number FK resolution. |
| Sitemap/keywords | Landing-page sitemap ignores mappings from other versions and uses SEO/fallback paths. Keyword cleanup executes multiple bounded delete batches without deleting referenced terms; keyword scoring uses numeric expressions on both engines. |

The source test is [ContentSqlContractTest](../../tests/integration/Core/Framework/DataAbstractionLayer/Contract/ContentSqlContractTest.php). Related DAL range cases are in [CriteriaSearchAggregationContractTest](../../tests/integration/Core/Framework/DataAbstractionLayer/Contract/CriteriaSearchAggregationContractTest.php). Existing focused unit tests cover the remaining service decisions and collaborator effects; these do not replace the database scenarios above.

## Boundary

This fixture validates Content SQL and selected DAL effects, without a complete application schema, HTTP/Messenger lifecycle or production migration replay. Concurrent order reservations, overselling protection and transaction-wide stock consistency need the dedicated checkout concurrency work. Full application lifecycle and database topology acceptance remain separate from this contract.

MySQL retains its native JSON overlap implementation; PostgreSQL evaluates the same immediate-value semantics using JSONB. MariaDB retains the MySQL dialect path. Database overlap comparisons do not recursively search arbitrary nested JSON objects or arrays.

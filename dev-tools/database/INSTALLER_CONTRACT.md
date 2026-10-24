# Installer database bootstrap

The web installer and maintenance commands accept `pdo_mysql` and `pdo_pgsql`.
PostgreSQL requires version 18.6 or later and `pdo_pgsql`; the development image
includes both PDO extensions. Existing MySQL/MariaDB defaults remain unchanged.
Historical PostgreSQL migration variants are incomplete. Bootstrap creates only
`plugin` and `migration`; a missing migration variant stops installation before
any selected migration executes or receives a success timestamp. An initialized
bootstrap is not a complete shop installation.

## Connections and permissions

`DATABASE_URL` selects the application database. Creation uses the same credentials
on a separate connection to PostgreSQL's `postgres` maintenance database, then
opens a new connection to the target database. The account needs `CONNECT` on the
maintenance database, `CREATEDB` when creating a database, and permission to create
the application tables, ICU collation and shared UUID function. MySQL/MariaDB use
an administrative connection without a selected database. Table inspection opens
and closes a separate target connection and leaves the administrative connection
unchanged. Inaccessible sibling databases are excluded from the installer's list.

PostgreSQL bootstrap installs the audited `sw_type_ci` collation before creating
tables, retaining case and accent insensitive plugin uniqueness and migration
identity comparisons. The base SQL file is selected inside Core; MySQL still uses
its original `schema.sql`.

## DSN and TLS

Use a PostgreSQL URL with a database name, for example
`postgresql://user:password@localhost:5433/shop?sslmode=verify-full&sslrootcert=%2Fetc%2Fdatabase%2Fca.pem`.
Credentials and database names are URL encoded, and IPv6 hosts use square brackets.
The `postgres`, `postgresql`, `pgsql` and `pdo-pgsql` URL schemes select the
`pdo_pgsql` driver and default to port 5432. The web form selects the database engine;
changing it resets the port to its default. Interactive CLI setup selects it with:

```sh
bin/console system:setup --database-driver=pdo_pgsql
```

Non-interactive setup accepts the complete URL through `--database-url`.
Supported DSN options retained through installer configuration are `sslmode`,
`sslrootcert`, `sslcert`, `sslkey`, `sslcrl`, `application_name`, `gssencmode` and
`serverVersion`. Server compatibility is checked against the actual server version.
The existing `DATABASE_SSL_CA`, `DATABASE_SSL_CERT` and `DATABASE_SSL_KEY` variables
override matching DSN certificate options. A CA path selects `verify-full`;
`DATABASE_SSL_DONT_VERIFY_SERVER_CERT=1` explicitly selects `require`. Values `0`
and `false` preserve certificate verification. MySQL TLS options remain PDO driver
options. A deployment's actual certificate chain and host verification must be
validated against its TLS server; the contract suite checks option mapping and
persistence, and connects to the local database fixtures without TLS.

## Standalone validation

```sh
vendor/bin/phpunit -c phpunit.database-contract.xml.dist --testsuite database-installer
```

Set `DATABASE_URL` to a maintenance-capable account on PostgreSQL 18.6 or the MySQL
reference server. This suite does not boot the Shopware kernel or run its test
bootstrap. Each test creates a randomly named `sw_022_*` database and drops only
that database. PostgreSQL cleanup can force-close its own fixture sessions retained
by PHPUnit exception traces. The initial database named in the URL is untouched.

The suite covers database create/drop and identifier quoting, introspection,
repeat bootstrap, binary UUIDs, JSON, timestamp precision, collated uniqueness,
web database creation, form DSN persistence, trigger probing and the explicit
missing-variant failure. Configuration SQL runs on a minimal synthetic schema
with cascading foreign keys: basic configuration upsert, existing/new currencies,
language and locale swaps, country-state translations, storefront and headless
configuration, admin provisioning and rollback after a failed configuration.
This validates the SQL adapters independently of historical schema and seed parity.
A complete install/update matrix, plugin hooks and browser onboarding require the
historical migration ports and their own acceptance tests.

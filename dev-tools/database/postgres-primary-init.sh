#!/bin/sh
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
CREATE ROLE shopware LOGIN PASSWORD 'shopware';
CREATE ROLE replicator LOGIN REPLICATION PASSWORD 'replicator';
ALTER DATABASE shopware_pg OWNER TO shopware;
CREATE DATABASE shopware_pg_test OWNER shopware;
SQL

if [ "${CREATE_REPLICATION_SLOTS:-0}" = 1 ]; then
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
SELECT pg_create_physical_replication_slot('shopware_replica_0');
SELECT pg_create_physical_replication_slot('shopware_replica_1');
SQL
    printf '%s\n' 'host replication replicator 0.0.0.0/0 scram-sha-256' >> "$PGDATA/pg_hba.conf"
fi

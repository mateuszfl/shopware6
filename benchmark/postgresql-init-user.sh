#!/bin/sh
set -eu

psql \
    --set=ON_ERROR_STOP=1 \
    --set=app_user="$BENCHMARK_DB_USER" \
    --set=app_password="$BENCHMARK_DB_PASSWORD" \
    --set=app_database="$POSTGRES_DB" \
    --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" <<'SQL'
CREATE ROLE :"app_user" LOGIN PASSWORD :'app_password';
ALTER DATABASE :"app_database" OWNER TO :"app_user";
SQL

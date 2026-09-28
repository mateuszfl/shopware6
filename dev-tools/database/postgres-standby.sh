#!/bin/bash
set -euo pipefail

: "${PGDATA:?}"
: "${REPLICATION_SLOT:?}"

if [[ ! -f "$PGDATA/PG_VERSION" ]]; then
    mkdir -p "$PGDATA"
    if [[ -n "$(ls -A "$PGDATA")" ]]; then
        echo "Refusing to overwrite nonempty standby data directory: $PGDATA" >&2
        exit 1
    fi
    chown -R postgres:postgres "$PGDATA"
    for attempt in {1..60}; do
        if gosu postgres pg_isready -h pg-primary -U postgres -q; then
            break
        fi
        if (( attempt == 60 )); then
            echo 'Primary did not become ready within 120 seconds' >&2
            exit 1
        fi
        sleep 2
    done
    gosu postgres env PGPASSWORD=replicator pg_basebackup \
        -h pg-primary -U replicator -D "$PGDATA" -R -X stream -S "$REPLICATION_SLOT" \
        --checkpoint=fast --no-password
    echo "primary_conninfo = 'host=pg-primary port=5432 user=replicator password=replicator application_name=$REPLICATION_SLOT'" >> "$PGDATA/postgresql.auto.conf"
fi

exec docker-entrypoint.sh postgres

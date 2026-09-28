#!/usr/bin/env bash
set -euo pipefail

mode=${1:?Usage: smoke.sh pg-single|pg-replicated|pg-second|mysql}
case "$mode" in
    pg-single) files=(-f compose.pg-single.yaml); services=(pg-single) ;;
    pg-replicated) files=(-f compose.pg-replicated.yaml); services=(pg-primary pg-replica-0) ;;
    pg-second) files=(-f compose.pg-replicated.yaml -f compose.pg-second.yaml --profile second-replica); services=(pg-primary pg-replica-0 pg-replica-1) ;;
    mysql) files=(-f compose.mysql-replicated.yaml); services=(mysql-primary mysql-replica-0) ;;
    *) echo "Unknown mode: $mode" >&2; exit 2 ;;
esac

compose() { docker compose "${files[@]}" "$@"; }
assert_eq() {
    if [[ "$1" != "$2" ]]; then
        echo "Expected '$2', got '$1': $3" >&2
        exit 1
    fi
    echo "PASS $3"
}
wait_for() {
    local label=$1 command=$2
    for ((attempt=0; attempt<30; attempt++)); do
        if eval "$command"; then
            echo "PASS $label"
            return
        fi
        sleep 1
    done
    echo "Timed out after 30 seconds: $label" >&2
    exit 1
}
pg_query() { compose exec -T "$1" psql -U postgres -d shopware_pg -Atqc "$2"; }
mysql_query() { compose exec -T "$1" mysql -uroot -proot -N -B -e "$2" 2>/dev/null; }

compose build web >/dev/null
compose up -d --wait --wait-timeout 120 "${services[@]}"

if [[ $mode == pg-* ]]; then
    primary=${services[0]}
    # The embedded PHP must keep its dollar signs literal for the shell.
    # shellcheck disable=SC2016
    compose run --rm --no-deps -e DB_TEST_HOST="$primary" --entrypoint php web -r '
        if (!in_array("pgsql", PDO::getAvailableDrivers(), true)) { exit(1); }
        $pdo = new PDO("pgsql:host=" . getenv("DB_TEST_HOST") . ";dbname=shopware_pg", "shopware", "shopware", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE IF NOT EXISTS environment_pdo (id text PRIMARY KEY, payload bytea NOT NULL, label text NOT NULL)");
        $id = "pdo_" . bin2hex(random_bytes(6));
        $bytes = "\x00\xff\x80";
        $statement = $pdo->prepare("INSERT INTO environment_pdo VALUES (?, ?, ?)");
        $statement->bindValue(1, $id);
        $statement->bindValue(2, $bytes, PDO::PARAM_LOB);
        $statement->bindValue(3, "Zażółć");
        $statement->execute();
        $statement = $pdo->prepare("SELECT encode(payload, '\''hex'\''), label FROM environment_pdo WHERE id = ?");
        $statement->execute([$id]);
        if ($statement->fetch(PDO::FETCH_NUM) !== [bin2hex($bytes), "Zażółć"]) { exit(1); }
    '
    echo 'PASS PDO pgsql connect, UTF-8 and binary round-trip'
    assert_eq "$(pg_query "$primary" "SELECT split_part(current_setting('server_version'), ' ', 1)")" 18.6 "$primary version"
    assert_eq "$(pg_query "$primary" 'SELECT pg_is_in_recovery()')" f "$primary is writable primary"
    assert_eq "$(pg_query "$primary" "SELECT pg_encoding_to_char(encoding) FROM pg_database WHERE datname='shopware_pg'")" UTF8 'UTF-8 database'
    assert_eq "$(pg_query "$primary" 'SHOW timezone')" UTC 'UTC timezone'
    assert_eq "$(pg_query "$primary" "SELECT count(*) FROM pg_database WHERE datname='shopware_pg_test'")" 1 'isolated test database'
    if [[ $mode == pg-single ]]; then
        assert_eq "$(pg_query "$primary" 'SELECT count(*) FROM pg_replication_slots')" 0 'single node has no replication slots'
    fi
    marker="smoke_$(date +%s)_$$"
    pg_query "$primary" 'CREATE TABLE IF NOT EXISTS public.environment_smoke (id text PRIMARY KEY)'
    pg_query "$primary" "INSERT INTO public.environment_smoke (id) VALUES ('$marker')"
    barrier=$(pg_query "$primary" 'SELECT pg_current_wal_lsn()')
    for replica in "${services[@]:1}"; do
        assert_eq "$(pg_query "$replica" "SELECT split_part(current_setting('server_version'), ' ', 1)")" 18.6 "$replica version"
        assert_eq "$(pg_query "$replica" 'SELECT pg_is_in_recovery()')" t "$replica is physical standby"
        wait_for "$replica replay LSN $barrier" "[[ \$(pg_query '$replica' \"SELECT pg_last_wal_replay_lsn() >= '$barrier'::pg_lsn\") == t ]]"
        assert_eq "$(pg_query "$replica" "SELECT count(*) FROM public.environment_smoke WHERE id='$marker'")" 1 "$replica sees committed marker"
        assert_eq "$(pg_query "$replica" "SELECT status FROM pg_stat_wal_receiver LIMIT 1")" streaming "$replica WAL receiver"
        sender_name=${replica/pg-replica-/shopware_replica_}
        assert_eq "$(pg_query "$primary" "SELECT state FROM pg_stat_replication WHERE application_name='$sender_name'")" streaming "$replica WAL sender"
        if pg_query "$replica" "INSERT INTO public.environment_smoke (id) VALUES ('forbidden_$marker')" >/dev/null 2>&1; then
            echo "$replica accepted a write" >&2
            exit 1
        fi
        echo "PASS $replica rejects write"
    done
    compose restart "${services[@]}"
    compose up -d --wait --wait-timeout 120 "${services[@]}"
    assert_eq "$(pg_query "$primary" "SELECT count(*) FROM public.environment_smoke WHERE id='$marker'")" 1 'marker persists after restart'
    for replica in "${services[@]:1}"; do
        assert_eq "$(pg_query "$replica" "SELECT count(*) FROM public.environment_smoke WHERE id='$marker'")" 1 "$replica marker persists after restart"
    done
    single_slot_check=0
    if [[ $mode == pg-single ]]; then
        single_slot_check=1
    fi
    echo "PASS $mode: $((7 + single_slot_check + (${#services[@]}-1) * 8)) assertions"
else
    # The embedded PHP must keep its dollar signs literal for the shell.
    # shellcheck disable=SC2016
    compose run --rm --no-deps --entrypoint php web -r '
        if (!in_array("mysql", PDO::getAvailableDrivers(), true)) { exit(1); }
        $pdo = new PDO("mysql:host=mysql-primary;dbname=shopware_mysql;charset=utf8mb4", "shopware", "shopware", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (!str_starts_with($pdo->query("SELECT VERSION()")->fetchColumn(), "8.4.11")) { exit(1); }
    '
    echo 'PASS PDO mysql connect'
    assert_eq "$(mysql_query mysql-primary 'SELECT @@version')" 8.4.11 'MySQL primary version'
    assert_eq "$(mysql_query mysql-replica-0 'SELECT @@version')" 8.4.11 'MySQL replica version'
    assert_eq "$(mysql_query mysql-replica-0 'SELECT @@read_only')" 1 'MySQL replica read only'
    assert_eq "$(mysql_query mysql-primary "SELECT count(*) FROM information_schema.schemata WHERE schema_name='shopware_mysql_test'")" 1 'isolated MySQL test database'
    marker="smoke_$(date +%s)_$$"
    mysql_query mysql-primary "CREATE TABLE IF NOT EXISTS shopware_mysql.environment_smoke (id VARCHAR(80) PRIMARY KEY); INSERT INTO shopware_mysql.environment_smoke VALUES ('$marker')"
    wait_for 'MySQL marker replicated' "[[ \$(mysql_query mysql-replica-0 \"SELECT count(*) FROM shopware_mysql.environment_smoke WHERE id='$marker'\") == 1 ]]"
    if compose exec -T mysql-replica-0 mysql -ushopware -pshopware -e "INSERT INTO shopware_mysql.environment_smoke VALUES ('forbidden_$marker')" >/dev/null 2>&1; then
        echo 'MySQL replica accepted an application write' >&2
        exit 1
    fi
    echo 'PASS MySQL replica rejects application write'
    status=$(compose exec -T mysql-replica-0 mysql -uroot -proot -e 'SHOW REPLICA STATUS\G' 2>/dev/null)
    [[ $status == *'Replica_IO_Running: Yes'* && $status == *'Replica_SQL_Running: Yes'* ]] || { echo "$status" >&2; exit 1; }
    echo 'PASS MySQL IO and SQL replication threads'
    compose restart "${services[@]}"
    compose up -d --wait --wait-timeout 120 "${services[@]}"
    assert_eq "$(mysql_query mysql-primary "SELECT count(*) FROM shopware_mysql.environment_smoke WHERE id='$marker'")" 1 'MySQL marker persists after restart'
    assert_eq "$(mysql_query mysql-replica-0 "SELECT count(*) FROM shopware_mysql.environment_smoke WHERE id='$marker'")" 1 'MySQL replica marker persists after restart'
    echo 'PASS mysql: 10 assertions'
fi

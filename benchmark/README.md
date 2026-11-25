# Benchmark Shopware: PostgreSQL vs MySQL

## Środowisko

Warianty uruchamiają tę samą aplikację w obrazie Shopware FrankenPHP oraz jedną bazę danych. Nie ma Nginx, Redis ani OpenSearch. `FRANKENPHP_CONFIG` jest pusty, więc obraz działa w zwykłym trybie HTTP, bez worker mode. Oba Compose korzystają ze wspólnego `compose.common.yaml`, z tym samym limitem CPU/RAM dla aplikacji.

Obrazy są przypięte: Shopware `docker-base` z PHP 8.4.28, PostgreSQL 18.6 i MySQL 8.4.11. Obraz Shopware zawiera `pdo_mysql`; `Dockerfile` dodaje `pdo_pgsql`. Shopware publikuje FrankenPHP jako rekomendowany serwer, a worker mode włącza się jawnie przez `FRANKENPHP_CONFIG=worker ...`; tutaj nie ustawiamy workera. Zobacz [dokumentację obrazu Shopware](https://developer.shopware.com/docs/guides/hosting/installation-updates/docker.html) i [FrankenPHP](https://github.com/php/frankenphp/blob/main/docs/docker.md).

Początkowy profil zasobów w `.env.example` zakłada VPS 4 vCPU / 8 GB RAM: aplikacja 2 vCPU / 2 GB, baza 2 vCPU / 4 GB, reszta dla systemu i cache plików. Należy dopasować limity do docelowego VPS, ale zachować je identyczne przy obu bazach. K6 uruchamiamy z innej maszyny, najlepiej w tym samym regionie. Domyślny bind HTTP to localhost; dla testera zdalnego użyj tunelu SSH albo świadomie zmień `APP_BIND_IP` i ogranicz firewall do adresu testera.

Konfiguracja benchmarku wyłącza HTTP cache Shopware. Dynamiczna grupa kategorii jest oceniana na każdym żądaniu listingu; jej filtry cenowe muszą być ustawione w fixture sklepu. Test zakłada, że kategoria ma `productAssignmentType=product_stream`, przypisany poprawny stream, a katalog zawiera pasujące aktywne produkty. Indeksowanie product stream jest wyłączone w konfiguracji podmontowanej tylko do benchmarkowego kontenera.

## Start

```sh
cp benchmark/.env.example benchmark/.env
docker compose --env-file benchmark/.env -f benchmark/compose.postgresql.yaml up -d --build
```

Do uruchomienia wariantu MySQL użyj `benchmark/compose.mysql.yaml`. Wolumeny są oddzielone nazwami projektu. Baza nie publikuje portu na hoście; administruj nią przez `docker compose exec`. Nie uruchamiaj obu wariantów jednocześnie na jednym VPS.

### Storefront assety i motyw

Po instalacji Shopware zbuduj assety storefrontu i skompiluj motyw kanału sprzedaży, zanim sprawdzisz stronę sklepu. Obraz benchmarkowej aplikacji nie zawiera Node.js, więc zależności i assety buduj na hoście z wersjami wymaganymi przez `package.json`:

```sh
STOREFRONT_APP=src/Storefront/Resources/app/storefront
npx --yes npm@11.8.0 ci --prefix "$STOREFRONT_APP"
docker compose --env-file benchmark/.env -f benchmark/compose.postgresql.yaml exec app php bin/console bundle:dump
docker compose --env-file benchmark/.env -f benchmark/compose.postgresql.yaml exec app php bin/console feature:dump
PROJECT_ROOT="$PWD" APP_URL=http://localhost:8000 npm --prefix "$STOREFRONT_APP" run production
node "$STOREFRONT_APP/copy-to-vendor.js"
docker compose --env-file benchmark/.env -f benchmark/compose.postgresql.yaml exec app php bin/console assets:install
docker compose --env-file benchmark/.env -f benchmark/compose.postgresql.yaml exec app php bin/console theme:change Storefront --sales-channel=<storefront-id> --sync
```

Przy kompilacji na PostgreSQL wybierz konkretny kanał przez `theme:change`; `theme:compile --active-only` w tej gałęzi porównuje kolumnę `sales_channel.active` typu `smallint` z wartością boolean i kończy się błędem.

### Adminer do pre-testów

Adminer jest opcjonalny, aby nie dodawać kontenera do właściwego pomiaru. Uruchom go razem z bazą na stałym porcie `8081`:

```sh
docker compose --env-file benchmark/.env.example -f benchmark/compose.postgresql.yaml --profile tools up -d db adminer
# albo zamiast PostgreSQL:
docker compose --env-file benchmark/.env.example -f benchmark/compose.mysql.yaml --profile tools up -d db adminer
```

Otwórz <http://localhost:8081>. W Adminerze wybierz `Benchmark` i kliknij `Auto Sign-In`; dane logowania pozostają po stronie kontenera, nie trafiają do formularza przeglądarki. Serwis jest przypięty do localhost; nie wystawiaj tego portu publicznie. Właściwy pomiar wykonuj bez profilu `tools`.

## Scenariusz dynamicznej grupy

`scenarios/dynamic-product-group-listing.js` wysyła stały napływ żądań do Store API listingu kategorii. Przykład:

```sh
k6 run \
  --env SHOP_URL=http://localhost:8000 \
  --env SALES_CHANNEL_ACCESS_KEY=... \
  --env BENCHMARK_CATEGORY_ID=... \
  --env RATE_PER_SECOND=10 \
  --env DURATION=5m \
  --out json=benchmark/results/postgresql-dynamic-group.json \
  benchmark/scenarios/dynamic-product-group-listing.js
```

Przed porównaniem wykonaj krótki smoke test i sprawdź, czy listing zwraca produkty należące do grupy oraz mieszczące się w jej przedziale cenowym. Dla głównego pomiaru używaj tych samych fixture, rate, czasu, page size i rozgrzewki; odtwórz czystą bazę dla każdej mierzonej rundy.

## Obserwowalność

Rekomendacja: **lekkie pomiary, bez Prometheus/Grafana na benchmarkowym VPS**. Dodatkowe kontenery i scrape mogą zabierać CPU/RAM lub I/O. Zawsze zachowuj surowy JSON k6 (RPS, p50/p95/p99, błędy), a równolegle próbkuj `docker stats` co sekundę i z hosta `vmstat 1` oraz `iostat -xz 1`.

Przed i po każdym przebiegu zapisz natywne liczniki bazy. PostgreSQL: wiersz z `pg_stat_database` dla testowej bazy, w tym `blks_hit`, `blks_read`, tuples i deadlocks. MySQL: delty `Com_select`, `Com_insert`, `Com_update`, `Innodb_buffer_pool_read_requests`, `Innodb_buffer_pool_reads`, `Threads_running` i `Slow_queries` z `SHOW GLOBAL STATUS`. To mały narzut i pozwala odróżnić nasycenie aplikacji od DB/I/O. Dla analizy najwolniejszych statementów wykonaj osobny przebieg diagnostyczny z `pg_stat_statements` oraz MySQL Performance Schema; nie mieszaj go z wynikiem głównym.

## Dane i import

Katalog źródłowy w `data/product-families.en.json` zawiera 48 fikcyjnych anglojęzycznych rodzin produktów, nazwy, opisy i właściwości. Teksty przygotowano lokalnie modelem Ollama `qwen2.5:1.5b-instruct` (około 1 GB); model nie jest potrzebny do odtworzenia fixture. `build_catalog.py` deterministycznie tworzy 5 000 rekordów: rodziny, stabilne UUID/SKU, ceny, stany i warianty w dwóch osiach. Plik wynikowy jest ignorowany przez Git.

```sh
python3 benchmark/build_catalog.py
# mniejszy fixture do szybkiego importu
python3 benchmark/build_catalog.py --count 500
```

Wynikowy JSON to neutralny format fixture z wariantami i metadanymi. Po czystej instalacji Shopware oraz utworzeniu storefront sales channel zaimportuj go przez Admin API/Sync API:

```sh
python3 benchmark/build_catalog.py
SHOPWARE_ADMIN_PASSWORD=shopware python3 benchmark/import_catalog.py \
  --sales-channel "PostgreSQL Benchmark" \
  --metadata-file /tmp/postgresql-benchmark.json
```

Skrypt tworzy grupy/opcje właściwości, 48 rodzin rodziców i 4 952 warianty z tymi samymi ID/SKU w obu bazach. Tworzy też kategorię przypisaną do dynamicznego product stream z filtrem ceny 50–250 EUR. Zapisuje czasy przygotowania właściwości i importu produktów oddzielnie. Po imporcie sprawdź listing oraz liczność danych; dopiero potem wykonuj przebiegi k6. Nie licz czasu seedowania do wyniku odczytowego k6.

Wyniki benchmarku i wygenerowany plik `data/catalog.generated.json` są ignorowane przez Git. Repo zawiera źródłowe rodziny produktów i generator, nigdy sekrety ani kopię bazy.

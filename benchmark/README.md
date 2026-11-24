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

Wynikowy JSON to neutralny format fixture z wariantami i metadanymi; przed importem do konkretnego Shopware należy zmapować kategorie, podatki, walutę, sales channel, grupy/opcje właściwości i dostępność do ID tej instalacji. Import przez Sync API należy mierzyć jako oddzielny scenariusz: czas całego importu, produkty/s, wielkość batcha, błędy i czas do zakończenia indeksowania. Po nim sprawdź liczność i próbki danych, odśwież indeksy, a dopiero potem uruchom scenariusze odczytowe. Nie licz czasu seedowania do wyniku k6 listingu.

Wyniki benchmarku i wygenerowany plik `data/catalog.generated.json` są ignorowane przez Git. Repo zawiera źródłowe rodziny produktów i generator, nigdy sekrety ani kopię bazy.

# Performance baseline

Base revision `690db0b55ff03a1b45df10d808128dcd702ecb23` on `feat/performance-optimization`. Measurements made before application changes. PHP 8.4.7 on Windows, Laravel through a local PHP HTTP server on port 8097, SQLite in a separate file, array session and cache. The benchmark router is restricted to loopback and GET. It authenticates a fictional administrator on protected routes and counts queries with a listener. It does not use the development database.

Fixture: one fictional customer, 400 orders, one normal line per order, no stock or assortment records. This measures empty stock and assortment catalogs and a normal order detail. It cannot establish scaling for large inventories or complex assorted orders. Browser interaction has not been measured.

Three warmups and 30 sequential valid HTTP 200 samples per route. TTFB and SQL times in ms. Percentiles use nearest rank. Bytes are HTTP body bytes without compression.

Local rerun inputs are in the workspace's `.local-dev`: `performance-baseline.php` (setup and HTTP router), `performance-capture.ps1` (curl capture), `performance-synthetic.sqlite` (SHA-256 `0d2fb382a84c36ecdd8094242345a78894e86b6ada8e1993adc6aa5f07d34d04`) and `performance-before.csv`. Run the router with the repo as the working directory and the existing local PHP configuration. The commercial report uses explicit dates and covers all 400 synthetic order lines.

| Route | TTFB p50 | TTFB p95 | Queries | SQL p50 | Bytes |
|---|---:|---:|---:|---:|---:|
| /salud | 174.70 | 184.45 | 1 | 0.76 | 15 |
| /login | 187.13 | 207.86 | 0 | 0.00 | 26560 |
| /pedidos | 225.00 | 259.71 | 8 | 2.51 | 26652 |
| /pedidos/1 | 244.65 | 422.95 | 9 | 2.82 | 29185 |
| /indicadores (commercial, November 2026) | 247.85 | 273.55 | 10 | 4.48 | 38814 |
| /inventario/stock | 216.25 | 234.38 | 7 | 2.31 | 18717 |
| /modalidades-surtidas | 218.47 | 240.42 | 8 | 2.35 | 17594 |

Baseline automated checks: 28 JavaScript tests passed. PHPUnit ran 622 tests with one pre-existing calendar-dependent error in `IndicadoresOperacionesTest::test_historicos_entregados_sin_desglose_no_se_convierten_en_aceptados`. The fixture uses the current date while the helper selects October 2026. Keep the assertions and align the fixture clock with the report period.

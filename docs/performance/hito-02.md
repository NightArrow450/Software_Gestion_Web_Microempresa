# Milestone 2 Batch order assortment reads

## Objective

Reduce SQL work in order detail and editing while preserving the assortment editor, history, read-only composition views and WhatsApp summary.

## Findings

Before changes, a synthetic order with 12 assortment lines required 140 queries for detail and 39 for edit. The compositions partial queried current packs, history and formats per line. The detail also reloaded packs for the clipboard summary.

The targeted routes are GET `/pedidos/{pedido}` and GET `/pedidos/{pedido}/editar`. The shared compositions partial is also used by fulfillment, tracking and print.

## Changes Implemented

- `app/Services/GestionarSurtidos.php` reads current packs and historic revisions by sets of detail IDs, then groups by detail. The existing preparation read reuses the pack loader. Component queries use integer IDs from the database to avoid bound-parameter limits when combining lines.
- `app/Http/Controllers/PedidoController.php` and `resources/views/orders/show.blade.php` share one current-pack result between the detail and the clipboard summary.
- The `composiciones.blade.php` and `form.blade.php` partials reuse the batched results. Read-only views retain their existing branch. The edit form skips the pack read when it already has previous submitted details.
- `tests/Feature/LecturaPreparacionSurtidosTest.php` covers equivalence with individual pack reads, historic revision order, retention of withdrawn packages and a fixed two-query history load.

## Validation

The focused benchmark rendered both HTTP-handler responses through Laravel's test kernel in SQLite memory. Each run checked all requests returned HTTP 200. The fixture uses the repository's assortment scenario, a normal line and 0, 1 or 12 assortment lines with initial and revised compositions. Three warmups and 30 measured samples per case. The comparison includes Laravel request dispatch and Blade rendering in-process. It has no network TTFB and no browser timing.

PHPUnit SQLite passed 622 tests and 3,831 assertions. MySQL 8.4.3 passed 68 tests and 1,089 assertions in a disposable container, with a fresh schema and a separate compiled-view directory. JavaScript passed 28 tests. There were no omissions in these suites. Larastan, Pint and the Vite build passed. Existing coverage exercises print, permissions, delivery, inventory and exports, along with the strengthened assortment fixture. An earlier MySQL run overlapped edits and produced view errors. The isolated rerun on the final diff was green.

## Metrics

### Before

For 12 lines, detail took 140 queries, with p50 43.79 ms, p95 51.19 ms, SQL p50 6.39 ms and 94,302 bytes. Edit took 39 queries, with p50 15.98 ms, p95 18.09 ms, SQL p50 1.77 ms and 35,059 bytes.

### After

| Route | Assortment lines | Queries before → after | p50 before → after, ms | p95 before → after, ms | SQL p50 before → after, ms | Bytes before → after |
|---|---:|---:|---:|---:|---:|---:|
| Detail | 0 | 8 → 8 | 9.55 → 9.23 | 10.59 → 10.67 | 0.37 → 0.34 | 18766 → 18766 |
| Edit | 0 | 15 → 15 | 11.26 → 11.21 | 11.81 → 11.65 | 0.69 → 0.66 | 17049 → 17049 |
| Detail | 1 | 19 → 17 | 12.76 → 12.19 | 16.27 → 17.85 | 0.88 → 0.77 | 25046 → 25046 |
| Edit | 1 | 17 → 17 | 12.48 → 11.70 | 16.41 → 13.12 | 0.84 → 0.79 | 18542 → 18542 |
| Detail | 12 | 140 → 17 | 43.79 → 18.85 | 51.19 → 20.95 | 6.39 → 1.08 | 94302 → 94302 |
| Edit | 12 | 39 → 17 | 15.98 → 14.03 | 18.09 → 14.58 | 1.77 → 1.06 | 35059 → 35059 |

### Difference

For the 12-line detail, queries fell 87.9% and in-process p50 fell 57.0%. For edit, queries fell 56.4% and p50 fell 12.2%. Increasing from one to 12 lines no longer increased queries in either targeted fixture. Payload size stayed equal in all six cases. The single-line detail p95 rose by 1.58 ms in this run, so the measured latency win is concentrated in larger orders.

The original seven-route loopback HTTP benchmark was also repeated on the same 400-order dataset. This dataset has no assortment lines. Counts and body sizes matched hito 01. Other local activity overlapped the run, so these TTFB readings are controls rather than estimates of the assortment change.

| Route | TTFB p50 ms | TTFB p95 ms | Queries | SQL p50 ms | Bytes |
|---|---:|---:|---:|---:|---:|
| /salud | 171.32 | 186.52 | 1 | 0.74 | 15 |
| /login | 181.67 | 192.87 | 0 | 0.00 | 26560 |
| /pedidos | 229.00 | 255.23 | 7 | 2.46 | 26652 |
| /pedidos/1 | 226.77 | 249.68 | 9 | 2.51 | 29185 |
| /indicadores (commercial, November 2026) | 232.91 | 265.54 | 10 | 4.24 | 38814 |
| /inventario/stock | 228.03 | 243.16 | 7 | 2.49 | 18717 |
| /modalidades-surtidas | 227.59 | 248.02 | 8 | 2.44 | 17594 |

## Risks

SQL work is batched, while history still grows as more revisions are displayed. Lazy loading history would change when the UI fetches data and was deferred. Indicators remains a separate optimization opportunity. Its MySQL reporting suites protect totals and exports, and its report implementation is unchanged. The historical k6/ZAP lab remains unavailable and the user authorized the local scope.

The MySQL test fixture with 1,500 commercial lines still logged 18 queries for the screen and 16 for CSV. Those counts matched hito 01. This is a candidate for separate profiling in hito 03.

The benchmark harness and raw samples are in the workspace's `.local-dev`: `SurtidosBenchmarkTest.php`, `h2-surtidos-before.json`, `h2-surtidos-final.json` and `performance-h2-final.csv`. The before revision is `883ef55` and the implementation is versioned with this report in `perf(hito-2): batch assortment reads`.

## Acceptance Criteria

- [x] All locally available automated suites passed without omissions.
- [x] Batched pack output matches individual reads, including pending packs. Tests cover historic versions, totals, inventory calculations and report exports.
- [x] Queries fell in the targeted flows and stayed constant on the seven control routes.
- [x] Focused latency and payload results recorded for all six cases.
- [ ] Historical external k6/ZAP and browser profiling were outside the authorized local scope.

## Recommendation

YES for the authorized validation scope. Profile the commercial report independently before changing its calculation or pagination.

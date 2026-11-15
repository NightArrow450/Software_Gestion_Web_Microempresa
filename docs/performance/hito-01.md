# Milestone 1 Safe performance wins

## Objective

Remove a database read from the orders listing with no effect on the rendered list or printing workflow.

## Findings

`PedidoController::index()` selected up to 200 recent orders in addition to the paginated list and handed them to the view as `opcionesImpresion`. Searching application and view sources found no consumer. Both print routes dispatch to `ImpresionPedidosController` independently.

## Changes Implemented

- `app/Http/Controllers/PedidoController.php` removes the unused read from GET `/pedidos`.
- `tests/Feature/IndicadoresOperacionesTest.php` fixes the date of a synthetic historical order to October, the month already selected by the test's report filter. The baseline failed before application changes when it ran in November. Assertions are unchanged.

## Validation

- PHPUnit SQLite: 622 passed, 3,826 assertions, no omissions. Includes print and security tests.
- PHPUnit MySQL 8.4.3: 68 passed, 1,089 assertions, no omissions. Includes concurrency, inventory, reports, exports, and historic migrations.
- JavaScript: 28 passed, no omissions. Vite build passed.
- Larastan and Pint passed.
- HTTP: 210 valid responses across the seven routes in each run. Body size and query counts were unchanged on the six untargeted routes.

## Metrics

Same isolated fixtures and server as [baseline](baseline.md). Three warmups and 30 measured HTTP requests per route. Time in milliseconds. Query counts include the benchmark's test-user lookup on protected routes.

### Before

GET `/pedidos`: p50 225.00, p95 259.71, 8 queries, SQL p50 2.51, 26,652 bytes.

### After

| Route | TTFB p50 | TTFB p95 | Queries | SQL p50 | Bytes |
|---|---:|---:|---:|---:|---:|
| /salud | 175.26 | 184.04 | 1 | 0.74 | 15 |
| /login | 195.00 | 229.63 | 0 | 0.00 | 26560 |
| /pedidos | 223.13 | 238.76 | 7 | 2.45 | 26652 |
| /pedidos/1 | 238.55 | 257.30 | 9 | 2.64 | 29185 |
| /indicadores (commercial, November 2026) | 246.97 | 262.08 | 10 | 4.40 | 38814 |
| /inventario/stock | 239.54 | 250.86 | 7 | 2.66 | 18717 |
| /modalidades-surtidas | 236.35 | 249.35 | 8 | 2.54 | 17594 |

### Difference

The orders listing executes one fewer query per request, a reduction of 12.5%, and avoids hydrating up to 200 unused models. Body size stayed at 26,652 bytes. TTFB p50 fell 1.87 ms and p95 fell 20.95 ms in these runs. Untargeted routes also varied, and part of the second HTTP run overlapped MySQL QA on the same host. The query count is the validated improvement. The latency difference does not isolate the effect of the change.

## Risks

SQLite loopback timings are specific to this fixture and host. No browser profiling was performed for this backend-only change. The PHP HTTP server profile differs from production. Search and rendering work remain candidates for profiling.

The existing end-to-end k6/ZAP suites require the independently configured Fase 9B lab described in `tests/Performance/README.md`. The documented lab manifest is absent on this host and k6 is not on PATH. The local HTTP benchmark does not replace those suites. Early MySQL attempts exposed fixture setup issues. The passing run uses the CI privilege prefix inside the disposable loopback container and a fresh database name for every run, including the historic-migration fixture.

## Acceptance Criteria

- [x] One measured query reduction and no query count increase on the measured routes.
- [x] Existing PHPUnit SQLite and JavaScript assertions pass without omissions.
- [x] The application diff removes only an unused view input. Existing print flows passed their tests.
- [x] Independent MySQL suite passes without omissions.
- [ ] Historical end-to-end k6/ZAP suites were unavailable locally. The user authorized advancing with the available validation.

## Recommendation

YES for the authorized local scope. Historical k6/ZAP coverage remains outstanding.

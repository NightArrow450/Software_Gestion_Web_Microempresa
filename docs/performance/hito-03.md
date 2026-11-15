# Milestone 3 Commercial report pagination

## Objective

Reduce work per page of the commercial indicators report in its default view without economic amounts.

## Findings

GET `/indicadores?reporte=comercial` streamed all matching lines into PHP in blocks of 100, built the report row for each line and kept the visible 25. It then queried distinct-order counts and active quantities separately. With 400 lines, the HTTP route made 10 queries. The existing MySQL case with 1,500 lines measured 18 queries inside the economic report service, illustrating how its streaming work scales.

## Changes Implemented

- `app/Services/ResumenIndicadores.php` uses a grouped SQL aggregate for the commercial view without amounts. It aggregates by order so distinct order counts do not grow with duplicated order lines. Package totals continue to include matching canceled lines, while the active quantity indicators exclude them.
- `app/Services/PedidosIndicadores.php` reuses the same filters and row mapping to select just the visible page, ordered by detail ID. The service skips the page query when the requested offset has no rows. The economic view still uses the existing per-line amount logic, and the CSV still streams the complete filtered result.
- `tests/Feature/IndicadoresTest.php` covers mixed active and canceled orders, composition filtering, empty results, a later page and a page beyond the result. `tests/MySql/IndicadoresMySqlTest.php` checks exact equivalence to the already tested economic result with amount fields removed and a fixed two-query count for nonempty pages.

## Validation

Focused SQLite indicator suite passed 16 tests and 127 assertions. The baseline and comparison reuse the same 400-order SQLite database, PHP server, synthetic user, dates and capture script as the earlier milestones. The HTTP measurement captures 30 samples per route after three warmups.

Full results: PHPUnit SQLite 622 tests and 3,854 assertions. PHPUnit MySQL 8.4.3 68 tests and 1,099 assertions, including exact comparisons of operational outputs on both report sizes. JavaScript 28 tests. No skips. Larastan, Pint and Vite passed.

## Metrics

### Before

Commercial indicators without amounts, November 2026 on the synthetic fixture: TTFB p50 223.76 ms, p95 228.74 ms, 10 queries, SQL p50 4.06 ms and 38,814 response bytes.

### After

| Route | TTFB p50 before → after, ms | TTFB p95 before → after, ms | Queries before → after | SQL p50 before → after, ms | Body bytes |
|---|---:|---:|---:|---:|---:|
| /salud | 162.70 → 173.94 | 167.19 → 224.00 | 1 → 1 | 0.71 → 0.75 | 15 |
| /login | 177.25 → 189.47 | 183.76 → 218.33 | 0 → 0 | 0.00 → 0.00 | 26560 |
| /pedidos | 212.69 → 224.61 | 222.94 → 239.48 | 7 → 7 | 2.23 → 2.37 | 26652 |
| /pedidos/1 | 225.65 → 229.45 | 239.96 → 240.40 | 9 → 9 | 2.47 → 2.52 | 29185 |
| /indicadores (commercial, November 2026) | 223.76 → 226.16 | 228.74 → 247.30 | 10 → 5 | 4.06 → 3.20 | 38814 |
| /inventario/stock | 218.99 → 221.86 | 236.95 → 232.51 | 7 → 7 | 2.43 → 2.42 | 18717 |
| /modalidades-surtidas | 217.57 → 221.68 | 234.32 → 231.32 | 8 → 8 | 2.35 → 2.35 | 17594 |

In-process A/B, same database and PHP process, alternating order across 50 pairs after five warmup pairs. Each call includes a read transaction.

| Metric | Before | After |
|---|---:|---:|
| Service p50, ms | 5.461 | 1.822 |
| Service p95, ms | 6.478 | 2.679 |
| SQL p50, ms | 2.210 | 1.120 |
| SQL queries | 7 | 2 |

### Difference

The service p50 decreased 66.6% and SQL query count decreased 71.4%. The HTTP route ran five fewer queries and moved 25 rows into the PHP page formatter instead of 400. SQL still scans matching records for the grouped totals. The rendered body size was unchanged. TTFB rose in this pair of HTTP runs, and control routes also moved. The HTTP runs provide no evidence of a TTFB improvement.

The A/B used the `fa615de` summary implementation for the before case. Complete report arrays compared strictly equal on pages 1, 8, 16 and 17 (out of range). Raw samples and the harness are stored in workspace `.local-dev` as `performance-h3-before.csv`, `performance-h3-after.csv`, `performance-h3-ab.php` and `performance-h3-ab.json`.

## Risks

The aggregation still reads the matching result to compute totals. Deep offsets may cost more even though only the displayed rows are serialized into PHP. The measured HTTP fixture has normal order lines. SQLite and MySQL regression coverage add report correctness checks, and the MySQL suite uses its existing 3- and 1,500-line fixtures. Production traffic, browser responsiveness and historic k6/ZAP were outside the locally available validation.

## Acceptance Criteria

- [x] Full local automated suites passed without skips.
- [x] Report output parity checked in SQLite and MySQL, including pagination and amounts visibility.
- [x] The view, CSV path and inventory logic use their existing behavior. No UI assets were edited.
- [x] Backend improvement demonstrated and seven-route HTTP comparison completed.
- [ ] Production traffic, browser responsiveness and historic k6/ZAP remain unverified.

## Recommendation

YES for closing the milestone and reviewing the branch with the agreed local validation. The measurements support the scoped backend gains.

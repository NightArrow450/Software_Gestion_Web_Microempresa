# Final performance report

## Executive Summary

Across three milestones the isolated performance branch removed one unused list query, batched assortment reads and pushed commercial report pagination into the database for the view without amounts. The largest gain was in order details with multiple assorted lines. The strongest report result is the alternating, in-process A/B. The HTTP control runs did not demonstrate faster TTFB across the application.

## Optimization Summary

| Milestone | Scope | Evidence |
|---|---|---|
| 1 | Removed the unused print-option read from the orders list | [Hito 01](hito-01.md), `883ef55` |
| 2 | Batched current packs, modifications and formats and shared the current result with the clipboard summary | [Hito 02](hito-02.md), `fa615de` |
| 3 | Combined grouped counts and quantities in SQL and fetched only the displayed lines of the commercial view without amounts | [Hito 03](hito-03.md), `perf(hito-3): paginate operational commercial indicators` |

The workflow, authorizations and output checks stayed in place. The report with amounts and CSV retain their existing per-line calculations and export behavior.

## Metrics Summary

| Target and fixture | Query count before → final | Response or service timing before → final |
|---|---:|---|
| Orders index, 400 ordinary orders, HTTP | 8 → 7 | TTFB p50 225.00 → 224.61 ms, p95 259.71 → 239.48 ms across separate runs |
| Order detail, 12 assorted lines, in-process Laravel | 140 → 17 | p50 43.79 → 18.85 ms, p95 51.19 → 20.95 ms |
| Order edit, 12 assorted lines, in-process Laravel | 39 → 17 | p50 15.98 → 14.03 ms, p95 18.09 → 14.58 ms |
| Commercial report without amounts, 400 lines, alternating service A/B | 7 → 2 | p50 5.461 → 1.822 ms, p95 6.478 → 2.679 ms |
| Same commercial report, loopback HTTP | 10 → 5 | TTFB p50 223.76 → 226.16 ms, p95 228.74 → 247.30 ms |

Body size stayed equal for every compared route and fixture. The original seven-route HTTP baseline and full repeat tables are in the [baseline](baseline.md) and milestone reports. Service and Laravel-kernel timings measure distinct scopes from HTTP TTFB.

## Largest Wins

The 12-line assortment detail saved 123 SQL queries per request and reduced median measured Laravel time by 57%. The report service saved five queries and about 3.64 ms at the median on its 400-line fixture. It formats up to 25 rows in PHP per page. SQL still reads matching rows for totals.

## Remaining Bottlenecks

- The commercial report with amounts still computes sums per line. Its 1,500-line MySQL case uses 18 screen queries and 16 CSV queries.
- Large order histories still add to the rendered page size. Their timing and browser impact were outside this validation.
- Bootstrap, development server and host variation contribute to loopback TTFB. The current measurements cannot isolate each contribution.

## Recommendations

Profile real-size datasets and a production-like server before changing transaction boundaries or report arithmetic. Benchmark catalog search in a browser before choosing an interaction optimization. Preserve output comparisons for exports, canceled orders, historic package data and exact cent amounts when extending the report work.

## Final Verdict

**⚠️ Partial improvement.** The three targeted changes delivered measured reductions in SQL and application processing, and the local test suites cover the changed flows. End-to-end latency is not established, and the historical k6/ZAP lab and browser timing were outside the authorized local scope. The branch is suitable for review against the local evidence.

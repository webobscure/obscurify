# Analytics Platform + Reporting

## 1. Overview

Milestone 20 adds a merchant-facing analytics and reporting surface —
dashboards of configurable widgets, a report builder across nine
report types, and CSV/Excel/PDF export — built entirely on top of the
Platform Event Bus (M11) rather than by querying commerce tables at
read time. The core design constraint (spec section 14, "Performance"):
every dashboard/report read serves from **precomputed daily
snapshots**, never a synchronous aggregate query over `orders`,
`payments`, `refunds`, etc.

Core entities, all under `App\Domain\Analytics`:

| Entity | Purpose |
|---|---|
| `MetricDefinition` | The global metric catalog (19 built-ins) — key, label, category, unit, calculation kind. Not tenant-scoped. |
| `AnalyticsEvent` | Analytics' own normalized, append-only projection of relevant Platform Events — see §2. |
| `AnalyticsSnapshot` | One metric's precomputed value for one store on one day — see §3. |
| `Dashboard` / `DashboardWidget` | A merchant's saved widget layout — see §5. |
| `Report` / `SavedReport` | An ad-hoc or saved report definition and its materialized result — see §6. |
| `ReportExport` | A CSV/XLSX/PDF export of one report's result — see §8. |

## 2. The projection pipeline

`AnalyticsProjector` is the **one** place Analytics reads commerce
tables directly. It runs as a third subscriber inside
`ProcessOutboxEventsCommand`'s existing claimed-row transaction,
alongside M11's `DispatchWebhooksForEvent` and M19's
`DispatchWorkflowTriggersForEvent` — the same "add a subscriber, don't
build a second poller" pattern ADR-025 established for Automation.

For each of 10 relevant event types (`OrderCreated`,
`OrderPaymentConfirmed`, `RefundCompleted`, `ReturnCompleted`,
`CustomerCreated`, `PromotionApplied`, `ShipmentDelivered`,
`InventoryChanged`, `WorkflowExecuted`, `WorkflowExecutionFailed`), the
projector reads the triggering aggregate **exactly once** — e.g.
`OrderPaymentConfirmed` loads the `Order` with
`items.product.categories/collections`, `discountApplications`, and
`shippingLine` eager-loaded, and flattens it into one normalized
`AnalyticsEvent` row (amount, currency, customer_id, and a `payload`
JSON blob with line items/discounts/shipping breakdown). From that
point on, **every** downstream reader — the aggregator, the widget
resolver, the report builder — touches only `analytics_events`/
`analytics_snapshots`, never `orders`/`payments`/`refunds` again.

This is a deliberate departure from M18's "recompute everything from
the current row state, store nothing" discipline (see
`docs/adr/026-analytics-platform.md` §1): Analytics must answer
"total revenue for the last 90 days" cheaply and repeatedly, which
recomputation-from-scratch cannot do at scale.

Idempotent via a unique constraint on `analytics_events.outbox_event_id`
— the same claim-or-skip-on-`UniqueConstraintViolationException`
pattern `WebhookDelivery`/`WorkflowExecution` already use.

## 3. Aggregation and snapshots

`AnalyticsAggregator::aggregateDay(storeId, day)` computes all 19
metrics for one store/day and upserts one `AnalyticsSnapshot` row per
metric (unique on `store_id, metric_key, period_date`):

- **15 base metrics** (`MetricCalculator::calculateBase()`) —
  sum/count/leaderboard queries against `AnalyticsEvent` scoped to that
  one day.
- **3 derived metrics** (`net_revenue`, `average_order_value`,
  `repeat_purchase_rate`) — computed from the day's own base values,
  no extra query.
- **1 gauge metric** (`inventory_value`) — the only metric that reads
  outside `AnalyticsEvent`, joining `InventoryLevel`/`inventory_items`/
  `product_variants` for a point-in-time on-hand valuation. Gauges are
  never summed across a range (see §4) — only the latest day's reading
  is meaningful.

**Only daily granularity is ever persisted.** Every coarser time
dimension (Last 7 Days, Month, Quarter, Year, Custom) is computed by
summing the relevant daily `AnalyticsSnapshot` rows at *read* time
(`WidgetDataResolver`/`TimeRangeResolver`), not by maintaining separate
weekly/monthly rollup rows — one write path, no rollup-consistency
problem to solve.

Real-time freshness: `AnalyticsProjector::claim()` calls
`aggregateDay()` for the affected day immediately after projecting a
new event, so a dashboard reflects an order placed seconds ago without
waiting for a scheduled rebuild. `RebuildAnalyticsSnapshotsCommand`
(`analytics:rebuild-snapshots --store= --from= --to=`, default: last 30
days) exists for backfill/recovery — not wired into a scheduler, same
"run externally on a cron" convention every other operational command
in this codebase follows.

`AnalyticsAggregator` resolves its own `Store` and wraps its work in
`TenantContext::scope()` rather than trusting an ambient tenant scope —
it's called from two different contexts (the real-time post-projection
hook, already store-scoped, and the backfill command's loop, which
isn't) and `BelongsToTenant`'s `creating()` hook always forces
`store_id` from whatever `TenantContext` happens to be active
regardless of query-side `withoutGlobalScopes()`, so self-scoping is
the only way to be correct in both callers.

## 4. Metric catalog

19 built-in metrics (`RegisterBuiltInAnalyticsCatalog`, seeded via
`php artisan analytics:install`), each with a `MetricCategory`
(Revenue/Orders/Customers/Inventory/Leaderboard), a `MetricUnit`
(Currency/Count/Percentage/Ratio), and a `MetricCalculation` kind
(Sum/Count/Derived/Leaderboard/Gauge/Placeholder):

Gross Revenue, Revenue (net), Refund Amount, Orders, Paid Orders,
Refunds, Returns, Average Order Value, Conversion Rate (**placeholder**
— spec section 14 explicitly defers this, no storefront visit tracking
exists yet), New Customers, Returning Customers, Repeat Purchase Rate,
Lifetime Value, Inventory Value, and five Top-N leaderboards (Products,
Categories, Collections, Discounts, Shipping Methods).

Percentage metrics (`repeat_purchase_rate`) are stored in basis points,
matching M18's `CustomerMetric.return_rate_bps` precedent, so all money-
and-percentage-adjacent storage in this codebase stays integer.

## 5. Dashboards and widgets

A store's first `GET /analytics/dashboard` call auto-creates its
`is_default` dashboard — no seeding step required. Each
`DashboardWidget` has a `type` (`line_chart`/`bar_chart`/`pie_chart`/
`metric_card`/`table`/`leaderboard`), a `title`, and a `config` JSON
blob (`metric_key` + an optional `time_dimension` override).

`WidgetDataResolver::resolve()` is the one function every widget type
reads from: it sums (or, for a gauge metric, takes the latest of) the
relevant `AnalyticsSnapshot` rows across the resolved time range and
returns `{ total, series[], breakdown }` — `series` feeds line/bar
charts, `breakdown` (merged leaderboard JSON across the range) feeds
pie charts, leaderboards, and tables.

**Drill down**: every widget can call
`GET /analytics/widgets/{widget}/drill-down`, which paginates the raw
`AnalyticsEvent` rows behind that widget's metric and time range —
`DRILL_DOWN_EVENT_TYPES` maps each metric key to its primary event
type (e.g. `gross_revenue` → `OrderPaymentConfirmed`). This is the one
read path that returns individual events rather than aggregates,
answering "which orders make up this number."

## 6. Reports

`RunReport::handle()` (`MAX_ROWS = 1000`) builds a `Report` row
synchronously against nine `ReportType`s: Orders, Products, Customers,
Inventory, Shipping, Payments, Returns, Promotions, Automation
Executions. Most types go through a generic `simpleReport()` mapper
(one row per matching `AnalyticsEvent` in the filtered range); Products
and Customers get dedicated builders that group/aggregate across
events (line items summed per product; orders summed per customer).
`filters` (currently `from`/`to`) and `columns` are stored on the
`Report` row itself, so a report's exact parameters remain inspectable
after the fact.

A `SavedReport` is just a named, reusable `{report_type, filters,
columns}` triple — running one creates a normal `Report` row with
`saved_report_id` set, not a separate execution model.

## 7. Time dimensions

`TimeDimension`: Today, Yesterday, Last 7 Days, Last 30 Days, Month,
Quarter, Year, Custom. `TimeRangeResolver::resolve()` is the single
place every dimension becomes a concrete `[from, to]` `Carbon` pair —
used identically by widget data, drill-down, and report filters, so
"last 30 days" means the same thing everywhere in the product.

## 8. Export

Three formats (`ExportFormat`: Csv/Xlsx/Pdf), each a small hand-rolled
writer under `App\Domain\Analytics\Support\Export` — no
PhpSpreadsheet/reporting-library dependency, matching this codebase's
standing preference for small hand-rolled solutions over a new library
for one narrow need (the same call `DeliverWebhookJob` made for its own
HMAC signing rather than a webhook SDK):

- **CSV** — `fputcsv()`, nothing else needed.
- **XLSX** — a genuinely valid single-sheet OOXML package built via
  `ZipArchive` (5 parts: content types, root rels, workbook, workbook
  rels, one worksheet), inline strings rather than a shared-strings
  table (simpler, one-pass, slightly larger file — a fine tradeoff at
  `RunReport::MAX_ROWS` scale). Independently verified byte-for-byte
  with Python's `zipfile`/`ElementTree`.
- **PDF** — a genuinely valid paginated PDF built from raw PDF object/
  xref syntax (`ROWS_PER_PAGE = 50`, Courier monospace). Independently
  verified by manually checked xref byte offsets.

`ExportReport::handle()` writes immediately to
`Storage::disk('local')` at `analytics-exports/{store}/{export}.{ext}`
for an unscheduled export (`ReportExportResource.download_url`
resolves as soon as the response comes back); a `scheduled_at` value
stores the request without generating anything yet — spec section 10
asks only for "scheduled export architecture," not a working scheduler,
so no cron/queue actually consumes `scheduled_at`/`recurrence` this
milestone.

## 9. Tenant isolation

Every entity uses `BelongsToTenant`, except the global
`MetricDefinition` catalog (deliberately not tenant-scoped, for the
same reason M19's `WorkflowVariable`/`WorkflowTemplate` catalogs
aren't — `BelongsToTenant`'s global scope would hide shared built-in
rows from every store). Verified in
`tests/Feature/Analytics/TenantIsolationTest.php`: one store's
dashboards/widgets/reports/exports/snapshots are invisible to another,
and the metric catalog is confirmed identical across stores (the
opposite property, tested on purpose).

## 10. Explicitly not implemented

Per spec section 18: AI-generated insights, predictive analytics/
forecasting, external BI tool integration, a ClickHouse or other
analytical-database migration, cross-store/multi-store dashboards, and
a working scheduled-export runner (architecture only — see §8).

# ADR-026: Analytics Platform — An Owned Event Projection Instead of Recomputation, Daily-Only Snapshot Granularity, Hand-Rolled Export Writers

## Status
Accepted

## Context

Milestone 20 adds dashboards, a report builder, and CSV/Excel/PDF
export, with one hard constraint (spec section 14): "Dashboard loads
must be fast" and must never run a synchronous aggregate query over
commerce tables — "snapshots/aggregations only." It must also serve
arbitrary time ranges (Today through a custom multi-year range),
support drill-down from any widget back to individual events, and
never let a widget query `orders`/`payments`/`refunds` directly (spec:
"Widgets must never query commerce tables directly").

Three design questions dominated the implementation: whether Analytics
should read commerce tables live (as every prior milestone's read side
does) or maintain its own copy; whether precomputed snapshots need
sub-day granularity to serve "Today"/"Last 7 Days" accurately; and
whether export (XLSX/PDF in particular) justifies a new Composer
dependency.

## Decision 1: `AnalyticsEvent` — Analytics owns a normalized projection, not live reads of commerce tables

**Options considered:**

1. Compute every metric on demand by querying `orders`, `payments`,
   `refunds`, etc. directly (or through Eloquent scopes), matching
   M18's `CustomerMetric` recomputation discipline ("recompute from
   the current row state, never mutate incrementally").
2. Have `AnalyticsProjector` read the triggering commerce aggregate
   **once**, at projection time, and write a normalized
   `AnalyticsEvent` row; every metric/report/drill-down read
   afterward queries only `analytics_events`.

**Decision: option 2.** M18's recomputation discipline works because
`CustomerMetric` answers one bounded question (this customer's
lifetime stats) recomputed from a handful of that customer's own rows.
Analytics must answer unbounded-range questions ("gross revenue for
any 90-day window a merchant picks") across every order, refund,
return, and inventory adjustment a store has ever had — recomputing
that from live commerce tables on every dashboard load is exactly the
synchronous-aggregate-query performance failure spec section 14
explicitly forbids, and it would also violate "widgets must never
query commerce tables directly" by construction, since a widget read
would *be* a commerce-table query. Projecting once into Analytics' own
table means every downstream read (aggregation, drill-down, report
building) only ever touches `analytics_events`/`analytics_snapshots` —
the isolation the spec asks for isn't a coding convention enforced by
review, it's the only tables that exist to query.

## Decision 2: Snapshot granularity is daily-only; every coarser range sums at read time

**Options considered:**

1. Persist one `AnalyticsSnapshot` row per metric per time dimension
   (a "Today" row, a "Last 7 Days" row, a "Month" row, ...) — precompute
   every range a widget might ask for.
2. Persist one row per metric per **day** only; compute every coarser
   range (Last 7 Days, Month, Quarter, Year, a merchant's arbitrary
   custom range) by summing the relevant daily rows at read time.

**Decision: option 2.** Option 1 multiplies write volume by the number
of supported dimensions and, worse, doesn't actually solve custom
ranges — a merchant can pick *any* start/end date, so a fixed set of
precomputed dimension-rows can never fully cover the read side anyway;
some summation logic is unavoidable. Given that, precomputing only the
one granularity that composes into every other range (a day) and
summing at read time is strictly simpler: one write path regardless of
how many dimensions the UI later adds, no rollup-consistency problem
(a "Month" row silently drifting from the sum of its own days), and
summing 7-31 small indexed rows per widget load is cheap. The one
carve-out: **gauge metrics** (`inventory_value`) are never summed
across days — a point-in-time on-hand valuation summed across a range
would double- or triple-count the same inventory. `WidgetDataResolver`
takes the *latest* day's gauge reading instead, an explicit special
case rather than a silent wrong number.

## Decision 3: Hand-rolled XLSX/PDF writers, not PhpSpreadsheet/a reporting library

**Options considered:**

1. Add `phpoffice/phpspreadsheet` (or a comparable PDF library like
   `dompdf`/`mpdf`) as a new Composer dependency for correct,
   fully-featured XLSX/PDF generation.
2. Hand-roll minimal-but-genuinely-valid writers: raw OOXML via
   `ZipArchive` for XLSX, raw PDF object/xref syntax for PDF.

**Decision: option 2.** This codebase has an established, deliberate
pattern of hand-rolling a narrow, well-understood file format instead
of pulling in a library for one feature — `DeliverWebhookJob`'s own
HMAC signing over an SDK being the clearest precedent. Report exports
here have a genuinely narrow requirement (one flat sheet/one simple
paginated table, up to `RunReport::MAX_ROWS` rows, no formulas, no
styling, no multi-sheet workbooks) that a full spreadsheet/PDF engine
is significant overkill for. `PhpSpreadsheet` in particular pulls in a
large transitive dependency tree for capabilities (formulas, charts,
rich styling, multiple sheet types) this feature will never use. The
cost is real and accepted: both writers had to be built carefully
enough to produce byte-correct output (verified independently — the
XLSX via Python's `zipfile`/`ElementTree`, the PDF via manual xref
offset verification) rather than "close enough that Excel guesses
correctly."

## Consequences

- Analytics carries genuine data duplication: every relevant commerce
  fact now exists twice (once in its owning domain's tables, once
  projected into `analytics_events`). This is an accepted, intentional
  cost of the isolation the spec requires, not an oversight — the
  alternative (live commerce-table reads) was already ruled out by
  Decision 1.
- A bug in `AnalyticsProjector` that projects a wrong or missing value
  is now a silent, permanent skew until a `analytics:rebuild-snapshots`
  backfill is run — there is no "read the source of truth and notice
  the discrepancy" fallback, since the projection *is* the source of
  truth for every downstream read. `RebuildAnalyticsSnapshotsCommand`
  exists specifically as that recovery path.
- Because gauge metrics are excluded from range-summing, any *future*
  gauge-kind metric must be added to that special case explicitly in
  `WidgetDataResolver` — a new gauge metric that's forgotten there
  would silently (and incorrectly) get summed like a flow metric.
- The hand-rolled export writers cover today's narrow requirement
  (flat tabular data) well but would need real, non-trivial work to
  grow multi-sheet, styled, or chart-embedding exports — a deliberate
  bet that this feature's needs stay narrow, matching the spec's own
  "CSV/Excel/PDF" (not "richly formatted Excel workbooks") framing.

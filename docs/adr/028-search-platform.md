# ADR-028: Search Platform — Unconditional Default Provider, Live-Resolved Facet Labels, Bounded Candidate Window, Fixed Ranking Weights, Admin-Only Reindex

## Status
Accepted

## Context

Milestone 22 adds Search, Filtering, Faceted Navigation, Autocomplete,
Merchandising, Ranking, and Search Analytics, explicitly architected so
a real external engine (Meilisearch/Typesense/OpenSearch/Elasticsearch)
can later replace `DatabaseSearchProvider` without any caller changing —
"The entire platform must depend only on the SearchProviderContract."

Five design questions dominated the implementation: whether
`DatabaseSearchProvider` should be environment-gated like the fake
Payment/Notification providers or always registered; whether
`category_ids`/`collection_ids` should be denormalized as text into
`SearchDocument` or resolved live at read time; how to bound the work a
single search request can do against a large catalog; whether ranking
weights should be merchant-configurable this milestone; and whether
`POST /search/reindex` belongs on the storefront surface as spec section
14 literally lists it.

## Decision 1: `DatabaseSearchProvider` is registered unconditionally, not environment-gated

**Options considered:**

1. Mirror `FakeNotificationProvider`/fake Payment providers: register
   only when a config flag (e.g. `config('search.database.enabled')`)
   is set, treating it as a test/dev harness.
2. Register `DatabaseSearchProvider` unconditionally in
   `SearchServiceProvider::boot()`, with no gate.

**Decision: option 2.** The fake Notification/Payment providers are
gated because they are explicitly *not* real implementations — sending
a real email or charging a real card through them would be wrong in
production. `DatabaseSearchProvider` is different: spec section 1
states "The default implementation must be DatabaseSearchProvider,"
meaning it is the intended production search experience for every store
that hasn't (or never will) configure a real external engine — not a
placeholder. Gating it behind a flag would make search silently
non-functional by default, the opposite of what "the default
implementation" means.

## Decision 2: Facet labels (category/collection) are resolved live, never denormalized as text into `SearchDocument`

**Options considered:**

1. Store `category_titles`/`collection_titles` (text) directly on
   `SearchDocument` alongside the existing `category_ids`/
   `collection_ids`, updated by `BuildSearchDocument` like every other
   field.
2. Store only IDs on `SearchDocument`; `SearchFacetBuilder` resolves
   `Category`/`Collection` labels live, at read time, from the id list
   a search's facet counts produced.

**Decision: option 2.** A category or collection's title changing is a
`CategoryUpdated`/`CollectionUpdated` Platform Event — but per §4 of the
architecture doc, those events deliberately do *not* trigger a product
reindex, because doing so for every category/collection rename would
mean reindexing every product in that category, a needless amplification
for a field that's cheap to resolve at read time instead (a store has a
handful to a few hundred categories/collections, not thousands). Storing
only IDs means a rename is reflected in facets immediately, with zero
reindexing, at the cost of one extra (small, indexed) lookup per facet
build.

## Decision 3: Candidate window, not full-catalog ranking

**Options considered:**

1. Fetch every filtered, matching document from the provider (no
   window), rank/sort/merchandise the complete set in PHP.
2. Fetch a bounded top-N candidate window from the provider
   (`CANDIDATE_WINDOW = 500`, ordered by `sales_count`/`created_at` as a
   base relevance proxy), then rerank/merchandise only within that
   window.

**Decision: option 2.** Option 1 has unbounded work per request — a
popular unfiltered query against a 50,000-product catalog would rank
and sort 50,000 rows in PHP on every request, the exact "table scan on
every request" spec section 16 rules out. The tradeoff is honest and
documented, not hidden: for a sort *other than* relevance-adjacent ones
(e.g. `PriceAsc`) on a query matching more than 500 products, the
window's own truncation (ordered by sales/recency, not price) means the
true lowest-priced match across the *entire* filtered set is not
guaranteed to be inside the 500 candidates reranked. A real search
engine (the kind `SearchProviderContract` exists to make swappable in
later) would not have this limitation — its own index supports
efficient sort-native pagination without a PHP-side rerank step at all.

## Decision 4: Ranking weights are fixed constants, not merchant-configurable, this milestone

**Options considered:**

1. Add a `SearchRankingSettings` row (or fields on `SearchSettings`)
   exposing `popularity_weight`/`sales_weight`/`availability_bonus`/
   `freshness_max_bonus`/`freshness_window_days` as merchant-editable
   values, with an admin "Ranking" page to tune them.
2. Keep `SearchRankingEngine`'s weights as `final class` constants; the
   merchant's actual lever for influencing ranking is `SearchRule`
   (boost/hide/position), already spec'd and built for §10.

**Decision: option 2.** Spec section 11 lists the ranking *factors*
(text relevance, manual boost, pinned position, popularity, sales,
availability, freshness) but does not ask for merchant-tunable weights
— and section 10's `SearchRule.boost_amount` already gives a merchant a
real, working lever to push a specific product up for a specific
keyword (or globally), which is the actual merchant-facing need a
"Ranking" admin page would otherwise exist to serve. Building a second,
overlapping configurability surface (numeric weight sliders nobody asked
for) for factors that are inherently platform-wide heuristics, not
per-product merchandising decisions, would be speculative scope beyond
what's specified. This is why the admin UI has no standalone "Ranking"
page — see architecture doc §15 and `navigation.ts`'s own "no fake
pages" rule.

## Decision 5: `POST /search/reindex` is admin-only, not on the storefront, despite spec section 14

**Options considered:**

1. Implement literally per spec section 14's own "Storefront API"
   listing: expose `POST /search/reindex` on the public, unauthenticated
   storefront route group alongside `GET /search` etc.
2. Implement it only on the tenant-scoped, Sanctum-authenticated admin
   API (`SearchIndexController`), and document the deviation.

**Decision: option 2.** An unauthenticated, publicly-reachable endpoint
that triggers a full chunked reindex of a store's entire catalog is a
real, low-cost DoS/operational-load vector — any anonymous visitor could
repeatedly trigger expensive reindex jobs with no rate limit or
legitimate customer-facing reason to ever call it (customers never
initiate reindexing; only merchants and internal event-driven jobs do).
Every other capability spec section 14 lists under "Storefront API"
(`GET /search`, `/suggestions`, `/facets`, `/popular`, click/conversion
recording) has a genuine customer-facing purpose; reindexing does not.
Treating the spec's placement as a literal requirement over an obvious
security tradeoff would be following the letter of the spec against its
own evident intent.

## Consequences

- A future real provider (the first of `SearchProvider::FUTURE_CODES` to
  ship) registers into the same always-on `SearchProviderRegistry`
  `DatabaseSearchProvider` already occupies — no `SearchServiceProvider`
  change needed beyond adding the new registration alongside it.
- `SearchFacetBuilder`'s live category/collection lookups add a small,
  indexed read to every facet-enabled search — an accepted, minor cost
  in exchange for zero-reindex renames (Decision 2).
- The 500-candidate window (Decision 3) is a documented, real limitation
  on non-relevance sorts over very large result sets — flagged in the
  architecture doc's "Known limitations" section rather than hidden; a
  future provider swap removes it entirely, since a real engine sorts
  natively.
- `SearchRankingEngine`'s constants (Decision 4) mean "make ranking
  configurable" is a real, identifiable future milestone if a merchant
  need for it ever materializes — not silently foreclosed, just not
  built speculatively now.
- Decision 5's admin-only reindex is a deliberate, documented deviation
  from a literal reading of spec section 14 — any future spec update
  that revisits this should explicitly re-evaluate the DoS tradeoff
  rather than assume the original placement was an oversight.

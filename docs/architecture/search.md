# Search & Discovery Platform

## 1. Overview

Milestone 22 adds a full search, filtering, faceted-navigation, and
merchandising layer — not just keyword search. Per spec, it is built
provider-agnostic from the start: the entire platform depends only on
`SearchProviderContract`, with `DatabaseSearchProvider` as the sole
registered implementation this milestone. A future Meilisearch/
Typesense/OpenSearch/Elasticsearch provider is a matter of implementing
the contract and registering it — no caller anywhere else in the
platform changes. No AI/vector/semantic search, no real external search
engine, no recommendations, and no personalization are implemented this
milestone (see §13/§14).

Core entities, all under `App\Domain\Search`:

| Entity | Purpose |
|---|---|
| `SearchProvider` | A store's configured instance of a provider `code` (only `database` is actually wired up — see §2). |
| `SearchSettings` | One row per store — active provider, results-per-page, autocomplete limit, and per-feature toggles (typo tolerance/synonyms/facets). |
| `SearchIndex` | One row per store — index status/document count/last-(re)indexed bookkeeping. |
| `SearchDocument` | The one denormalized, indexed representation of a product — the only thing search/facet/autocomplete reads ever query (see §3). |
| `SearchQuery` | A raw log of one search request — also the source of truth for popular/zero-result searches. |
| `SearchSuggestion` | A materialized, refreshed-not-live cache of popular queries for autocomplete. |
| `SearchSynonym` | A term + its OR-alternatives, optionally bidirectional. |
| `SearchRule` | A boost or hide action for a product, optionally scoped to a keyword. |
| `SearchAnalyticsEvent` | A click or conversion attributed to a search. |
| `PinnedSearchResult` | A product that always wins the top slot(s) for an exact keyword. |

## 2. Provider architecture

`SearchProviderContract` (`code()`, `index()`, `bulkIndex()`, `delete()`,
`search()`, `suggestProducts()`) is the provider-neutral boundary.
`SearchProviderRegistry` is a boot-time singleton populated in
`SearchServiceProvider`, mirroring `PaymentProviderRegistry`/
`NotificationProviderRegistry` exactly.

`DatabaseSearchProvider` (`code = 'database'`) is the default reference
implementation (spec: "The default implementation must be
DatabaseSearchProvider") — it queries `SearchDocument` directly with SQL,
no external engine. Its `index()`/`bulkIndex()`/`delete()` are
deliberate no-ops: for this provider, the `SearchDocument` row itself
*is* the index, already written by `BuildSearchDocument`/
`RemoveSearchDocument` before the contract is ever called. A real remote
provider (Meilisearch etc.) would instead push the document to its own
engine inside those methods. Unlike the fake Payment/Notification
providers, `DatabaseSearchProvider` is registered unconditionally — it
is a real production feature this milestone, not a test harness (see
ADR-028 Decision 1).

`SearchProvider::FUTURE_CODES` lists the spec's future providers
(`meilisearch`, `typesense`, `opensearch`, `elasticsearch`) — selectable
in the admin Search Settings page as catalog placeholders, but resolving
one throws `UnknownSearchProviderException` since none is registered.

## 3. Index model

`SearchDocument` is indexed by `(store_id, product_id)` and carries
title/slug/description/vendor/product_type/tags/collection_ids/
category_ids/variant_option_values/price_min/price_max/currency/
availability/inventory_quantity/status/is_searchable/thumbnail_url/
popularity/sales_count/search_score/search_text/product_created_at/
product_updated_at. Every field a search result or facet displays lives
here — "Never search directly from Product models" is enforced by
construction: `BuildSearchDocument` is the *only* place `Product`/
`ProductVariant`/`InventoryLevel`/`Collection`/`Category` are read for
search purposes, and every other class in this domain reads
`SearchDocument` only.

`category_ids`/`collection_ids` are denormalized as IDs only, never as
text — `SearchFacetBuilder` resolves their labels live against
`Category`/`Collection` at read time (see ADR-028 Decision 2), so a
renamed collection is reflected immediately without a reindex.

`search_text` is the normalized (lowercased, accent-stripped)
concatenation of every searchable field, built once at index time by
`SearchTextNormalizer::normalize()`.

## 4. Indexing pipeline

Incremental indexing reuses Platform Events (M11) through the outbox's
fifth subscriber, `SearchIndexingSubscriber`. `ProductCreated`/
`ProductUpdated`/`VariantUpdated`/`PriceChanged`/`VisibilityChanged`
dispatch `IndexProductJob` (queued, tenant-scoped); `ProductDeleted`
dispatches `RemoveProductFromIndexJob`; `InventoryChanged` resolves the
owning product through the changed `InventoryItem` and reindexes it.
`CollectionUpdated`/`CategoryUpdated` deliberately do **not** trigger a
reindex — since collection/category labels are resolved live (§3), only
their own membership-changing events (`ProductUpdated`, fired by
attach/detach) need to touch `SearchDocument`.

Every Catalog/Collections write path that can affect a `SearchDocument`
now fires the corresponding event — `CreateProduct`, `UpdateProduct`,
`DeleteProduct`, `CreateProductVariant`, `UpdateProductVariant`,
`DeleteProductVariant`, `AttachProductToCollection`/
`DetachProductFromCollection`, `AttachProductToCategory`/
`DetachProductFromCategory`, `CreateCollection`/`UpdateCollection`/
`DeleteCollection`, `CreateCategory`/`UpdateCategory`/`DeleteCategory`.

Merchant-triggered reindexing (`ReindexStore`) supports both **Full**
(chunked 100 at a time over every product, including soft-deleted, with
stale `SearchDocument` rows for products no longer present removed) and
**Partial** (a given list of product IDs) — both admin-only, see §14's
storefront-vs-admin note.

`BuildSearchDocument::upsert()` catches `UniqueConstraintViolationException`
and retries as a plain `update()` — `updateOrCreate()` alone is a
SELECT-then-INSERT-or-UPDATE, not atomic, and two concurrent reindex
passes for the same product could otherwise crash on the
`(store_id, product_id)` unique constraint (see
`tests/Concurrency/SearchDocumentConcurrencyTest.php`).

## 5. Search features

`DatabaseSearchProvider::search()` performs full-text, prefix, and
contains matching, case- and accent-insensitive by construction (both
the query and the indexed `search_text` are run through the same
`SearchTextNormalizer`). `SearchTypoTolerance::correct()` is a
Levenshtein-distance (max 2) fallback against a bounded per-store
dictionary, applied only when the literal query returns zero results.
`SearchTextNormalizer::transliterate()` is a documented no-op seam for a
future script-to-script mapping (spec: "transliteration-ready
architecture").

Results are paginated and sortable (§7). A bounded candidate window
(`ExecuteSearch::CANDIDATE_WINDOW = 500`) is fetched from the provider
before merchandising/ranking/pagination run — see ADR-028 Decision 3 for
the tradeoff this creates on non-relevance sorts over very large result
sets.

## 6. Faceted navigation

`SearchFacetBuilder` enriches the provider's raw facet counts with
vendor/product_type/availability/tags (value + count), category/
collection (id + live-resolved label + count), variant_options
(option + value + count), and price (min/max). Facets are computed over
the same filtered query the results themselves come from, so counts
never drift from what's actually shown.

## 7. Sorting

`SearchSortOption`: Relevance (default, provider score + ranking, §9),
Newest, Oldest, PriceAsc, PriceDesc, BestSelling, MostViewed
(future-ready — no page-view tracking exists yet, so this currently
falls back to `popularity`, itself mirroring `sales_count`), Manual
(`search_score`, merchant-settable via future tooling).

## 8. Autocomplete

`SearchSuggestionEngine::suggest()` returns products (via the active
provider's `suggestProducts()`), collections, categories (simple prefix
lookups against their own small tables), and popular queries. Popular
queries come from the **materialized** `SearchSuggestion` cache, refreshed
by `RefreshSearchSuggestions` (`search:refresh-suggestions`) — never a
live aggregation on every keystroke (spec section 16: "No table scans on
every request"). Each store's `SearchSettings.autocomplete_limit`
bounds every list.

## 9. Synonyms

`SearchSynonym` (`term`, `synonyms[]`, `is_bidirectional`, `locale`)
is expanded by `SynonymExpander::expand()` into **per-word OR-groups** —
`["tv"]` becomes `[["tv", "television"]]`, one group per original query
word, each holding that word's alternatives. Groups are OR-matched
internally and AND-required against each other
(`DatabaseSearchProvider::applyTextMatch()`), which is the only sound
semantics: "tv" and "television" never both literally appear in the
same document, so requiring both would make the synonym useless.
`correctTokens()` (typo tolerance) corrects only the primary
alternative of each group, replacing that group with a single
corrected alternative — a documented simplification that avoids
re-running synonym expansion on an already-corrected word.

As of Milestone 26, `locale` is live: `SynonymExpander::expand()`
accepts the current request locale (via `LocaleContext`, injected into
`ExecuteSearch`) and only matches synonym rows where `locale` is
`null` (locale-agnostic) or equal to the current locale — see
[localization.md](localization.md#6-search-locale-awareness) and
ADR-032. Stemming and language-specific text analysis remain out of
scope; `SearchTextNormalizer::stripAccents()` still silently discards
non-Latin-transliterable characters (e.g. Cyrillic), a pre-existing
(Milestone 22) gap tracked in `technical-debt.md`.

## 10. Merchandising

`SearchRule` (boost/hide, optional `keyword` — null means every search,
`boost_amount`, `position`) and `PinnedSearchResult` (keyword + product +
position) both execute in `ExecuteSearch`, explicitly **after**
`$provider->search()` and **before** pagination (spec section 10,
verified literally by the method's own step order: provider search →
merchandising → ranking → pagination). A pinned product always occupies
its slot regardless of score, ranking, or any boost/hide rule.

## 11. Ranking

`SearchRankingEngine::score()` layers additive factors on top of the
provider's own text-relevance/boost score: popularity (×0.01), sales
count (×0.05), an availability bonus (+20), and a freshness bonus (up to
+10, decaying linearly over 30 days). These weights are fixed constants
this milestone — no merchant-facing UI edits them (see ADR-028
Decision 4); the admin "Rules & Ranking" page is instead where a
merchant influences ranking, through boost/hide/position.

## 12. Search analytics

`SearchQuery` logs every search (including zero-result ones — no
separate "zero result" event type exists; `SearchQuery.result_count = 0`
already fully captures it). `SearchAnalyticsEvent` captures the
downstream funnel: `ResultClicked` and `Converted` (explicit,
non-heuristic — the storefront must report which `search_query_id` led
to which order/product; there is no session-based inference). Both also
integrate with the Analytics Platform (M20): `SearchPerformed` and
`SearchResultClicked` Platform Events feed three new metrics
(`search_count`, `zero_result_search_count`, `search_click_count`) via
`AnalyticsProjector`/`MetricCalculator`. `SearchAnalyticsSummary` backs
the admin Search Analytics page (total searches/clicks/conversions,
CTR, conversion rate, top 10 popular searches, top 10 zero-result
searches).

## 13. Recommendation architecture (interfaces only)

Per spec: "Do NOT implement recommendations. Only create interfaces for
future." `App\Domain\Search\Support\Recommendations` defines
`RelatedProductsProviderContract`, `AlsoBoughtProviderContract`,
`RecentlyViewedProviderContract`, `TrendingProductsProviderContract`,
and `FrequentlyBoughtTogetherProviderContract` — each a single-method
interface with zero implementations registered anywhere.

## 14. HTTP API

**Storefront** (public, tenant-resolved by hostname):
`GET /search`, `GET /search/suggestions`, `GET /search/facets`,
`GET /search/popular`, `POST /search/click`, `POST /search/conversions`.
`GET /search/facets` reuses `ExecuteSearch` with `perPage: 1` rather
than a second query path, so facet counts can never drift from
`GET /search` itself.

**Admin** (Sanctum-authenticated, tenant-scoped): full CRUD for
synonyms/rules/pinned-results/providers, settings show/update, index
show + reindex, analytics, and a "try a search" preview.

`POST /search/reindex` is deliberately **not** exposed on the storefront
despite spec section 14 listing it there — an unauthenticated reindex
trigger is a real operational/DoS risk with no legitimate customer-facing
use. It lives only under the admin-authenticated `search-index/reindex`
route (see ADR-028 Decision 5).

## 15. Admin UI

Search Dashboard (index status + Full reindex + "try a search"
preview), Synonyms, Rules & Ranking (a single page — see §11), Pinned
Products, Search Settings (behavior toggles + provider management),
Search Analytics. There is no separate "Ranking" page: `SearchRule`'s
own `position` column *is* how a merchant manually orders/boosts
results, so it lives on the same page as Rules rather than a
page with nothing distinct to show (navigation.ts's own "no fake pages"
rule).

## 16. Performance

Indexing is incremental by default (one product reindexed per relevant
event) and chunked (100 at a time) for full reindexes. All indexing runs
in queued jobs (`IndexProductJob`/`RemoveProductFromIndexJob`), never
inline on the request that changed the product. Popular-query
autocomplete reads a materialized cache, never a live aggregation.
Facet/candidate queries are bounded (`CANDIDATE_WINDOW`) rather than
scanning a store's entire catalog on every request.

## 17. Tenant isolation

Every entity in this domain uses the platform's standard
`BelongsToTenant` trait; every read scopes to `TenantContext`. Search
results, synonyms, rules, pinned results, settings, index status, and
analytics are all verified to never leak across stores (see
`tests/Feature/Search/TenantIsolationTest.php`).

## 18. Known limitations (explicitly not implemented)

- No AI/vector/semantic search.
- No Meilisearch/Typesense/OpenSearch/Elasticsearch integration —
  `DatabaseSearchProvider` is the only registered provider.
- No recommendations (interfaces only, §13).
- No personalization.
- Ranking weights (§11) are fixed constants, not merchant-configurable.
- The candidate-window (§5) means non-relevance sorts (e.g. PriceAsc) on
  a catalog exceeding 500 matches for a given query may not reflect the
  true global order — a real engine would not have this limitation (see
  ADR-028 Decision 3).

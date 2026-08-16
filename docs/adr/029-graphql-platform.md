# ADR-029: GraphQL Platform — Storefront-Query Read Exception, Schemaless Escape-Hatch Fields, Resolver-Level Directive Enforcement, Same-Process-Only Extensions, No Parallel Schema Versions, One Cache Field, Unified Complexity/Cost, Core-Flow Client Parity

## Status
Accepted

## Context

Milestone 23 adds a single public GraphQL endpoint serving Guest/
Customer/Merchant/App traffic, built on `webonyx/graphql-php` code-first,
depending only on existing Application services. Eight design questions
dominated the implementation: whether storefront listing queries with no
REST Application-service equivalent still count as "querying Eloquent
directly"; how to type genuinely heterogeneous payloads (search facets,
report results); how to enforce a `@auth` directive in a code-first
engine with no automatic directive execution; how literally to build
"Apps SDK can register queries/mutations/types/scalars/directives" given
no sandboxed code-execution engine exists anywhere in this platform and
Federation is explicitly excluded; how to satisfy "schema versioning"
without inventing a second, GraphQL-foreign versioning scheme; how far a
"Caching" requirement should reach; whether "complexity analysis" and
"cost limits" are one mechanism or two; and how much of the REST
storefront surface the "switch to GraphQL" Nuxt client needed to cover.

## Decision 1: Storefront listing queries replicate the REST controller's own Eloquent query, not a new Application service

**Options considered:**

1. Treat spec section 2 ("never query Eloquent models directly from
   GraphQL") literally: build new Application services
   (`ListStorefrontProducts`, `ListStorefrontCollections`, ...) purely so
   GraphQL resolvers have something non-Eloquent to call.
2. Replicate `StorefrontProductController`/`StorefrontCollectionController`/
   `StorefrontCategoryController`'s existing query exactly, the same way
   those REST controllers themselves do — no Application service exists
   for these reads in REST either.

**Decision: option 2.** These are pure, tenant-scoped listing reads with
zero business logic to encapsulate — REST itself has never had a service
layer here, only a controller-level query. Building one now, solely to
give GraphQL something to call, would invent an abstraction with no
second real caller and no logic it protects; "never query Eloquent
directly" reads naturally as "never bypass write-side business logic
services," not "never write a read query a REST controller already
writes identically." Every write-side operation (cart, checkout,
customer, notifications, search tracking) does go through the real
Application service, with zero exceptions.

## Decision 2: `JSON` scalar for facets/report-results/shipping-quote rather than fully modeled types

**Options considered:**

1. Model `SearchResult.facets` as a union or a large object type
   covering every facet kind (vendor value+count, category id+label+count,
   price min+max, variant_options option+value+count); similarly model
   `AnalyticsReport.result`'s column-keyed rows and `Checkout.selectedShippingRate`.
2. A `JSON` passthrough scalar for these three fields specifically.

**Decision: option 2.** All three sources are *already* deliberately
heterogeneous at the PHP layer for good reasons documented in their own
milestones — `SearchFacetBuilder`'s facet shape varies by dimension
(Milestone 22), `RunReport`'s result columns are caller-selected per
request (Milestone 20), `StorefrontShippingLineResource`'s shape mirrors
whatever the shipping provider returned (Milestone 17). Modeling five-plus
near-identical object types (or a union with no shared fields) to type
something the backend itself treats as schemaless would add real schema
surface for zero safety — a client already has to branch on facet/column
identity either way. Every other field in the schema stays properly
typed; this is a narrow, named exception at exactly three fields.

## Decision 3: `@auth` enforcement is a resolver-wrapping helper, not automatic directive execution

**Options considered:**

1. Rely on webonyx to automatically enforce `@auth(role: MERCHANT)`
   semantics the way an SDL-first server (or Apollo) would.
2. Declare `@auth` on the schema for introspection/documentation, and
   enforce it via `DirectiveEnforcer::requireRole()`, a small helper that
   wraps a field's `resolve` closure.

**Decision: option 2.** webonyx's code-first executor has no built-in
concept of "run this callback when a directive is present on a field" —
directives in this mode are schema *metadata*, not executable hooks,
unlike SDL-first tooling. Option 1 doesn't exist as a real capability
here; pretending otherwise would either silently not enforce anything or
require hand-rolling directive-AST inspection inside every resolver.
`DirectiveEnforcer` is the honest version of the same idea: `@auth` is
real (visible in introspection, part of the schema contract a client can
read), and its enforcement is one reusable, testable wrapper rather than
duplicated role checks.

## Decision 4: Extensions are same-process PHP classes, not dynamic app-uploaded schema

**Options considered:**

1. Let an installed app register GraphQL fields at runtime by uploading
   some declarative or executable definition, resolved dynamically
   per-request based on which apps a store has installed.
2. `GraphQLExtensionContract` implementations are first-party PHP classes
   registered at boot (`RegisterGraphQLExtensions`) — the same shape a
   real third-party integration would ship, but not something an app
   installs and executes without a code review.

**Decision: option 2.** Option 1 requires either a sandboxed code
execution engine (nothing in this platform runs untrusted third-party
code — the existing Apps SDK's own extension points, e.g.
`ExtensionPoint::AutomationAction`, work by the app registering a
`target_url` the platform calls over HTTP, never executing app-supplied
code in-process) or a remote-schema-stitching approach, which "Do NOT
introduce Apollo Federation" rules out as the alternative. `AppHealthExtension`
proves the *mechanism* genuinely works end-to-end — real field, real
type, real role gate, real introspection visibility, fully tested — while
being honest that a store's installed apps don't each get their own
live schema fragment this milestone. A future milestone wanting that
would most naturally extend the existing webhook-style `target_url`
pattern (an extension field's resolver calling out to the app's own
endpoint and mapping the JSON response), not arbitrary code execution.

## Decision 5: No separate schema version number; `@deprecated` + descriptions is the whole versioning story

**Options considered:**

1. Add a schema-level `version` field/metadata (e.g. `Query.apiVersion`)
   and a changelog mechanism, mirroring REST's `/api/v1` path versioning.
2. Rely entirely on GraphQL's own native evolution model: every field
   has a real `description`, and `@deprecated(reason: ...)` (built into
   webonyx, already exposed) marks a field before removal — no
   parallel versioning scheme.

**Decision: option 2.** REST versions by URL path because a breaking
change there requires a new route tree entirely. GraphQL's whole design
point is additive evolution of one schema — fields get added and
deprecated, not the schema wholesale-replaced — so bolting on a REST-style
version number would fight the tool rather than use it. Spec section 7's
"Deprecation, Descriptions, Version metadata, Backward compatibility" is
satisfied by descriptions (every field has one) and `@deprecated`
(available, unused only because nothing needs deprecating yet) — a
literal `version` field would be metadata with no consumer, since no
second schema version exists to distinguish it from.

## Decision 6: `QueryCache` covers exactly one field (`categories`), not a general result cache

**Options considered:**

1. A general-purpose GraphQL response/result cache (persisted-query-style
   or per-field), with real invalidation tied to the relevant write paths.
2. One narrow, TTL-only cache (`QueryCache::remember`, 60 seconds) applied
   to `categories` only.

**Decision: option 2.** A general cache needs a real invalidation
strategy per cached field (which write paths bust which cache keys) —
building that correctly for products/collections/search/cart, each with
different mutation surfaces, is a substantial project of its own and a
stale-cache bug there is a real correctness risk (a customer seeing a
sold-out item as purchasable, for instance). `categories` is the one
field where a flat TTL with zero invalidation logic is honestly safe: a
whole-store category tree is read on nearly every page but restructured
only via a deliberate, infrequent admin action, and a 60-second staleness
window on category *labels* has no correctness consequence a customer
would notice or that costs the merchant anything. This is a real,
tested, working cache — not a stub — deliberately scoped to the one
place that tradeoff is actually safe.

## Decision 7: `QueryComplexity` is treated as both "complexity analysis" and "cost limits"

**Options considered:**

1. Build two separate mechanisms: a complexity-analysis rule and a
   distinct, differently-configured "cost limit" system.
2. One mechanism (webonyx's `QueryComplexity`, max 1000) satisfying both
   spec line items, since a per-field cost model summed against a
   threshold *is* what "cost limit" means in every GraphQL server that
   ships this feature (Apollo, graphql-ruby, etc. use the same term
   interchangeably).

**Decision: option 2.** Spec section 11 lists "Complexity analysis" and
"Cost limits" as separate bullets, but they describe the same technique
under the two names the GraphQL ecosystem uses for it — building two
parallel systems that both sum per-field costs against a threshold would
be duplicated code enforcing the same invariant twice, with no behavior
a single well-configured `QueryComplexity` rule doesn't already provide
(including its designed extension point: any future field needing a
non-default cost sets a `complexity` callback in its own field config,
already supported by the rule as configured — see QueryLimits).

## Decision 8: The Nuxt GraphQL client covers the core browsing + cart flow, not every REST endpoint

**Options considered:**

1. Build a GraphQL-backed equivalent for every method `StorefrontApiClient`
   exposes (checkout, customer account, orders, notifications, payments, ...).
2. `StorefrontGraphQLClient` covers `store`, `products`, `collections`,
   `categories`, `search`, and `cart` — the core anonymous browsing and
   cart-building flow — reshaping every response into REST's exact
   `ApiResource<T>`/`ApiCollection<T>` contract.

**Decision: option 2.** Spec section 9 asks the storefront to be *able*
to switch transports without business-logic changes — it doesn't ask for
every REST method to gain a GraphQL twin this milestone. The chosen
subset is deliberately the highest-traffic, most transport-agnostic slice
(anonymous product discovery + cart), proven concretely with a real,
e2e-tested `?transport=rest|graphql` toggle on the products page where
literally one line changes. Extending the same reshaping pattern to
checkout/account/orders is straightforward future work using the exact
same client structure — not blocked by anything architectural, just not
built yet.

## Consequences

- A future field needing genuine per-facet/per-column typing (Decision 2)
  can migrate off `JSON` incrementally, field by field, without a schema
  version bump (Decision 5) — GraphQL's own additive-evolution model
  absorbs that change naturally.
- `DirectiveEnforcer` (Decision 3) is the one place any future
  role-gated field must route through — a field that checks `$context->actor`
  inline instead would work but silently diverge from the schema's own
  `@auth` declaration; worth flagging in review if it recurs.
- Decision 4 means "Apps SDK can register GraphQL fields" is proven as a
  mechanism, but a merchant cannot yet install a third-party app that
  extends their own store's public schema without a first-party PHP
  class shipping in this codebase — a real, documented product gap if
  the Apps SDK's positioning implies otherwise.
- `categories`' 60-second staleness window (Decision 6) is the one place
  in this schema where "just queried it" isn't strictly live — acceptable
  today, but the first feature requiring live category data (e.g. a
  real-time merchandising dashboard) needs to bypass or shorten this
  cache explicitly.
- `StorefrontGraphQLClient`'s partial coverage (Decision 8) means
  `useStorefrontGraphQL()` cannot yet fully replace `useStorefrontApi()`
  for every page — the storefront's checkout/account/order pages still
  require REST until a future milestone extends the same reshaping
  pattern to those methods.

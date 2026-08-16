# Public GraphQL API + Developer Platform

## 1. Overview

Milestone 23 adds a public GraphQL API for headless commerce, built on
top of `webonyx/graphql-php` (code-first, not SDL-first — see §2).
Per spec: this is not a REST replacement. REST remains the internal/
admin API exactly as every prior milestone left it; GraphQL is the new
public storefront and developer surface, reachable at a single endpoint
that serves all four actor types (Guest/Customer/Merchant/App — §5)
rather than REST's many purpose-specific route groups.

Core components, all under `App\Domain\GraphQL`:

| Component | Purpose |
|---|---|
| `SchemaRegistry` | Assembles the single `Schema` from the three registries below. |
| `TypeRegistry` | Named-type registry (lazy factories) — every object/scalar/input type, including Apps SDK extensions. |
| `QueryFieldRegistry` / `MutationFieldRegistry` | Top-level `Query`/`Mutation` field registries, equally open to extensions. |
| `DataLoader` | Generic per-request batching engine (spec section 6). |
| `GraphQLAuthenticator` / `GraphQLContext` | Resolves the caller's identity once per request (§5). |
| `GraphQLExtensionRegistry` | Apps SDK extension point (§8). |
| `QueryLimits` | Depth/complexity/introspection limits + query timeout (§11). |

## 2. Architecture

Resolvers depend only on existing Application services — `ExecuteSearch`,
`AddCartItem`, `OpenCheckout`, `RegisterCustomer`, `UpdateNotificationPreference`,
`RunReport`, and so on, the exact same classes every REST controller
already calls. No GraphQL resolver contains business logic; each is a
thin argument-mapping + service-call + response-reshaping layer, mirroring
the REST controller for the same operation field-for-field (see each
`*Queries`/`*Mutations` class's own docblock for which REST controller it
mirrors).

The one narrow exception, documented per-case: a handful of storefront
*read* queries (`products`, `product`, `collections`, `collection`,
`categories`, `category`) replicate the exact Eloquent query
`StorefrontProductController`/`StorefrontCollectionController`/
`StorefrontCategoryController` already run, because — like those REST
controllers — no dedicated Application service exists for a plain,
tenant-scoped storefront listing (there's no business logic to
duplicate, only a query). "Never query Eloquent models directly from
GraphQL" (spec section 2) targets bypassing *write-side* business logic
and reusing an already-loaded relation — not inventing new read logic
where none existed. See ADR-029 Decision 1.

## 3. Schema

Every named type is registered into `TypeRegistry` as a lazy factory
(`RegisterGraphQLTypes`) so types can reference each other by name
inside their own `fields` closures without an eager-construction
ordering problem. Two custom scalars exist: `DateTime` (ISO-8601) and
`JSON` (a deliberate escape hatch for genuinely schemaless payloads —
`SearchResult.facets`, `AnalyticsReport.result`, `Checkout.selectedShippingRate`
— see ADR-029 Decision 2 for why these specific fields stay untyped
rather than modeled as a union of near-identical object types).

Public queries (spec section 3): `store`, `products`/`product`,
`collections`/`collection`, `categories`/`category`, `page`,
`navigation`, `search`/`searchSuggestions`, `cart`, `customer`,
`orders`/`order`, `notifications`/`notificationPreferences`, `analytics`
(merchant-only). Public mutations (spec section 4): cart
(`addCartItem`/`updateCartItem`/`removeCartItem`), checkout
(`openCheckout`/`updateCheckout`/`completeCheckout`), customer auth
(`registerCustomer`/`loginCustomer`/`refreshCustomerToken`/`logoutCustomer`/
`requestPasswordReset`/`resetPassword`), profile (`updateProfile`,
`createCustomerAddress`/`updateCustomerAddress`/`deleteCustomerAddress`),
notification preferences (`updateNotificationPreferences`,
`markNotificationRead`), search tracking (`recordSearchClick`/
`recordSearchConversion`).

Deprecation/versioning (spec section 7): every field carries a real
`description`; webonyx's built-in `@deprecated` directive is available
for any field a future milestone needs to retire — none is deprecated
yet, so none is marked. There is no separate version number in the
schema itself: GraphQL's own convention is additive, backward-compatible
evolution of one schema (add fields/types, deprecate before removing)
rather than parallel numbered schema versions — see ADR-029 Decision 5.

## 4. Resolvers

Every resolver receives `(root, args, GraphQLContext $context, ResolveInfo $info)`
— `$context` is the one value threaded through webonyx's `contextValue`
parameter, carrying the resolved actor/store/customer/user/installedApp
for the whole request (see §5). Object types use a type-level
`resolveField` callback (webonyx's `ObjectType::$config['resolveField']`)
rather than one closure per field, matching the existing codebase's
preference for one explicit `match()` over many near-identical small
closures.

## 5. Authentication

`GraphQLAuthenticator` is the single consolidation point for all four
REST auth guards, since GraphQL has no per-route middleware stack to
attach them to individually:

1. **Merchant** — `Auth::guard('sanctum')->user()`, then the `X-Store-Id`
   header + `StoreUser` active-membership check, exactly like
   `EnsureTenantContext`.
2. **App** — an `AppToken` bearer, exactly like `AuthenticateAppToken`;
   tenant comes from the token's own installed store, never a header.
3. **Customer** — hostname-resolved tenant (like every storefront REST
   route) plus a `CustomerAccessToken` bearer, exactly like
   `AuthenticateCustomerToken`.
4. **Guest** — hostname-resolved tenant, no bearer token at all.

A Sanctum personal-access-token string and an App/Customer token string
never collide (Sanctum requires an `{id}|token` prefix a plain
`hash('sha256', $bearer)` lookup can never match), so trying each guard
in sequence is safe. Resolution happens once, in `GraphQLController`,
*before* the GraphQL document is ever executed — an auth/tenant failure
is a real HTTP status (401/403/404/428), not a GraphQL `errors[]` entry,
matching how every REST guard already fails. Once resolved, the whole
document executes inside `TenantContext::scope($store, ...)`, so every
resolver's ambient tenant-scoped Eloquent query is correctly bounded —
the same mechanism REST middleware already provides, invoked once around
a GraphQL document instead of once around a REST action.

`@auth(role: MERCHANT)` is declared on the schema (introspectable) for
the one merchant-only query (`analytics`); webonyx's code-first executor
doesn't auto-enforce directive semantics the way an SDL-first server
would, so real enforcement is `DirectiveEnforcer::requireRole()`, a
resolver-wrapping helper (see ADR-029 Decision 3).

## 6. Extensions

Spec section 8: "Apps SDK can register Queries, Mutations, Types,
Scalars, Directives." `GraphQLExtensionContract` (`queries()`,
`mutations()`, `types()`) is the interface; `GraphQLExtensionRegistry`
is a boot-time singleton collecting instances, read by
`RegisterGraphQLExtensions` *after* the built-in registries are
populated. `AppHealthExtension` is a real, working reference
implementation — not a stub — registering one field (`appHealth`), one
type (`AppHealthStatus`), gated to the App actor, fully covered by
`tests/Feature/GraphQL/ExtensionTest.php` (including proving the field
and type are discoverable via real schema introspection, not just
hardcoded). See ADR-029 Decision 4 for why a truly dynamic,
app-uploaded-at-runtime schema (arbitrary third-party code executing
inside this process) is explicitly out of scope.

## 7. Performance

**DataLoader** (spec section 6) prevents N+1 across six entities. A
generic `DataLoader` engine (webonyx's `Deferred` pattern — collect
every sibling field's requested id during one resolution pass, dispatch
one batched query the moment the first `Deferred`'s executor runs, every
other sibling then reads the shared cache) backs `ProductLoader`,
`VariantLoader`, `CollectionLoader`, `CategoryLoader`, `CustomerLoader`,
`OrderLoader`. Five are wired into real fields with genuine GraphQL-only
N+1 risk (`Order.customer`, `OrderItem.product`/`variant`,
`Product.collections`/`categories` — see each field's own code comment
for why Eloquent eager-loading alone can't solve it, since the top-level
query resolver has no way to know in advance which nested fields a given
GraphQL document will ask for). `OrderLoader`'s batching is implemented
and unit-tested (`tests/Feature/GraphQL/DataLoaderTest.php`) but has no
resolver call site yet — an honestly-documented gap, not a hidden one.

Every DataLoader (and `CartCookie`, the per-request cart-identity holder)
is bound as a container singleton but explicitly `forgetInstance()`'d at
the top of every request in `GraphQLController` — under real PHP-FPM the
container is torn down every request anyway, but that guarantee doesn't
hold inside a single test method simulating several requests back to
back, nor under a long-lived process (Octane) — so it's enforced
explicitly rather than assumed.

**Caching**: DataLoader's per-request cache is the *within-request*
layer. `QueryCache` adds one *cross-request* cache, deliberately narrow
— only the `categories` query (a whole-store tree read on nearly every
page load, changing only on a deliberate admin edit), a flat 60-second
Redis TTL with no event-driven invalidation. See ADR-029 Decision 6 for
why this stays narrow rather than a general query-result cache.

## 8. Complexity limits

Spec section 11: complexity analysis, depth limits, cost limits, query
timeout. `QueryComplexity` (webonyx built-in, max 1000 — every field
defaults to a cost of 1) *is* both "complexity analysis" and "cost
limits" in this implementation — the same mechanism under two names in
the spec, not two parallel systems (see ADR-029 Decision 7).
`QueryDepth` caps nesting at 12 (Category's own recursive `children`
field is the one place a legitimate query could otherwise nest
arbitrarily deep). `DisableIntrospection` is added conditionally —
disabled in production, enabled in local/testing (spec section 10).
Query timeout uses `set_time_limit()` around the `GraphQL::executeQuery()`
call (10 seconds), the pragmatic PHP-native bound available without
async infrastructure this platform doesn't have.

## 9. Storefront (Nuxt) integration

Spec section 9: "Nuxt storefront should be able to switch from REST to
GraphQL without business logic changes. Keep both clients available."
`StorefrontGraphQLClient` (`packages/api-client/src/storefront-graphql.ts`)
exposes the *exact same* method names and return shapes as
`StorefrontApiClient`'s `store`/`products`/`collections`/`categories`/
`search`/`cart` namespaces — every GraphQL response is reshaped, once,
inside the client, into the identical `ApiResource<T>`/`ApiCollection<T>`
contract REST already returns. `apps/storefront/app/pages/products/index.vue`
demonstrates this concretely with a `?transport=rest|graphql` toggle: the
only line that changes is which client resolves `.products.list()` —
sort, pagination, and the template are unaware which transport served
the data (e2e-verified in `graphql-transport-toggle.spec.ts`). This
deliberately covers the core browsing + cart flow, not every REST
endpoint — see ADR-029 Decision 8 for the scope boundary.

## 10. Playground

Spec section 10: a developer GraphQL explorer at `GET /api/graphql/playground`
— a small, self-contained (no CDN dependency) query editor, gated off
entirely (`404`) outside local/testing via `graphql.playground_enabled`.

## 11. Tenant isolation

Every resolver runs inside `TenantContext::scope()` (§5) — the same
`BelongsToTenant` global-scope guarantee every REST endpoint already
relies on. `tests/Feature/GraphQL/AuthenticationAndTenantIsolationTest.php`
and `tests/Feature/GraphQL/CatalogQueryTest.php`'s own isolation test
verify products/search/store/customer-auth all stay correctly
store-scoped across guest, customer, merchant, and app actors.

## 12. Explicitly not implemented

Per spec section 15: no subscriptions, no Apollo Federation, no live
queries, no persisted queries, no GraphQL gateway. Additionally (scope
decisions, see ADR-029): no fully dynamic per-app-uploaded schema
extension (only same-process PHP extension classes), no general
query-result cache beyond `categories`, no merchant-configurable schema
versioning beyond standard field-level `@deprecated`.

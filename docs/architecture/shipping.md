# Shipping Architecture

Milestone 6 — Shipping Foundation + Fake Shipping Provider, extended by
the Fulfillment Core milestone (see
[fulfillment.md](./fulfillment.md) — `Shipment` now always ships against
a `ready` `Fulfillment`, not arbitrary Order quantities) and by the
FakeShippingProvider Hardening milestone (§12 below — realistic rates,
pickup points, the expanded async lifecycle, and dev-only failure
simulation). See [ADR-012](../adr/012-shipping-provider-abstraction.md)
for why the provider abstraction is shaped the way it is.

## 1. Shipping domain

A dedicated `App\Domain\Shipping` module — shipping logic never lives
inside Orders or Checkout, mirroring how Payments got its own module
rather than living inside Orders. Directory shape matches every other
domain (`Models`, `Enums`, `Application`, `Http\{Controllers,Requests,
Resources}`, `Contracts`, `Support`, `Infrastructure\Providers`,
`Exceptions`).

Core concepts and where they live:

```text
ShippingZone            Shipping\Models        merchant-defined destination group
ShippingZoneRegion      Shipping\Models        one country/region/postal match rule
ShippingMethod          Shipping\Models        a priced, provider-backed offering
ShippingMethodZone      Shipping\Models        explicit pivot (ShippingMethod <-> ShippingZone)
ShippingQuote           Shipping\Models        a checkout's selected, TTL'd rate
Shipment                Shipping\Models        a registered, tracked parcel
ShipmentItem            Shipping\Models        (Shipment, OrderItem, quantity) — partial shipment
TrackingEvent           Shipping\Models        append-only status history
ShippingWebhookEvent    Shipping\Models        webhook idempotency claim (no BelongsToTenant — see §7)
OrderShippingLine       Orders\Models           Order's immutable shipping snapshot (see §6)
ShippingRate            Shipping\Support        ephemeral, not persisted (see §3) — a calculated option
ShippingRateContext     Shipping\Support        destination + currency, passed into calculateRates()
ShipmentCreationResult  Shipping\Support        what a provider hands back from createShipment()
TrackingWebhookEvent    Shipping\Support        a parsed, provider-neutral webhook event
```

`OrderShippingLine` lives under `Orders\Models`, not `Shipping\Models` —
it's Order's own immutable snapshot, the same relationship `OrderItem`/
`OrderAddress` already have to Orders. `CompleteCheckout` (in
`Checkouts\Application`) creates it directly, exactly how it already
creates `OrderItem`/`OrderAddress`; this is the one place Shipping-owned
data flows into an Orders-owned table, and it's a read of `ShippingQuote`
plus a write of `OrderShippingLine`, never a write into Shipping's own
tables from outside the module.

## 2. Provider abstraction

See ADR-012. `ShippingProviderContract` defines `code()`,
`calculateRates()`, `createShipment()`, `cancelShipment()`,
`getTracking()`, `verifyWebhook()`, `parseWebhook()`.
`ShippingProviderRegistry` resolves a provider by its registry code
(currently only `fake`); an unresolvable code fails explicitly
(`UnknownShippingProviderException`) rather than falling through to a
default. `ShippingServiceProvider` registers `FakeShippingProvider` only
when `commerce.shipping.fake.enabled` — the provider genuinely does not
exist in the container when disabled, the same failure mode as a carrier
that was never implemented.

## 3. Rate flow

```text
Checkout's saved shipping address
  -> ResolveShippingZone            (destination -> best-matching active zone)
  -> ResolveAvailableShippingMethods (zone -> active methods attached to it)
  -> group methods by provider
  -> ShippingProviderContract::calculateRates() per provider group
  -> merge -> Collection<ShippingRate>
```

Orchestrated by `CalculateShippingRates`, called from
`GET /api/v1/storefront/checkout/shipping-rates`. Always live-recalculated
— nothing about a rate is ever cached or trusted from a prior response
(spec section 9). A `ShippingRate` is a plain value object, not a database
row — see §4 for when a rate actually gets persisted. If no zone matches,
no methods are attached to the matched zone, or every attached method's
provider is unregistered/disabled, the whole call fails with
`NoShippingMethodsAvailableException` (422, `no_shipping_methods_
available`) — a single, well-known error the storefront can render as
"no shipping options for this address," not a scattered set of empty-list
edge cases.

## 4. Zone matching

Deliberately simple (spec section 3) — not a worldwide tax/address engine.
`ResolveShippingZone` scores every active zone's regions against the
destination:

- `country_code` must match exactly (case-insensitive).
- `region`, if the zone row specifies one, must match exactly; a `null`
  region on the zone row matches any region in that country.
- `postal_code_pattern`, if specified, must be a prefix of the
  destination's postal code (e.g. `"101"` matches `"101000"`).

The *most specific* matching region wins: a postal-code-pattern match
outscores a region-only match, which outscores a country-only match.
Ties break on the zone's creation order (oldest first) — deterministic
without a merchant-facing priority field this milestone.

## 5. Quote lifecycle

A `ShippingRate` becomes a persisted `ShippingQuote` only when a checkout
actually selects one (`PATCH /api/v1/storefront/checkout/shipping`, backed
by `SelectShippingRate`) — spec section 5's "do not necessarily persist
every rate lookup." Selection re-runs `CalculateShippingRates` server-side
and matches the client's `(provider, service_code, shipping_method_id)`
against a *fresh* result; the price, name, and estimate written to the
`ShippingQuote` row always come from that fresh calculation, never from
anything the client sent (spec section 11). The quote gets an `expires_at`
(`commerce.shipping.quote_ttl_minutes`, default 15) and the checkout's
`shipping_quote_id`/`shipping_amount`/`total_amount` update in the same
transaction.

**Why a quote model instead of always-recalculating at completion** (spec
section 12's two policy options): a live rate isn't guaranteed stable
between selection and checkout completion (a merchant could deactivate the
method, a provider's real-world quote could expire) — a quote makes that
window explicit and boundeded via `expires_at`, and gives
`RevalidateShippingQuote` a single, cheap check (row lookup + status
checks) instead of a second live provider call on every checkout
completion, mirroring `CreatePayment` reading the Order's already-computed
total rather than re-deriving it.

`RevalidateShippingQuote`, called from `CompleteCheckout`, checks:
belongs to this checkout and store (`ShippingQuoteInvalidException`,
422), not expired (`ShippingQuoteExpiredException`, 409), currency
matches, and — if the quote references a `ShippingMethod` — that method
is still active. **Shipping selection is optional at completion**: a
checkout that never selected a rate completes with `shipping_amount = 0`
and no `OrderShippingLine`, exactly the behavior that existed before this
milestone. Making it mandatory was considered and rejected — it would
have been a Checkout behavior change outside this milestone's brief
("do not redesign Checkout... unless a concrete issue requires it"), and
would have broken every existing checkout-completion test that predates
shipping selection.

## 6. Order shipping snapshot

`OrderShippingLine` is `Order`'s immutable shipping record, created once
by `CompleteCheckout` (only when a quote was selected — see §5) directly
from the winning `ShippingQuote`'s fields, and never updated afterward —
the same reasoning as `OrderItem.product_title`/`unit_price_amount`: a
later `ShippingMethod` rename, price change, or deletion must never change
what a past order reports (spec section 14).

## 7. Shipment lifecycle

```text
pending -> ready -> created -> accepted -> in_transit -> out_for_delivery -> delivered
                                   \            \              \
                                    \            \-> delivery_exception <-/  (recoverable — see below)
                                     \-> failed / cancelled (from pending, ready, created, accepted, or in_transit)
```

`accepted` and `out_for_delivery` were added by the FakeShippingProvider
Hardening milestone (§12) to model a realistic carrier lifecycle instead
of jumping straight from `created` to `in_transit` to `delivered`.
`delivery_exception` is deliberately **not** terminal — a failed delivery
attempt (nobody home, access issue) is recoverable in real carrier
tracking, so it transitions back to `out_for_delivery` or `in_transit`,
in addition to `failed`/`cancelled`. `delivered`/`failed`/`cancelled`
remain the only true terminal states — no transition leaves them (spec
section 25: no `delivered -> in_transit` "correction" without a
documented policy, and none exists yet). Enforced by
`ShipmentStateMachine`, the only place a transition is allowed to happen,
mirroring `PaymentStateMachine`.

**Creation** (`CreateShipment`, spec section 18) is always a merchant
action taken after the Order is paid — nothing dispatches a shipment
automatically. It locks every referenced `OrderItem` row before checking
`(already-shipped quantity) + (requested quantity) <= ordered quantity`
(`OvershipmentException` if violated), which is what makes two concurrent
shipment-create requests for the same `OrderItem` safe (see
`tests/Concurrency/ShipmentConcurrencyTest.php`). Supports partial
shipment: one `OrderItem` may appear across multiple `Shipment`s. The
`Shipment` row is created `pending`, the provider's `createShipment()` is
called, then the row moves to `created` with the provider's tracking
details — the provider itself never moves the Shipment's status (same
division of responsibility as `PaymentProviderContract::createPayment()`).

**Cancellation** (`CancelShipment`) is a separate, synchronous,
merchant-initiated action — deliberately *not* routed through a simulated
webhook the way the fake provider's other test transitions are, since a
merchant cancelling a real shipment is a real action for any provider, not
a dev-only simulation of what a carrier would report.

**Every other transition** (`in_transit`, `delivered`, `failed`, a
carrier-initiated `cancelled`) arrives only via a provider webhook — see
§8. The dev-only fake shipment control page
(`FakeShipmentOutcomeController`, gated by `commerce.shipping.fake.
enabled` at both the route and controller level — spec section 40) drives
these by building a self-signed fake webhook and dispatching it through
the exact same handler a real carrier's callback would hit, so the "test
control" and "real webhook" code paths are the same path, not a parallel
shortcut.

## 8. Webhook flow

Mirrors `ProcessPaymentWebhook` exactly:

```text
POST /api/v1/shipping/webhooks/{provider}
  -> registry.resolve(provider)          (unknown provider -> 404, not 422 — see below)
  -> provider.verifyWebhook(request)     (bad signature -> 403)
  -> provider.parseWebhook(request)      (malformed -> 422)
  -> ProcessShippingWebhook::handle()
       -> replay-tolerance check (stale timestamp -> 422)
       -> claim/poll idempotency (ShippingWebhookEvent)
       -> resolve Shipment by (provider, external_shipment_id), withoutGlobalScopes
       -> TenantContext::scope(shipment's store)
       -> lock Shipment row, apply transition if valid, append TrackingEvent
       -> mark webhook event processed
```

No auth, no tenant middleware — a webhook arrives from outside the
platform entirely (spec section 22). Tenant resolution happens *inside*
`ProcessShippingWebhook`, from the `(provider, external_shipment_id)` ->
`Shipment` -> `store_id` mapping — never from anything in the webhook
payload itself (spec section 24). `ShippingWebhookEvent` deliberately does
not use `BelongsToTenant`, for the same reason `PaymentWebhookEvent`
doesn't: a webhook has no tenant yet when it needs to be claimed.

**Idempotency** reuses the exact claim/poll shape `PaymentWebhookEvent`
already established against its own `(provider, external_event_id)`
unique index — not a third generic system layered on top of
`IdempotencyKeyStore` (spec section 23: "do not create a third unrelated
idempotency system"). A duplicate delivery of the same event is a no-op;
an event id reused with a *different* payload is rejected
(`MalformedShippingWebhookPayloadException`); an out-of-order or
already-superseded transition (e.g. `in_transit` arriving after
`delivered`) is safely ignored *as a transition* but still recorded as a
`TrackingEvent` — the timeline shows every delivery it received, the
`Shipment.status` only reflects transitions the state machine allowed.

**Two deliberate corrections from Payments**, both because this is new
code, not existing behavior to preserve (see `docs/architecture/technical-
debt.md` TD-13/TD-35 for the equivalent Payments findings, left as debt
there rather than fixed, since fixing them would have been an unrelated
change):

- Unknown provider from the webhook URL path returns **404**, not 422 —
  a nonexistent provider code there is a nonexistent route resource, not
  a validation failure.
- Invalid signature returns **403**, not 401 — this route carries no
  Sanctum auth to collide with, and 403 ("credential presented and
  rejected") is the accurate code for a failed signature check.

## 9. Tenant isolation

Every Shipping table except `shipping_webhook_events` uses
`BelongsToTenant` — the same global-scope-plus-forced-`store_id`
guarantee every other tenant table has. `ShippingMethodZone` (the
`ShippingMethod` <-> `ShippingZone` pivot) is an explicit pivot *model*,
not a bare Eloquent `sync()`/`attach()` table — `sync()` writes the pivot
table directly and would bypass `BelongsToTenant`'s `creating()` hook
entirely, both losing `store_id` and (more importantly) making a
cross-store pairing possible if a caller ever supplied a same-tenant-
looking but actually-foreign id. Every write to this pivot goes through
`ShippingMethodZone::query()->create()`/`firstOrCreate()`, the same
pattern `CollectionProduct`/`ProductCategory` already established for
tenant-scoped many-to-many relationships in this codebase.

Cross-tenant relation pairs are explicitly tested, not just individually-
scoped resources (spec section 33) — see
`tests/Feature/Shipping/ShippingTenantIsolationTest.php`.

## 10. Inventory / fulfillment boundary

Superseded by the Fulfillment Core milestone — see
[fulfillment.md §4](./fulfillment.md#4-reservation-consumption--the-invariant-this-milestone-exists-to-define)
for the full `Order paid -> Fulfillment -> ready -> Shipment -> reservation
consumed` flow and its concurrency guarantees. What remains true and
worth restating here:

- **Shipping-rate selection never touches inventory** —
  `SelectShippingRate` only reads/writes `ShippingQuote` and `Checkout`
  rows.
- **`FakeShippingProvider` never touches Inventory tables at all** (spec
  section 20 of the Hardening milestone, §12 below) — it reports carrier
  lifecycle state (`created`/`accepted`/`in_transit`/.../`delivered`)
  through `ProcessShippingWebhook`, which only ever writes `Shipment`/
  `TrackingEvent` rows. Reservation consumption is driven entirely by
  `Fulfillment\Application\CompleteFulfillment` (called from
  `CreateShipment`, same transaction as Shipment creation) — Shipping
  reports state, Fulfillment/Inventory own stock semantics, and that
  boundary is intentionally one-directional: Shipping code has no
  `Inventory*` model imports anywhere in the module.

## 11. What's deliberately not implemented

Per spec section 45: no real carrier (CDEK, Russian Post, Boxberry,
Yandex Delivery) and no real outbound HTTP calls to one, and this
constraint was reaffirmed (not relaxed) by the Hardening milestone in
§12 below — hardening `FakeShippingProvider` explicitly does **not**
mean integrating a second, real provider, nor does it mean building a
second fake provider alongside it; no returns, refunds, discount engine,
or taxes; the fake provider's own management surface is dev/test-only,
double-guarded at both the route and controller level
(`commerce.shipping.fake.enabled`) so it is never reachable in a
production configuration by default.

## 12. FakeShippingProvider hardening

`FakeShippingProvider` is the platform's permanent reference
implementation of `ShippingProviderContract` — realistic enough to
exercise every real-world carrier behavior (variable pricing, pickup
networks, async multi-step delivery, webhook failure modes) without ever
calling out to a real carrier. See also
[docs/development/fake-shipping.md](../development/fake-shipping.md) for
the developer-facing walkthrough of the dev control page and simulated
failure modes.

### 12.1 Rate algorithm

All pricing lives under `commerce.shipping.fake.services.{standard,
express,pickup}` — no magic numbers scattered through
`FakeShippingProvider`. For a `ShippingMethod` whose `service_code`
matches a configured service:

```text
price = base_price_amount + ceil(billable_weight_kg) * price_per_kg_amount
price *= 1 + international_surcharge_percent/100   (if destination country != domestic_country_code)
price *= 1 + rate_markup_percent/100
```

A `ShippingMethod` with a `service_code` that isn't in
`commerce.shipping.fake.services` falls back to its own flat
`price_amount` unmodified — an intentional escape hatch so a merchant
can still define a custom fake-provider service without the weight/
destination logic applying to it.

**Weight**: `ShipmentWeightCalculator` computes actual weight (sum of
`ProductVariant.weight` × quantity), volumetric weight (`(length * width
* height) / commerce.shipping.fake.volumetric_divisor` per unit, industry-
standard "dimensional weight"), and billable weight = `max(actual,
volumetric)`, aggregated across every cart/order line. **Frontend-
supplied weight is never trusted** — rates are always recomputed
server-side from the cart's/order's own `ProductVariant` rows (spec
section 3). **Missing-weight policy**: a variant with no `weight` set
contributes `0kg`, not an error — documented here rather than silently
implicit, since treating a missing weight as a hard failure would break
every product that predates this milestone.

### 12.2 Pickup-point architecture

`ShippingProviderContract::listPickupPoints(ShippingRateContext)` returns
a deterministic, provider-neutral `Collection<PickupPoint>`.
`FakeShippingProvider`'s implementation filters a static list configured
at `commerce.shipping.fake.pickup_points` (id/name/address/city/
country_code/postal_code/opening_hours/lat/lng) by country match against
the destination — the fake network is RU-only by design, so a non-RU
destination legitimately gets zero points back, not an error.

Selection flow (`SelectShippingRate`, spec section 6):

```text
Checkout selects a Pickup-service rate + pickup_point_id
  -> SelectShippingRate re-runs calculateRates() AND listPickupPoints()
     fresh against the checkout's saved address (never trusts a
     frontend-supplied point)
  -> pickup_point_id must appear in that fresh listPickupPoints() result
     (PickupPointInvalidException / 422 `invalid_pickup_point` otherwise)
  -> the matched point is snapshotted into ShippingQuote.metadata.pickup_point
  -> CompleteCheckout copies that same snapshot into
     OrderShippingLine.metadata.pickup_point (spec section 18: the
     snapshot must never depend on current provider config, so it's a
     plain array copy, not a live re-lookup)
```

The storefront never invents a point id — `StorefrontShippingRateResource`
curates exactly one field out of the otherwise-hidden `ShippingRate.
metadata` (`pickup_points`, spec section 17), and the checkout page
renders that array as a second radio group, gated behind having already
selected the Pickup-service rate.

### 12.3 Async shipment lifecycle & webhook flow

Extends §7/§8 above with the fuller state machine (`created -> accepted
-> in_transit -> out_for_delivery -> delivered`, plus the recoverable
`delivery_exception`). Every non-`created`, non-cancel transition still
arrives exclusively through `ProcessShippingWebhook` — including when
it's the dev control page that triggered it:

```text
Dev control click (FakeShipmentOutcomeController::outcome)
  -> FakeShippingProvider builds a self-signed payload
     (HMAC-SHA256, commerce.shipping.fake.secret)
  -> dispatched through the *same* POST /api/v1/shipping/webhooks/fake
     endpoint a real carrier's callback would hit (app()->call(), not a
     direct Shipment::update()) — spec section 11
  -> provider.verifyWebhook() / parseWebhook() / ProcessShippingWebhook
     (identical to §8's flow)
```

**Delayed delivery** (spec section 14): the same dev action, with
`delayed: true`, dispatches `SimulateFakeShippingWebhookJob`
(`ShouldQueue`) with a per-status delay from
`commerce.shipping.fake.delayed_lifecycle` instead of calling the webhook
endpoint synchronously — tests assert this via `Queue::fake()` +
`Queue::assertPushed(..., fn ($job) => ...)`, never a real sleep/wait.

**Idempotency and out-of-order handling** are unchanged from §8 — a
duplicate `event_id` is a no-op (proven by the dev control page's "Send
duplicate webhook" action), and an out-of-order transition (e.g.
`delivered` already recorded, then an `in_transit` arrives) is rejected
*as a transition* by `ShipmentStateMachine::canTransition()` but still
recorded as a `TrackingEvent` — the append-only timeline shows every
delivery the endpoint received, never mutated after the fact.

### 12.4 Failure simulation

`commerce.shipping.fake.failure_simulation.enabled` gates a set of
deliberately-triggerable failures (rate calculation failure/timeout,
shipment creation failure/timeout, invalid webhook signature, out-of-
order webhook, duplicate event) — **double-gated**: the config flag is
checked both by the request validation (so the option isn't even offered
in production) and independently inside `FakeShippingProvider` itself
(so a config flag flip alone, without a code change, can never
accidentally make failure simulation reachable through some other path).
Allowlisted to `local`/`testing` environments the same way
`commerce.shipping.fake.enabled` is — see §13 below.

### 12.5 Fulfillment/Inventory boundary re-verified

Re-confirmed (not re-derived) for this milestone: `FakeShippingProvider`
has no `Inventory*`/`Fulfillment*` model imports; `CreateShipment` still
requires shipping against a `ready` `Fulfillment` (Fulfillment Core,
§10 above) with `quantity <= fulfilled/ready quantity`, unchanged by this
milestone. Shipping only ever reports carrier state.

## 13. Production safety

- `commerce.shipping.fake.enabled` defaults to `in_array(APP_ENV, ['local',
  'testing'])`, never a plain `env()` boolean read directly — the same
  pattern `payments.fake.enabled` uses. `ShippingServiceProvider` does not
  register `FakeShippingProvider` in the container at all when disabled;
  resolving it fails the same way an unimplemented real carrier would.
- The fake-shipment dev-control routes (`fake-shipments/{id}`,
  `.../outcome`, `.../invalid-signature`) are gated by that same config
  flag inside the controller, independent of the route registration —
  they 404 in any environment where the flag is off, including a
  misconfigured production deploy that somehow still registered the
  routes.
- `commerce.shipping.fake.failure_simulation.enabled` is a second,
  independent flag on top of the above — failure-simulation triggers are
  invisible even in an environment where the fake provider itself is
  enabled but this second flag isn't (e.g. a shared staging environment
  that wants realistic-but-not-chaotic fake shipping).
- The admin Shipment Detail page renders its entire "Fake provider
  controls" section behind `import.meta.dev` (build-time dead code
  elimination in a production Nuxt build, not a runtime `if`) — never
  shipped to a merchant-facing production build regardless of the
  backend flags above.
- `commerce.shipping.fake.secret` is never returned by any API
  response — it exists only in backend config, used to sign/verify
  payloads server-side.

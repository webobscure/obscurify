# ADR-012: Shipping Provider Abstraction

## Status
Accepted

## Context
Milestone 6 needed a provider-independent way to price, register, and track
shipments, validated end-to-end with an internal fake carrier before any
real carrier (CDEK, Russian Post, Boxberry) is integrated — the same
problem Payments solved for payment gateways (`PaymentProviderContract` /
`PaymentProviderRegistry` / `FakePaymentProvider`, never itself given a
dedicated ADR, just documented in `ARCHITECTURE.md` §13). Shipping needed
its own decision recorded because the shape isn't identical: a carrier
also needs to *price* a shipment before anything is created (Payments
never quotes a price — the Order already has one), and shipment lifecycle
has more states than payment lifecycle.

## Options
1. Skip an explicit contract; call `FakeShippingProvider` directly from
   application services, generalizing later when a real carrier is added.
2. Mirror `PaymentProviderContract`'s shape exactly: an interface + a
   registry + a fake implementation, from the start.
3. Model the contract around a specific real carrier's API (e.g. CDEK's)
   now, to reduce rework later.

## Decision
Option 2. `ShippingProviderContract` (`calculateRates`, `createShipment`,
`cancelShipment`, `getTracking`, `verifyWebhook`, `parseWebhook`) +
`ShippingProviderRegistry` + `FakeShippingProvider`, registered only when
`commerce.shipping.fake.enabled` — the same environment-gated pattern as
`PaymentServiceProvider`. Option 3 was rejected explicitly (spec: "do not
design the contract around CDEK-specific concepts") — a contract shaped by
one carrier's quirks (numeric status codes, carrier-specific service
catalogs) would need to change shape the moment a second carrier with
different quirks is added, which defeats the point of an abstraction.
Option 1 was rejected because Payments already proved the contract-first
version works and costs little extra up front; retrofitting an interface
after a second real implementation exists is strictly more expensive than
writing it before the first one.

Two sub-decisions worth recording explicitly, since they diverge from
Payments' shape:

- **Rate calculation is provider-neutral but zone/method resolution is
  not part of the contract.** `ShippingProviderContract::calculateRates()`
  takes an already-filtered `Collection<ShippingMethod>` — zone matching
  (`ResolveShippingZone`) and method-availability filtering
  (`ResolveAvailableShippingMethods`) happen before a provider is ever
  consulted, in `CalculateShippingRates`. A provider only prices what it's
  told is eligible; it never decides eligibility itself. This keeps
  destination-matching logic in one place instead of duplicated inside
  every future provider adapter.
- **A shipping quote is persisted (`ShippingQuote`), a payment amount is
  not.** Payments always reads its price from the Order (already computed,
  never separately quoted). Shipping prices a rate *before* an order
  exists, so the selected rate has to be remembered somewhere between
  selection and checkout completion — see `docs/architecture/shipping.md`
  §Quote lifecycle for why a persisted, TTL'd quote was chosen over
  always-recalculating.

## Consequences
### Positive
- A second real carrier is additive: implement `ShippingProviderContract`,
  register it, done — no change to `CalculateShippingRates`,
  `CreateShipment`, `ProcessShippingWebhook`, or any controller.
- The fake carrier genuinely exercises the same code path a real one will
  (rate calculation, shipment creation, webhook-driven status transitions),
  so Milestone 6's "validate it end-to-end" goal is met by construction,
  not by a parallel test-only code path.
- Provider-specific status vocabularies never leak past `parseWebhook()` —
  `ShipmentStatus`/`TrackingEventStatus` stay a fixed, small, carrier-
  neutral vocabulary (spec section 16).

### Negative
- The contract's real shape is still a guess informed by one fake
  implementation, same caveat `PaymentProviderContract`'s own docblock
  states. `capturePayment`/`cancelPayment`/`refundPayment`'s no-op-shaped
  history on the payment side is the concrete precedent: expect
  `cancelShipment`/`getTracking` to need revisiting once a real carrier
  exists (see `docs/architecture/technical-debt.md` TD-8.4 for the
  analogous Payments finding, and the equivalent risk noted for Shipping
  in `docs/architecture/shipping.md`).
- Zone/method resolution living outside the contract means a future
  carrier with genuinely dynamic, real-time zone coverage (not a static
  merchant-configured zone table) doesn't fit this shape without a second
  resolution path. Not a problem for any of CDEK/Russian Post/Boxberry,
  which all work against merchant-declared zones; revisit if that changes.

## Security Requirements
- The fake provider's webhook HMAC secret (`SHIPPING_FAKE_SECRET`) has no
  hardcoded default and the app refuses to boot with the fake provider
  enabled and no secret set — the same guard added to `PaymentServiceProvider`
  after the architecture review's TD-1/TD-2 findings, applied here from day
  one rather than as a follow-up fix.
- `commerce.shipping.fake.enabled` defaults to an explicit `local`/`testing`
  allowlist, not a `!== 'production'` denylist — the same fail-closed
  correction, applied from day one.

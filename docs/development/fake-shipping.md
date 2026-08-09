# Developing against FakeShippingProvider

A practical guide to the dev-only fake shipping harness — see
[docs/architecture/shipping.md §12](../architecture/shipping.md#12-fakeshippingprovider-hardening)
for the architecture behind it. This page is about *using* it while
building storefront/admin features that touch shipping.

## Enabling it locally

`commerce.shipping.fake.enabled` defaults on for `local`/`testing`
`APP_ENV` — nothing to configure for a normal local setup. It is never
on by default outside those two environments; set `SHIPPING_FAKE_ENABLED=true`
(and a real `SHIPPING_FAKE_SECRET` — see `.env.example`, which ships
`SHIPPING_FAKE_SECRET` pre-filled for local dev) if you need to exercise
it in a non-local environment deliberately, e.g. a shared staging box.
`ShippingServiceProvider` refuses to boot whenever the provider is
enabled with an empty secret, in any environment, so this can't be
half-configured.

## Getting a shipment to play with

1. Seed a store with at least one active `ShippingZone` +
   `ShippingMethod` (see `SeedE2EStorefrontCommand` for a scripted
   example, or create both from the admin UI: Shipping Zones → Shipping
   Methods).
2. For the **Pickup** service specifically, the fake provider's
   pickup-point network is RU-only
   (`commerce.shipping.fake.pickup_points`) — your zone needs to match a
   RU destination for `listPickupPoints()` to return anything.
3. Run a storefront checkout through to a paid order (cart → checkout →
   select a rate → place order → Fake Payment → "Pay successfully").
4. In admin, open the order, create a Fulfillment, allocate/pick/pack it
   (or accept the defaults), then "Create shipment (fake provider)". This
   lands you on `/shipments/{id}`.

## Driving the lifecycle

The Shipment Detail page's "Fake provider controls" section (visible
only in a dev build, i.e. `import.meta.dev` — never in a production
build) has three groups of buttons:

- **Lifecycle** — `Mark accepted` / `Mark in transit` / `Mark out for
  delivery` / `Mark delivered` / `Delivery exception` / `Fail shipment`.
  Each sends a real, self-signed webhook through
  `POST /api/v1/shipping/webhooks/fake` — the same endpoint and code
  path a real carrier's callback would hit. `delivery_exception` isn't
  terminal: after triggering it you can still transition onward (e.g.
  back to `out_for_delivery`), matching how a real carrier reports a
  recoverable failed delivery attempt.
- **Delayed (queued)** — the same `accepted`/`in_transit` transitions,
  but dispatched through the queue with a configured delay
  (`commerce.shipping.fake.delayed_lifecycle`) instead of processing
  immediately. Useful for seeing what an order looks like mid-flight
  before the next carrier update lands; requires a running queue worker
  (`php artisan queue:work`) to actually process, same as any other
  queued job in this app.
- **Developer actions** — exercises the webhook endpoint's own
  hardening, not something a real carrier would trigger on purpose:
  - **Send duplicate webhook** resends the last event's exact
    `event_id`. The tracking timeline must not grow — if it does,
    idempotency broke.
  - **Send invalid signature** sends a validly-shaped payload signed
    with a wrong secret. It must come back rejected
    (`invalid_webhook_signature`) — if it's accepted, the signature
    check broke.
  - **Send out-of-order webhook** re-sends an earlier lifecycle status
    after a later one already landed (e.g. `in_transit` after
    `delivered`). The transition must be ignored (the Shipment's status
    doesn't move) but a `TrackingEvent` is still recorded — the
    append-only timeline shows every delivery it received, not just the
    ones that changed state.

## Simulating provider failures

`commerce.shipping.fake.failure_simulation.enabled` (also
`local`/`testing`-only by default) unlocks additional triggers, mainly
exercised through automated tests rather than the UI:

- Rate calculation failure/timeout — trigger by requesting rates against
  a destination whose postal code is the sentinel `SIMFAIL-RATE` /
  `SIMFAIL-TIMEOUT`.
- Shipment creation failure/timeout — pass `simulate:
  "creation_failure"` / `"creation_timeout"` to `POST
  /fulfillments/{id}/complete` (only accepted when failure simulation is
  enabled; otherwise the field is silently ignored and the shipment
  creates normally — this is a deliberate double-gate, not a validation
  bug, see `ShippingFailureSimulationTest`).

Both flags are checked independently at the request-validation layer
*and* inside `FakeShippingProvider` itself — flipping only one without
the other is not enough to make failure simulation reachable, by design.

## Things this harness will never do

- Make an outbound HTTP call to any real carrier.
- Leak a fake-provider-specific status string through to `Order`/
  `Shipment` consumers — everything is mapped through
  `ShipmentStateMachine`'s own vocabulary first.
- Mutate `Inventory`/`Fulfillment` rows directly — it only ever reports
  carrier state through the normal webhook pipeline; reservation
  consumption is entirely Fulfillment's responsibility.
- Be reachable in a production build/config by default — every entry
  point (routes, controller, admin UI section) is independently gated.

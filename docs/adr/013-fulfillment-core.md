# ADR-013: Fulfillment Core — a Dedicated Domain Between Orders and Shipping

## Status
Accepted

## Context
Shipping Foundation (Milestone 6, see ADR-012 and docs/architecture/
shipping.md) deliberately left one thing undefined: *when a reservation
becomes consumed*. Its own §10 states the concrete pre-Milestone-7 reality
— stock is reserved at checkout and stays reserved through payment and
through shipment creation/delivery; nothing ever converts a reservation
into consumed `on_hand`. That gap needed an owner before real logistics
providers are integrated, because a real carrier integration will assume
"the warehouse actually pulled and packed this order" is a fact the
platform already tracks, not something bolted on afterward.

The milestone brief was explicit that Shipping and Fulfillment are
different concerns — "Shipping delivers packages. Fulfillment prepares
products for shipment." — and that Fulfillment must be independent from
Shipping, not an extension of it.

## Options
1. Extend `Shipment`/`ShipmentItem` with picking/packing fields and treat
   "shipment created" as the consumption trigger directly, no new domain.
2. A dedicated `Fulfillment` domain (Fulfillment, FulfillmentItem,
   FulfillmentAllocation, FulfillmentEvent) sitting between Order and
   Shipment, owning allocation and reservation consumption; Shipping
   depends on it (one-directional), not the reverse.
3. Model Fulfillment as a sub-resource of Order instead of Shipping,
   duplicating warehouse-state fields onto `OrderItem`.

## Decision
Option 2. A dedicated `App\Domain\Fulfillment` module, independent of
Shipping (zero imports from `App\Domain\Shipping` anywhere in the
Fulfillment domain), with its own state machine
(`FulfillmentStateMachine`: pending → allocated → picking → packing →
ready → completed, cancellable from any non-terminal state) and its own
timeline (`FulfillmentEvent`, mirroring `TrackingEvent`).

Shipping depends on Fulfillment, never the reverse: `Shipping\Application\
CreateShipment` now takes a `Fulfillment` (must be `ready`) instead of an
`Order` + raw line array, and — on success, in the same transaction — calls
`Fulfillment\Application\CompleteFulfillment` to consume the Fulfillment's
allocations. This is the one deliberate coupling point (spec: "Shipment
must reference Fulfillment"), and it's a downstream dependency in the
pipeline's own direction (Order → Fulfillment → Shipment →
ShippingProvider), not a circular one. Option 1 was rejected because it
would have re-coupled two concerns the milestone brief explicitly wanted
separated, and because `ShipmentItem` already has a settled meaning ("this
much of this OrderItem is on this parcel") that picking/packing progress
doesn't share. Option 3 was rejected because reservation consumption is an
inventory-adjacent decision, not an order-record one — `OrderItem` is
already documented as an immutable snapshot (spec: never mutated after
Order creation), which warehouse-progress fields would violate.

**Reservation consumption invariant** (spec section 7, now a settled
fact rather than shipping.md's open question): a reservation is consumed
— `InventoryLevel.on_hand` and `.reserved` both decrement, `InventoryReservation.status`
flips to `Consumed` once every unit of it has been claimed — at the moment
its Fulfillment's Shipment is successfully created (`CreateShipment` →
`CompleteFulfillment`, same transaction). Allocation itself (`POST
/fulfillments/{id}/allocate`) never touches `on_hand`/`.reserved` — it is
pure bookkeeping that claims *which* reservation rows back a Fulfillment,
so two concurrent Fulfillments can never claim the same reserved units
twice (see `tests/Concurrency/FulfillmentConcurrencyTest.php`).

**Allocation strategy** mirrors `ReserveInventory`'s own split-across-
locations approach for consistency: deterministic ordering (reservation
rows ordered by `location_id` then `id`), taking as much as each
reservation has left (its own quantity minus what's already allocated to
other, non-cancelled Fulfillments) until the requested quantity is
satisfied, erroring (`FulfillmentOvershipmentException`) if reserved
capacity runs out first. This reuses existing reservation semantics
exactly as instructed ("do not redesign Inventory") — `InventoryReservation`
gained no new columns; `FulfillmentAllocation` is the new bookkeeping
layer, referencing `inventory_reservation_id` so consumption always knows
which reservation to (partially) resolve.

## Consequences
### Positive
- Reservation consumption finally has a defined owner and a single
  trigger point, closing a real gap docs/architecture/shipping.md left
  open on purpose.
- Fulfillment genuinely doesn't need Shipping to function — allocate/pick/
  pack/cancel never touch a Shipping table — so a future alternate
  delivery path (e.g. in-store pickup) could reuse Fulfillment without
  inheriting Shipping's provider machinery.
- Partial fulfillment (spec section 11) falls out naturally: multiple
  Fulfillments per Order, each with its own allocation/consumption
  lifecycle, rather than a single Order-level "fulfilled" boolean.
- `Order.fulfillment_status` (Unfulfilled/Partial/Fulfilled — a column
  that existed since the Foundation milestone but nothing ever wrote to)
  is now genuinely maintained, recomputed from completed Fulfillments'
  quantities each time one completes.

### Negative
- One more domain to reason about in the Order → Payment → Fulfillment →
  Shipment pipeline; a merchant now performs two separate multi-step
  flows (fulfill, then ship) instead of one. Accepted as the accurate
  shape of a real warehouse operation, not incidental complexity.
- `CreateShipment`'s signature changed (`Fulfillment` instead of `Order` +
  lines) and the old `POST /orders/{order}/shipments` route was removed
  outright rather than deprecated — a breaking change for any caller of
  the pre-Milestone-7 API. Acceptable pre-any-real-carrier-integration,
  and required by the milestone brief ("do not create Shipment directly
  from Order anymore").

## Security Requirements
- Every Fulfillment table (`fulfillments`, `fulfillment_items`,
  `fulfillment_allocations`, `fulfillment_events`) uses `BelongsToTenant` —
  the same global-scope-plus-forced-`store_id` guarantee every other
  tenant table has; verified with dedicated cross-tenant tests
  (`tests/Feature/Fulfillment/FulfillmentTenantIsolationTest.php`), not
  just individually-scoped resources.
- Overshipment is checked under row locks at two independent points —
  against ordered quantity at Fulfillment creation, against reserved
  quantity at allocation — both enforced with `lockForUpdate()`, not
  read-then-write, per the milestone's concurrency requirement (spec
  section 13/19).

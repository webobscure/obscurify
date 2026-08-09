# Fulfillment Architecture

Milestone 7 — Fulfillment Core. See [ADR-013](../adr/013-fulfillment-core.md)
for why the domain is shaped the way it is (independent of Shipping,
Shipping depends on it — never the reverse).

## 1. Fulfillment domain

A dedicated `App\Domain\Fulfillment` module. Directory shape matches every
other domain (`Models`, `Enums`, `Application`, `Http\{Controllers,
Requests, Resources}`, `Support`, `Exceptions`).

```text
Fulfillment             Fulfillment\Models     one warehouse "pick list" against an Order
FulfillmentItem         Fulfillment\Models     (Fulfillment, OrderItem, quantity) — what this pick list covers
FulfillmentAllocation   Fulfillment\Models     exactly which InventoryReservation/Location backs a slice of an item
FulfillmentEvent        Fulfillment\Models     append-only timeline, mirrors TrackingEvent
FulfillmentStatus       Fulfillment\Enums      the entity's own 7-state workflow (not Order's 3-state rollup — see §2)
```

Multiple Fulfillments may exist per Order (spec section 2/11 — partial
fulfillment): one `OrderItem` can be split across several Fulfillments, as
long as the running total of *non-cancelled* Fulfillments' quantities per
`OrderItem` never exceeds what was ordered.

## 2. Two different "fulfillment status" concepts

`App\Domain\Fulfillment\Enums\FulfillmentStatus` (this domain, 7 states —
`pending`/`allocated`/`picking`/`packing`/`ready`/`completed`/`cancelled`)
is not the same thing as `App\Domain\Orders\Enums\FulfillmentStatus`
(`Order.fulfillment_status`, a 3-state rollup —
`unfulfilled`/`partial`/`fulfilled`). The Order's own column existed since
the Foundation milestone but nothing wrote to it until this milestone —
`CompleteFulfillment` now recomputes it from scratch (total ordered
quantity vs. total quantity across `completed` Fulfillments) every time a
Fulfillment completes, rather than incrementing it, so it stays correct
regardless of completion order.

## 3. State machine

```text
pending -> allocated -> picking -> packing -> ready -> completed
   \-> cancelled (from pending, allocated, picking, packing, or ready)
```

Enforced by `FulfillmentStateMachine`, mirroring `ShipmentStateMachine`/
`PaymentStateMachine` — no intermediate state is ever skipped by a caller
going through the guarded actions (spec section 3). One deliberate
divergence from Shipment/Payment's state machines: `AllocateFulfillment`
does **not** rely on the state machine's generic "same state is a no-op"
rule. Allocation has a real, non-idempotent side effect (it claims
reservation capacity), so a second `allocate` call against an
already-allocated Fulfillment is rejected outright
(`InvalidFulfillmentTransitionException`, 409) rather than silently
re-attempted and failing confusingly deep inside the allocation loop.

`packing -> ready` is the one transition with no dedicated admin endpoint
(spec section 15's endpoint list has no "mark ready" action) — it happens
automatically inside `PackFulfillmentItems` the moment every
`FulfillmentItem.packed_quantity` reaches its `quantity`. "Ready" isn't a
merchant decision, it's a fact that becomes true once packing is
genuinely complete.

## 4. Reservation consumption — the invariant this milestone exists to define

```text
Inventory
  -> Reservation           (ReserveInventory, at CompleteCheckout — unchanged)
  -> Payment                (verified webhook — unchanged)
  -> Fulfillment Allocation (AllocateFulfillment — claims reservation capacity, no stock movement)
  -> Shipment                (CreateShipment, against a `ready` Fulfillment)
  -> Reservation consumed   (CompleteFulfillment, same transaction as Shipment creation)
  -> Inventory Movement     (FulfillmentCompleted, on_hand -= quantity)
```

- Checkout completion does **not** decrease `on_hand` (unchanged from
  Foundation).
- Payment confirmation does **not** decrease `on_hand` (unchanged from
  Payment Foundation).
- **Allocation does not decrease `on_hand` or `.reserved`.** It only
  records, in `FulfillmentAllocation`, which `InventoryReservation` rows
  (and therefore which `Location`s) will eventually back this
  Fulfillment's items — a claim on stock that is already reserved, not a
  new reservation or a stock movement.
- **Consumption happens exactly once, at the moment a Shipment is
  successfully created against the Fulfillment** — `Shipping\Application\
  CreateShipment` calls `Fulfillment\Application\CompleteFulfillment` in
  the same DB transaction, right after the Shipment and its items commit.
  This is "the point stock has genuinely left the building," per
  `docs/architecture/shipping.md`'s own §10 recommendation for where this
  milestone should put the transition.
- A reservation may be split across multiple Fulfillments (partial
  fulfillment). `InventoryReservation.status` only flips to `Consumed`
  once the sum of *all* its allocations' consumed quantity reaches the
  reservation's own quantity — not on the first Fulfillment to touch it.

Real, row-locked concurrency tests back this — see
`tests/Concurrency/FulfillmentConcurrencyTest.php` (two Fulfillments
racing to allocate the same scarce reservation: exactly one wins, the
allocated total never exceeds what was actually reserved) and
`tests/Concurrency/ShipmentConcurrencyTest.php` (two simultaneous
shipment-create requests against the same ready Fulfillment: exactly one
succeeds, `on_hand` drops by the fulfilled quantity exactly once, never
double-consumed).

## 5. Allocation strategy

`AllocateFulfillment` splits each `FulfillmentItem`'s quantity across the
Order's own `InventoryReservation` rows for that `InventoryItem`,
deterministically — ordered by `location_id` then reservation `id`,
taking `min(remaining reservation capacity, remaining item quantity)` from
each in turn, the same split-across-locations approach `ReserveInventory`
already uses at checkout time (spec section 6: "reuse existing reservation
semantics... do not redesign Inventory"). "Remaining reservation capacity"
accounts for what other, non-cancelled `FulfillmentAllocation` rows have
already claimed against that same reservation — so two Fulfillments
drawing from the same reservation never oversubscribe it (enforced under
`lockForUpdate()` on the reservation rows, not read-then-write).

An `OrderItem` with no `product_variant_id`, or whose variant has no
tracked `InventoryItem`, has nothing to allocate — the same rule
`ReserveInventory` applies; `allocateItem` returns immediately, recording
no allocation and no movement for that item.

If the requested quantity exceeds what's actually reserved,
`FulfillmentOvershipmentException::exceedsReservedQuantity` (422) is
thrown — this is a *different* overshipment check from the one at
Fulfillment creation (§7 below), catching the narrower case where
reservations don't cover what a Fulfillment claims to want, even though
the claim itself didn't exceed what was ordered.

## 6. Warehouse workflow (picking and packing)

```text
Pending -> Allocate -> Picking -> Packing -> Ready -> Create Shipment
```

- **Picking** (`POST /fulfillments/{id}/pick`) records `picked_quantity`
  per `FulfillmentItem`, validated `picked <= quantity` under a row lock.
  The first call transitions `allocated -> picking`; subsequent calls are
  expected and safe (a merchant picks incrementally as items come off
  shelves) and don't re-trigger the transition or its `PickingStarted`
  outbox event.
- **Packing** (`POST /fulfillments/{id}/pack`) records `packed_quantity`,
  validated `packed <= picked_quantity`. Confirms correct quantity,
  correct items, package prepared — it never marks anything shipped (spec
  section 10). The first call transitions `picking -> packing`; once every
  item is fully packed, the Fulfillment auto-advances to `ready` (see §3)
  and a `PackingCompleted` outbox event fires.
- **Creating the Shipment** (`POST /fulfillments/{id}/complete`, backed by
  `Shipping\Application\CreateShipment`) is the only way from `ready` to
  `completed` — see §4.

## 7. Overship protection

Two independent checks, both under row locks, both required by spec
section 13:

1. **At Fulfillment creation** (`CreateFulfillment`): locks every
   referenced `OrderItem`, sums the quantity already claimed by
   non-cancelled Fulfillments for that item, rejects
   (`FulfillmentOvershipmentException::exceedsOrderedQuantity`, 422) if
   adding the new request would exceed what was ordered.
2. **At allocation** (`AllocateFulfillment`, see §5): locks the
   `InventoryReservation` rows, rejects
   (`FulfillmentOvershipmentException::exceedsReservedQuantity`, 422) if
   the Fulfillment's items want more than what's actually reserved.

A third, pre-existing check survives unchanged inside `CreateShipment`
itself: the total shipped quantity per `OrderItem` across all Shipments
still can't exceed what was ordered (the same lock-based guard Shipping
Foundation established) — now a second, independent safety net layered
under Fulfillment's own checks rather than the only guard.

## 8. Cancellation

`CancelFulfillment` (spec section 14):

- Releases the Fulfillment's open `FulfillmentAllocation` rows —
  `cancelled_at` is set, rows are never deleted (so the
  `FulfillmentAllocated` movements they drove stay in history), and their
  quantity becomes available again for a future Fulfillment attempt
  against the same reservation.
- Does **not** touch `InventoryReservation.status` or
  `InventoryLevel.reserved` — a cancelled-before-completion Fulfillment
  never consumed anything, so there's nothing to reverse; the underlying
  reservation stays exactly as reserved as it was before this Fulfillment
  ever claimed part of it.
- Does **not** cancel the Order — a merchant may simply be restarting with
  a different pick list.

**Cancelling a Shipment that already triggered consumption** is the
mirror case, handled in `Shipping\Application\CancelShipment`: if the
Shipment had reached `created` (or later) before being cancelled, its
Fulfillment's consumed allocations are reversed — `on_hand` restored,
recorded as a new `ShipmentCancelled` movement (never by editing the
original `FulfillmentCompleted` movement — history stays append-only).
`InventoryReservation.status` is deliberately left as `Consumed` in this
case rather than flipped back to `Active`, a known, documented limitation
(see §10).

## 9. Inventory movements

Four new `InventoryMovementReason` cases (spec section 8), all immutable,
none ever updated after creation:

| Reason | `quantity_delta` | Fires when |
|---|---|---|
| `FulfillmentAllocated` | `0` | An allocation is created — a bookkeeping/audit entry, not a stock change (allocation never moves `on_hand`/`.reserved` — see §4). |
| `FulfillmentCompleted` | `-quantity` | An allocation is consumed (`CompleteFulfillment`) — the real, physical stock decrease. |
| `ReservationReleased` | `0` | An allocation is released by `CancelFulfillment` — bookkeeping only, since allocation itself never moved stock. |
| `ShipmentCancelled` | `+quantity` | A Shipment that already consumed stock is cancelled (`CancelShipment`) — reverses `FulfillmentCompleted`'s decrease. |

`InventoryMovement`'s original set of reasons (`ManualAdjustment`,
`InitialStock`, `Import`, `ReturnStock`, `Damage`, `Correction`) already
represented the only writer of `on_hand` outside checkout's reservation
flow (`AdjustInventory`). This milestone extends the table's role from
"on_hand audit log" to "immutable record of every inventory-affecting
Fulfillment event" — zero-delta entries (`FulfillmentAllocated`,
`ReservationReleased`) are deliberate: the table's value here is a
complete audit trail, not exclusively a physical-count history.

## 10. Shipment relationship

`shipments.fulfillment_id` (foreign key, not nullable) — a Shipment must
reference the Fulfillment it ships (spec section 12). `CreateShipment`
changed signature from `(Order $order, string $provider, array $lines)` to
`(Fulfillment $fulfillment, string $provider)`: `$lines` are now derived
from the Fulfillment's own `FulfillmentItem`s (their `quantity`, i.e. what
packing already confirmed) rather than passed by the caller. The old
`POST /orders/{order}/shipments` route was removed outright (not
deprecated) — every remaining Shipment-creation path requires a `ready`
Fulfillment.

`Shipment.order_id` is kept (not removed) purely for query convenience —
every Shipment's Order is reachable via its Fulfillment too
(`shipment.fulfillment.order`), but keeping the direct foreign key avoids
an extra join on every Order-scoped Shipment listing.

**Known limitation** (deliberately not solved this milestone): cancelling
a Shipment after consumption reverses `on_hand` but does not flip the
underlying `InventoryReservation` back to `Active` (see §8) — a real
carrier integration will need a documented policy for whether a
cancelled-and-reversed shipment's stock becomes reservable again
automatically or requires a fresh Fulfillment/reservation cycle. Left
open rather than guessed at, matching this milestone's stated boundary:
"complete the internal order lifecycle... do not integrate real logistics
providers" — that policy question is a real-carrier concern, not an
internal-lifecycle one.

## 11. Tenant isolation

Every Fulfillment table (`fulfillments`, `fulfillment_items`,
`fulfillment_allocations`, `fulfillment_events`) uses `BelongsToTenant` —
no exception (unlike `shipping_webhook_events`/`payment_webhook_events`,
Fulfillment has no webhook surface, so there's no "tenant not yet known"
case to carve out). Verified with dedicated cross-tenant tests
(`tests/Feature/Fulfillment/FulfillmentTenantIsolationTest.php`) covering
every state-changing route, not just read endpoints.

## 12. What's deliberately not implemented

Per the milestone brief: no CDEK/Russian Post/Boxberry integration, no
returns, no refund workflow, no marketplace integrations. Also out of
scope, consistent with "complete the internal order lifecycle" rather than
extending it: reversing a cancelled-and-consumed Shipment's reservation
back to `Active` (§10's known limitation); any UI for merchants to choose
*which* Location to pick from (allocation is fully automatic/deterministic
this milestone, per §5 — no manual override); partial *cancellation* of a
single Fulfillment item (cancellation is whole-Fulfillment only).

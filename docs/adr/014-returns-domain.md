# ADR-014: Returns Domain — a Dedicated Reverse-Logistics Module

## Status
Accepted

## Context
Milestone 8's brief is explicit and repeated three times in different
words: this is not about refunds. No payment provider integration, no
refund amount computation, no courier return-label API. It is entirely
about the internal domain that has to exist *before* a refund can ever
be trusted — someone has to claim a return, the package has to physically
come back, someone has to verify what condition it's actually in, and a
decision has to be made about whether it can be resold. Until that chain
exists and is trustworthy, any refund logic built on top of it would be
refunding based on an unverified claim.

The milestone brief also names the exact concepts that must exist as
first-class entities: `ReturnRequest`, `ReturnItem`, `ReturnInspection`,
`ReturnDisposition`, `ReturnEvent`, `ReturnStatus` — and is explicit that
the module must be independent from Payments, Shipping, Fulfillment, and
Orders, reusing them "through well-defined boundaries" rather than living
inside any of them.

## Options
1. Extend `Order`/`OrderItem` with return-related fields and treat a
   return as an Order sub-resource, the same shape Fulfillment's option
   3 (rejected in ADR-013) would have taken.
2. A dedicated `App\Domain\Returns` module, independent of Payments/
   Shipping/Fulfillment/Orders, reading `OrderItem`/`ShipmentItem` to
   validate returnable quantity and writing `InventoryMovement` directly
   — the same boundary `Fulfillment\Application\CompleteFulfillment`
   already uses toward Inventory.
3. Model `ReturnRequest` as a new status a `Fulfillment` or `Shipment`
   can enter (e.g. `Shipment.status = 'returned'`), avoiding new tables
   entirely.

## Decision
Option 2 — directly following the precedent ADR-013 already set for
exactly this kind of question. A dedicated `App\Domain\Returns` module
with six models (`ReturnRequest`, `ReturnItem`, `ReturnInspection`,
`ReturnDisposition`, `ReturnEvent`, and the internal
`ReturnNumberSequence` locking primitive), its own state machine
(`ReturnStateMachine`: `requested -> approved -> awaiting_return ->
received -> inspection -> completed`, with `rejected`/`cancelled` as
additional terminal exits), and its own timeline (`ReturnEvent`,
mirroring `FulfillmentEvent`/`TrackingEvent`).

Option 1 was rejected for the same reason ADR-013 rejected it for
Fulfillment: `OrderItem` is documented as an immutable snapshot, and a
return's multi-step verification lifecycle (claimed condition -> physical
receipt -> verified condition -> disposition -> applied) has no natural
home on a row that's supposed to never change after Order creation.
Option 3 was rejected because a `Shipment`/`Fulfillment` reaching a
terminal "returned" state doesn't capture partial returns (one Shipment,
two different reasons, two different dispositions), doesn't capture
inspection detail, and would have made Shipping/Fulfillment depend on
Returns concepts — inverting the dependency direction every other
boundary in this codebase already established (downstream domains depend
on upstream ones, never the reverse; see ADR-012/013).

**Reused, not reimplemented, from three other domains**: returnable
quantity is derived from `ShipmentItem`/`Shipment.status` (Shipping) and
`OrderItem` (Orders) under a row lock, exactly the pattern
`CreateFulfillment`/`CreateShipment` already established for their own
overshipment checks. `CompleteReturn` writes `InventoryMovement`/
`InventoryLevel` directly — the same boundary `CompleteFulfillment`
already uses — rather than inventing a second stock-mutation pathway.
Nothing in `App\Domain\Returns` imports a `Payments`, `Shipping`, or
`Fulfillment` `Application` class; the dependency is strictly Returns ->
(Orders, Shipping models, Inventory), never the reverse.

**Inspection and disposition share one endpoint, not two** (spec section
13 lists a single `POST /returns/{id}/inspect`) — see
`docs/architecture/returns.md` §4 for the full reasoning: a
`ReturnDisposition` is *decided* at inspection time but only *applied*
to Inventory at `CompleteReturn`, keeping spec section 8's "inventory
changes happen only after inspection" literal (after, not as part of).

## Consequences
### Positive
- The domain that any future refund milestone needs already exists and
  is independently testable — a refund flow can react to
  `ReturnCompleted` (already emitted via `RecordOutboxEvent`) without
  Returns ever needing to know about `PaymentProviderContract`.
- Partial returns fall out naturally: multiple `ReturnRequest`s per
  Order, each with its own item-level reason/condition/disposition,
  rather than a single Order-level "returned" boolean.
- Damaged/discarded stock is structurally prevented from silently
  re-entering sellable inventory — `CompleteReturn`'s disposition
  `match` only ever increments `on_hand` for `restock`, and every other
  branch is either a zero-delta audit entry or writes nothing at all.
- Returnable-quantity validation composes with the existing Fulfillment/
  Shipping overshipment checks rather than duplicating their logic —
  Returns reads their already-committed state, it doesn't recompute
  "was this shipped" itself.

### Negative
- One more domain in the Order -> Payment -> Fulfillment -> Shipment ->
  **Returns** pipeline; a merchant now has a fifth multi-step flow to
  operate. Accepted as the accurate shape of real reverse logistics, not
  incidental complexity — the same tradeoff ADR-013 already accepted for
  Fulfillment.
- `CancelReturn` and its route are not literally named in spec section
  13's endpoint list — added anyway because `cancelled` is a required
  status (section 3) and every sibling domain (Fulfillment, Shipment)
  exposes a dedicated, state-machine-guarded cancel action rather than a
  bare status write through `PATCH`. A stricter reading of the spec
  could treat this as scope creep; it was judged a necessary consistency
  fix rather than a new feature, since leaving `cancelled` reachable only
  by contradiction (a status nothing can ever set) would have been the
  worse inconsistency.

## Security Requirements
- Every Returns table (`return_requests`, `return_items`,
  `return_inspections`, `return_dispositions`, `return_events`) uses
  `BelongsToTenant` — verified with dedicated cross-tenant tests
  (`tests/Feature/Returns/ReturnTenantIsolationTest.php`), not just
  individually-scoped resources.
- Returnable-quantity and disposition-application are both checked/
  applied under row locks (`lockForUpdate()` on `OrderItem` in
  `RequestReturn`, on `ReturnRequest` and `ReturnDisposition` in
  `CompleteReturn`) — proven under genuine concurrent PostgreSQL
  connections, not just sequential retries, per the milestone's
  concurrency requirement (spec section 16); see
  `tests/Concurrency/ReturnConcurrencyTest.php`.

# Returns Architecture

Milestone 8 — Returns & Reverse Logistics Core. See
[ADR-014](../adr/014-returns-domain.md) for why the domain is shaped the
way it is. **This milestone is explicitly not about refunds** — no
payment provider is touched, no money moves. It is entirely about the
physical reverse-logistics lifecycle: a customer/merchant claims a
return, the package comes back, it gets inspected, and a disposition
decision controls whether the stock becomes sellable again.

## 1. Returns domain

A dedicated `App\Domain\Returns` module — independent of Payments,
Shipping, and Fulfillment, mirroring how Fulfillment itself stayed
independent of Shipping (ADR-013). Directory shape matches every other
domain (`Models`, `Enums`, `Application`, `Http\{Controllers,Requests,
Resources}`, `Exceptions`, `Support`). No `Infrastructure\Providers` —
there is no external reverse-logistics provider this milestone (no
courier return-label API, spec section 18).

Core concepts and where they live:

```text
ReturnRequest           Returns\Models   one return attempt against an Order
ReturnItem               Returns\Models   (ReturnRequest, OrderItem, quantity) — one claimed line
ReturnInspection          Returns\Models   the merchant's verified, write-once assessment of one ReturnItem
ReturnDisposition         Returns\Models   the post-inspection decision + when it was applied to Inventory
ReturnEvent               Returns\Models   append-only timeline
ReturnNumberSequence       Returns\Models   internal locking primitive for AllocateReturnNumber (mirrors OrderNumberSequence)
ReturnStatus              Returns\Enums    the state machine's vocabulary
ReturnReason               Returns\Enums    why the customer/merchant is returning it
ReturnCondition            Returns\Enums    shared vocabulary for both the claimed and verified condition
ReturnDisposition (enum)   Returns\Enums    restock / damaged / repair / discard / manual_review
ReturnStateMachine          Returns\Support  the only place status transitions are allowed
ReturnInventoryContext      Returns\Support  resolves which InventoryItem/Location a returned unit lands on
```

**Boundaries, reused not reimplemented**: Returns reads `OrderItem` (to
validate ownership and returnable quantity) and `ShipmentItem`/
`Shipment.status` (to compute how much of an OrderItem was actually
shipped) from the Orders/Shipping domains, and writes `InventoryMovement`/
`InventoryLevel` directly from its own `Application` classes — the exact
same boundary `Fulfillment\Application\CompleteFulfillment` already uses
toward Inventory. Nothing in Returns imports a Payments or Fulfillment
Application class; nothing in those domains imports anything from
Returns. This is a one-directional dependency (Orders/Shipping ->
Returns -> Inventory), not a circular one.

## 2. Why only shipped quantity is returnable

Spec section 4 says "quantity <= fulfilled quantity." In this codebase,
"fulfilled" already has a precise, narrower meaning after Fulfillment
Core (ADR-013) — a `Fulfillment` reaching `ready` doesn't mean stock left
the building, a `Shipment` being `created` (or later) does. A return is
only meaningful for a unit that is actually, physically in the
customer's hands, so `RequestReturn` computes returnable quantity as:

```text
returnable = SUM(ShipmentItem.quantity WHERE order_item_id = X AND Shipment.status != cancelled)
           - SUM(ReturnItem.quantity WHERE order_item_id = X AND ReturnRequest.status NOT IN (rejected, cancelled))
```

Both sums are computed under a row lock on the `OrderItem` (`RequestReturn`
locks it before either query, mirroring `CreateFulfillment`/
`CreateShipment`'s identical discipline) — this is what makes two
concurrent return requests for the same `OrderItem` safe (see
`tests/Concurrency/ReturnConcurrencyTest.php`). A rejected or cancelled
`ReturnRequest` does not consume this budget — its quantity becomes
claimable again, the same way a cancelled `Fulfillment` releases its
allocation.

## 3. Status lifecycle

```text
requested -> approved -> awaiting_return -> received -> inspection -> completed
    \-> rejected (terminal)
{requested, approved, awaiting_return, received, inspection} -> cancelled (terminal)
```

Enforced by `ReturnStateMachine`, the only place a transition is allowed
to happen — mirrors `FulfillmentStateMachine`/`ShipmentStateMachine`
exactly, including the same-state-is-a-no-op rule.

**`approved` always advances straight to `awaiting_return`, in the same
call.** Spec section 3 lists both as distinct statuses, but spec section
13's endpoint list has only one `POST /returns/{id}/approve` — there is
nothing left for a merchant to decide between "approved in principle"
and "now we wait for the physical package," so `ApproveReturn` writes
both transitions (and both `ReturnEvent`s) in one request, the same
"auto-advance without a dedicated endpoint" precedent
`PackFulfillmentItems` already established for `packing -> ready`.

**`cancel` is not in spec section 13's literal endpoint list**, but
`cancelled` is a required status (section 3) and every sibling domain
exposes a dedicated, state-machine-guarded cancel action rather than a
bare field write through `PATCH` — `CancelReturn` was added for the same
reason. See §8 ("What's deliberately not implemented") for the full list
of things that were *not* added this way.

## 4. Inspection and disposition — one endpoint, two records

Spec sections 6/7 describe them as separate concepts (`ReturnInspection`,
`ReturnDisposition`), and they are separate tables — but spec section
13 lists only one `POST /returns/{id}/inspect` endpoint, no separate
disposition endpoint. `InspectReturn` therefore records both in the same
call, per item: a write-once `ReturnInspection` (condition, photos
metadata, notes, who/when) and a `ReturnDisposition` (restock / damaged /
repair / discard / manual_review, who/when *decided*, but not yet
*applied*).

**Inspecting the same `ReturnItem` twice is rejected** (`ValidationException`,
422) — re-inspection is not a feature this milestone builds; a mistaken
inspection is a support/manual-review matter, not a data-correction
workflow.

## 5. Inventory integration — disposition is decided at Inspect, applied at Complete

Spec section 8 is explicit: **"Inventory changes happen ONLY after
inspection."** Read literally — *after*, not *as part of* — this means
the disposition decision and its inventory effect are two different
moments:

```text
InspectReturn   -> ReturnDisposition row created (disposition chosen, applied_at = null)
CompleteReturn  -> for each item's disposition, apply the inventory effect, stamp applied_at
```

`CompleteReturn` is Returns' equivalent of `CompleteFulfillment` — "the
one place stock actually moves," except in reverse:

| Disposition     | InventoryMovement reason | on_hand delta | Why |
|---|---|---|---|
| `restock`        | `ReturnRestocked`         | `+quantity`    | The one real positive delta this domain ever writes — the unit is genuinely sellable again. |
| `damaged`         | `ReturnDamaged`            | `0`             | Logged for audit, but "damaged items must never automatically return to sellable inventory" (spec section 8) — `on_hand` stays untouched. |
| `discard`         | `ReturnDiscarded`          | `0`             | The unit is destroyed; it never re-entered `on_hand` to begin with. |
| `repair`           | *(none)*                    | —                | Not in spec section 9's list of movement types — no InventoryMovement is written at all. Logged only via `ReturnEvent`; resolving it is left to a human, through Inventory's existing manual-adjustment tool (out of scope this milestone). |
| `manual_review`   | *(none)*                    | —                | Same as `repair` — deliberately inert, a flag for a human to act on later. |

**Where "receiving" fits**: `ReceiveReturn` (the package physically
arriving) writes a `ReturnReceived` movement too — but with a **zero**
delta. It exists purely as an audit-trail entry ("this ReturnItem's
package showed up on this date"), the same zero-delta bookkeeping
pattern `FulfillmentAllocated`/`ReservationReleased` already established
for "something happened, but no physical stock moved yet." This is how
the domain satisfies spec section 9's requirement that `ReturnReceived`
exists as a movement type without contradicting section 8's "only after
inspection" rule for anything that actually changes sellable stock.

**Location resolution** (`ReturnInventoryContext`): a returned unit lands
back at the same `Location` its consumed `FulfillmentAllocation`
originally shipped from — resolved by walking `OrderItem -> 
FulfillmentItem -> FulfillmentAllocation (consumed, most recent) ->
location_id`. An untracked `InventoryItem`, or an `OrderItem` that
somehow has no consumed allocation, resolves to `null` — `CompleteReturn`
then still marks the disposition `applied_at` and closes the return, it
just never writes an `InventoryMovement` (the same untracked-item skip
`AllocateFulfillment` already uses; `inventory_movements.inventory_item_id`/
`location_id` are both required columns, so there is nothing to write
against).

## 6. Return events (timeline)

Append-only, mirrors `FulfillmentEvent`/`TrackingEvent` exactly: a plain
`type` string (not a state-machine input), `description`, `occurred_at`,
no `updated_at` column. Written inline by every `Application` class, in
the same transaction as the status change it describes — `requested`,
`approved`, `awaiting_return`, `rejected`, `received`, `inspection_completed`,
`completed`, `cancelled`.

## 7. Tenant isolation

Every Returns table uses `BelongsToTenant` — the same global-scope-plus-
forced-`store_id` guarantee every other tenant table has. Every route is
tenant-scoped route model binding (`{returnRequest}` resolves through
`ReturnRequest`'s own tenant-scoped query — a cross-store id 404s, never
leaks another store's return). Cross-tenant pairs are explicitly tested,
not just individually-scoped resources — see
`tests/Feature/Returns/ReturnTenantIsolationTest.php`: Store A cannot
list, view, or act on Store B's `ReturnRequest`, cannot request a return
against Store B's order, and cannot reference Store B's `OrderItem` from
a Store A return.

## 8. What's deliberately not implemented

Per spec section 18, and reaffirmed here rather than silently skipped:

- **No refunds.** Nothing in this milestone calls a `PaymentProviderContract`
  method, mutates `Payment`/`Order.financial_status`, or computes a
  refund amount. `CompleteReturn` never touches the Payments domain at
  all.
- **No courier return-label API, no warehouse routing.** `ReturnItem`
  has no shipping-label/tracking fields; the physical return trip back
  to the warehouse is assumed to have already happened by the time
  `ReceiveReturn` is called.
- **No customer accounts / customer-facing return portal.** Spec section
  12 asked for "provider-neutral API endpoints" a future customer-facing
  surface could consume — the existing admin API (`/orders/{order}/returns`,
  `/returns/{id}/*`) is that provider-neutral shape (JSON in, JSON out,
  no admin-UI-specific fields baked into the response), but every route
  still requires `auth:sanctum` + `tenant` (merchant-side) — no anonymous
  customer-facing endpoint was added this milestone, since building
  customer login was explicitly out of scope.
- **No email notifications.**
- **No re-inspection / inspection-correction workflow** (§4 above).

### Future refund integration

The natural seam already exists: `CompleteReturn`'s transaction is the
one place a return's outcome is fully known (every item's verified
condition + disposition + inventory effect). A future refund milestone
would most naturally hook in there — e.g. `RecordOutboxEvent('ReturnCompleted',
...)` (already emitted) is exactly the kind of event a `ProcessReturnRefund`
listener could react to, without `CompleteReturn` itself needing to know
anything about `PaymentProviderContract`. This mirrors how Shipping's own
webhook-driven state changes never call into Fulfillment directly —
`CreateShipment` calls `CompleteFulfillment` as an explicit, one-directional
dependency, not the reverse. A refund milestone should preserve that
shape: Returns stays the source of truth for "what physically came back
and in what condition," Payments/refunds would consume that fact, not
the other way around.

## 9. Admin API

```text
GET    /api/v1/returns
GET    /api/v1/returns/{returnRequest}
POST   /api/v1/orders/{order}/returns
PATCH  /api/v1/returns/{returnRequest}          (notes only — status changes always go through a dedicated action route)
POST   /api/v1/returns/{returnRequest}/approve
POST   /api/v1/returns/{returnRequest}/reject
POST   /api/v1/returns/{returnRequest}/receive
POST   /api/v1/returns/{returnRequest}/inspect
POST   /api/v1/returns/{returnRequest}/complete
POST   /api/v1/returns/{returnRequest}/cancel
```

Same nested-creation/flat-action route shape Fulfillment established:
`POST /orders/{order}/returns` to start one, everything else flat under
`/returns/{returnRequest}`.

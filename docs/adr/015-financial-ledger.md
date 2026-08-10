# ADR-015: Financial Ledger — a Dedicated Accounting Layer Built On Payments

## Status
Accepted

## Context
Milestone 9's brief introduces accounting: refunds, and an immutable
double-entry ledger recording every payment and refund. It is explicit
that this is not a payment-provider integration — no YooKassa/T-Bank/
CloudPayments refund API is to be called; the only provider this
milestone touches is the existing `FakePaymentProvider`.

The milestone brief names `Payment` as one of the "Financial module"
entities, alongside `Refund`, `RefundItem`, `LedgerEntry`,
`LedgerTransaction`, and `FinancialEvent` — but `Payment` already exists
as a settled, tested domain (`App\Domain\Payments`, Milestone 5's Payment
Foundation) with its own state machine, provider contract, webhook
pipeline, and admin surface. The question this ADR answers: does
`Payment` move into a new `Financial` module, or does `Financial` build
alongside it?

## Options
1. Move `Payment` (and its models/webhook pipeline) into a new
   `App\Domain\Financial` module, with `Refund`/`Ledger*`/`FinancialEvent`
   alongside it as originally-sibling entities.
2. Leave `App\Domain\Payments` exactly where it is; add a new
   `App\Domain\Financial` module for `Refund`/`RefundItem`/
   `LedgerTransaction`/`LedgerEntry`/`FinancialEvent`, reading `Payment`
   the same way Returns already reads `OrderItem`/`ShipmentItem` —
   through the model, never re-implementing its state machine.
3. Model refunds and ledger entries as new tables inside
   `App\Domain\Payments` itself, no new domain.

## Decision
Option 2 — directly following the precedent ADR-013/ADR-014 already set
for exactly this question ("does X move, or does the new module build
alongside it"). `Payment` stays in `App\Domain\Payments`, entirely
unmoved; `App\Domain\Financial` is new, and reads `Payment`/
`PaymentStatus`/`PaymentStateMachine` the same way `Returns` already
reads `OrderItem`/`ShipmentItem`.

Option 1 was rejected because moving a settled, tested domain
(`Payments`: its own state machine, provider contract, webhook
controller, admin resource, five existing test files) is a large,
purely-cosmetic risk with no functional benefit — every consumer of
`App\Domain\Payments\Models\Payment` across Checkout/Orders/Fulfillment/
Shipping would need updating for a rename that changes nothing about how
the system behaves. It would also have obscured the actual, interesting
architectural decision (how does a new accounting layer attach to an
existing payment domain) behind a mechanical file move.

Option 3 was rejected for the same reason ADR-013/ADR-014 rejected
folding a new concern into an existing module: `Refund`'s lifecycle
(requested/processing/completed/failed/cancelled, its own provider
submission and webhook confirmation) and the ledger's own invariants
(balance, immutability) are substantial enough to warrant their own
state machine and their own home, not bolted onto `Payments`' already-
settled shape.

**The one deliberate cross-domain call this decision requires**:
`Payments\Application\ProcessPaymentWebhook` now calls
`Financial\Application\RecordPaymentCapturedLedgerEntries` the moment a
Payment first reaches `paid`, inside the same transaction/row lock. This
is a Payments -> Financial dependency, which could be read as inverting
the "new domain reads the old one" shape — but it's the same pattern
`Shipping\Application\CreateShipment` already uses calling
`Fulfillment\Application\CompleteFulfillment`: a downstream domain's
completion action invoked directly from the trigger that produced it,
not a circular dependency (`Financial` never imports or calls back into
`Payments`' webhook-processing code — it only reads `Payment` and writes
to it through `PaymentStateMachine`, the same access `Financial`'s own
`RequestRefund`/`ApplyRefundCompletion` already have).

**Webhook pipeline sharing**: spec section 7 explicitly asks for refunds
to use "the same webhook pipeline as payments." Rather than a second
route/controller, `PaymentWebhookController` now routes the parsed
`WebhookEvent` to `ProcessPaymentWebhook` or the new `ProcessRefundWebhook`
based on the event's own `event_type` prefix (`"payment."` vs
`"refund."`), decided after the identical `verifyWebhook()`/
`parseWebhook()` steps run — literally one pipeline, two downstream
processors.

## Consequences
### Positive
- `Payments` (Milestone 5) needed zero structural changes — only one new
  constructor dependency and one new call site inside
  `ProcessPaymentWebhook::applyTransition()`. Every existing Payments
  test continues to pass unmodified.
- The ledger's balance invariant has exactly one enforcement point
  (`PostLedgerEntries::assertBalanced()`) regardless of which domain
  event triggered it (payment capture or refund completion) — a future
  third ledger-posting trigger (e.g. a manual adjustment tool) reuses the
  same guarantee for free.
- `Order.financial_status` finally has a real derivation
  (`RecomputeOrderFinancialStatus`), closing a gap that existed since
  Payment Foundation: `PartiallyRefunded`/`Refunded` were declared on the
  `FinancialStatus` enum from the start but never reachable until now.

### Negative
- `CreateFulfillment`/`CreateShipment`'s `financial_status !== Paid`
  guards needed widening to also accept `PartiallyRefunded` — a genuine,
  if small, change to Fulfillment/Shipping Core code to keep them correct
  once partial refunds became reachable. Covered by the widened guard's
  own inline comment and exercised indirectly by the refund lifecycle
  tests (a partially-refunded order's remaining Fulfillment/Shipment
  actions are never blocked).
- A merchant now has a sixth multi-step flow (Order -> Payment ->
  Fulfillment -> Shipment -> Return -> **Refund**) to operate. Accepted
  as the accurate shape of real order-lifecycle accounting, the same
  tradeoff ADR-013/ADR-014 already accepted for Fulfillment and Returns.

## Security Requirements
- Every Financial table (`refunds`, `refund_items`, `ledger_transactions`,
  `ledger_entries`, `financial_events`) uses `BelongsToTenant` — verified
  with dedicated cross-tenant tests
  (`tests/Feature/Financial/RefundTenantIsolationTest.php`), not just
  individually-scoped resources.
- Refund creation requires an `Idempotency-Key` header (reusing
  `IdempotencyKeyStore`, not a new mechanism) and refund webhook
  processing is idempotent under real concurrent PostgreSQL connections,
  proven in `tests/Concurrency/RefundConcurrencyTest.php` — both the
  literal duplicate-event-id case and the "two independent completion
  attempts race for the same Refund" case never double-post ledger
  entries or double-apply `Payment.refunded_amount`.

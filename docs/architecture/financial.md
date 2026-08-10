# Financial Architecture

Milestone 9 — Refunds + Financial Ledger. See
[ADR-015](../adr/015-financial-ledger.md) for why the domain is shaped
the way it is. **This milestone introduces accounting, not a payment
provider integration** — no YooKassa/T-Bank/CloudPayments refund API is
called anywhere; the only provider this domain ever talks to is the
existing `FakePaymentProvider`, extended with `createRefund()`/
`simulateRefundWebhookPayload()`.

## 1. Financial domain

A dedicated `App\Domain\Financial` module, built on top of the existing
`App\Domain\Payments` module rather than absorbing it — `Payment` stays
exactly where it was (ADR-015 explains why moving it was rejected).
Directory shape matches every other domain.

```text
Refund                  Financial\Models   one refund attempt against a Payment
RefundItem                Financial\Models   (Refund, ReturnItem, quantity, amount) — one refunded line
LedgerTransaction          Financial\Models   groups a balanced set of LedgerEntry rows
LedgerEntry                 Financial\Models   one debit or credit against one account
FinancialEvent               Financial\Models   append-only, unified per-order timeline
RefundNumberSequence          Financial\Models   internal locking primitive (mirrors OrderNumberSequence)
RefundWebhookEvent             Financial\Models   webhook idempotency claim (no BelongsToTenant — see §5)
RefundStatus                    Financial\Enums    the refund state machine's vocabulary
LedgerAccount                    Financial\Enums    cash / revenue — see §4
LedgerDirection                   Financial\Enums    debit / credit
RefundStateMachine                 Financial\Support  the only place Refund transitions are allowed
PostLedgerEntries                   Financial\Application  the only place ledger rows are ever written
```

**Boundaries, reused not reimplemented**: Financial reads `ReturnItem`
(Returns) to validate refundable quantity and `Payment`/`PaymentStatus`
(Payments) to validate refundable balance and transition payment status
— the exact same one-directional-dependency shape Returns already
established toward Fulfillment/Shipping. The one deliberate exception is
`ProcessPaymentWebhook` (Payments) calling into
`Financial\Application\RecordPaymentCapturedLedgerEntries` — see §3.

## 2. Refund lifecycle

```text
requested -> processing -> completed
    \-> completed (manual refund — no provider, no webhook to wait for)
    \-> cancelled (from requested only)
processing -> failed
```

Enforced by `RefundStateMachine`. Two things deviate from
`PaymentStateMachine`'s own precedent, both explained in that class's
docblock:

- **`requested -> completed` directly** is the manual-refund path (spec
  section 11): no provider call means nothing async to wait for, so
  `RequestRefund` completes it synchronously in the same request.
- **`processing` cannot be cancelled.** `PaymentStateMachine` allows
  `processing -> cancelled` because that state models a customer still
  sitting on an interactive fake payment page. A refund's `processing`
  means "already submitted to the provider" — there's no session left to
  back out of, so cancellation is only reachable from `requested`.

### Manual vs. provider refunds

`Refund.provider` is nullable — `null` means manual (spec section 11):
the merchant is recording that money moved outside the platform (e.g. a
bank transfer), no provider is called, and `RequestRefund` runs
`ApplyRefundCompletion` synchronously in the same transaction that
created the Refund. A non-null `provider` (only `"fake"` this milestone)
submits through `FakePaymentProvider::createRefund()`, which returns an
`external_refund_id` stored as `provider_reference`; completion then
arrives later, exclusively through a webhook.

### Partial refunds — three composable pieces

Spec section 9 asks for item refunds, shipping refunds, and manual
adjustments, "not mutually exclusive" in real life. `Refund.amount` is
always the sum of three independently-optional components, validated
together in one `RequestRefund` call:

```text
amount = SUM(refund_items.amount)   (spec section 4 — tied to ReturnItems)
        + shipping_amount            (spec section 10 — refund shipping alone)
        + adjustment_amount          (spec section 9 — a free-standing amount, no items/shipping)
```

`refund_items` ties each line to a `ReturnItem`, not an `OrderItem`
directly — a refund is only valid for what was actually **returned and
inspected** (spec section 8's workflow: Return -> Inspection ->
Disposition -> Refund Request), never merely ordered. `RequestRefund`
requires the owning `ReturnRequest` to be `completed` before any of its
items are refundable, and validates:

```text
refunded quantity <= ReturnItem.quantity - already-refunded quantity
                      (across every non-failed, non-cancelled Refund)
shipping_amount    <= Order.shipping_amount - already-refunded shipping
amount (total)     <= Payment.captured_amount - already-committed refund amount
                      (across every non-failed, non-cancelled Refund on that Payment)
```

All three checks happen under row locks (`ReturnItem`, `Payment`) inside
one transaction, the same discipline `CreateFulfillment`/`RequestReturn`
already established for their own quantity ceilings — see
`tests/Concurrency/RefundConcurrencyTest.php`.

## 3. Ledger

Immutable, double-entry (spec section 5): `LedgerTransaction` groups a
balanced set of `LedgerEntry` rows (`sum(debits) == sum(credits)`,
enforced by `PostLedgerEntries::assertBalanced()` before any row is
written — the *only* place a ledger row is ever created in this
codebase). Both tables are append-only — `created_at` only, no
`updated_at`, no delete path exposed anywhere.

**Chart of accounts — deliberately minimal**: `cash` and `revenue`.
This is not full GAAP accounting (spec section 20: no invoices, no
accounting export, no tax accounts, no COGS/AR). Two events post
entries:

| Trigger | Entries |
|---|---|
| Payment reaches `paid` (`RecordPaymentCapturedLedgerEntries`, called from `ProcessPaymentWebhook`) | Dr `cash`, Cr `revenue`, both = captured amount |
| Refund reaches `completed` (`ApplyRefundCompletion`) | Dr `revenue`, Cr `cash`, both = refund amount — the exact reversal |

**The one cross-domain call this milestone adds**: `ProcessPaymentWebhook`
(Payments) now calls `Financial\Application\RecordPaymentCapturedLedgerEntries`
the first time a Payment reaches `paid`, inside the same transaction and
row lock that write already holds. This mirrors `CreateShipment`
(Shipping) calling `CompleteFulfillment` (Fulfillment) — a downstream
domain's completion action invoked directly from the upstream trigger,
not a circular dependency (Financial never calls back into Payments'
webhook-processing code, only reads `Payment`/writes to it via
`PaymentStateMachine`).

## 4. Financial status — derived, never an arbitrary flag

Spec section 6 is explicit: "Order financial status must become
derived... Do not mutate arbitrary flags." `RecomputeOrderFinancialStatus`
recomputes `Order.financial_status` from scratch every time it's called
— the same "recompute rather than increment" discipline
`CompleteFulfillment::refreshOrderFulfillmentStatus` already established
for `Order.fulfillment_status`:

```text
totalCaptured = SUM(Payment.captured_amount WHERE status IN (paid, partially_refunded, refunded))
totalRefunded = SUM(Payment.refunded_amount)

totalCaptured <= 0        -> Pending
totalRefunded >= totalCaptured -> Refunded
totalRefunded > 0          -> PartiallyRefunded
else                        -> Paid
```

Called from both trigger points (`ProcessPaymentWebhook` on first
capture, `ApplyRefundCompletion` on every refund completion) — never
written directly anywhere else. `Voided` is explicitly excluded from
this recompute (an explicit merchant override this milestone doesn't
build a trigger for) so a payment/refund event landing afterward can
never silently reverse it.

**Consequence for Fulfillment/Shipping**: `CreateFulfillment`/
`CreateShipment` originally required `financial_status === Paid` exactly
— before this milestone, nothing could ever move a paid order off
`Paid`, so that guard was accidentally correct rather than deliberately
narrow. Now that `PartiallyRefunded` is reachable, both guards were
widened to accept `Paid` OR `PartiallyRefunded` — a partial refund (e.g.
one damaged item) must never block fulfilling/shipping the rest of a
still-owed order.

## 5. Webhook pipeline — shared with Payments, not duplicated

Spec section 7: "Use same webhook pipeline as payments." Literally the
same route, same controller (`PaymentWebhookController`), same
`verifyWebhook()`/`parseWebhook()` sequence — `WebhookEvent` (the shared
parsed-payload value object) gained an optional `externalRefundId`
field, and `FakePaymentProvider::parseWebhook()` now branches on the
payload's `event_type` prefix (`"payment."` vs `"refund."`) to decide
which id field is required. `PaymentWebhookController::handle()` then
routes the parsed event to `ProcessPaymentWebhook` or
`Financial\Application\ProcessRefundWebhook` based on that same prefix —
never based on the URL.

```text
POST /api/v1/payments/webhooks/{provider}
  -> registry.resolve(provider) -> verifyWebhook() -> parseWebhook()
  -> event_type starts with "refund."?
       yes -> ProcessRefundWebhook  (Financial)
       no  -> ProcessPaymentWebhook (Payments)
```

`ProcessRefundWebhook` mirrors `ProcessPaymentWebhook` structurally —
same replay-tolerance check, same claim/poll idempotency shape — but
against its own `refund_webhook_events` table (`(provider,
external_event_id)` unique index), not `payment_webhook_events`. Kept
separate rather than reused so Financial's own webhook idempotency never
requires writing into a table owned by the Payments domain; the
docblock reasoning is transplanted verbatim from
`payment_webhook_events`' own migration (a webhook arrives with no
`TenantContext` at all, so `store_id` can't be tenant-scoped until the
owning `Refund` is resolved).

**Idempotency, in depth** (spec section 13): a duplicate webhook
delivery is caught first by `RefundWebhookEvent`'s claim/poll table
(identical `event_id` → no-op, matching payload hash required); a
*different* event reporting the same outcome again is still caught by
`ApplyRefundCompletion`'s own `if ($refund->status === Completed) return`
guard — the same defense-in-depth pattern `Returns\Application\
CompleteReturn::applyDisposition()` established for disposition
idempotency. Proven under genuine concurrent PostgreSQL connections in
`tests/Concurrency/RefundConcurrencyTest.php`, not just sequential
retries.

## 6. Refund request idempotency

Spec section 13 also requires refund *requests* to stay idempotent —
`RefundController::store()` requires an `Idempotency-Key` header and
reuses the existing tenant-scoped `IdempotencyKeyStore` (the same
infrastructure `StorefrontPaymentController::store()` already uses for
payment creation), not a new mechanism.

## 7. Tenant isolation

Every Financial table (`refunds`, `refund_items`, `ledger_transactions`,
`ledger_entries`, `financial_events`) uses `BelongsToTenant`.
`refund_webhook_events` deliberately does not, for the same reason
`payment_webhook_events` doesn't (§5). Cross-tenant pairs are explicitly
tested — see `tests/Feature/Financial/RefundTenantIsolationTest.php`:
Store A cannot list/view/cancel Store B's refunds, cannot request a
refund against a Store B order or `ReturnItem`, and cannot see Store B's
payments or order-level financial data.

## 8. Admin API & UI

```text
GET   /api/v1/refunds
GET   /api/v1/refunds/{refund}
POST  /api/v1/orders/{order}/refunds
POST  /api/v1/refunds/{refund}/cancel
```

Same nested-creation/flat-action shape Fulfillment/Returns established.
Admin UI: `Refunds` list + detail page (with dev-only fake-provider
"mark succeeded/failed" controls, gated by `payments.fake.enabled`, only
shown while a provider refund is genuinely `processing`); the Order page
gained Payments, Refunds (with an inline "Request a refund" form),
Ledger, and Financial timeline sections, alongside the existing
Reservations/Fulfillments/Shipments/Returns ones.

## 9. What's deliberately not implemented

Per spec section 20:

- **No YooKassa/T-Bank/CloudPayments refund integration.** The only
  provider this domain calls is the existing `FakePaymentProvider`.
- **No bank integrations, no invoices, no accounting export, no taxes.**
  The ledger's two-account chart (§3) is intentionally not extensible to
  these this milestone — adding them would mean designing a real chart
  of accounts, which is a deliberately deferred, separate decision.

# Fiscalization

## 1. Overview

The fiscal receipt architecture models Russia's 54-FZ requirement that
most sales issue a fiscal receipt through a registered cash-register/OFD
(fiscal data operator) provider. This milestone builds the **entire
domain model and provider boundary** with a fake, in-process reference
provider — no real OFD integration (ATOL, OrangeData, CloudKassir) is
implemented. The pattern mirrors Payments/Shipping/Search/Notifications
exactly: a contract, a registry, and exactly one real, working
implementation that behaves like an honest async provider.

## 2. Receipt model

`FiscalReceipt` (`operation`, `status`, `provider`, `external_receipt_id`,
`seller_inn`/`seller_kpp`, `customer_email`/`customer_phone`, `currency`,
`total_amount`, `fiscalized_at`, `error_message`, `attempt_count`) has
many `FiscalReceiptItem` rows, one per 54-FZ line item:

| Field | Meaning |
|---|---|
| `name`, `quantity`, `price_amount`, `amount` | The line itself. |
| `vat_rate` | A `VatRate` — resolved per-item via `ResolveProductFiscalProfile`, so a single receipt can legitimately mix rates. |
| `payment_method` | `FiscalReceiptItemPaymentMethod` — `full_payment`/`prepayment`/`advance`/`credit` (the 54-FZ "способ расчёта"). Every line this milestone reports `full_payment`, since no checkout path yet produces a split/deposit sale. |
| `payment_subject` | `FiscalReceiptItemPaymentSubject` — `commodity`/`service`/`work`/`payment`/`agent_commission`. Product lines default to `commodity`; a synthesized shipping line uses `service`. |
| `unit_of_measure` | Optional, from the item's resolved fiscal profile. |

## 3. Lifecycle

`FiscalReceiptStatus` (`pending` → `processing` → `fiscalized` |
`failed` | `cancelled`) is **its own lifecycle, deliberately separate
from `Payment.status`** (spec: "Payment success should not directly
mean fiscalization success"). `CreateFiscalReceipt` creates a receipt
`pending`, builds its items, then calls the active provider's
`submitReceipt()`:

- On success, the receipt moves to `processing` and records the
  provider's `external_receipt_id` — the receipt is **not** fiscalized
  yet, only accepted for async processing.
- On a submission-time exception (the provider rejected the request
  outright), the receipt moves straight to `failed` with
  `error_message` set — **the triggering Order/Payment are never
  touched**, only this receipt's own row records the failure.

Fiscalization only ever completes through a later, verified provider
callback, handled by `ProcessFiscalizationCallback` — never
synchronously inside `CreateFiscalReceipt`. A duplicate/replayed
callback for an already-terminal receipt (`fiscalized` or `failed`) is
a no-op, checked under `lockForUpdate()` to serialize concurrent
deliveries of the same callback.

## 4. Provider boundary

`FiscalizationProviderContract` (`code()`, `submitReceipt()`,
`verifyCallback()`, `parseCallback()`) mirrors `PaymentProviderContract`'s
async shape deliberately: a real OFD provider confirms fiscalization
out-of-band, the same way a card payment confirms via webhook, not
synchronously in the request that triggered it.
`FiscalizationProviderRegistry` is a boot-time singleton, populated in
`RussianCommerceServiceProvider`.

`FakeFiscalizationProvider` (`code = 'fake'`) makes no external HTTP
requests. It signs callbacks with HMAC-SHA256 via an
`X-Fake-Fiscalization-Signature` header (secret from
`config('russian_commerce.fake_fiscalization.secret')`, raw-`env()`
read to avoid the config-boot-crash pattern documented in
`config/payments.php`). Its `simulateCallbackPayload(externalReceiptId,
outcome)` test/dev harness supports three outcomes:

- `success` — an immediate `fiscalized` callback payload.
- `failure` — an immediate `failed` callback payload with a simulated
  error message.
- `delayed_success` — **byte-identical to `success`**. A real OFD
  provider's "delay" is entirely about *when* it calls back, not a
  different payload shape — modeling delay as timing (the caller
  simply waits, or a test advances time, before invoking the callback
  endpoint) rather than a distinct payload keeps the fake honest about
  what a real provider's delay actually looks like.

Only registered when `russian_commerce.fake_fiscalization.enabled` is
true (local/testing by default) — in a real deployment with no OFD
provider configured, `FiscalizationProviderRegistry` simply has no
provider named `fake`, the same failure mode as a provider that was
never implemented.

## 5. Payment → fiscalization boundary

`FiscalizationSubscriber` is the platform's 6th `ProcessOutboxEventsCommand`
subscriber, listening for `OrderPaymentConfirmed` (fired by
`ProcessPaymentWebhook` when a payment first reaches `paid`) and
dispatching `RequestFiscalizationJob` — never fiscalizing inline, since
a payment webhook response must never wait on a fiscalization provider
call. The job re-enters `TenantContext::scope()` and calls
`CreateFiscalReceipt`, which itself is a no-op (returns `null`) unless
the order's `OrderFiscalSnapshot.receipt_required` is true.

If no active `FiscalizationProvider` is configured despite receipts
being required, `CreateFiscalReceipt` throws
`FiscalizationNotConfiguredException` before writing any row — a real
admin misconfiguration. `RequestFiscalizationJob` catches this
specifically and logs a warning rather than letting the queue retry
indefinitely against the same guaranteed outcome; an admin completing
the Fiscalization Settings page (not a queue retry) is what actually
recovers this.

Fiscalization events (`FiscalReceiptCreated`, `FiscalReceiptFiscalized`,
`FiscalReceiptFailed`) are recorded via the existing Platform Events/
Outbox pipeline, always with `aggregate_type=FiscalReceipt,
aggregate_id=the receipt's own id` — never `Order`/`Payment`, keeping
fiscalization's own event trail independent of either domain's.

## 6. Not implemented this milestone

- Real OFD HTTP integration (ATOL, OrangeData, CloudKassir).
- Real SBP QR/deeplink generation.
- Refund/correction receipts: `FiscalReceiptOperation::Refund` and
  `FiscalReceipt.correction_of_id` (a self-referencing FK) exist as
  storable values — a receipt *can* reference the original it
  corrects — but no Application service creates one yet. The future
  flow: a `Refund` reaching a terminal succeeded state would trigger a
  new `CreateFiscalReceipt`-equivalent call with
  `operation=refund` and `correction_of_id` set to the original sale
  receipt, reusing the same provider submission/callback shape
  end-to-end; no new architecture is needed, only the trigger and a
  refund-specific item-building step.

## 7. Tenant isolation and security

`FiscalReceipt`/`FiscalReceiptItem`/`FiscalizationProvider`/
`FiscalizationSettings` all use `BelongsToTenant` — a receipt or
provider config from one store is never visible to another (see
`tests/Feature/RussianCommerce/TenantIsolationTest.php`).
`FiscalizationProvider.credentials` is encrypted at rest and never
serialized in any API response. `ProcessFiscalizationCallback` resolves
its tenant from the (provider, external_receipt_id) → `FiscalReceipt`
mapping — never from anything caller-supplied in the callback body —
the same pattern `ProcessPaymentWebhook` already established for
payment webhooks.

See also: [russian-commerce.md](russian-commerce.md), [ADR-030](../adr/030-russian-commerce-foundation.md).

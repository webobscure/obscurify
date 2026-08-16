# ADR-030: Russian Commerce Foundation — Opt-In Snapshot, Representative Snapshot VAT Rate, State-Based Callback Idempotency, Fake-Only Provider, Synthesized Shipping Line, Format-Only OGRN/OGRNIP

## Status
Accepted

## Context

Milestone 24 adds the Russian commerce domain architecture — legal
identity, VAT, fiscal receipts, extended payment methods — without any
real external integration. Six design questions dominated the
implementation: whether every store gets an `OrderFiscalSnapshot` or
only stores that opt in; how a snapshot with a single `vat_rate` column
reports an order whose lines legitimately carry different rates; how a
fiscal receipt's async callback stays idempotent under duplicate
delivery without a dedicated event-dedup table (unlike Payments); how
literally "provider-neutral fiscal receipt architecture" should be
proven without a second real provider to prove it against; whether
shipping needs its own fiscal receipt line item; and how much of
OGRN/OGRNIP validation this milestone actually owes.

## Decision 1: `BuildOrderFiscalSnapshot` returns `null`, not a placeholder row, for a store with no legal profile

**Options considered:**

1. Always write an `OrderFiscalSnapshot` row per completed order, using
   placeholder/empty values (`seller_legal_name = ''`, etc.) when the
   store has no `StoreLegalProfile`.
2. Return `null` and write nothing when no `StoreLegalProfile` exists.

**Decision: option 2.** `seller_legal_name`/`seller_inn` are
non-nullable columns specifically because a snapshot without a real
seller identity is meaningless — a placeholder value would be silently
wrong data sitting in a table whose entire purpose is being a trustworthy
historical record. Since the vast majority of stores on this platform
have no reason to ever configure Russian legal details, treating the
whole domain as opt-in (no legal profile → no snapshot → no receipt →
zero behavioral difference from before this milestone) is both the
honest data model and the correct default for every non-Russian
merchant.

## Decision 2: The snapshot's single `vat_rate` is a representative label, not a computation input

**Options considered:**

1. Store per-line VAT breakdown directly on `OrderFiscalSnapshot`
   (a JSON column, or a related table).
2. Keep `OrderFiscalSnapshot.vat_rate` as one column — the rate
   responsible for the largest share of the order's `vat_amount` — and
   let `FiscalReceiptItem.vat_rate` (built later, per line, via
   `ResolveProductFiscalProfile`) be the only place per-line VAT
   actually lives.

**Decision: option 2.** The snapshot's job (spec section 11) is
recording *seller identity at order time*, not being the fiscal
receipt itself — duplicating a full per-line VAT breakdown into a
second table before any receipt has even been requested would be
premature: many orders complete with `receipts_required = false` and
never get a `FiscalReceipt` at all, making a fully-modeled VAT
breakdown on every order pure overhead. A single representative rate
plus the aggregate `vat_amount` is enough for the order detail page to
show "roughly what VAT applied" without pretending to be the
authoritative fiscal record — that's `FiscalReceiptItem`'s job, built
fresh from the order's live items whenever a receipt is actually
requested.

## Decision 3: Callback idempotency is a terminal-status check under `lockForUpdate()`, not a dedicated event-dedup table

**Options considered:**

1. Mirror `PaymentWebhookEvent` exactly: a dedicated table keyed on
   (provider, external_event_id), claimed via a unique constraint
   before any business logic runs.
2. `ProcessFiscalizationCallback` locks the `FiscalReceipt` row and
   checks whether its status is already terminal (`fiscalized` or
   `failed`) before applying a transition; a duplicate delivery finds
   the row already terminal and no-ops.

**Decision: option 2.** Payments needs a dedicated dedup table because
one `Payment` legitimately receives *many* distinct webhook events over
its life (authorize, capture, refund, cancel — each a real, separate
event worth recording even after the payment reaches a stable state).
A `FiscalReceipt` has exactly one meaningful async outcome — it either
gets fiscalized or it fails — so "is this receipt still non-terminal"
is a complete, correct idempotency check with no separate ledger
needed. This is simpler and asks fewer questions of the schema for a
domain that genuinely has a simpler event shape than Payments.

## Decision 4: `FakeFiscalizationProvider` is the only registered provider — no second fake to prove "provider-neutral" against

**Options considered:**

1. Build two fake providers (e.g. `fake` and `fake_v2`) purely to
   demonstrate that `FiscalizationProviderRegistry` genuinely supports
   more than one implementation.
2. One fake provider, with the contract itself (`FiscalizationProviderContract`)
   and the registry's `register()`/`resolve()`/`has()` shape being what
   proves neutrality — the same standard Payments/Shipping/Search set
   with their own single fake/default provider.

**Decision: option 2.** A second fake provider whose only purpose is
"prove the registry pattern works" would be dead code with no real
behavioral difference from the first — the actual proof of
provider-neutrality is that `CreateFiscalReceipt`/
`ProcessFiscalizationCallback` never reference `FakeFiscalizationProvider`
by name, only `FiscalizationProviderContract` and the registry. That's
the same bar every other provider-neutral domain in this codebase is
held to, and adding a second fake here would be inconsistent with that
precedent rather than more rigorous.

## Decision 5: A non-zero shipping amount gets a synthesized `service` fiscal receipt line, not silent omission

**Options considered:**

1. Only emit `FiscalReceiptItem` rows for `OrderItem`s — leave shipping
   out of the receipt entirely.
2. Synthesize one additional `service`-subject line for
   `Order.shipping_amount` when non-zero, so item amounts always sum to
   `FiscalReceipt.total_amount`.

**Decision: option 2.** A real 54-FZ receipt must account for the full
amount actually charged — a receipt whose lines sum to less than
`total_amount` (silently missing the shipping fee) would misrepresent
the sale to a tax authority, which is worse than the minor complexity
of one extra synthesized line. `service` is the correct payment_subject
for a shipping fee under the existing `FiscalReceiptItemPaymentSubject`
enum; no new subject type was needed.

## Decision 6: OGRN/OGRNIP validated by digit-count only this milestone

**Options considered:**

1. Implement OGRN's and OGRNIP's real checksum algorithms alongside
   INN/KPP's (spec section 23 lists "INN/KPP validation," not
   OGRN/OGRNIP, as a required test).
2. Format-only validation (13/15 digits respectively), documented as
   deliberately incomplete.

**Decision: option 2.** The spec's explicit test requirement names
INN/KPP specifically — those are the fields every downstream fiscal
computation (VAT code, receipt seller identity) actually depends on.
OGRN/OGRNIP are registry identifiers with no downstream computation
reading them this milestone; implementing their checksum algorithms now
would be scope creep against a concrete, unrequested requirement.
`InnKppValidator::isValidOgrn()`/`isValidOgrnip()` exist with clear
docblocks marking the gap, so a future milestone adding a real
registry-lookup integration (where a checksum genuinely matters) has an
obvious, already-named place to extend.

## Consequences

- Decision 1 means a store's first-ever order after configuring a
  legal profile is the first one that gets a snapshot — any order
  placed *before* that configuration has no fiscal snapshot and can
  never retroactively get one (correct: it genuinely wasn't fiscally
  relevant at the time).
- Decision 2's representative `vat_rate` is explicitly documented as
  display-only in both the model docblock and this ADR — a future
  contributor reading only the snapshot (not `FiscalReceiptItem`) must
  not treat it as authoritative for a mixed-rate order.
- Decision 3's simpler idempotency model would need revisiting if a
  future milestone adds a second async event type per receipt (e.g. a
  correction/refund receipt reaching its own async outcome
  independently) — at that point the "one meaningful outcome per
  receipt" assumption breaks and a Payments-style dedup table becomes
  the right call for that specific receipt.
- Decision 5 means `FiscalReceipt.total_amount` always equals
  `Order.total_amount` exactly (items subtotal + shipping - discount +
  tax, with a synthesized line closing the shipping gap) — a future
  discount-aware receipt (net-of-discount lines) is a natural next step
  once/if that becomes a real requirement.
- Decision 6 is a documented, narrow gap — flagged in both
  `docs/architecture/russian-commerce.md` and the validator's own
  docblocks, not silently absent.

# Russian Commerce Foundation

## 1. Overview

Milestone 24 adds the Russian commerce *domain architecture* a merchant
selling into the Russian market needs: legal entity identity, address
normalization, phone normalization, VAT modeling, provider-neutral
fiscal receipts, and extended payment methods (SBP, bank transfer). Per
spec, **no real external integration is implemented this milestone** —
no YooKassa, no CDEK, no OFD (ATOL/OrangeData/CloudKassir), no real SBP,
no 1C/MoySklad, no marketplace sync. Everything is built against
provider-neutral contracts with exactly one working reference
implementation: a fake fiscalization provider, mirroring the same
pattern Payments/Shipping/Search/Notifications established in earlier
milestones.

Core entities, all under `App\Domain\RussianCommerce`:

| Entity | Purpose |
|---|---|
| `StoreLegalProfile` | One row per store — legal identity (entity type, INN/KPP/OGRN/OGRNIP, addresses, contact). |
| `FiscalizationProvider` | A store's configured fiscalization provider instance (only `fake` is registered — see [fiscalization.md](fiscalization.md)). |
| `FiscalizationSettings` | One row per store — active provider, default VAT rate, whether receipts are required. |
| `PaymentMethodSettings` | One row per store — which `RussianPaymentMethod`s are enabled. |
| `ProductFiscalProfile` | An optional VAT/payment-subject override for a `Product` or `ProductVariant`. |
| `OrderFiscalSnapshot` | A frozen copy of the seller's legal identity as of order completion — see §4. |
| `FiscalReceipt` / `FiscalReceiptItem` | The provider-neutral fiscal receipt and its 54-FZ line items — see [fiscalization.md](fiscalization.md). |

## 2. Legal profile

`StoreLegalProfile` is a **separate domain model**, not fields bolted
onto `Store` — a store's legal identity is optional (most stores using
this platform outside Russia will never have one) and versionable
independently of the store record itself.

`LegalEntityType` is a closed enum: `LegalEntity` (ООО/АО — a company),
`IndividualEntrepreneur` (ИП), `SelfEmployed` (самозанятый). INN length
and check-digit algorithm both depend on this type (10 digits/1 check
digit for `LegalEntity`, 12 digits/2 check digits for the other two) —
`InnKppValidator` implements the real ФНС (Federal Tax Service) modulo-11
checksum algorithms for both, not a digit-count check. KPP is 9 digits,
format-only (no checksum exists for it), and only ever applies to
`LegalEntity` — `CreateOrUpdateLegalProfile` requires a valid KPP for a
`LegalEntity` profile and silently nulls it for the other two types
(spec: "kpp nullable"). OGRN (13 digits, legal entities)/OGRNIP (15
digits, entrepreneurs) are validated by digit count only this
milestone — both have real checksum algorithms a future milestone can
add if a registry-lookup integration ever needs one.

## 3. Address and phone normalization

`RussianAddress` is a **readonly value object**, not an Eloquent model —
it's always owned by something else (currently `StoreLegalProfile.legal_address`/
`actual_address`, stored as `jsonb`). Its fields (`country_code`,
`postal_code`, `region`, `district`, `city`, `settlement`, `street`,
`house`, `building`, `apartment`, `raw_address`) are more granular than
the platform's existing generic address shape.

`RussianAddressMapper` bridges the two **without modifying** the
existing `CustomerAddress`/`OrderAddress`/`CheckoutAddress` tables:
`toGenericLines()` flattens a `RussianAddress` into the generic
`address_line1`/`address_line2` shape those tables already use;
`fromGenericLines()` is the reverse and is **explicitly lossy** —
granular fields (street vs. house vs. building) can't be reliably
recovered from flattened free text, so it's a best-effort parse, not a
guaranteed round-trip.

`RussianPhoneNormalizer::normalize()` accepts `8XXXXXXXXXX`,
`7XXXXXXXXXX`, `+7XXXXXXXXXX` (with or without common separators) and
always produces the same canonical `+7XXXXXXXXXX` form — identity and
lookup must always use this normalized form, per spec **never a
display-formatted string**. `format()` exists purely for UI rendering
and is never what gets stored, compared, or looked up by.

## 4. VAT model

`VatRate` is a closed enum: `None`, `Zero` (`vat_0`), `Five` (`vat_5`),
`Seven` (`vat_7`), `Ten` (`vat_10`), `Twenty` (`vat_20`). `None` (not a
VAT payer — e.g. a merchant on a simplified tax regime) is a distinct
concept from `Zero` (a real 0% rate on specific goods/exports) even
though both charge no VAT amount, because a fiscal receipt must report
*which one* to the tax authority — `percentage()` returns `null` for
`None` specifically because "no VAT" has no percentage to compute
against, not a 0-shaped rate. `fiscalVatCode()` maps each rate to the
real 54-FZ integer code a cash-register/OFD provider expects.
`amountFromInclusiveTotal()` back-calculates the VAT portion of a
VAT-inclusive total (Russian receipts always state the customer-facing
price as already including VAT).

A product/variant's VAT treatment is **not hardcoded** — it's resolved
through `ResolveProductFiscalProfile`, in order: a variant-level
`ProductFiscalProfile` override → a product-level `ProductFiscalProfile`
→ the store's `FiscalizationSettings.default_vat_rate` (plain
`Commodity` payment subject). `ProductFiscalProfile` is polymorphic over
`Product`/`ProductVariant` via a `FiscalizableType` enum, mirroring the
existing `MediaEntityType` pattern exactly — no new coupling to Catalog.

## 5. Order integration

Every completed order gets an `OrderFiscalSnapshot` — a **frozen copy**
of the store's legal identity and receipt requirement at the moment of
completion, written once by `BuildOrderFiscalSnapshot` inside
`CompleteCheckout`'s own transaction (spec section 11: "Historical
orders must not change if merchant legal profile changes later"). If a
store has no `StoreLegalProfile` configured, `BuildOrderFiscalSnapshot`
returns `null` and no snapshot row is written — Russian Commerce stays
an entirely opt-in bolt-on for stores that never touch it.

The snapshot's `vat_rate` column is a single representative label (the
rate responsible for the largest share of the order's VAT amount) — an
order can legitimately mix VAT rates across lines, and per-line VAT is
what actually drives `FiscalReceiptItem` later (see
[fiscalization.md](fiscalization.md) §2), never the snapshot.

## 6. Payment methods

`RussianPaymentMethod` (`bank_card`, `sbp`, `bank_transfer`, `cash`,
`credit`) is a new nullable `Payment.payment_method` column, cast to
this enum, **distinct** from `Payment.provider` (which payment gateway
processed it) and from `FiscalReceiptItemPaymentMethod`
(`full_payment`/`prepayment`/`advance`/`credit` — the 54-FZ line-item
"способ расчёта," a different concept despite the overlapping name).
`Payment.method_metadata` (nullable `jsonb`) holds a method-specific
value object when relevant:

- `SbpPaymentMetadata` — QR/deeplink metadata and a provider
  confirmation URL, for the (not-yet-real) SBP flow.
- `BankTransferMetadata` — invoice number, legal entity details,
  payment purpose, due date, for the (not-yet-final) bank-transfer/
  invoice flow.

`PaymentMethodSettings.enabled_methods` (one row per store) is the
merchant-facing on/off switch for each method — no provider makes a
real HTTP call for any of them this milestone.

## 7. Not implemented this milestone

Per spec, explicitly out of scope:

- YooKassa, CDEK, or any other real payment/shipping provider HTTP
  integration.
- Real OFD integration (ATOL, OrangeData, CloudKassir) — see
  [fiscalization.md](fiscalization.md) §6.
- Real SBP QR/deeplink generation or bank confirmation.
- Legally final bank-transfer/invoice accounting documents.
- 1C, MoySklad, or any marketplace synchronization API.
- OGRN/OGRNIP checksum validation (format-only this milestone).
- Refund/correction fiscal receipts (architecture prepared, not
  issuable — see [fiscalization.md](fiscalization.md) §6).

## 8. Tenant isolation and security

Every model in this domain uses the existing `BelongsToTenant` trait —
no new isolation mechanism. `FiscalizationProvider.credentials` uses
Eloquent's `'encrypted'` cast (mirroring `WebhookSubscription.secret`),
kept as a separate column from the plain `config` jsonb so a future
provider's non-secret configuration stays readable/editable in the
admin UI while its credentials never round-trip in plaintext.
`FiscalizationProviderResource` never serializes `credentials` — only a
`has_credentials` boolean. INN/KPP and full addresses are never logged
by any Application service in this domain; the only place they appear
in a log line is `FiscalizationNotConfiguredException`'s own message
(a store id, not personal/legal data).

See also: [fiscalization.md](fiscalization.md), [ADR-030](../adr/030-russian-commerce-foundation.md).

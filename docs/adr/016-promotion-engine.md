# ADR-016: Promotion Engine — a Provider-Neutral Discount Layer Owned by One Orchestrator

## Status
Accepted

## Context
Milestone 10's brief is explicit that the Discount & Promotion Engine
"must be fully provider-neutral," "must not depend on any payment
provider," "must not contain storefront-specific logic," and must be
"reusable from: Checkout, Draft Orders, Admin, Future APIs." It also
mandates that "Checkout must never calculate discounts itself" — the
engine is the one authority, called with `(Cart, Customer, Shipping)` and
returning `(AppliedDiscounts, FinalTotals)`.

Unlike Payments/Shipping (ADR-012), there is no external counterparty
here — a discount is entirely the platform's own computation, so the
provider-abstraction pattern (a `Contract` + `Registry` + swappable
implementations) doesn't apply. The real design questions were: (1) how
to decompose "Automatic Promotion / Discount Code / Free Shipping / Fixed
Amount / Percentage / Buy X Get Y" — the spec's flat list of "Promotion
Types" — into an actual schema; (2) how rule/action parameter shapes,
which vary per type, get stored and validated; (3) whether an update to a
Promotion's rules/actions can safely wholesale-replace them, given that
usage/snapshot records exist; and (4) how stacking/priority conflict
resolution actually works.

## Options considered

**Promotion Types decomposition:**
1. One flat `type` enum on `Promotion` covering all six listed types
   (`automatic`, `code`, `free_shipping`, `fixed_amount`, `percentage`,
   `buy_x_get_y`), with type-specific columns/logic branching on it.
2. Recognize that the six are actually two orthogonal axes — *how a
   Promotion is activated* (`trigger_type`: automatic vs. code) and *what
   it does* (`PromotionAction.type`: free shipping, fixed/percentage off,
   free product, ...) — and model `Promotion` + child `PromotionRule`/
   `PromotionAction` rows accordingly, with "Buy X Get Y" as a rule+action
   pairing rather than a distinct entity.

**Rule/action parameter storage:**
1. A wide `PromotionRule`/`PromotionAction` table with one nullable
   column per possible parameter across every type.
2. `type` (enum-backed string) + `parameters` (jsonb), validated per-type
   in the FormRequest — the pattern this codebase has no prior exact
   precedent for (closest analog: `PaymentTransaction.metadata`, which is
   generic/untyped, not type-validated).

**Update semantics for rules/actions:**
1. Diff incoming rules/actions against existing rows, preserving ids for
   unchanged ones.
2. Wholesale-replace on update (delete all, recreate), mirroring
   `UpdateShippingZone`/`ShippingZoneRegion`.

**Stacking/conflict resolution:**
1. A single boolean "combinable" flag with implicit first-eligible-wins
   ordering.
2. `stacking_mode` (`stackable`/`exclusive`) + integer `priority`: an
   eligible exclusive Promotion always wins alone (best-of-ties by
   priority, then computed discount amount); with none eligible, every
   eligible stackable Promotion applies in ascending priority order.

## Decision
Option 2 for all three: `trigger_type`/`PromotionAction.type` as
orthogonal axes; `type` + `parameters` jsonb for both `PromotionRule` and
`PromotionAction`; wholesale-replace-on-update for both; and
`stacking_mode` + `priority` for conflict resolution.

**Promotion Types.** Modeling "Automatic Promotion" and "Discount Code"
as `trigger_type` values, and "Free Shipping / Fixed Amount / Percentage
/ Buy X Get Y" as `PromotionActionType` values, lets a single Promotion
combine multiple actions (e.g. 15% off *and* free shipping in one
Promotion) and lets "Buy X Get Y" reuse the exact same rule engine every
other condition uses (a `product`/`order_quantity` rule gates the `free_product`
action) instead of inventing a bespoke "buy X get Y" entity with its own
one-off matching logic. Option 1 would have forced an artificial choice
between "this Promotion is a code" and "this Promotion gives free
shipping" when a merchant obviously wants both at once.

**jsonb parameters.** Given 11 rule types and 6 action types, each with
genuinely different parameter shapes (`{amount}`, `{product_ids}`,
`{percent, target, product_ids}`, `{product_variant_id, quantity}`, ...),
a wide nullable-column table would need dozens of columns, most always
null for any given row, with no compile-time or query-time guarantee
that a `min_subtotal` rule doesn't also have a stray `product_ids` value
sitting unused. `type` + `parameters` keeps the table narrow and puts
per-type shape documentation in one place (`PromotionRuleType`/
`PromotionActionType`'s own docblocks), validated by the FormRequest
(`rules.*.parameters` as array; deeper per-type validation left to
`RuleEngine`/`ActionEngine`'s own defensive `??`/`?? []` defaults rather
than a second parallel validation layer — a malformed parameter set
degrades to "this rule/action contributes nothing," never a fatal error,
which is the same defensive posture `CalculateShippingRates` already
takes toward a misconfigured `ShippingMethod`).

**Wholesale replace.** Verified safe before choosing it: neither
`PromotionUsage` nor `DiscountApplication` — the two tables that persist
past a Promotion's own edits — ever reference a specific `PromotionRule`
or `PromotionAction` row id. They reference the `Promotion` (and
`DiscountCode`) itself, plus a denormalized snapshot copy of whatever
mattered at redemption time (`promotion_name`, `code`, `action_type`,
`amount`). A rule/action row's id is purely internal to one evaluation
pass and never leaks into a persisted record, so the same
delete-and-recreate simplicity `ShippingZoneRegion` already established
applies here without the caveat flagged during design (re-checked
explicitly, since promotion evaluation touching money made it worth
confirming rather than assuming).

**Stacking + priority.** A plain boolean "combinable" flag can't express
"this promotion should win over everything else in the store, but only
if it actually beats what else is eligible" — real discount engines
(the spec's own "priority rules" + "conflict resolution" requirements)
need an ordering, not just a yes/no. `priority` (ascending, lower number
wins ties among exclusives) plus `stacking_mode` covers every case the
Definition of Done lists — automatic discounts, coupon codes, free
shipping, Buy X Get Y, priority rules, stacking rules — with two small
columns and one `resolveStacking()` method, rather than a more general
(and, for this milestone's scope, unneeded) rule-priority DSL.

## Consequences

### Positive
- `PromotionEngine` has exactly one call site pattern
  (`BuildPromotionContext` → `PromotionEngine::handle()` →
  `PromotionEvaluationResult`) reused identically by `OpenCheckout`,
  `SelectShippingRate`, `ApplyDiscountCode`, `RemoveDiscountCode`,
  `CompleteCheckout`, and the admin `PreviewPromotions` tool — Checkout
  genuinely never computes a discount itself, satisfying the spec's
  explicit constraint structurally, not by convention.
- Adding a new rule or action type is additive: one new enum case, one
  new `match` arm in `RuleEngine`/`ActionEngine`, no migration.
- The Order snapshot (`DiscountApplication`) is fully decoupled from live
  Promotion/DiscountCode state by design — deactivating or renaming a
  Promotion after the fact never changes what a placed Order displays,
  the same guarantee `OrderItem`'s `product_title`/`unit_price_amount`
  snapshot already gives for catalog changes.

### Negative
- `parameters` jsonb means a typo in an admin-submitted rule/action
  (e.g. `"ammount"` instead of `"amount"`) silently produces a
  zero-effect rule/action rather than a validation error at save time —
  accepted as the same tradeoff `PaymentTransaction.metadata` already
  makes elsewhere in this codebase, and mitigated by the admin preview
  tool (`POST /promotions/preview`) letting a merchant verify a
  Promotion actually does something before relying on it.
- Only one `DiscountCode` can be attached to a checkout at a time today
  (`Checkout.discount_code_id` is a single nullable FK, not a
  many-to-many) — multiple *automatic* promotions can still stack freely,
  but "stack two different coupon codes" isn't supported. Flagged as
  future extensibility in docs/architecture/promotions.md §12 rather than
  built now, since the spec's own storefront requirements ("enter
  discount code, remove code") describe a single active code.

## Security Requirements
- `Promotion`, `PromotionRule`, `PromotionAction`, `DiscountCode`,
  `PromotionUsage`, and `DiscountApplication` all use `BelongsToTenant` —
  verified with dedicated cross-tenant tests, not just individually
  scoped resources (`AdminPromotionApiTest`, `CheckoutDiscountCodeTest`).
- Discount-code redemption is concurrency-safe under real PostgreSQL
  connections: `CompleteCheckout` locks the `DiscountCode` row before
  evaluating eligibility, so two simultaneous completions racing a
  single-use code can never both redeem it — proven in
  `tests/Concurrency/DiscountCodeConcurrencyTest.php`.
- `ApplyDiscountCode` validates eligibility (including that the cart
  actually earns a discount from the code) *before* persisting
  `discount_code_id` onto the Checkout — a code is never "applied" for a
  cart that doesn't qualify, closing off a path to a misleading
  UI/checkout-total mismatch.

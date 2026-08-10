# Promotions Architecture

Milestone 10 — Discount & Promotion Engine. Provider-neutral by nature (no
external provider exists to abstract — see [ADR-016](../adr/016-promotion-engine.md)
for why this differs from the Payments/Shipping provider-abstraction
pattern). Reusable from Checkout, Draft Orders, Admin, and any future API:
every call site goes through one `PromotionEngine`, never computes a
discount itself.

## 1. Promotions domain

A dedicated `App\Domain\Promotions` module, same directory shape as every
other domain (`Models`, `Enums`, `Application`, `Http\{Controllers,
Requests,Resources}`, `Support`, `Exceptions`).

```text
Promotion              Promotions\Models   name, trigger_type, stacking_mode, priority, status, date range
PromotionRule          Promotions\Models   one AND-ed condition (type + jsonb parameters)
PromotionAction        Promotions\Models   one effect (type + jsonb parameters)
DiscountCode           Promotions\Models   a redeemable code belonging to a Promotion
PromotionUsage         Promotions\Models   append-only redemption ledger (concurrency/usage-limit source of truth)
DiscountApplication    Promotions\Models   Order's immutable discount snapshot (see §7)
PromotionContext       Promotions\Support  PromotionEngine's sole input — cart/customer/shipping facts
PromotionLine          Promotions\Support  one cart line's promotion-relevant facts
AppliedDiscount         Promotions\Support  one computed PromotionAction effect
PromotionEvaluationResult Promotions\Support PromotionEngine's sole output — AppliedDiscounts + FinalTotals
RuleEngine              Promotions\Support  evaluates a Promotion's rules against a PromotionContext
ActionEngine            Promotions\Support  computes a Promotion's actions' effects
PromotionEngine         Promotions\Support  the orchestrator — never persists (mirrors CalculateShippingRates)
```

## 2. Promotion types — trigger vs. shape

The spec lists "Automatic Promotion, Discount Code, Free Shipping, Fixed
Amount, Percentage, Buy X Get Y" together as one set of "Promotion
Types," but these are actually two different axes:

- **How it's activated** (`Promotion.trigger_type`): `automatic` (applies
  on its own once its rules pass) or `code` (only applies when a matching,
  usable `DiscountCode` is attached to the checkout).
- **What it does** (`PromotionAction.type`): `free_shipping`,
  `fixed_amount_off`, `percentage_off`, `free_product` (the "Buy X Get Y"
  shape), `line_item_discount`, `order_discount`.

A Promotion is `trigger_type` + any number of `PromotionRule`s (all must
pass) + any number of `PromotionAction`s (all that pass are applied
together). "Buy X Get Y" is not a distinct entity — it's a `product` or
`order_quantity` rule (the "Buy X" condition) paired with a
`free_product` action (the "Get Y" effect).

## 3. Rule engine

`RuleEngine::passes(Promotion, PromotionContext): bool` — every rule on a
Promotion must pass; there is no OR-grouping or nesting. Supported types
(`PromotionRuleType`, parameter shapes documented on the enum itself):
`min_subtotal`, `product`, `collection`, `category`, `customer`,
`country`, `currency`, `order_quantity`, `order_total`, `date_range`,
`usage_limit`.

Every rule type except `usage_limit` evaluates purely against the
in-memory `PromotionContext` — no database query. `usage_limit` is the
one exception: it reads `PromotionUsage` (total redemptions, and
per-customer redemptions) to cap an *automatic* promotion's total uses,
independent of any `DiscountCode`'s own `usage_limit`/`per_customer_limit`
(which are enforced separately — see §6).

## 4. Action engine

`ActionEngine::apply(Promotion, PromotionContext, ?DiscountCode):
Collection<AppliedDiscount>` — computes every action's effect
independently. Percentage/fixed amounts are always calculated against the
*original* context figures (subtotal, or a matching line's own total),
never against a running already-discounted balance: this keeps a
stacked promotion's output independent of what order it's evaluated in,
rather than compounding off a previous promotion's discount.

`percentage_off`/`fixed_amount_off`/`line_item_discount` share one
targeting rule: no `product_ids`/`collection_ids`/`category_ids` selector
and no explicit `target` means "the whole order"; a selector (or
`line_item_discount`, whose entire purpose is targeting) means "matching
cart lines only," each capped at its own line total. `free_shipping`
discounts the full current shipping amount. `free_product` looks up a
specific `product_variant_id` already in the cart and discounts
`min(requested quantity, cart quantity) * unit price`.

## 5. PromotionEngine — evaluation and conflict resolution

```
PromotionEngine::handle(PromotionContext): PromotionEvaluationResult
```

Input (`PromotionContext`, built by `BuildPromotionContext` from a real
Cart/Checkout, or hand-built by `PreviewPromotions` for the admin preview
tool): cart lines (with resolved collection/category membership),
subtotal, shipping amount, currency, country, customer, and the
currently-applied `DiscountCode` (if any). Output
(`PromotionEvaluationResult`): every `AppliedDiscount` and the single
`discountAmount` Checkout/Order subtract from their total. Never
persists anything — the same shape as `CalculateShippingRates`.

Steps:
1. Load every `active`, in-date-range Promotion (with its rules/actions
   eager-loaded).
2. A Promotion is **eligible** when: its rules all pass (§3), and — for a
   `code`-triggered Promotion — the context's applied `DiscountCode`
   belongs to it and is itself still usable (active, unexpired, under its
   usage/per-customer limits).
3. **Stacking/conflict resolution** (spec section 5): if any eligible
   Promotion is `exclusive`, it always wins alone — when several
   exclusives are eligible at once, the one with the lowest `priority`
   number is kept (ties broken by whichever computes the larger
   discount). With no exclusive eligible, every eligible `stackable`
   Promotion applies, evaluated in ascending `priority` order.
4. Compute every selected Promotion's actions (§4), sum their amounts,
   and cap the total at `itemsSubtotal + shippingAmount` (a promotion can
   never make a total negative).

## 6. Discount codes

`DiscountCode.code` is always normalized to uppercase on write
(`setCodeAttribute`), so lookup (`DiscountCode::findByCode()`) is a plain
case-insensitive match without a citext/lower() index. A Promotion
supports "single code, multiple codes" (spec section 6) simply by having
many `DiscountCode` rows — there is no separate bulk-generate endpoint;
the admin creates one at a time.

A code is usable when: `status = active`, not expired, `usage_count <
usage_limit` (when set), and the requesting customer hasn't already hit
`per_customer_limit` (checked against `PromotionUsage`). Redemption is
counted only at order completion (§7), inside a transaction that first
locks the `DiscountCode` row — see §9 for why.

## 7. Order snapshot

Checkout never calculates its own discount (spec section 7) — every call
site that needs one (`OpenCheckout`, `SelectShippingRate`,
`ApplyDiscountCode`, `RemoveDiscountCode`) delegates to
`RecalculateCheckoutTotals`, a thin wrapper that builds a
`PromotionContext` and persists whatever `PromotionEngine` returns.
`CompleteCheckout` does its own final, authoritative evaluation instead
of trusting whatever is cached on the Checkout row — the same pattern it
already uses for shipping (`RevalidateShippingQuote`): a stale/racing
cached value is never trusted at the moment that matters.

At completion, for every `AppliedDiscount` PromotionEngine returned:
- One `DiscountApplication` row is created, carrying its **own copy** of
  `promotion_name` and `code` (not a live `promotion_id`/`discount_code_id`
  lookup) — a later rename or deactivation of the Promotion/DiscountCode
  can never change what an already-placed Order displays. `order_item_id`
  is set only for a line-item-targeted action (mapped from the
  `AppliedDiscount`'s `productVariantId` back to the `OrderItem` just
  created for that variant).
- One `PromotionUsage` row is created per distinct Promotion applied
  (summing that Promotion's own actions' amounts) — the append-only
  record `usage_limit`/`per_customer_limit`/the `usage_limit` rule type
  are all checked against.
- If a `DiscountCode` was used, its `usage_count` is incremented and a
  `DiscountCodeRedeemed` event is recorded via the existing outbox
  mechanism (`RecordOutboxEvent`) — the same pattern `OrderCreated`
  already uses.

`ApplyDiscountCode`/`RemoveDiscountCode` record `PromotionApplied`/
`PromotionRemoved` outbox events too (spec section 11's timeline
requirement) — scoped specifically to the explicit, customer-initiated
code apply/remove action, not to every automatic-promotion recalculation
(which happens on effectively every checkout request and would make the
timeline unreadable noise).

## 8. Admin API

`GET/POST/PATCH /promotions`, `GET /promotions/{promotion}`, `GET
/promotions/{promotion}/usage`, `POST /promotions/preview`,
`GET/POST /discount-codes`, `PATCH /discount-codes/{discountCode}` — no
`destroy` on either resource, the same pragmatic bar as
`shipping-zones`/`shipping-methods`: a Promotion/DiscountCode is
deactivated via `status`, never deleted, so existing
`DiscountApplication`/`PromotionUsage` snapshots and their foreign keys
stay intact. `rules`/`actions` are wholesale-replaced on update (mirrors
`UpdateShippingZone`/`ShippingZoneRegion`) — safe because nothing
references a specific rule/action row's id; `PromotionUsage`/
`DiscountApplication` only ever reference the Promotion itself.

`POST /promotions/preview` (`PreviewPromotions`) builds a
`PromotionContext` from a hypothetical `{product_variant_id, quantity}`
list instead of a real Cart, then runs the exact same `PromotionEngine` —
an admin can check "what would apply" without creating anything.

## 9. Storefront API and concurrency

`POST/DELETE /storefront/checkout/discount-code`. `ApplyDiscountCode`
validates the code is usable *and* that this exact cart actually earns a
discount from it (evaluating `PromotionEngine` before persisting
anything) — a code that's active/unexpired/under its limit but whose
Promotion's rules the cart doesn't meet (e.g. a minimum subtotal) is
rejected the same way an unknown code is, rather than silently "applying"
for zero.

Concurrency (spec section 13): `CompleteCheckout` locks the
`DiscountCode` row (`lockForUpdate`) before evaluating `PromotionEngine`
— the same locked-row pattern `AllocateOrderNumber` uses for its counter,
just without a separate sequence table since gap-free numbering isn't
needed here. Two concurrent completions racing the same single-use code
serialize on that lock: the second sees the first's committed
`usage_count` and `PromotionEngine` naturally excludes the code as no
longer usable, causing that completion to fail outright (the whole
transaction rolls back — not "an order without the discount"; genuinely
no order at all). Proven under a real Postgres connection per side in
`tests/Concurrency/DiscountCodeConcurrencyTest.php` (mirrors
`ReservationConcurrencyTest`'s fork-based pattern).

## 10. Tenant isolation

`Promotion`, `PromotionRule`, `PromotionAction`, `DiscountCode`,
`PromotionUsage`, and `DiscountApplication` all use `BelongsToTenant` —
every query is scoped to the active store and fails closed with no
active tenant. Verified with dedicated cross-tenant tests
(`AdminPromotionApiTest`, `CheckoutDiscountCodeTest`): Store A can never
list, read, update, or redeem a Store B promotion, discount code, or
usage record.

## 11. Explicitly not implemented

No loyalty program, no gift cards, no subscriptions, no marketplace
promotions (spec section 15).

## 12. Future extensibility

- **Boolean rule grouping** (OR/nested conditions): today all rules on a
  Promotion are AND-ed. If a merchant ever needs "country is RU OR
  customer is in this list," `PromotionRule` would need a `group`/`logic`
  column and `RuleEngine::passes()` would need to fold groups instead of
  a flat `foreach`.
- **Per-line discount on OrderItem**: `DiscountApplication.order_item_id`
  already links a line-targeted discount to its `OrderItem`, but
  `OrderItem` itself carries no discount column — Order/Checkout's
  existing single `discount_amount` field remains the only aggregate.
  Adding one (e.g. for a line-item receipt breakdown) is additive and
  doesn't change `PromotionEngine`'s contract.
- **Multiple simultaneous discount codes**: today a Checkout holds at
  most one `discount_code_id`. `PromotionContext.appliedDiscountCode`
  would need to become a collection, and `PromotionEngine`'s eligibility
  check would need to check membership instead of equality — the rest of
  the evaluation/stacking logic is already code-count-agnostic.
- **Type-specific evaluator classes**: `RuleEngine`/`ActionEngine` are
  each one class with a `match` per type today, deliberately (11 rule
  types, 6 action types — a registry-of-resolver-classes per type, the
  way `ShippingProviderRegistry` does for providers, would be premature
  for this many, simply-shaped cases). If rule/action types grow
  significantly, or a merchant-facing custom-rule plugin system is ever
  wanted, splitting into a registry the same way is a mechanical,
  additive refactor — the enum-typed `parameters` jsonb column already
  isolates each type's shape.

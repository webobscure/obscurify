# ADR-024: Customer Intelligence — Recompute-From-Scratch Metrics, a Single Rule Engine Shared by Groups and Segments, Direct Calls Instead of Pub/Sub

## Status
Accepted

## Context

Milestone 18 introduces Customer Groups, Customer Segments (rule-based),
Customer Tags, Customer Metrics, and Customer Snapshots — collectively
"Customer Intelligence" — on top of the Customer Identity layer
(ADR-022). The spec requires: dynamic segments built from a merchant-
facing rule engine (AND/OR, nesting, comparison/date/numeric/string/
boolean operators); metrics that stay current as `Order`/`Payment`/
`Refund`/`ReturnRequest` state changes, "incrementally... via the Event
Bus"; a Promotions integration with "no direct SQL coupling"; and
automation-trigger events exposed "through the Platform Event Bus" for
a not-yet-built automation engine.

Three design questions dominated the implementation: how metrics should
be kept current given the codebase has no internal pub/sub mechanism to
literally "subscribe" to; whether Groups and Segments need two rule
engines or one; and how a group's `in_group` rule condition can query
group membership without creating a circular dependency between the
rule engine and the membership-lookup facade that wraps it.

## Decision 1: Metrics are recomputed from scratch, not incremented

**Options considered:**

1. Incremental updates — an `OrderPaid` event adds its amount to
   `total_spent_amount`, a `RefundCompleted` event subtracts, etc. This
   is what "incrementally... via the Event Bus" in the spec text most
   literally suggests.
2. Full recompute from scratch on every triggering event — sum
   `Payments`/`Refunds`/`Returns` fresh each time, exactly the way
   `RecomputeOrderFinancialStatus` already computes `Order.financial_status`
   on every relevant change rather than incrementing a running balance.

**Decision: full recompute (option 2).**

Incremental updates require every mutation path to remember to adjust
the running total correctly, forever — a `Refund` that gets reversed, a
manually-corrected `Payment`, a backfilled historical order, or any bug
in an increment call site silently drifts the stored number away from
truth with no self-correcting mechanism. A full recompute is idempotent:
running it twice, or after any out-of-order event delivery, or after a
manual data fix, always converges on the same correct number. This
codebase already made this exact tradeoff for `Order.financial_status`
and it has held up; Customer Metrics reuses the same discipline rather
than introducing a second, riskier pattern. The cost — recomputing from
a customer's full order history on every trigger rather than doing O(1)
arithmetic — is acceptable because triggers are per-customer,
per-order-lifecycle events (checkout, payment, return), not high-frequency.

"Incrementally... via the Event Bus" in the spec is satisfied at the
call-site granularity (only the affected customer is recomputed, not a
full-table sweep) rather than at the arithmetic granularity.

## Decision 2: One rule engine, shared by Groups and Segments

**Options considered:**

1. Two separate rule evaluators — `GroupRuleEngine` and
   `SegmentRuleEngine` — since the spec lists them as distinct core
   entities with distinct example use cases.
2. One `SegmentRuleEngine`, with `SegmentRule` rows attached
   polymorphically (`segmentable_type`/`segmentable_id`) to either a
   `CustomerGroup` (when `type = dynamic`) or a `CustomerSegment`.

**Decision: one engine (option 2).**

A rule tree evaluates identically regardless of which entity owns it —
"AND/OR, nested groups, comparison/date/numeric/string/boolean
operators" is one specification, not two. Building two evaluators would
mean duplicating `SegmentRuleFieldRegistry` and
`SegmentRuleConditionEvaluator` (or worse, subtly diverging them over
time) for no behavioral difference. The polymorphic pair follows this
codebase's existing convention of manual fixed-string type discriminators
over Eloquent's morph-map magic (matching `PlatformEventCatalog`-adjacent
patterns elsewhere), which keeps the stored `segmentable_type` value
explicit and greppable rather than implicit in a runtime-registered map.

## Decision 3: `SegmentMembership` is a one-way facade; the engine resolves `in_group` directly

The `in_group` condition needs to answer "is this customer a member of
group X" — which sounds exactly like what `SegmentMembership` (the
facade every other caller, including Promotions, uses for this
question) already does. Making `SegmentRuleEngine` depend on
`SegmentMembership` for this one case creates a cycle:
`SegmentMembership` depends on `SegmentRuleEngine` to evaluate dynamic
groups, and the engine would depend back on `SegmentMembership` to
evaluate `in_group` conditions.

**Decision:** `SegmentRuleEngine` resolves `in_group` by querying
`CustomerGroup`/`CustomerGroupMember` directly (with a `$visitedGroupIds`
guard against a group whose rule tree references itself, directly or
through a chain of other dynamic groups) rather than going through
`SegmentMembership`. `SegmentMembership` remains a one-way facade over
the engine — everything *outside* this module talks to membership only
through `SegmentMembership`, and the engine never depends on it. This
keeps the dependency graph a DAG: `SegmentMembership` → `SegmentRuleEngine`
→ (`CustomerGroup`/`CustomerMetric`/`Customer`, directly).

## Decision 4: No internal pub/sub — direct synchronous calls, same as every other cross-domain reaction

The spec's "Event Integration: subscribe to OrderCreated/OrderPaid/
RefundCompleted/CustomerCreated/CustomerUpdated/ReturnCompleted" implies
a subscriber model. This codebase has none — the Outbox/`OutboxEvent`
mechanism (`RecordOutboxEvent` → `ProcessOutboxEventsCommand`) exists
specifically for *external* delivery (webhooks to installed Apps), not
for one internal domain to react to another's writes. The established
precedent for "domain A reacts to domain B's completion," set by the
Financial Ledger calling `RecomputeOrderFinancialStatus` directly inside
the same transaction, is what Customer Intelligence follows:
`RecomputeCustomerMetrics::handle()` is called directly, synchronously,
in the same transaction as the triggering write, from `CompleteCheckout`,
`RecomputeOrderFinancialStatus` (covering both `OrderPaid` and
`RefundCompleted`), `CompleteReturn`, `RegisterCustomer`, and
`UpdateCustomerProfile`. Building a real pub/sub layer for this one
milestone would be new infrastructure with no other consumer, contradicting
this codebase's repeated preference (see ADR-022's equivalent decision
for `CustomerEvent`) for reusing what already exists over building a
parallel mechanism.

The automation-facing events the spec does ask to be "exposed... through
the Platform Event Bus" (`CustomerEnteredSegment`, `CustomerBecameVip`,
etc. — for a *future*, not-yet-built automation engine to subscribe to)
are handled correctly by the existing Outbox path, since that path's
actual job — recording a durable, externally-observable fact — is
exactly what those events are for.

## Consequences

- Metrics recomputation cost scales with a customer's order history on
  every trigger; acceptable at expected trigger frequency, would need
  revisiting (e.g. capping the lookback window) if a customer
  accumulates an extreme order count.
- Groups and Segments share one validation/evaluation code path, so a
  rule-engine bug or enhancement (a new operator, a new field) applies
  to both automatically — but also means the two entities can never
  diverge in rule capability without an explicit new discriminator.
- `in_group` resolution bypasses `SegmentMembership`'s cached
  `CustomerSegmentMembership` table and always re-evaluates live,
  trading a small amount of consistency (a nested dynamic group's
  membership is evaluated fresh, not from the cache) for avoiding the
  circular dependency; the cache is still authoritative for direct
  admin-facing membership counts/search (`withCount('computedMemberships')`).
- Cross-domain reactions in this codebase remain "know your caller's
  application-service class and call it directly," not "publish and
  don't know who's listening." This keeps failures loud and
  synchronous (a metrics recompute failure fails the checkout
  transaction, which is correct — order data and derived metrics
  should never observably diverge) at the cost of tighter coupling
  between domains than a real event bus would give.

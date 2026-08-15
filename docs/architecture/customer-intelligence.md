# Customer Intelligence — Groups, Segmentation, Tags, Metrics

## 1. Overview

Milestone 18 adds a dedicated Customer Intelligence module on top of the
Customer Identity layer (Milestone 16). It answers three merchant
questions the CRM record alone cannot: *which fixed cohort is this
customer in* (Groups), *which computed cohort does this customer
currently match* (Segments, via a rule engine), and *what does this
customer look like numerically* (Metrics/Snapshots). None of it touches
authentication, orders, or checkout directly — it reads from them and
is read by Promotions and admin search.

Core entities, all under `App\Domain\CustomerIntelligence`:

| Entity | Purpose |
|---|---|
| `CustomerGroup` | A named cohort — manual (admin adds/removes members), dynamic (membership computed by a rule tree, same engine as Segments), or protected (system-managed, e.g. auto-tag-driven; cannot be deleted or have members added manually while protected). |
| `CustomerSegment` | A named, always-dynamic cohort defined entirely by a rule tree. |
| `SegmentRule` | One node of a rule tree — a condition (`field`/`operator`/`value`) or a group (`boolean_operator` + `children`). Shared by both Groups and Segments via a polymorphic `segmentable_type`/`segmentable_id` pair. |
| `CustomerTag` | A short label, manually assignable or system-assigned (VIP, First Order, Repeat Customer, Inactive — see §4). |
| `CustomerTagAssignment` | Join row between `Customer` and `CustomerTag`, records `source` (manual vs. system) and, for system tags, which rule fired. |
| `CustomerMetric` | One row per customer, the current computed numbers (see §3). |
| `CustomerSnapshot` | An immutable point-in-time copy of a `CustomerMetric` row, for trend/timeline views. |
| `CustomerSegmentMembership` | A materialized cache of "this customer currently matches this group/segment," rebuilt on every metrics recompute — exists purely so admin list/search/count queries don't have to re-run the rule engine (see §5). |

## 2. Groups vs. Segments

Both are "a named cohort of customers" and both can hold a rule tree, but
they differ in how membership is established:

- **Manual group**: an admin explicitly adds/removes members
  (`CustomerGroupMember` rows). No rule tree. This is the only path for
  cohorts a computer can't infer — "Wholesale," "Employee," "Influencer."
- **Dynamic group** or **segment**: membership is *computed* from a rule
  tree, recalculated whenever a customer's metrics change (§5). A
  dynamic group behaves identically to a segment; the distinction is
  purely organizational (spec section 3 asks for both concepts, and a
  merchant thinks of "Wholesale" and "High-value repeat customers"
  differently even though the latter could technically be modeled as a
  dynamic group).
- **Protected group**: a group flagged as system-managed. The four
  system tags (§4) create/populate a protected group of the same name
  automatically; protected groups reject `POST .../members` and
  `DELETE` (see `ProtectedCustomerGroupException`, 422).

A group or segment's `rootRules()` relation holds only its *top-level*
nodes (`parent_id IS NULL`); a group node's `children` relation holds
the next level down, recursively. `SegmentRuleTreeLoader` eager-loads
the whole tree in one pass regardless of depth.

## 3. Customer Metrics

`RecomputeCustomerMetrics` is the single place metrics are computed —
always from scratch off `Orders`/`Payments`/`Refunds`/`ReturnRequest`,
never incremented, mirroring the "recompute rather than increment"
discipline `RecomputeOrderFinancialStatus` already established for
`Order.financial_status` (see `docs/adr/024-customer-intelligence.md`
for why incremental updates were rejected).

| Field | Definition |
|---|---|
| `total_spent_amount` | Captured payments minus refunds, across all the customer's orders (net). |
| `average_order_value_amount` | Mean of `Order.total_amount` (gross — deliberately a different basis than total spent). |
| `order_count` | Count of the customer's orders. |
| `refund_count` | Count of completed refunds on those orders. |
| `return_count` | Count of completed returns on those orders. |
| `return_rate_bps` | `return_count / order_count`, stored as basis points (ADR-010-style integer storage; converted to a percent at the resource boundary). |
| `lifetime_value_amount` | Alias of `total_spent_amount` today — true LTV forecasting is explicitly out of scope (§8). |
| `first_order_at` / `last_order_at` | Min/max of the customer's `Order.created_at`. |
| `days_since_last_order` | Derived at read time from `last_order_at`, not stored. |

Every recompute holds a row lock on the `Customer` and the
`CustomerMetric` (`lockForUpdate()`) so concurrent triggers (e.g. an
`OrderPaid` and a `RefundCompleted` landing close together) serialize
rather than race — see `CustomerGroupMembershipConcurrencyTest` for the
sibling concurrency guarantee on group membership writes.

## 4. Automatic ("system") tags

`AutoTagCustomer`, called at the end of every metrics recompute,
assigns/removes four system tags based on thresholds in
`config/customer_intelligence.php`:

- **First Order** — `order_count === 1`.
- **Repeat Customer** — `order_count > 1` (replaces First Order).
- **VIP** — `lifetime_value_amount >= vip_lifetime_value_amount` (default 50000 minor units / $500, matching the spec's "Spent > $500" example).
- **Inactive** — `days_since_last_order >= inactive_after_days` (default 90).

System tags cannot be manually assigned or removed
(`SystemCustomerTagException`, 422) — they only change as a side effect
of a metrics recompute, and each transition emits a Platform Event
(`CustomerTagAssigned`/`CustomerTagRemoved`, and for VIP/Inactive
specifically also `CustomerBecameVip`/`CustomerBecameInactive` — see
§6).

## 5. The Rule Engine

A rule tree is a list of top-level nodes, implicitly ANDed together.
Each node is either:

- a **condition**: `{ field, operator, value }`
- a **group**: `{ boolean_operator: 'and' | 'or', children: [...] }` (recursively)

`SegmentRuleEngine::evaluate()` walks the tree for one customer:

- A condition resolves its `field` via `SegmentRuleFieldRegistry`
  (reads from `Customer`, `CustomerMetric`, or a lazy-loaded
  country/tag lookup bundled in `SegmentEvaluationContext`), then
  applies `operator` via `SegmentRuleConditionEvaluator` — comparison
  (`equals`/`not_equals`/`greater_than`/…), string
  (`contains`/`starts_with`/`ends_with`), set
  (`in_set`/`not_in_set`), boolean (`is_true`/`is_false`), and one date
  operator (`this_month`, for the "Birthday this month" example).
- A group evaluates all `children` and combines them with `and`/`or`,
  recursing for nested groups.
- `field = in_group` is a special case: rather than depending on
  `SegmentMembership` (which would create a circular dependency — see
  ADR-024), the engine queries `CustomerGroup`/`CustomerGroupMember`
  directly, with a `$visitedGroupIds` guard so a group that (mis)references
  itself, directly or transitively, terminates instead of recursing forever.

Available fields (`SegmentRuleField`): `total_spent`,
`average_order_value`, `order_count`, `refund_count`, `return_count`,
`return_rate`, `lifetime_value`, `days_since_last_order`,
`days_since_registration`, `country_code`, `email_verified`,
`date_of_birth`, `has_tag`, `in_group`.

`SegmentMembership` is a one-way facade over the engine
(`isCustomerInGroup`, `isCustomerInSegment`, `customerHasTag`, plus
nullable-safe `...ById`/`...IdHasAnyTag` variants) — everything outside
this module, including Promotions, talks to the engine only through
this facade, never to `SegmentRuleEngine` directly.

## 6. Event integration

There is no internal pub/sub mechanism in this codebase (see ADR-024)
— "subscribing" to `OrderCreated`/`OrderPaid`/`RefundCompleted`/
`CustomerCreated`/`CustomerUpdated`/`ReturnCompleted` means a direct,
synchronous call to `RecomputeCustomerMetrics::handle()` inside the
same DB transaction as the triggering write, at these call sites:

- `Checkouts\Application\CompleteCheckout` — after the order is created.
- `Financial\Application\RecomputeOrderFinancialStatus` — unconditionally, covering both `OrderPaid` and `RefundCompleted` (both flow through here).
- `Returns\Application\CompleteReturn` — resolves the customer via the order (`ReturnRequest.customer_id` is nullable; the order's `customer_id` is not).
- `Customers\Application\RegisterCustomer` and `UpdateCustomerProfile` — cover `CustomerCreated`/`CustomerUpdated`, including the "Registered > 1 year," "Birthday this month," and "Email verified" segment conditions, which depend on customer fields, not order activity.

Automation-facing Platform Events (spec §12 — "expose triggers... through
the Platform Event Bus," for a future automation engine to subscribe to,
not built this milestone): `CustomerTagAssigned`, `CustomerTagRemoved`,
`CustomerEnteredSegment`, `CustomerLeftSegment`, `CustomerBecameVip`,
`CustomerBecameInactive`. These are recorded via the existing
`RecordOutboxEvent`/`OutboxEvent` mechanism, the same path every other
domain uses for webhook delivery — added to `PlatformEventCatalog`
alongside the Milestone 16 customer events, which had shipped without
ever being added to the catalog.

## 7. Promotions integration

`PromotionRuleType` gained four cases: `CustomerGroup`, `CustomerSegment`,
`CustomerTag`, `CustomerMetric`. `Promotions\Support\RuleEngine` now
takes a `SegmentMembership` and evaluates these rule types by delegating
to it (`isCustomerIdInAnyGroup`, `isCustomerIdInAnySegment`,
`customerIdHasAnyTag`, `evaluateCustomerMetricCondition`) — no direct SQL
coupling between the two domains, only calls through the facade.

## 8. Admin search & API surface

`AdminCustomerController::index()` accepts `search`, `status`, `tag`,
`group_id`, `segment_id`, `min_total_spent`, `max_total_spent`,
`min_order_count`, `min_lifetime_value` — every filter optional and
ANDed together server-side. Additional per-customer endpoints:
`GET /customers/{id}/metrics`, `/metrics/history` (snapshots),
`/groups`, `/segments`, `/tags`.

Group/segment/tag management:
`GET|POST /customer-groups`, `GET|PATCH|DELETE /customer-groups/{id}`,
`POST|DELETE /customer-groups/{id}/members[/{customer}]`,
`GET|POST /customer-segments`, `GET|PATCH|DELETE /customer-segments/{id}`,
`GET|POST /customer-tags`, `DELETE /customer-tags/{id}`,
`POST|DELETE /customers/{id}/tags[/{tag}]`.

A rule tree is sent/received as a nested array under `rules` on the
group/segment resource; `Concerns\ValidatesSegmentRuleTree` recursively
validates arbitrary nesting depth via `withValidator()`.

## 9. Snapshots and background recalculation

`CaptureCustomerSnapshot` copies the current `CustomerMetric` row into
an immutable `CustomerSnapshot`, exposed via
`GET /customers/{id}/metrics/history` for a timeline view. The
`customer-intelligence:recompute-metrics` Artisan command
(`--snapshot` flag) recomputes every customer's metrics and optionally
snapshots them in one pass, for scheduled/backfill use — day-to-day
updates are incremental and event-driven (§6), this command exists for
initial backfill and periodic reconciliation, not as the primary update
path.

## 10. Tenant isolation

Every entity in this module uses `BelongsToTenant` (global scope +
`creating()` hook forcing `store_id`, per the existing multi-tenancy
convention). `SegmentRule` and `CustomerSegmentMembership` are
polymorphic (`segmentable_type`/`segmentable_id`) with no direct FK to
`stores`, so tenant isolation for them is verified transitively through
their owning Group/Segment — see `TenantIsolationTest`.

## 11. Explicitly not implemented

Per spec §16: email campaigns, loyalty, product recommendations, AI/ML
features, CRM system integrations, predictive analytics (LTV *forecasting*
specifically — the `lifetime_value_amount` field exists but is an alias
of historical total spent, not a prediction).

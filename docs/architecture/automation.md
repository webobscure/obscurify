# Automation Engine

## 1. Overview

Milestone 19 adds a platform-wide, no-code automation engine — a
Shopify Flow analogue. A merchant builds a **Workflow**: one trigger
(a Platform Event), an optional condition tree, and an ordered list of
actions. Whenever a matching event fires, the engine evaluates the
conditions and, if they pass, runs the actions in order — including
pausing for a delay and resuming later, retrying a failed action with
backoff, and giving up loudly (dead letter) if it never succeeds.

The engine deliberately reuses everything this platform already has:
Platform Events (M11) for triggers, Customer Intelligence (M18) for
segment/group/tag conditions and actions, Promotions (M6) for discount
codes, and the Apps SDK (M12) for third-party extension. No new
subsystem was built where an existing one already solved the problem.

Core entities, all under `App\Domain\Automation`:

| Entity | Purpose |
|---|---|
| `Workflow` | The merchant-facing automation — name, description, lifecycle status, a pointer to its one published version. |
| `WorkflowVersion` | An immutable snapshot of a workflow's trigger/conditions/actions — see §2. |
| `WorkflowTrigger` | The one Platform Event a version listens for. |
| `WorkflowCondition` | One node of a condition tree (leaf or group) — see §4. |
| `WorkflowAction` | One step in a version's ordered action list — see §5. |
| `WorkflowExecution` | One run of a workflow, caused by one matching Platform Event. |
| `WorkflowExecutionStep` | One condition evaluation or action run within an execution — the execution log. |
| `WorkflowVariable` | The variable-picker catalog (built-in + app-contributed) — see §7. |
| `WorkflowTemplate` | A starter workflow definition — see §9. |

## 2. Workflow lifecycle

Four states (`WorkflowStatus`): **Draft**, **Published**, **Disabled**,
**Archived**. A workflow always has at least one `WorkflowVersion`
(created alongside it); `workflows.published_version_id` is the single
source of truth for "only one published version" (spec section 2) — a
pointer, not a status flag scattered across version rows.

**Editing** (`UpdateWorkflow` + `WorkflowVersioning::editableVersion()`):
- A never-published workflow has exactly one version, still `draft` —
  edits mutate it in place.
- A published workflow's edits create a **new** draft version instead
  of touching the live one. That new version is seeded from the
  currently-published version's full content before the caller's
  changes are applied, so a partial `PATCH` (e.g. "just update the
  actions") never silently drops the trigger or conditions — PATCH
  semantics mean the caller isn't required to resend every field.

**Publishing** (`PublishWorkflow`): the current editable version becomes
`published`; whatever was previously published (if any) is demoted to
`archived` in the same transaction, and `workflows.published_version_id`
is repointed. Refuses to publish a version with no trigger, and refuses
a version whose own actions would create an immediately obvious loop
(§13).

**Disable/Enable** (`DisableWorkflow`/`EnableWorkflow`): flips
`Workflow.status` only — the trigger dispatcher checks
`Workflow.status = published`, not `WorkflowVersion.status`, so
disabling is instant and touches no version data; re-enabling is lossless.

**Rollback** (`RollbackWorkflow`): clones an old (archived) version's
full content into a **brand-new** version and publishes it. History is
never mutated or un-archived — every version a workflow ever had stays
an immutable, inspectable snapshot, and `version_number` is strictly
increasing.

**Archive** (`ArchiveWorkflow`): terminal. No un-archive path.

## 3. Triggers

Triggers come exclusively from the Platform Event Bus (spec section 3).
`WorkflowTrigger.event_type` is a plain string matched against
`PlatformEventCatalog::knownEventTypes()` — the exact same
"not-an-enforced-enum" convention `OutboxEvent.event_type` and
`WebhookSubscription` already use, so a future event needs zero
Automation-domain schema changes to become triggerable ("Future events
must register automatically").

Dispatch hooks into the existing outbox consumer rather than building a
second one: `ProcessOutboxEventsCommand` already fans every claimed
`OutboxEvent` out to `DispatchWebhooksForEvent` (M11); this milestone
adds `DispatchWorkflowTriggersForEvent` right alongside it, in the same
tenant-scoped transaction that marks the event processed. Idempotent via
a unique constraint on `(workflow_version_id, outbox_event_id)` — the
same claim-or-skip pattern `WebhookDelivery` uses for its own fan-out.

Most of spec section 3's example events already fire for real
(`OrderCreated`, `RefundCompleted`, `ReturnApproved`, `ReturnCompleted`,
`CustomerCreated`, `CustomerUpdated`, `CustomerEnteredSegment`,
`CustomerLeftSegment`, `CustomerBecameVip`, `ShipmentDelivered`).
Two spec examples map onto this platform's actual event names rather
than needing a new one: **"OrderPaid"** is `OrderPaymentConfirmed`,
**"PaymentSucceeded"** is `PaymentPaid`. Three needed real work:

- **`ProductBackInStock`** / **`InventoryBelowThreshold`** — wired into
  `AdjustInventory` (Inventory domain): fires when on-hand stock
  actually *crosses* a threshold (0 → positive, or above → at-or-below
  an opt-in nullable `inventory_items.low_stock_threshold`), not on
  every adjustment.
- **`AppWebhookReceived`** — a new inbound gateway endpoint,
  `POST /api/apps/v1/automation/events` (`AutomationEventGatewayController`,
  scope `automation.write`), lets an installed app push an event into
  this platform's own Platform Event Bus — the inbound counterpart to
  `DeliverWebhookJob`'s outbound delivery.

`OrderCancelled` is catalog-only: no order-cancellation feature exists
anywhere in this codebase to emit it (confirmed before adding it), so
it's registered as a selectable trigger for forward-compatibility but
genuinely does not fire yet — real technical debt, not an oversight.

## 4. Conditions

A `WorkflowCondition` tree is a list of top-level nodes, implicitly
ANDed (spec section 4) — identical shape to M18's `SegmentRule`: a
*condition* leaf (`variable_key`/`operator`/`value`) or a *group*
(`boolean_operator` + `children`, recursively). An empty tree matches
everyone — a workflow with no conditions is a valid, common "run on
every trigger" workflow.

`variable_key` is a free-form dot path into the execution's Context
(§7) — `order.total_amount`, `customer.status`, `payment.status`,
`trigger.payload.on_hand` — resolved via `Arr::get()`, not a fixed
enum, since it must also reach app-contributed variables.

Operators (`WorkflowConditionOperator`) split into two families:
- **Comparison/string/set**: `equals`, `not_equals`, `greater_than(_or_equal)`,
  `less_than(_or_equal)`, `contains`, `starts_with`, `ends_with`,
  `is_true`, `is_false`, `in_set`, `not_in_set`.
- **Customer-Intelligence-aware**: `in_segment`/`not_in_segment`,
  `in_group`/`not_in_group`, `has_tag`/`not_has_tag` — these read
  `customer.id` out of the Context and delegate to M18's
  `SegmentMembership` facade, exactly as Promotions already does. No
  direct coupling to `SegmentRuleEngine` internals.

`WorkflowConditionEvaluator` walks the tree recursively; `and` requires
every child true, `or` requires at least one.

## 5. Actions

`WorkflowAction` is a flat, ordered list per version — spec section 16
explicitly excludes a visual BPMN editor, so there is no branching,
just `position`-ordered execution. `WorkflowActionExecutor` dispatches
on `WorkflowActionType`:

| Type | Reuses |
|---|---|
| `add_customer_tag` / `remove_customer_tag` | M18 `AssignCustomerTag`/`RemoveCustomerTag` |
| `add_customer_to_group` / `remove_customer_from_group` | M18 `AddCustomerToGroup`/`RemoveCustomerFromGroup` |
| `create_discount_code` | M6 `CreateDiscountCode` (against an existing `Promotion`; the action generates a fresh code) |
| `expire_discount` | Deactivates an existing `DiscountCode` |
| `publish_event` | `RecordOutboxEvent`, tagged with `caused_by_workflow_execution_id` (§13) |
| `call_app_webhook` / `app_action` | Direct HTTP POST — `app_action` resolves its target from an `AppExtension` row (§10) |
| `create_internal_notification` / `create_task` | New minimal models, since nothing comparable existed |
| `update_customer_metadata` / `update_order_metadata` | New `metadata` jsonb columns on `customers`/`orders` |
| `delay` | Handled entirely by `WorkflowRunner`, never reaches the executor — see §6 |

An action's `config` values may reference an **earlier action's own
output** within the same execution via
`{{steps.<action_id>.output.<key>}}` (e.g. `expire_discount` referencing
the `discount_code_id` a prior `create_discount_code` step produced) —
resolved by `WorkflowActionExecutor::interpolate()` just before dispatch.

## 6. Delays

Three delay types (`DelayType`): `minutes`, `hours`, `until_date` are
time-based; `until_event` is event-based. A `delay` action is a step
like any other — the runner pauses the *whole* execution at that
position (`WorkflowExecution.status = waiting`,
`current_action_position` stays at the delay's index) and resumes it
later, rather than treating delay as structurally different from an
action.

Resumption:
- **Time-based**: `automation:resume-delayed` (not wired into a
  scheduler — see §11) re-dispatches any `waiting` execution whose
  `next_resume_at` has passed.
- **Event-based**: `DispatchWorkflowTriggersForEvent::resumeEventWaits()`
  checks every newly-arrived event against `waiting` executions'
  `wait_until_event_type` and re-dispatches a match.

`WorkflowRunner::resumePastDelay()` is the actual authority on whether
a delay is really over — not just whatever caused `run()` to be
invoked again. Time-based delays are self-verified against
`next_resume_at` even on a stray duplicate dispatch (defense in depth);
event-based delays trust their caller, since there is no independent
timestamp to check.

## 7. Variables

Spec section 7's Customer/Order/Payment/Shipment/Return/Inventory/Store/
Trigger-payload categories become one Context array per execution,
built by `WorkflowVariableResolver::resolve()` from the triggering
`OutboxEvent`. Resolution follows `aggregate_type` outward to the
entities a merchant would expect — e.g. an `OrderPaymentConfirmed`
trigger (`aggregate_type = Order`) also exposes `customer`, since an
order's own customer is a natural variable even though the order is the
direct aggregate.

The variable-*picker* (what the condition/action builder UI offers) is
a separate read side: `WorkflowVariableRegistry::all()` merges the
global `workflow_variables` catalog (32 built-ins, seeded by
`RegisterBuiltInAutomationCatalog` / `php artisan automation:install`)
with app-contributed entries (§10). "Strongly typed" (spec section 7)
means each catalog entry declares a `WorkflowVariableType`
(string/number/boolean/date/enum/collection) for the picker UI, not a
runtime type-checked value — the Context itself is a plain array,
consistent with `variable_key` being an arbitrary dot path.

## 8. Execution engine

- **`WorkflowRunner`** — orchestrates one execution: evaluate
  conditions once (recorded as the first `WorkflowExecutionStep`, even
  when there are no conditions), then run actions in `position` order,
  each as its own `WorkflowExecutionStep`.
- **`WorkflowConditionEvaluator`** — §4.
- **`WorkflowActionExecutor`** — §5.
- **`ExecutionQueue`** — Laravel's own queue (`RunWorkflowExecutionJob
  implements ShouldQueue`), not a bespoke one. `DispatchWorkflowTriggersForEvent`
  dispatches it after creating a `Pending` execution row.
- **`RetryPolicy`** — mirrors `DeliverWebhookJob` (M11) exactly: same
  `MAX_ATTEMPTS = 6`, same backoff schedule
  (`[10, 30, 60, 300, 900, 3600]` seconds), tracked as explicit
  `attempts`/`next_retry_at` columns on `WorkflowExecution` rather than
  Laravel's queue-level `$tries`/backoff, for the same reason
  `DeliverWebhookJob` does: retry timing must survive across separate
  job dispatches, not just in-worker attempts.
- **`DeadLetterQueue`** — not a separate table; `WorkflowExecutionStatus::DeadLetter`
  is a terminal status on the same row once `MAX_ATTEMPTS` is exhausted,
  with `error_message` explaining why. `automation:retry-failed` only
  ever selects `failed` rows — `dead_letter` is deliberately excluded,
  matching `webhooks:retry-failed`'s identical convention.

The runner deliberately does **not** wrap a whole run in one long DB
transaction: actions make real HTTP calls (`call_app_webhook`,
`app_action`) and a delay can pause for days, so holding a row lock for
that span would be wrong. Instead, an execution is *claimed* via a
single guarded `UPDATE ... WHERE status IN (pending, waiting, failed)`
— an optimistic claim, not a held lock — so two workers picking up the
same execution can't both run it (verified by
`tests/Concurrency/WorkflowExecutionConcurrencyTest.php`, the sibling of
M18's `CustomerGroupMembershipConcurrencyTest`).

## 9. Templates

Eight starter workflows (`RegisterBuiltInAutomationCatalog`, seeded via
`php artisan automation:install`), each a portable
`{trigger, conditions, actions}` JSON blob on a global `workflow_templates`
catalog row: Welcome customer, VIP customer, Low inventory alert,
Abandoned payment, Shipment delivered, Refund completed, High value
order, Back in stock. `InstantiateWorkflowFromTemplate` turns a
template into a real, store-owned `Workflow` — always created as a
**draft**, never auto-published, since some templates reference a
placeholder (e.g. VIP customer's `create_discount_code.promotion_id` is
`null` until the merchant picks a real promotion in the editor).

## 10. Apps Platform integration

No new extension mechanism was built. Apps register triggers, actions,
templates, and variables through the **existing** `AppExtension`
mechanism (M12) — four new `ExtensionPoint` cases
(`AutomationTrigger`, `AutomationAction`, `AutomationTemplate`,
`AutomationVariable`), each with its own required-config shape in
`ExtensionPointRegistry::assertValidConfig()`:

- `AutomationAction` needs `label` + `target_url` — a workflow's
  `app_action` step references the `AppExtension` id, and
  `WorkflowActionExecutor` POSTs to its `target_url` with the
  execution's context and the step's own payload.
- `AutomationTrigger` needs `event_type` + `label` — surfaced by
  `WorkflowTriggerRegistry` alongside the platform catalog.
- `AutomationVariable` needs `source`/`key`/`label`/`type` — surfaced
  by `WorkflowVariableRegistry` alongside the built-in catalog.
- `AutomationTemplate` needs `name` + `definition` (not yet consumed by
  a read endpoint this milestone — registration works today; a
  dedicated "app templates" listing is a natural, small follow-up).

"No core changes required" holds exactly: none of `Workflow`,
`WorkflowVersion`, `WorkflowCondition`, `WorkflowAction`, or the runner
know an app exists. The `app_action`/`AutomationAction` case is the one
integration point, and it reads generic `AppExtension.config`.

## 11. Console commands

Three, following the exact "not wired into Laravel's scheduler, run
externally on a cron" convention every other operational command in
this codebase already uses (`outbox:process`, `webhooks:retry-failed`,
`RecomputeCustomerMetricsCommand`, ...):

- `automation:resume-delayed` — §6.
- `automation:retry-failed` — §8.
- `automation:install` — §9/§7, idempotent, safe to re-run after a
  platform upgrade adds new built-ins.

## 12. Security: loop prevention

Three independent defenses (spec section 13), implemented in
`WorkflowLoopGuard`:

1. **Static, publish-time**: `PublishWorkflow` refuses to publish a
   version whose own `publish_event` action targets its own trigger's
   `event_type` — the one cycle shape cheap to detect without walking
   an ancestor chain (`CircularWorkflowException`, 422).
2. **Runtime depth cap**: every `WorkflowExecution` caused by another
   execution's action (tracked via a new
   `outbox_events.caused_by_workflow_execution_id` column, set when an
   action calls `RecordOutboxEvent`) inherits `depth + 1`; once depth
   exceeds `MAX_DEPTH` (5), the chain is refused — catches indirect/
   transitive cycles (A → B → A) a static check can't.
3. **Rate limiting**: caps a single workflow to 30 executions/minute,
   independent of chain shape — guards against a high-frequency trigger
   overwhelming the queue, not just cycles.

A blocked execution is never silently dropped: it's still created and
immediately marked `dead_letter` with a human-readable
`error_message`, so it stays visible in Execution History — see
`tests/Feature/Automation/LoopGuardTest.php`.

## 13. Tenant isolation

Every entity in this module uses `BelongsToTenant`, except the two
global catalogs (`WorkflowVariable`, `WorkflowTemplate`) — deliberately
**not** tenant-scoped, since `BelongsToTenant`'s global scope always
forces a non-null `store_id`, which would hide shared built-in rows
from every store (see `docs/adr/025-automation-engine.md`). Verified in
`tests/Feature/Automation/TenantIsolationTest.php`: one store's
workflows are invisible to another (list/show/publish all 404 or
empty), one store's trigger event never executes another store's
workflow even with the same `event_type`, executions are strictly
store-scoped, and templates are confirmed identical across stores (the
global-catalog case, tested for the *opposite* property on purpose).

## 14. Explicitly not implemented

Per spec section 16: AI workflow generation, a visual BPMN editor,
cross-store workflows, external Zapier execution, a cron builder.

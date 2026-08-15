# ADR-025: Automation Engine — Reuse the Outbox Consumer for Triggers, One Condition Tree Shape Diverging From SegmentRule, Optimistic Claims Over Held Locks

## Status
Accepted

## Context

Milestone 19 adds a no-code automation engine (Workflow: trigger →
conditions → actions, with delays and retries) that must "reuse the
existing Platform Events, Customer Intelligence, Promotions, Apps SDK
and Event Bus," support long-running paused executions (delays up to
days), retry/dead-letter failed actions, prevent infinite recursion,
and let Apps extend triggers/actions/templates/variables with "no core
changes required."

Four design questions dominated the implementation: how a trigger
actually starts an execution given this codebase has no internal
pub/sub (the same fact ADR-024 already established); whether a
workflow's condition tree can just *be* M18's `SegmentRule` table;
whether an execution needs a long-held DB transaction/lock while it
runs; and how to stop a workflow whose own action re-triggers itself
without building a full graph-cycle detector.

## Decision 1: Trigger dispatch hooks the existing outbox consumer, not a new poller

**Options considered:**

1. A second, independent command polling `OutboxEvent` for automation
   purposes, with its own `processed_at`-equivalent cursor.
2. Add `DispatchWorkflowTriggersForEvent` as a second call inside
   `ProcessOutboxEventsCommand`, right alongside the existing
   `DispatchWebhooksForEvent` call, sharing the same claimed-row
   transaction.

**Decision: option 2.** `OutboxEvent.processed_at` is a single flag —
having two independent consumers each try to own it would mean either
one consumer's completion incorrectly marks the event "done" for the
other, or a second cursor column bolted onto a table four other
milestones already depend on. `ProcessOutboxEventsCommand` already
claims each row inside a row-locked transaction specifically so a
subscriber's side effects and the "processed" flag commit atomically —
adding a second subscriber inside that same transaction is the reuse
the spec actually asks for, and it costs one new constructor parameter
and one new line, not a new command.

## Decision 2: A new `workflow_conditions` table, not a shared `segment_rules`

**Options considered:**

1. Reuse M18's `segment_rules` table and `SegmentRuleEngine` directly —
   the tree shape (AND/OR, nested groups, condition leaves) is
   identical.
2. A new `workflow_conditions` table and a new
   `WorkflowConditionEvaluator`, structurally parallel to
   `SegmentRule`/`SegmentRuleEngine` but not the same code.

**Decision: option 2.** The tree *shape* is identical, but the *field
resolution* isn't: `SegmentRuleField` is a fixed enum resolving against
`Customer`/`CustomerMetric` only, while a workflow condition's
`variable_key` must resolve against a whole trigger's Context
(Order/Payment/Shipment/Return/Inventory/Store/Trigger-payload — spec
section 7) *and* reach app-contributed variables that don't exist as
PHP enum cases. Retrofitting `SegmentRuleField` into an open string
would change M18's already-shipped, tested validation semantics for
Customer Intelligence's own use case. A second table with the same
proven node shape (leaf vs. group, `parent_id` self-reference, the
same `WorkflowConditionTreeLoader`/`WorkflowRuleTreeLoader` two-step
FK-migration pattern) costs one migration and one evaluator class, and
keeps each domain's condition semantics independently evolvable. The
six Customer-Intelligence-aware operators
(`in_segment`/`in_group`/`has_tag` and negations) still reuse
`SegmentMembership` directly — the actual expensive/tested logic isn't
duplicated, only the tree-walking scaffolding is.

## Decision 3: Optimistic claim, not a held transaction, for one execution run

**Options considered:**

1. Wrap `WorkflowRunner::run()`'s whole action loop in one
   `DB::transaction()`, holding a row lock (`lockForUpdate()`) on the
   `WorkflowExecution` for the duration — mirrors how several other
   application services in this codebase (`RecomputeCustomerMetrics`,
   `AddCustomerToGroup`) lock a row for their whole operation.
2. Claim the execution via a single guarded `UPDATE ... WHERE status IN
   (...)` (an optimistic compare-and-swap, not a held lock), then run
   actions and delays outside any long transaction.

**Decision: option 2.** Every other row-locking precedent in this
codebase locks for the duration of a single, fast, synchronous
operation. A workflow execution is neither: `call_app_webhook`/
`app_action` steps make real outbound HTTP calls (an unbounded, in
practice sub-second-but-not-guaranteed wait), and a `delay` action can
legitimately pause an execution for days. Holding a DB row lock (or an
open transaction) across either is a correctness and availability
risk with no upside. The guarded `UPDATE` gives the same "only one
worker actually runs this" guarantee `lockForUpdate()` would, without
holding anything open — verified directly by
`WorkflowExecutionConcurrencyTest`'s fork-based race, the same
methodology M18's `CustomerGroupMembershipConcurrencyTest` established.
The tradeoff: a delay resumed early by a stray duplicate dispatch isn't
caught by the claim UPDATE alone (the claim only rejects a *second
concurrent* run, not a *premature* one) — `WorkflowRunner::resumePastDelay()`
adds its own explicit `next_resume_at` check as a second, independent
guard for that specific case (a real bug this project's own test suite
caught and fixed during development — see the "Problems fixed" section
of the milestone's final report).

## Decision 4: Loop prevention via a new `caused_by_workflow_execution_id` column on the shared `outbox_events` table, not a payload flag

**Options considered:**

1. Stuff a marker into `OutboxEvent.payload` (e.g.
   `payload['_automation_execution_id']`) when an action records an
   event, and have `DispatchWorkflowTriggersForEvent` read it back out.
2. Add a real, typed, nullable `caused_by_workflow_execution_id`
   column to `outbox_events` (a `Shared\Commerce` table predating this
   domain by many milestones), and thread it through
   `RecordOutboxEvent::handle()` as a new optional trailing parameter.

**Decision: option 2.** `outbox_events.payload` is also the exact
payload every external webhook subscriber receives (`DeliverWebhookJob`
serializes it verbatim) — smuggling internal execution-chain
bookkeeping into it would leak an implementation detail to every
installed app watching that event, for no benefit over a real column.
A typed FK column is self-documenting, indexable, and — since
`RecordOutboxEvent`'s new parameter is optional and defaults to
`null` — is fully backward compatible with the ~35 existing call sites
across every other domain, none of which pass it and none of which
needed to change.

## Consequences

- Two independent subscribers now share `ProcessOutboxEventsCommand`'s
  transaction; a future third subscriber (or a genuine move to a real
  message broker) should follow the same pattern rather than adding a
  third bespoke poller — but a broker migration would need to touch
  both existing subscribers' call sites, not just add a new one.
- `workflow_conditions` and `segment_rules` will drift over time if one
  domain's operator set grows and the other's doesn't — an accepted
  cost of keeping the two domains independently evolvable rather than
  coupling Customer Intelligence's validation rules to Automation's.
- Because no transaction is held across a `delay` or an outbound HTTP
  call, a crash mid-action can leave a `WorkflowExecutionStep` row
  showing `pending` with no terminal status — the next
  `automation:retry-failed`/`resume-delayed` pass re-claims and
  re-runs from `current_action_position`, which is safe for every
  built-in action (each either reuses an idempotent M18/M6 service or
  is naturally re-runnable) but is a real constraint on any future
  built-in action: it must tolerate at-least-once execution, not
  assume exactly-once.
- `caused_by_workflow_execution_id` is a permanent, if rarely non-null,
  column on a table four other milestones' code touches — a small,
  accepted footprint for a correct, independently-verifiable
  loop-prevention chain.

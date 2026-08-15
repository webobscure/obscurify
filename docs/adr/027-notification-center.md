# ADR-027: Notification Center — Synchronous Provider Contract, Reused Automation Variable Resolver, Enforced-vs-Structural Preference Split

## Status
Accepted

## Context

Milestone 21 adds a unified notification/messaging layer used by every
domain (Orders, Shipping, Payments, Customer Accounts, Automation), a
provider abstraction with `FakeNotificationProvider` as the only real
implementation, template variables drawn from the same
Customer/Order/Payment/Shipment/Refund/Return/Store/Workflow shape
Automation already resolves, customer-configurable channel
preferences, and a replacement for Automation's minimal
`InternalNotification` action with five real send actions — explicitly
without integrating a real email/SMS provider, building marketing
campaigns, AI copy generation, push certificates, or a scheduling
engine.

Three design questions dominated the implementation: whether
`NotificationProviderContract` should mirror `PaymentProviderContract`'s
async redirect-then-webhook shape or be synchronous; whether the
template-variable context needs its own resolver or can reuse
Automation's; and how literally to implement "customers can configure
... quiet hours (structure only)" when only some of the listed
preference fields have anything to actually gate yet.

## Decision 1: `NotificationProviderContract` is synchronous, not async-with-webhook

**Options considered:**

1. Mirror `PaymentProviderContract`'s shape exactly: `send()` returns
   an initiation result, and a real outcome (delivered/bounced/failed)
   arrives later through a signed provider webhook, resolved
   asynchronously — the pattern Payments and Shipping both use because
   a real payment/carrier webhook genuinely is async.
2. `send()` returns the outcome directly and synchronously
   (`NotificationSendResult`), with no webhook half of the contract at
   all.

**Decision: option 2.** Payments and Shipping are async because the
*real* systems behind them are async by nature — a card network or a
carrier confirms an outcome on its own schedule, not on the calling
request's thread. A transactional email/SMS/push send is different:
every real provider this milestone's `NotificationProvider::FUTURE_CODES`
names (SMTP, Mailgun, Resend, SES, Twilio, Telegram, WhatsApp,
Firebase Push) resolves accept-or-reject synchronously on the same API
call that submits the message — there is no async confirmation step to
model. Building the async/webhook machinery anyway, for a contract
this milestone explicitly excludes from having a real backing provider
("Do NOT integrate with real email/SMS providers yet"), would be
speculative complexity with nothing to verify it against. `send()`
returning `NotificationSendResult` directly is both simpler and a
truer model of what a real integration will actually need.

## Decision 2: Reuse `WorkflowVariableResolver` for notification template context, not a second resolver

**Options considered:**

1. Build a `NotificationVariableResolver` — structurally similar to
   Automation's own resolver, walking an `OutboxEvent`'s
   `aggregate_type` out to Customer/Order/Payment/Shipment/Return/Store
   — as its own class in the Notifications domain.
2. Call `WorkflowVariableResolver::resolve(OutboxEvent, Store)` directly
   from `DispatchNotificationsForEvent`, the identical function
   Automation's own condition evaluator and action executor already
   use.

**Decision: option 2.** Unlike ADR-025's `workflow_conditions`-vs-
`segment_rules` decision (a case where the tree *shape* matched but the
*field resolution semantics* genuinely differed between domains, which
is what justified a second implementation), here the context spec
section 4 asks for — Customer/Order/Payment/Shipment/Refund/Return/
Store/Workflow — is *exactly* the same set `WorkflowVariableResolver`
already builds, resolved from the exact same input (an `OutboxEvent` +
`Store`). There is no divergent semantics to protect by duplicating
it; a second resolver would be the same code with a different class
name. Reusing it directly means a future improvement to variable
resolution (a new entity category, a bug fix in how `Return`'s
customer is derived) automatically benefits both Automation's
condition/action evaluation and Notification template rendering,
rather than needing the same fix applied twice.

## Decision 3: Only channel-enabled flags are enforced; `marketing_opt_in`, `transactional_only`, and quiet hours are stored but inert

**Options considered:**

1. Enforce every `NotificationPreference` field this milestone —
   including building a "is this send transactional or marketing"
   classification on `Notification` so `transactional_only` has
   something real to check, and a quiet-hours suppression window in
   `NotificationDispatcher`.
2. Enforce only `email_enabled`/`sms_enabled`/`push_enabled` (the
   three fields with an unambiguous, already-real meaning: "does this
   customer want messages on this channel at all"); store the rest as
   settable columns with no enforcement logic reading them yet.

**Decision: option 2.** Spec section 14 explicitly excludes marketing
campaigns from this milestone — there is no marketing-vs-transactional
distinction anywhere else in the system for `transactional_only` to
gate against; building that classification now would be inventing a
feature (marketing sends) the spec says not to build, just to give an
already-listed preference field something to do. Quiet hours are
spec'd explicitly as "structure only" (spec section 6) — the schema
exists so a future scheduling/marketing milestone doesn't need a
migration, but no code reads it yet, consistent with how M19 catalogued
`OrderCancelled` as a selectable trigger with no feature behind it yet.
The three channel-enabled flags are different: "email notifications
off" is a real, complete, already-meaningful preference with nothing
speculative about it, so it's the one part of the preference model
this milestone actually enforces (`NotificationDispatcher` creates a
`suppressed`-status delivery, never attempting the send).

## Consequences

- A future real provider integration (the first of `NotificationProvider::FUTURE_CODES`
  to actually ship) can implement `NotificationProviderContract` as-is
  with no interface change — the synchronous contract was validated
  against every planned provider's actual API shape, not just the fake.
- If a genuinely async provider is ever needed (e.g. a provider whose
  API only offers a queued-then-webhook confirmation flow, unlike
  today's SMTP/Twilio-style synchronous accept/reject), the contract
  will need a second, async variant rather than forcing that provider
  into a synchronous `send()` — an accepted, currently-hypothetical
  cost of Decision 1's simpler shape.
- `DispatchNotificationsForEvent` now has a real dependency on
  Automation's `WorkflowVariableResolver` — a cross-domain coupling
  that didn't exist before. This is a deliberate, narrow dependency (one
  pure function, no shared mutable state) rather than the kind of
  coupling ADR-025 avoided when it kept `workflow_conditions` separate
  from `segment_rules`; if Automation's variable context shape needs to
  diverge from Notifications' needs in the future, this reuse will need
  to be revisited.
- `marketing_opt_in` and `transactional_only` are visible, settable API
  fields that currently do nothing — a real risk that a future reader
  (or a merchant) assumes they're enforced. Mitigated by this ADR and
  the architecture doc's explicit "not enforced this milestone" callout;
  the first milestone that adds marketing sends must either wire these
  up or explicitly reconsider their meaning.

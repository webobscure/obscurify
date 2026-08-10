# ADR-017: Platform Events + Webhooks — Extend the Existing Outbox, Don't Build a New Event Bus

## Status
Accepted

## Context
The platform needed a "Platform Events + Webhooks" foundation — both as
a real feature (merchants want to receive HTTP callbacks when things
happen) and as a hard prerequisite for the next milestone (App Platform:
"Apps consume Platform Events... through webhook subscriptions... never
subscribe directly to business domains").

A transactional outbox already existed: `RecordOutboxEvent` writes an
`OutboxEvent` row (event_type, aggregate_type, aggregate_id, payload)
inside the same DB transaction as ~30 call sites across every domain
(Checkout, Payments, Financial, Fulfillment, Shipping, Returns,
Promotions), and `ProcessOutboxEventsCommand` (`outbox:process`) already
polls and claims unprocessed rows — but its own docblock said so
explicitly: "deliberately minimal: no email/payment/webhook side effects
(out of scope this milestone)... with a real processing loop rather than
leaving the table to just accumulate rows." It was already the
documented extension point for exactly this.

## Options
1. Build a new, general-purpose internal event bus / message broker
   (e.g. a `DomainEvent` dispatcher with in-process listeners, or a
   Redis pub/sub layer), independent of `OutboxEvent`, with webhook
   delivery as one subscriber among others.
2. Extend the existing outbox: keep `OutboxEvent`/`RecordOutboxEvent`
   exactly as they are (zero changes to ~30 call sites), and add the one
   real subscriber `ProcessOutboxEventsCommand` was already designed to
   eventually gain — webhook fan-out — inside its existing claim/lock
   loop.
3. Hook webhook dispatch directly into `RecordOutboxEvent::handle()`
   itself, synchronously, at write time.

## Decision
Option 2. `OutboxEvent` already *is* the platform's event log — every
domain that matters already writes to it, atomically with the change it
describes. Building a second, parallel event mechanism (option 1) would
mean either migrating 30 call sites onto it (high-risk churn across
every settled domain for zero functional gain) or maintaining two event
systems side by side, one of which nothing actually populates. Option 3
was rejected because `RecordOutboxEvent` runs *inside* the caller's own
still-open transaction — dispatching an HTTP delivery (or even just
queuing a job) before that transaction commits means acting on an event
that might still roll back; polling the already-committed table, the way
`outbox:process` already does, is correctly ordered by construction.

The one real change to existing code: `ProcessOutboxEventsCommand` now
calls `DispatchWebhooksForEvent::handle($event)` (scoped to the event's
own tenant via `TenantContext::scope()`) inside the same row-locked
transaction that marks `processed_at`, right where its own docblock
already flagged as the deferred extension point.

**Idempotency mechanism**: `WebhookDelivery` has a unique constraint on
`(webhook_subscription_id, outbox_event_id)`, claimed via
insert-then-catch-`UniqueConstraintViolationException` — deliberately
the same pattern `ProcessShippingWebhook`/`ProcessPaymentWebhook` already
use for *inbound* webhook idempotency (a `(provider, external_event_id)`
unique constraint), applied to outbound fan-out instead of reinventing a
new locking primitive.

**Retry mechanism**: considered Laravel's native `$tries`/`backoff()`/
`failed()` job-retry machinery first (the obvious default for a queued
job), but rejected it — throwing an exception to trigger a retry
propagates directly to the caller on the `sync` queue driver this test
suite runs under, rather than being caught and retried, which would make
"the target returned a 500" (routine, expected) crash whatever dispatched
the job. `DeliverWebhookJob` instead tracks `attempt_count`/
`next_retry_at` on the `WebhookDelivery` row itself and a separate
command (`webhooks:retry-failed`) re-dispatches ready-to-retry
deliveries — decoupling "one HTTP attempt" from "when to try again,"
testable synchronously either way.

## Consequences

### Positive
- No existing call site of `RecordOutboxEvent` changed at all — every
  one of the ~30 domains that already record events gained webhook
  delivery for free, with zero code changes on their part.
- The transactional guarantee already established for `OutboxEvent`
  (commits/rolls back with the business change) now extends one hop
  further: a webhook fan-out and the event's `processed_at` flag also
  commit or roll back together.
- `owner_type`/`owner_id` on `WebhookSubscription` future-proofs
  Milestone 12's `AppWebhook` to reuse this exact delivery engine
  (signing, retry, idempotency) instead of building a second one.

### Negative
- `OutboxEvent.event_type` stays an unenforced free string (no shared
  enum) — a typo in a 31st future call site would silently create an
  unmatchable event type. Mitigated by `PlatformEventCatalog` (a
  documentation/admin-UI list, not a database constraint) and accepted
  as the same tradeoff already made for the 30 existing call sites.
- Webhook delivery latency is bounded by however often `outbox:process`
  runs (not wired to a scheduler in this codebase — an ops concern, see
  docs/architecture/webhooks.md §8) rather than being instant — a
  deliberate consequence of staying poll-based instead of dispatching
  from inside an open transaction.

## Security Requirements
- `WebhookSubscription`/`WebhookDelivery` both use `BelongsToTenant` —
  verified with dedicated cross-tenant tests, not just individually
  scoped resources.
- `WebhookSubscription.secret` is generated server-side, never accepted
  from a request, cast `encrypted` at rest, and returned by the API
  exactly once (at creation) — no endpoint can ever re-read it
  afterward.
- Every delivery is HMAC-signed (`hash_hmac('sha256', ...)`, hex,
  `X-Obscurify-Webhook-Signature` header) using the exact same
  algorithm/encoding this platform already uses to verify *inbound*
  provider webhooks, so the security properties (timing-safe compare via
  `hash_equals` on the receiving side) are consistent in both
  directions.
- Delivery idempotency is concurrency-safe under real PostgreSQL
  connections — proven in
  `tests/Concurrency/WebhookDeliveryConcurrencyTest.php`.

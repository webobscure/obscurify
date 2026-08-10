# Platform Events + Webhooks Architecture

Milestone 11 — the asynchronous side of the transactional outbox
(`OutboxEvent`/`RecordOutboxEvent`, introduced earlier and used across
every domain) finally gets a real subscriber. See
[ADR-017](../adr/017-platform-events-and-webhooks.md) for why this
builds on the existing outbox rather than a new event bus.

## 1. Platform Events

A "Platform Event" is just an `OutboxEvent` row — `event_type` (a free
string, e.g. `OrderCreated`), `aggregate_type`/`aggregate_id`, `payload`
(jsonb), `occurred_at`. ~30 call sites across Checkout/Payments/
Financial/Fulfillment/Shipping/Returns/Promotions already write one
inside the same DB transaction as the change it describes — see
`App\Domain\Webhooks\Support\PlatformEventCatalog::knownEventTypes()`
for the full current list (used by the admin subscription UI, not
enforced at write time — `OutboxEvent.event_type` stays a free string,
since retrofitting 30 call sites onto a shared enum changes no dispatch
behavior).

## 2. Dispatch — `webhooks:process` stays poll-based

`ProcessOutboxEventsCommand` (`outbox:process`) already claims unprocessed
`OutboxEvent` rows under a row lock, in `occurred_at` order, 100 at a
time. This milestone adds one real side effect inside that same locked
transaction: `DispatchWebhooksForEvent::handle($event)`, scoped to the
event's own store via `TenantContext::scope()`. A webhook fan-out and
the event's `processed_at` flag always commit or roll back together —
the same "either both happen or neither does" guarantee the outbox
already gives `Order` + `OutboxEvent`.

Deliberately **not** a hook inside `RecordOutboxEvent` itself: that class
has ~30 existing call sites across every domain, none of which need to
know webhooks exist, and it already runs inside the caller's own
transaction (before that transaction commits) — dispatching an HTTP
delivery from inside an uncommitted transaction would be premature
(the event might still roll back). Polling the already-committed,
already-durable table is simpler and correctly ordered.

## 3. WebhookSubscription and matching

`WebhookSubscription` (`event_types`: a jsonb array of event_type
strings, or `["*"]` for everything; `status`: active/inactive) —
`subscribesTo(string $eventType)` is a plain in-array/wildcard check, no
database round-trip. `owner_type`/`owner_id` distinguish a
merchant-created subscription (`owner_type = 'store'`, this milestone's
only case) from an app-owned one — Milestone 12's `AppWebhook` manages
rows here with `owner_type = 'app'` rather than duplicating the delivery
engine.

## 4. Delivery and retry

`WebhookDelivery` — one row per `(webhook_subscription_id,
outbox_event_id)` pair, enforced by a unique constraint. `DispatchWebhooksForEvent`
inserts a claim row and catches `UniqueConstraintViolationException` the
same way `ProcessShippingWebhook` already does for *inbound* webhook
idempotency — applied here to *outbound* fan-out, so a concurrent or
retried dispatch pass can never double-create a delivery for the same
pair (proven under real PostgreSQL connections in
`tests/Concurrency/WebhookDeliveryConcurrencyTest.php`).

`DeliverWebhookJob` (queued) signs the payload
(`hash_hmac('sha256', $jsonBody, $subscription->secret)`, hex-encoded —
the exact convention `FakePaymentProvider`/`FakeShippingProvider` already
use for *inbound* webhook verification, so a subscriber checks a
delivery the same way this platform checks a provider) and POSTs it with
three headers: `X-Obscurify-Webhook-Id`, `X-Obscurify-Webhook-Event`,
`X-Obscurify-Webhook-Signature`. A subscriber verifies with
`hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader)`,
mirroring `FakePaymentProvider::verifyWebhook()` exactly.

**Retry is deliberately not Laravel's `$tries`/`backoff()`/`failed()`
queue machinery.** A non-2xx response or a network error is business as
usual for a webhook target, not a queue-worker crash — throwing to
trigger Laravel's retry would also misbehave on the `sync` queue driver
this test suite runs under (an exception thrown from a synchronously-
dispatched job propagates straight to the caller instead of being
retried). Instead `DeliverWebhookJob` tracks its own `attempt_count`/
`next_retry_at` on the `WebhookDelivery` row and marks it `exhausted`
itself once `MAX_ATTEMPTS` (6) is reached, with the same backoff steps
(10s, 30s, 60s, 5m, 15m, 1h) Laravel's own `backoff()` would have used.
`RetryFailedWebhookDeliveriesCommand` (`webhooks:retry-failed`) re-
dispatches every `failed` delivery whose `next_retry_at` has passed —
intended to run on a short recurring schedule the same way
`outbox:process` does; neither is wired into Laravel's scheduler in this
codebase yet (no command here is).

A merchant can also manually retry a `failed`/`exhausted` delivery via
`POST /webhook-deliveries/{id}/retry` (`RetryWebhookDelivery`).

## 5. Secrets

`WebhookSubscription.secret` is generated server-side on creation
(`Str::random(48)`), cast `encrypted` (Laravel's built-in cast — the
first use of it in this codebase; no `WebhookSubscription`-external
secret was ever persisted to a database column before this milestone,
so there was no prior convention to follow), and returned by the API
**exactly once**, in `WebhookSubscriptionController::store()`'s
response only — `WebhookSubscriptionResource` never includes it, so
`index`/`show`/`update` can never leak it even by accident.

## 6. Admin API

`GET/POST/PATCH /webhook-subscriptions`, `GET
/webhook-subscriptions/{id}`, `GET
/webhook-subscriptions/{id}/deliveries`, `POST
/webhook-deliveries/{id}/retry` — no `destroy`, the same pragmatic bar
established for `promotions`/`shipping-zones`: deactivate via `status`,
never delete, so existing `WebhookDelivery` rows and their foreign keys
stay intact.

## 7. Tenant isolation

`WebhookSubscription` and `WebhookDelivery` both use `BelongsToTenant` —
verified with dedicated cross-tenant tests
(`tests/Feature/Webhooks/AdminWebhookApiTest.php`): Store A can never
read, update, list deliveries for, or retry a delivery belonging to a
Store B subscription.

## 8. Future extensibility

- **A delivery attempts log**: today `WebhookDelivery` mutates in place
  across attempts (one row, `attempt_count` + last response/error) rather
  than an append-only attempts table. This was a deliberate scope cut —
  no explicit "audit history" requirement exists for webhook deliveries
  the way Milestone 12 requires one for OAuth tokens — but adding a
  `WebhookDeliveryAttempt` child table (mirroring `PaymentAttempt`/
  `TrackingEvent`) is additive if per-attempt observability is ever
  needed.
- **Scheduler wiring**: `outbox:process` and `webhooks:retry-failed`
  are both plain artisan commands today, not registered with Laravel's
  scheduler (`routes/console.php`) — running them on a cron/Horizon
  schedule is an ops concern outside this milestone's scope, consistent
  with `outbox:process` already being unwired.
- **App-owned subscriptions**: `owner_type`/`owner_id` already exist on
  `WebhookSubscription` for exactly this — see Milestone 12's
  `AppWebhook`.

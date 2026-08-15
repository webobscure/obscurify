# Notification Center + Omnichannel Messaging

## 1. Overview

Milestone 21 adds a platform-wide notification and messaging layer used
by every domain that needs to tell a customer (or a merchant) that
something happened — order confirmations, workflow-driven alerts,
admin-composed one-offs. It reuses Platform Events (M11) as a trigger
source, Automation (M19) as a second trigger source and a set of real
actions, and the Customer Accounts (M16) guard for the portal side.
Per spec: no marketing campaigns, no AI-generated copy, no real
email/SMS provider integration this milestone — every send goes
through a provider **abstraction**, with `FakeNotificationProvider` as
the only registered implementation.

Core entities, all under `App\Domain\Notifications`:

| Entity | Purpose |
|---|---|
| `NotificationProvider` | A store's configured instance of a provider `code` (only `fake` is actually wired up — see §2). |
| `NotificationChannel` | Per-store, per-channel-type enablement + which `NotificationProvider` handles it. |
| `NotificationTemplate` | A reusable, variable-interpolated message body (+ subject/HTML for email). |
| `NotificationEvent` | Routes one Platform Event `event_type` (on one channel) to a template. |
| `Notification` | One rendered message on one channel, fanned out to its recipients. |
| `NotificationRecipient` | Who receives a Notification — a customer or an ad-hoc address — and their own read/unread state. |
| `NotificationDelivery` | One (notification, recipient) send attempt chain, with retry bookkeeping. |
| `NotificationPreference` | One row per (store, customer) — which channels they've opted into. |

## 2. Provider architecture

`NotificationProviderContract` (`code()`, `send()`) is the provider-neutral
boundary — deliberately synchronous and outcome-only, unlike
`PaymentProviderContract`'s async redirect-then-webhook shape, because
no real provider is integrated yet and a real email/SMS/push API call
*is* synchronous (see `docs/adr/027-notification-center.md`).
`NotificationProviderRegistry` is a boot-time singleton populated the
same way `PaymentProviderRegistry`/`ShippingProviderRegistry` are
(`NotificationServiceProvider`, gated by `config('notifications.fake.enabled')`).

`FakeNotificationProvider` (`code = 'fake'`) is the default reference
implementation (spec: "Implement provider abstractions with a
FakeNotificationProvider as the default reference implementation") —
deterministic and side-effect-free: it always succeeds, except for two
reserved test sentinels (`*@fail.test`, `+10000000000`) that always
fail, giving tests and the admin UI a controllable failure path
without a real provider.

`NotificationProvider::FUTURE_CODES` lists the spec's "Future
providers" (`smtp`, `mailgun`, `resend`, `ses`, `twilio`, `telegram`,
`whatsapp`, `firebase_push`, `apps_sdk`) — selectable in the admin
"Providers" page as catalog placeholders, but attempting to send
through one throws `UnknownNotificationProviderException` (the
identical failure mode a disabled/unimplemented Payment or Shipping
provider already has) since none is registered in
`NotificationProviderRegistry`.

## 3. Channels

Five built-in channels (`NotificationChannelType`): Email, SMS, Push,
In-app, Webhook. `EnsureDefaultNotificationSetup` idempotently seeds
one `NotificationProvider` (`fake`) and one `NotificationChannel` row
per channel type, all pointing at it and enabled — called both by
`php artisan notifications:install` and lazily by the admin
Channels/Providers list endpoints (the same "auto-create on first
read" convention Milestone 20's default dashboard uses), so a fresh
store or a test never needs an explicit install step. A future channel
registers the same way a future provider does — through the registry,
not a schema change.

## 4. Templates

`NotificationTemplate` has `subject` (meaningful for Email), `body_text`
(every channel's baseline content), and `body_html` (optional, Email
only). `locale` defaults to `'en'` — one row per locale is the
localization-ready structure spec section 4 asks for; no
locale-resolution logic reads it yet (explicit scope boundary, see
ADR-027).

Variables are interpolated by `NotificationTemplateRenderer` —
`{{path.to.value}}` placeholders resolved via `Arr::get()` against a
context array, the same dot-path convention
`WorkflowConditionEvaluator`'s `variable_key` and
`WorkflowActionExecutor`'s `{{steps.x.output.y}}` already use. A
missing path renders as an empty string rather than leaving the
literal placeholder in a customer-facing message. The context shape
(Customer/Order/Payment/Shipment/Return/Store/Workflow — spec section
4) is **reused directly** from Automation: `DispatchNotificationsForEvent`
calls `WorkflowVariableResolver::resolve()`, the exact function
Automation's own condition/action evaluation already uses, rather than
building a second resolver for the same data.

## 5. Delivery engine

`NotificationDispatcher::dispatch()` is the one entry point every
trigger source goes through (spec section 7). It renders the message
once, creates one `Notification` row, then fans out to N
`NotificationRecipient` + `NotificationDelivery` rows (one pair per
recipient) and queues one `SendNotificationDeliveryJob` per
non-suppressed delivery.

**Idempotency**: `NotificationDelivery.idempotency_key`
(`{notification_id}:{recipient_id}`) is unique, so a duplicated
dispatch can never create a second delivery row for the same
(notification, recipient) pair.

**Retry**: `SendNotificationDeliveryJob` mirrors `DeliverWebhookJob`
exactly — `MAX_ATTEMPTS = 6`, the identical backoff schedule
(`[10, 30, 60, 300, 900, 3600]` seconds) tracked as `attempt_count`/
`next_retry_at` columns on the row itself (survives across separate
job dispatches, unlike Laravel's queue-level `$tries`), and it never
throws on a failed send — that's business as usual for a provider, not
a queue-worker failure.

**Dead letter**: `NotificationDeliveryStatus::Exhausted` is a terminal
status on the same row once `MAX_ATTEMPTS` is spent, matching
`WebhookDeliveryStatus::Exhausted`'s convention exactly.
`notifications:retry-failed` only ever selects `failed` rows —
`exhausted` is deliberately excluded, the same "dead letter is not
retried by this command" rule every retry-failed command in this
codebase follows.

**Concurrency**: the job claims its delivery via a guarded
`UPDATE ... WHERE status IN (pending, failed)` before doing anything
else — the same optimistic-claim pattern `WorkflowRunner` uses for
`WorkflowExecution` (ADR-025 Decision 3). Necessary here because a
manual "Retry" click and an automatic `notifications:retry-failed`
pass can dispatch the same delivery id at nearly the same time;
without the claim, both would read the same starting `attempt_count`
and race. Verified by
`tests/Concurrency/NotificationDeliveryConcurrencyTest.php`.

**Aggregate status**: `RecalculateNotificationStatus` re-derives a
`Notification`'s rollup status (`pending`/`delivered`/`failed`/
`partially_delivered`) from its deliveries' current statuses — called
after every delivery attempt and once right after `NotificationDispatcher`
creates them, since the `sync` queue driver this test suite runs on
means a delivery can already be terminal before `dispatch()` returns.

## 6. Preferences

`NotificationPreference` (one row per store/customer, upserted lazily —
no seeded default row the way channels are) has three actively
**enforced** flags: `email_enabled`, `sms_enabled`, `push_enabled`.
`NotificationDispatcher` checks the relevant flag for a customer
recipient before creating a delivery; if disabled, the delivery is
created immediately with `status = suppressed` and a clear
`error_message`, rather than silently skipped — full audit trail, no
fake retry attempts. `marketing_opt_in`, `transactional_only`, and the
three `quiet_hours_*` columns are stored and settable through the API
but **not enforced** this milestone: there is no marketing-campaign
feature to gate (spec: "Do NOT implement marketing campaigns"), and
quiet hours are explicitly "structure only" (spec section 6) — see
ADR-027 for this scope boundary.

## 7. Trigger sources

`NotificationTriggerSource`: `platform_event`, `automation`, `admin`,
`apps_sdk`, `scheduled`.

- **Platform Events** — `DispatchNotificationsForEvent`, the 4th
  `ProcessOutboxEventsCommand` subscriber (after `DispatchWebhooksForEvent`
  M11, `DispatchWorkflowTriggersForEvent` M19, `AnalyticsProjector`
  M20), matches `NotificationEvent` routing rules for the fired
  `event_type` and dispatches per match.
- **Automation Engine** — see §8.
- **Admin UI** — `POST /notifications` (`SendNotification` application
  service) lets a merchant compose and send directly, bypassing
  template/event routing if they choose (literal subject/body).
- **Apps SDK** — satisfied generically: an app pushing an event via
  the existing `AppWebhookReceived` gateway (M19) can be routed like
  any other Platform Event through `NotificationEvent`. No new
  app-specific notification-sending surface was built this milestone
  (see §10, technical debt).
- **Scheduled** — catalog-only, registered for forward-compatibility;
  spec section 14 explicitly excludes a scheduling engine, matching
  M19's identical "catalog-only, not wired up yet" treatment of
  `OrderCancelled`.

## 8. Automation integration

Spec section 8: "Replace the minimal InternalNotification action with
real notification actions." `WorkflowActionType::CreateInternalNotification`
and its backing `internal_notifications` table (a write-only inbox row
with no read API anywhere — confirmed before removing it) are gone,
replaced by five real actions: `SendEmailNotification`,
`SendSmsNotification`, `SendPushNotification`, `SendInAppNotification`,
`SendWebhookNotification`. Each calls the same
`WorkflowActionExecutor::sendNotificationAction()`, which resolves a
recipient from the execution's `context.customer.id` (or an explicit
`to` config override — required for the Webhook channel, which has no
customer-facing address) and dispatches through `NotificationDispatcher`
exactly like every other trigger source.

## 9. Admin UI

Notification Center (`/notifications` — list + ad-hoc compose),
Templates (+ inline Event Routing management), Channels (assign a
provider per channel type), Providers (CRUD, including the
not-yet-implemented future codes), and Delivery Log — which also
**is** Failed Deliveries and Retry Queue, filtered by `?status=`
(spec's three separate admin views are one list with a filter, the
same pattern Milestone 20's Reports used for its own list-view
variants).

## 10. Customer Portal

`/account/notifications` (spec section 10): notification history read
through `NotificationRecipient` (not `Notification` directly, since
read/unread state lives on the recipient row — one customer's read
state must never affect another recipient of the same message), plus
inline preference toggles.

## 11. Tenant isolation

Every entity uses `BelongsToTenant`. Verified in
`tests/Feature/Notifications/TenantIsolationTest.php`: one store's
templates/notifications/deliveries/channels/providers/preferences are
invisible to another (list empty, show 404s), including a specific
check that a re-assigned channel's provider never resolves to a
different store's `NotificationProvider` row even by raw id lookup.

## 12. Explicitly not implemented

Per spec section 14: no real SMTP/Mailgun/Twilio/any real provider
integration, no marketing campaigns, no AI-generated copy, no push
certificates, no scheduling engine (the `scheduled` trigger source and
`quiet_hours_*` columns are structure only — see §6/§7).

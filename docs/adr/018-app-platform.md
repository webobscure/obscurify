# ADR-018: App Platform — Reuse the Webhook Engine, Hash Every Secret, Own the Retry Timing

## Status
Accepted

## Context
Milestone 12 explicitly scopes itself to architecture: "allow third-party
apps to integrate with the platform without modifying Commerce Core,"
with Marketplace UI, billing, app review, and public publishing all
explicitly excluded. It hard-depends on "Apps consume Platform Events...
through webhook subscriptions" — which did not exist in this codebase
until Milestone 11 (Platform Events + Webhooks, ADR-017) was built
immediately beforehand, specifically as this milestone's prerequisite.

Given that dependency, and OAuth 2.1's own requirements (Authorization
Code + PKCE, refresh token rotation, revocation), three design questions
mattered most: how `AppWebhook` relates to Milestone 11's
`WebhookSubscription`; how the three different kinds of secret this
milestone introduces (OAuth client secret, access/refresh tokens,
app-owned webhook secrets) should each be stored; and how refresh token
reuse detection should be triggered without corrupting the transaction
that has to survive it.

## Options

**AppWebhook representation:**
1. A new `app_webhooks` table + model, with its own delivery job, HMAC
   signing, and retry logic — a second, parallel implementation of
   everything Milestone 11 just built for `WebhookSubscription`.
2. `AppWebhook` is not a distinct entity at the database level at all —
   an app-owned webhook is a `WebhookSubscription` row with `owner_type
   = 'app'`, `owner_id = installed_app_id` (columns Milestone 11 added
   specifically for this reuse), created through a small gateway
   controller that sets those two fields; `DeliverWebhookJob` and every
   other part of the delivery engine are untouched and shared.

**Secret storage (three different secrets, one milestone):**
1. One storage strategy for all three (e.g. `Hash::make()` everywhere,
   or `encrypted` cast everywhere).
2. Match the strategy to how each secret is actually *used*:
   `Hash::make()`/`Hash::check()` (bcrypt) for the OAuth client secret
   (checked interactively, never looked up by value); `hash('sha256',
   ...)` for access/refresh tokens (looked up by exact hash on every
   request — bcrypt's deliberate slowness would make every gateway call
   and every webhook delivery pay a ~100ms tax for no security benefit,
   since these are high-entropy random tokens, not low-entropy
   human-chosen passwords); `encrypted` cast (Milestone 11's own
   precedent) for `WebhookSubscription.secret`, since HMAC-signing an
   outbound delivery needs the *plaintext* back, not just a comparison.

**Refresh token reuse detection's transaction shape:**
1. Detect reuse and revoke-everything-plus-throw all inside one
   `DB::transaction()` closure — simplest to write.
2. Detect reuse and revoke everything inside the transaction, but return
   an outcome value and let the transaction commit normally; throw the
   OAuth error *after* `DB::transaction()` returns, based on that
   outcome.

## Decision

**AppWebhook: Option 2.** Verified during design that this reuse is
sound: `WebhookSubscription`'s `owner_type`/`owner_id` columns already
existed for exactly this (added in Milestone 11 explicitly anticipating
this milestone), and every piece of the delivery engine — signing,
retry/backoff, the idempotent `(subscription, event)` claim — is
ownership-agnostic; nothing in `DeliverWebhookJob` or
`DispatchWebhooksForEvent` cares whether `owner_type` is `store` or
`app`. Building a second engine would mean either two divergent retry/
signing implementations to keep in sync forever, or an app-owned
subscription that behaves subtly differently from a merchant-owned one
for no functional reason.

**Secrets: Option 2**, matched per-use-case as described above. This is
also why `AppToken`/`OAuthAuthorization` never store anything encrypted-
but-reversible: a token or authorization code only ever needs to be
*checked*, never displayed back, so a one-way hash is strictly the
correct primitive — reaching for `encrypted` there would be weaker
(reversible) for no benefit.

**Refresh token reuse: Option 2.** Option 1 was implemented first and
caught by this milestone's own test suite: `RefreshAppToken`'s reuse
branch calls `revokeAllTokens()` (a real `UPDATE` marking every token
for the installation revoked) and then throws `OAuthErrorException`
*from inside* `DB::transaction()`. Laravel/Postgres roll the entire
transaction back before re-throwing — including the revocation that was
the entire point of that code path. The observable bug: a concurrency
test asserted that after a detected reuse, zero unrevoked tokens remain;
instead the "security response" itself never committed, and a supposedly
revoked installation kept working. Fixed by restructuring `handle()` to
return a plain outcome array (`{ok, reason}` or `{ok, issued, scope}`)
from inside the transaction — which lets it commit normally regardless
of outcome — and only throwing the OAuth error afterward, outside the
transaction boundary, based on that returned value.

## Consequences

### Positive
- Zero duplicate webhook-delivery code — `AppWebhook` cost roughly 60
  lines (a gateway controller + two routes) instead of a second
  migration/model/job/signing implementation.
- The three-secrets decision means every secret in this milestone is
  stored using the primitive that's actually correct for how it's
  checked, not a one-size-fits-all default — and none of them can ever
  be recovered from the database by an attacker with read access, except
  `WebhookSubscription.secret`, which is `encrypted` (reversible by
  design, using the application key) rather than hashed, exactly because
  HMAC signing genuinely needs the plaintext back.
- The transaction-boundary fix is a real, previously-uncaught category
  of bug (throwing inside a transaction silently discards intentional
  side effects) now avoided consistently in `RefreshAppToken` — and
  documented here specifically so the same mistake isn't repeated in a
  future action that needs "do X, then fail the request" semantics.

### Negative
- `AppWebhook` having no dedicated table means an admin/API consumer
  can't distinguish "list only my app's webhooks" from "list only this
  store's own webhooks" via a single unfiltered query — every read path
  must filter by `owner_type` explicitly (already true of
  `WebhookSubscriptionController`, which only ever shows `owner_type =
  'store'` rows, and the new `AppWebhookGatewayController`/
  `InstalledAppController::webhooks()`, which only ever show a specific
  `owner_type = 'app'`/`owner_id` pair). Accepted as a minor, consistent
  filtering discipline rather than a real risk — no endpoint returns
  both kinds mixed together.
- `OAuthClient` is intentionally 1:1 with `App` (`app_id` unique) — a
  marketplace-scale app wanting separate client credentials per
  environment isn't supported yet, flagged as future work in
  docs/architecture/apps.md §13 rather than built speculatively now.

## Security Requirements
- OAuth 2.1 only: Authorization Code + PKCE (`S256` only, `plain`
  rejected outright), no implicit flow anywhere in the codebase.
- Every secret (OAuth client secret, access/refresh tokens, webhook
  secrets) is generated server-side, never accepted from a request, and
  returned by the API exactly once at creation/issuance — no endpoint
  can ever re-read a client secret or token value afterward.
- Refresh token rotation with reuse detection: presenting an
  already-rotated refresh token revokes every token the installation
  holds, verified to actually commit under real concurrent PostgreSQL
  connections (`tests/Concurrency/AppTokenRefreshConcurrencyTest.php`).
- `InstalledApp`, `AppPermission`, `OAuthAuthorization`, `AppToken`,
  `AppSetting`, and `AppExtension` all use `BelongsToTenant`, verified
  with dedicated cross-tenant tests, not just individually-scoped
  resources.
- The `/api/apps/v1` gateway enforces scope per-route
  (`EnsureAppScope`), checked against the token's own frozen scope list
  from issuance — a scope revoked after a token was issued does not
  retroactively narrow that token; the next issued token reflects the
  change.

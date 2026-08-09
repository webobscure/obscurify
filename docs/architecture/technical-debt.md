# Technical Debt Register — 2026-08

Compiled from the architecture review in [`review-2026-08.md`](review-2026-08.md). Covers `apps/api`, `apps/admin`, `apps/storefront`, `packages/*`, and `infra/`. Findings are numbered `TD-1`…`TD-47` and grouped by severity; each carries the review section it came from in parentheses. Items marked **[FIXED]** were resolved during this review per the task's "fix Critical or obvious High" allowance — see [review-2026-08.md](review-2026-08.md) for the full list of code changes and test results.

## Summary table

| # | Severity | Area | Summary | Status |
|---|---|---|---|---|
| TD-1 | Critical | Payments (§12) | Fake payment provider fails open when `APP_ENV` unset/non-canonical | **FIXED** |
| TD-2 | Critical | Payments (§8,12) | Webhook HMAC secret has hardcoded default in source | **FIXED** |
| TD-3 | High | Error handling (§10) | `ModelNotFoundException`/404 leaks internal FQCNs to anonymous callers | **FIXED** |
| TD-4 | High | Security (§12) | `CORS_ALLOWED_ORIGINS=*` + credentials silently allows any-origin credentialed access | **FIXED** |
| TD-5 | High | Security (§12) | `TrustProxies` never configured — rate limiting collapses to one global bucket | Open |
| TD-6 | High | Security (§12) | No rate limiting on any endpoint except login/register | Open |
| TD-7 | High | Security (§7,12) | Order PII readable by ULID alone, unthrottled, leaks via URL/Referer/logs | Open |
| TD-8 | High | Observability (§11) | PII persisted indefinitely in `idempotency_keys`; **no scheduler exists at all** | Open |
| TD-9 | High | Observability (§11) | Effectively no observability — 2 log lines total, no request-id, no Sentry | Open |
| TD-10 | Medium | Error handling (§10) | `GET /stores/{store}` is a 403-vs-404 cross-tenant existence oracle | Open |
| TD-11 | Medium | Error handling (§10) | Three incompatible error envelope shapes across the API | Open |
| TD-12 | Medium | Error handling (§10) | 409-vs-422 status inconsistency for webhook event-id reuse | Open |
| TD-13 | Medium | Error handling (§10) | 422-vs-404 status inconsistency for unknown provider | Open |
| TD-14 | Medium | Observability (§11) | Zero logging on the payment critical path | Open |
| TD-15 | Medium | Observability (§11,12) | `APP_DEBUG=true` default; no log-redaction processor | Open |
| TD-16 | Medium | API boundaries (§7) | Storefront route group entirely unthrottled, incl. PII reads | Open (see TD-6/7) |
| TD-17 | Medium | Payments (§7) | `external_transaction_id` actually holds a webhook event id | Open |
| TD-18 | Medium | Payments (§7,12) | Fake payment outcome page is a cross-tenant read oracle when enabled | Open |
| TD-19 | Medium | Payments (§8) | Webhook pipeline invoked by calling a controller as a service | Open |
| TD-20 | Medium | Payments (§8) | `SimulateFakePaymentWebhookJob` type-hints concrete provider, ungated | Open |
| TD-21 | Medium | Payments (§8) | Three `PaymentProviderContract` methods unused/speculative | Open |
| TD-22 | Medium | Security (§12) | Tenant boundary decided by unvalidated `Host` header | Open |
| TD-23 | Medium | Security (§12) | Guest cart cookie `Secure` flag coupled to literal env name | Open |
| TD-24 | Medium | Security (§12,15) | `config/sanctum.php` stateful/CSRF plumbing is fully dead code | Open (documented, ADR-011) |
| TD-25 | Medium | Security (§12) | No CSRF middleware anywhere; `SameSite=Lax` is the sole, untested defense | Open |
| TD-26 | Medium | Infra (§12) | Docker Compose publishes Postgres/Redis/MinIO with weak/no auth | Open |
| TD-27 | Medium | Authorization (§12) | No RBAC — `role` column exists but is never read by any authorization check | Open |
| TD-28 | Medium | DB schema (§5) | Missing `(store_id, created_at)` composite indexes on 4 list endpoints | Open |
| TD-29 | Medium | Inventory (§9) | `available = on_hand - reserved` invariant duplicated inline instead of reused | Open |
| TD-30 | Medium | Module boundaries (§1) | Catalog directly creates Inventory-owned rows via raw Eloquent | Open |
| TD-31 | Low | Module boundaries (§1,8) | Payments directly mutates `Order.financial_status` (documented, accepted) | Open (by design) |
| TD-32 | Medium | Outbox (§6,11) | `outbox:process` proves atomicity only, no real side effects, never scheduled | Open (see TD-8) |
| TD-33 | Medium | Frontend (§13) | `packages/ui` is an empty stub; every admin page reimplements the same load/watch pattern | Open |
| TD-34 | Medium | Frontend/Tests (§13,14) | `products/[id].vue` is a 422-line god component, untestable via Vitest | Open |
| TD-35 | Low | Error handling (§10) | Webhook-signature 401 collides with Sanctum's 401, missing `WWW-Authenticate` | Open |
| TD-36 | Low | Error handling (§10) | `TenantContextMissingException` hardcodes HTTP 428 in a non-HTTP-only domain exception | Open |
| TD-37 | Low | Payments (§8,10) | Dead `PaymentStateMachine::guard()` + exception with no `render()` | Open |
| TD-38 | Low | API boundaries (§7) | `StoreResource.settings` passes raw JSONB blob through unfiltered | Open |
| TD-39 | Low | API boundaries (§7) | `MediaResource.url` reflects storage disk config, breaks on private disk | Open |
| TD-40 | Low | API boundaries (§7) | Unbounded recursion, no visibility filter in `StorefrontCategoryResource` | Open |
| TD-41 | Low | Security (§12) | Login vulnerable to timing-based user enumeration | Open |
| TD-42 | Low | Security (§12) | Access tokens never expire, unbounded accumulation, unscoped abilities | Open |
| TD-43 | Low | Security (§12) | Password policy is framework default (8 chars, no breach check) | Open |
| TD-44 | Low | Concurrency (§4,12) | A crashed webhook leaves a permanently unclaimable event row | Open |
| TD-45 | Low | Security (§12) | Media uploads flat/unsegmented per tenant | Open |
| TD-46 | Info | Module boundaries (§1,8) | Bidirectional Orders↔Payments model coupling — acceptable, note for future extraction | Open (by design) |
| TD-47 | Low | Tests (§14) | No dedicated Customers-domain test file; coverage only indirect via Checkout | Open |

---

## Critical — fixed during this review

### TD-1. Fake payment provider failed open when `APP_ENV` was unset or non-canonical
**Problem.** `config/payments.php` defaulted `payments.fake.enabled` to `env('APP_ENV') !== 'production'` — any value other than the exact string `production` (unset, `staging`, `Production`, a typo) enabled the entire fake-payment surface, including the unauthenticated `POST /api/v1/fake-payments/{id}/outcome` endpoint that self-signs a webhook and marks orders paid.
**Why it matters.** `.env.example` ships `APP_ENV=local` with no `PAYMENTS_FAKE_ENABLED` entry — the common failure mode (copy `.env.example`, edit DB creds, deploy) landed squarely in the vulnerable state. A real customer could mark their own unpaid order as paid.
**Fix applied.** Inverted to an allowlist: `in_array(env('APP_ENV'), ['local', 'testing'], true)` — fails closed for any unrecognized environment. Verified via `APP_ENV=staging php artisan config:show payments.fake` → `enabled: false`.
**When.** Done.

### TD-2. Webhook HMAC secret had a hardcoded default in source
**Problem.** `config/payments.php` defaulted `payments.fake.secret` to the literal string `'fake-payment-provider-secret'`, present in every environment that didn't explicitly override it.
**Why it matters.** That secret is the only thing standing between an anonymous request and a forged "payment succeeded" webhook. A hardcoded fallback for a value whose only job is to be unguessable defeats the mechanism, and it sets a bad precedent for the first real provider's secret.
**Fix applied.** Removed the default (`env('PAYMENTS_FAKE_SECRET', '')`); `PaymentServiceProvider::boot()` now throws at boot if the fake provider is enabled with an empty secret. Documented `PAYMENTS_FAKE_SECRET` in `.env.example`/`.env`/`phpunit.xml`. Verified the boot-time throw manually.
**When.** Done.

---

## High

### TD-3. `ModelNotFoundException`/404 leaked internal FQCNs — fixed
**Problem.** Laravel preserves `ModelNotFoundException`'s message through conversion to a 404, even with `app.debug` off — `GET /api/v1/storefront/products/nope` returned `{"message":"No query results for model [App\\Domain\\Catalog\\Models\\Product]."}` to any anonymous caller.
**Why it matters.** Hands an attacker the internal module map as free reconnaissance, and contradicts the codebase's own convention of message-free 404s.
**Fix applied.** `bootstrap/app.php` now normalizes both `ModelNotFoundException` and `NotFoundHttpException` on `api/*` requests to `{"message":"Not found.","error":"not_found"}`. Regression test added in `StorefrontProductTest.php`.
**When.** Done.

### TD-4. `CORS_ALLOWED_ORIGINS=*` + credentials — fixed
**Problem.** `.env.example` documented no `CORS_ALLOWED_ORIGINS` value; the natural operator fix for a broken-CORS incident is `CORS_ALLOWED_ORIGINS=*`. With `supports_credentials: true`, that doesn't behave like a real wildcard — the CORS library reflects the request's `Origin` header back with `Access-Control-Allow-Credentials: true`, letting any site make authenticated cross-origin requests.
**Why it matters.** Looks like the safe, standard "loosen CORS" move; is actually worse than no CORS restriction at all.
**Fix applied.** `AppServiceProvider::boot()` now throws at boot if `cors.allowed_origins` contains `*` while `cors.supports_credentials` is true. Documented `CORS_ALLOWED_ORIGINS` in `.env.example`. Verified the boot-time throw manually.
**When.** Done.

### TD-5. `TrustProxies` never configured
**Problem.** `bootstrap/app.php` never calls `->trustProxies(...)`. Behind `infra/nginx/` or any load balancer, `$request->ip()` returns the proxy's address for every request, and `$request->isSecure()` returns false behind TLS termination.
**Why it matters.** The only rate limiter in the app (`auth`, 5/min) keys on `$request->ip()` — behind a proxy it becomes one global 5-req/min bucket for every user, both a DoS vector (one attacker locks out all merchants) and a defeated protection (no longer scoped to the actual attacker).
**Recommended fix.** `->trustProxies(at: env('TRUSTED_PROXIES'))` in `bootstrap/app.php`, set per environment; add `X-Forwarded-For` plumbing in `infra/nginx/default.conf`.
**When.** Next sprint, before real traffic — needs real deployment topology to set correctly, out of scope for this review's guardrails against guessing infra values.

### TD-6. No rate limiting on any endpoint except login/register
**Problem.** Exactly one `RateLimiter::for` definition exists (`auth`, `AppServiceProvider.php`). Every webhook, checkout, payment-creation, and storefront-read endpoint is unthrottled.
**Why it matters.** Unmetered brute force against the order-ULID space (TD-7), resource exhaustion via the webhook HMAC path, cart/checkout row flooding.
**Recommended fix.** `->throttleApi()` for a global baseline, plus named limiters for `checkout`, `payment`, `webhook`. Depends on TD-5 for correct per-attacker keying.
**When.** Next sprint, after TD-5.

### TD-7. Order PII readable by ULID alone, unthrottled
**Problem.** `GET /api/v1/storefront/orders/{order}` returns email, phone, and both full addresses to anyone presenting the order ULID — a deliberate capability-URL design, undermined by: no rate limit (TD-6), the ULID appearing in the browser URL bar (history, `Referer`, access logs), and Symfony ULIDs being monotonic within a millisecond (same-millisecond orders are adjacent).
**Why it matters.** On a busy store, an attacker placing their own order narrows the neighboring search space; combined with no throttling, PII becomes bulk-scrapable.
**Recommended fix.** Bind order confirmation to the cart cookie the way `CompleteCheckout::resolveOpenCheckout` already does for its own read path. Where a bookmarkable link is required, issue a separate high-entropy `confirmation_token` column rather than overloading the primary key, and rate-limit the endpoint.
**When.** Next sprint — escalate to Critical before the store handles real customer data at volume. Needs a migration, out of scope for this review's fix budget.

### TD-8. PII in `idempotency_keys`, and no scheduler exists at all
**Problem.** `IdempotencyKeyStore` writes the full checkout-confirmation response body (email, phone, both addresses, line items) to a plaintext `jsonb` column with a 24h `expires_at` — but there is no `idempotency-keys:prune` command, and **no scheduler is registered anywhere** (`routes/console.php` has only the stock `inspire`, zero `Schedule::` calls in the whole repo). `carts:prune-expired` and `outbox:process` are equally dead — they exist but never run unless someone invokes them by hand.
**Why it matters.** A DB dump or staging restore leaks customer PII the team believes is 24-hour-scoped, indefinitely. For a Russian-market platform this is a 152-ФЗ retention exposure. The outbox not draining automatically is a live bug independent of the PII concern.
**Recommended fix.** Add `idempotency-keys:prune`; register a `withSchedule(...)` block in `bootstrap/app.php` for `idempotency-keys:prune` (hourly), `carts:prune-expired` (daily), `outbox:process` (every minute); reduce what's snapshotted (ids + status, re-render on replay) rather than storing PII at all.
**When.** Now, ideally — deferred here only because it's new command/schedule surface, not a pure fix to existing code, and the review's fix budget was spent on the two Critical items plus TD-3/TD-4. Next sprint at the latest.

### TD-9. Effectively no observability
**Problem.** Exactly two `Log::` call sites exist in the entire `app/` tree. No request-id middleware, no Sentry (despite `ARCHITECTURE.md` requiring Sentry-compatible reporting), no structured/JSON log formatter, no queue segmentation (Horizon supervises only `default`, despite `ARCHITECTURE.md` specifying `critical/payments/orders/webhooks/...`).
**Why it matters.** "Customer paid but order still shows unpaid" is undiagnosable today — no record the webhook arrived, no `store_id`/`order_id`/`payment_id` correlation, nothing to grep.
**Recommended fix.** Request-id middleware + `Log::shareContext()` wiring for `store_id`/`user_id` (both already resolved on every tenant request, just unused for logging); Sentry; structured JSON log channel; queue segmentation per `ARCHITECTURE.md`.
**When.** Before the next milestone ships.

---

## Medium

### TD-10. `GET /stores/{store}` is a 403-vs-404 existence oracle
Route-model-binding resolves any store on the platform before `StorePolicy::view` denies with 403; a non-existent ULID 404s. Every other cross-tenant path in this codebase deliberately 404s instead. **Fix:** convert the policy denial to 404 here and in `activate`. **When:** next sprint.

### TD-11. Three incompatible error envelope shapes
Domain exceptions return `{message, error}`; `ValidationException` returns `{message, errors}`; plain `abort()`/Symfony exceptions return `{message}` only. Frontends can't branch on one stable field. **Fix:** standardize via a `withExceptions` render fallback stamping an `error` code; document in a follow-up ADR to ADR-006. **When:** next sprint, before more modules land.

### TD-12. 409-vs-422 inconsistency for webhook event-id reuse
`MalformedWebhookPayloadException` (422) fires for the same "event id reused, different payload" case that `IdempotencyConflictException` (409) correctly handles elsewhere. **Fix:** throw the 409 variant. **When:** next sprint, with TD-13.

### TD-13. 422-vs-404 inconsistency for unknown payment provider
Unknown provider from the URL path (`/payments/webhooks/{provider}`, should 404 — nonexistent route resource) and from the request body (`CreatePayment`, correctly 422) share one exception mapped to a fixed 422. **Fix:** catch and rethrow as 404 in `PaymentWebhookController`. **When:** next sprint.

### TD-14. Zero logging on the payment critical path
`PaymentWebhookController::handle` and most of `ProcessPaymentWebhook`/`CreatePayment` log nothing, including on rejection — the single most important state change in the product (`financial_status → paid`) is invisible. **Fix:** add info/warning logs at webhook received, signature rejected, replay rejected, payment created, transition applied. **When:** alongside TD-9.

### TD-15. `APP_DEBUG=true` default; no log-redaction processor
Appropriate for local, but combined with TD-3 (before the fix) meant any environment provisioned from `.env.example` leaked stack traces. Pre-emptively: the natural debugging instinct when a real provider misbehaves is "log the raw webhook payload," which would carry payer metadata once a real provider lands. **Fix:** ensure deploy tooling sets `APP_DEBUG=false` explicitly; add a Monolog processor redacting `password/token/secret/card/pan/cvv/Authorization/X-*-Signature` keys from all log context; add an explicit "never log the raw payload" comment at `ProcessPaymentWebhook::handle`. **When:** pre-emptive, before a real provider is added.

### TD-16. Storefront route group entirely unthrottled
See TD-6/TD-7 — the whole `storefront` prefix group carries no throttle middleware, including the two DB-writing endpoints (`checkout/complete`, `orders/{order}/payments`). **When:** next sprint, with TD-6.

### TD-17. `external_transaction_id` actually holds a webhook event id
`PaymentTransactionResource.external_transaction_id` is populated from `WebhookEvent::eventId`, not a provider transaction id — a contract lie that's cheap to fix now and expensive after a real provider ships (it needs a migration). **Fix:** rename to `external_event_id`, or add a distinct column. **When:** before the next payment-provider milestone.

### TD-18. Fake payment outcome page is a cross-tenant read oracle when enabled
`FakePaymentOutcomeController::show` queries with `withoutGlobalScopes()` and returns payment/order data for any `external_payment_id`, no auth, no tenant context. Double-guarded today (route registration is config-gated, controller re-checks, TD-1's fix makes the default fail closed) but a single env var away from exposure in a shared non-local environment. **Fix:** additionally assert `app()->environment(['local', 'testing'])` inside the controller itself, not just the config default. **When:** next sprint.

### TD-19. Webhook pipeline invoked by calling a controller as a service
`FakePaymentOutcomeController::outcome` and `SimulateFakePaymentWebhookJob` both synthesize an `Illuminate\Http\Request` and call `app()->call([$webhookController, 'handle'], ...)`, using the controller as a domain service. **Fix:** extract `resolve → verify → parse → ProcessPaymentWebhook` into an application-layer `HandleProviderWebhook` class; controller becomes a thin delegate. **When:** before the next payment-provider milestone — needs the same pipeline, cheaper to migrate once now than three call sites later.

### TD-20. `SimulateFakePaymentWebhookJob` type-hints the concrete provider, ungated
Depends on `FakePaymentProvider` directly instead of the contract, and isn't guarded by `config('payments.fake.enabled')` the way the outcome controller is — fails via a confusing exception inside a queue worker rather than a clean early return. **Fix:** add the same config guard; resolve via the registry. **When:** next sprint, with TD-19.

### TD-21. Three `PaymentProviderContract` methods are unused and speculative
`capturePayment`/`cancelPayment`/`refundPayment` have zero call sites and no-op implementations, guessed ahead of any real provider's actual semantics (partial captures, async refund confirmation via webhook) — contradicts the contract's own docblock. **Fix:** reduce the contract to what's exercised; reintroduce shaped by the first real provider. **When:** before that provider lands — deleting now is cheaper than migrating later.

### TD-22. Tenant boundary decided by an unvalidated `Host` header
`EnsureStorefrontTenantContext` resolves tenant from `$request->getHost()`; no `trustHosts`/`trustProxies` configured, nginx passes `Host` through unvalidated (`server_name _`). `curl -H 'Host: store-b...' https://store-a.../...` resolves store B's context. Storefront content is public by design so no direct data leak today, but it's a client-controlled root of trust that compounds TD-7 and risks cache-poisoning once a CDN is added. **Fix:** `->trustHosts(at: [...])` scoped to registered storefront domains. **When:** next sprint, with TD-5.

### TD-23. Guest cart cookie `Secure` flag coupled to the literal environment name
`StorefrontCartController` sets `secure: app()->environment('production')` — bypasses the already-env-driven `config('session.secure')`. Any HTTPS deployment not named exactly `production` sends the cart token in cleartext. **Fix:** `secure: config('session.secure', true)`. **When:** next sprint.

### TD-24. `config/sanctum.php` stateful/CSRF plumbing is fully dead code
Confirmed by direct trace: `->statefulApi()` is never called, so `sanctum.stateful`/`sanctum.middleware` and `SANCTUM_STATEFUL_DOMAINS` are never read. Already documented in ADR-011 (merchant admin uses bearer tokens); the storefront cart also doesn't depend on it. **Fix:** delete the unused config keys and env var, pointing at ADR-011. **When:** opportunistic cleanup.

### TD-25. No CSRF middleware anywhere; `SameSite=Lax` is the sole, untested defense
The `api` middleware group carries no CSRF check. Defensible today (every state-changing storefront route is non-`GET`, `SameSite=Lax` blocks cross-site form/fetch submission), but it's one control with no defense in depth and no test pinning the cookie's `SameSite`/`HttpOnly`/`Secure` attributes. **Fix:** add a feature test asserting the cookie flags and that no storefront `GET` mutates state. **When:** next sprint, with TD-23.

### TD-26. Docker Compose publishes Postgres/Redis/MinIO with weak/no auth
Short port syntax (`"5433:5432"` etc.) binds `0.0.0.0`, not loopback; Postgres/MinIO have weak default creds, Redis has **no password at all** while `SESSION_DRIVER=redis` puts sessions there. **Fix:** bind to `127.0.0.1:...`; add `--requirepass` to Redis. **When:** opportunistic — low effort, real reduction in developer-machine exposure.

### TD-27. No RBAC — `role` exists but nothing reads it
`StoreUserRole` (`Owner`/`Administrator`/`Manager`) is defined and assigned (`Owner`, on store creation), but confirmed via direct grep that **no authorization check anywhere reads `->role`** — `EnsureTenantContext` and `StorePolicy` check only `status === Active`. There is also no invite/add-member flow yet, so in practice every `StoreUser` today is an `Owner`, which is why this hasn't caused an incident — but the schema promises a distinction the code doesn't honor. **Fix:** gate destructive/financial routes on role once an invite flow exists; design the check now. **When:** before onboarding merchants with multiple staff accounts.

### TD-28. Missing `(store_id, created_at)` composite indexes
Confirmed by tracing actual query patterns: `PaymentController`, `InventoryController`, `ProductController`, and `CollectionController` all list via `WHERE store_id = ? ORDER BY created_at DESC`, but `payments`, `inventory_items`, `products`, and `collections` only carry a single-column `store_id` index (products also has `(store_id, status)` and a `(store_id, slug)` unique index, neither of which covers the `created_at` sort). Orders is fine — its `(store_id, number)` unique index happens to cover its actual `ORDER BY number DESC` list query. **Fix:** add `(store_id, created_at)` composite indexes to the four affected tables. **When:** next sprint — cheap migration, matters more as data volume grows.

### TD-29. `available = on_hand - reserved` invariant duplicated inline
`InventoryLevel::available()` is the documented single source of truth (used by `InventoryLevelResource`), but `ReserveInventory::handle` — the most concurrency-critical code in the app, running under `lockForUpdate()` inside `CompleteCheckout`'s transaction — recomputes `$level->on_hand - $level->reserved` inline instead of calling `$level->available()`. **Why it matters:** if the formula ever changes (e.g. to exclude backordered stock), this copy silently goes stale in exactly the path where correctness matters most. **Fix:** replace the inline computation with `$level->available()`. **When:** opportunistic — zero behavior change, pure dedup.

### TD-30. Catalog directly creates Inventory-owned rows
`CreateProductVariant::handle` calls `InventoryItem::query()->create(...)` directly rather than through an Inventory-domain entry point — the only call site (confirmed via grep), so not yet duplicated, but it means Inventory's provisioning invariants (`tracked`, `requires_shipping` defaults) live in Catalog's application layer. **Fix:** introduce an `Inventory\Application\ProvisionInventoryItem` the way `ReserveInventory`/`AdjustInventory` already exist for other inventory operations. **When:** opportunistic — low risk today since it's one call site, but do it before a second creator (e.g. a bulk-import flow) copies the pattern.

### TD-32. `outbox:process` proves atomicity only, and was never scheduled
The command is deliberately minimal per its own docblock — no real event dispatch/side effects yet, exists to prove the transactional-outbox guarantee. Combined with TD-8: since nothing schedules it, `outbox_events` rows accumulate with `processed_at = null` forever in practice. The atomicity guarantee itself is real and verified (`RecordOutboxEvent::handle` is called from inside the same `DB::transaction` as the domain state change in all three call sites — `CompleteCheckout`, `CreatePayment`, `ProcessPaymentWebhook` — confirmed by tracing each). **Fix:** see TD-8. **When:** with TD-8.

### TD-33. `packages/ui` is an empty stub; admin pages duplicate the load/watch pattern
`packages/ui/package.json` exists with no actual components. Nine separate `apps/admin/app/pages/**/*.vue` files each independently implement the identical `onMounted(load) + watch(() => activeStore.storeId.value, load)` pattern instead of a shared composable (e.g. `useTenantScopedList()`). **Fix:** extract the pattern into a composable in `apps/admin/app/composables/`; decide `packages/ui`'s actual scope (delete if genuinely unneeded, or start using it). **When:** opportunistic — grows more expensive with every new list page added.

### TD-34. `products/[id].vue` is a 422-line god component
Mixes product core fields, options, option values, variants, media upload, collection membership, and inventory adjustment in one file with 20+ inline functions, no child-component or composable decomposition. **Why it matters:** none of this logic is unit-testable via Vitest as structured (matches the near-total absence of admin business-logic tests today — `apps/admin/tests/` has 2 small files unrelated to page logic). **Fix:** decompose into child components (`ProductOptionsPanel`, `ProductVariantsPanel`, `ProductMediaPanel`, ...) each owning its own composable. **When:** next time this page needs a non-trivial change — don't do a standalone rewrite now (see review guardrails).

---

## Low / Info

### TD-31 / TD-46. Orders↔Payments and Catalog↔Inventory coupling (by design, documented here for future reference)
`ProcessPaymentWebhook::applyTransition` directly mutates `Order.financial_status` via `$order->update(...)`, and `Order` holds a `payments()` relation. Acceptable in a modular monolith and the write direction is correct — Payments owns the transition per its own docblock. Recorded here as the first place to cut if Payments is ever extracted to a separate service.

### TD-35. Webhook-signature 401 collides with Sanctum's 401
`InvalidWebhookSignatureException` returns 401 without a `WWW-Authenticate` header on a route deliberately outside `auth:sanctum` — indistinguishable in logs/dashboards from an expired admin token. 403 is the more accurate code. Fix opportunistically with TD-11.

### TD-36. `TenantContextMissingException` hardcodes HTTP 428 in a non-HTTP-only exception
Thrown from `TenantContext::store()`, reachable from any queue job or console command via the `BelongsToTenant` global scope with no tenant restored — carries an HTTP status code into a context that isn't HTTP. Split into a domain-level exception plus a per-surface HTTP mapping when the first tenant-scoped queue job is added.

### TD-37. Dead `PaymentStateMachine::guard()` + exception with no `render()`
Zero call sites (`ProcessPaymentWebhook` uses `canTransition()` + logging instead); `InvalidPaymentTransitionException` has no `render()` and would 500 if `guard()` were ever wired up. Delete both, or wire `guard()` in and add a 409 `render()`.

### TD-38. `StoreResource.settings` passes the raw JSONB blob through unfiltered
No leak today (admin-only, membership-gated), but `settings` is documented as the catch-all for store config — the first integration secret stashed there ships to the admin client with no Resource-layer review catching it. Whitelist known keys before anything writes secrets into `settings`.

### TD-39. `MediaResource.url` reflects the storage disk configuration
Correct for a public S3/MinIO disk; discloses on-disk layout and won't resolve for a private disk. Address when private media (invoices, downloadable products) is introduced.

### TD-40. Unbounded recursion, no visibility filter in `StorefrontCategoryResource`
Recurses into `children` with no depth cap on an unauthenticated, unthrottled endpoint; `Category` has no status column today so the missing visibility filter is consistent, not yet a bug — note for whoever adds category drafts.

### TD-41. Login vulnerable to timing-based user enumeration
`AuthController::login` short-circuits before `Hash::check` when the user doesn't exist, skipping bcrypt entirely — a measurable timing difference. Always run `Hash::check` against a dummy hash when the user is absent, or use `Auth::attempt`.

### TD-42. Access tokens never expire, unbounded accumulation, unscoped abilities
`sanctum.expiration` is `null`; every login mints a new token, logout revokes only the current one. A single stolen token from `localStorage` (ADR-011's bearer-token model) is permanent, full-scope admin access. Add finite expiration, scoped abilities, stale-token pruning.

### TD-43. Password policy is the framework default
`Password::defaults()` resolves to `min(8)` with no complexity or breach check — no `Password::defaults(...)` is registered anywhere. Register `Password::min(12)->uncompromised()` in `AppServiceProvider::boot`.

### TD-44. A crashed webhook leaves a permanently unclaimable event row
If processing throws after the claim commits but before `processed_at` is set, every legitimate retry polls up to 2 seconds then fails. Add a stale-claim timeout allowing re-acquisition.

### TD-45. Media uploads flat and unsegmented per tenant
Not a traversal/execution risk (verified: SVG excluded, size-capped, server-generated filenames) — purely operational: no per-tenant prefix means no per-tenant bucket policy/quota/lifecycle rule, and tenant deletion can't be a prefix delete. Prefix paths with `store_id`.

### TD-47. No dedicated Customers-domain test file
`Customer`/`CustomerAddress` have no direct feature tests — coverage is indirect, via `CheckoutFlowTest`/`CheckoutTenantIsolationTest`/`OrderSnapshotImmutabilityTest` (the last of which is a genuinely good invariant test, proving order snapshots survive later customer edits). Low severity because there's no admin-facing Customers API yet to test directly (`ARCHITECTURE.md`/nav config both confirm this is intentional for the current milestone) — revisit once one exists.

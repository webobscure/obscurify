# Apps SDK + OAuth + Extension Platform

Milestone 12 — the application extension platform. Architecture only, as
the milestone brief demands: no Marketplace UI, no billing, no app
review, no public app publishing. See
[ADR-018](../adr/018-app-platform.md) for the key design decisions.

## 1. Apps domain

A dedicated `App\Domain\Apps` module.

```text
App                 Apps\Models   the app definition (private or public)
OAuthClient         Apps\Models   1:1 with App — client_id + hashed client_secret
InstalledApp        Apps\Models   one row per (Store, App) install — everything else hangs off this
AppPermission       Apps\Models   one row per granted scope, revoked_at not deleted
OAuthAuthorization   Apps\Models   a single-use authorization code grant (PKCE)
AppToken            Apps\Models   access or refresh token, hashed, rotated
AppSetting          Apps\Models   per-installation key/value config
AppExtension        Apps\Models   one row per extension-point contribution
CurrentAppContext   Apps\Support  the /api/apps/v1 gateway's analogue of TenantContext
AppScope            Apps\Support  the scope registry (plain string list)
ExtensionPointRegistry Apps\Support validates a contribution's config shape per point
```

`App` and `OAuthClient` are deliberately **not** `BelongsToTenant`: a
Public app has no owning store at all, and the OAuth token-exchange flow
must resolve a client by `client_id` before any tenant is known — the
same reasoning `PaymentWebhookEvent` already established for inbound
provider webhooks. Every other table here is `BelongsToTenant`.

## 2. Private vs. Public apps

`App.type` is the only distinction — `private` (`store_id` set, created
by and installable only on that store) or `public` (`store_id = null`,
installable by any store — "internal support only" per spec section 2:
there is no marketplace listing/discovery UI, a merchant who already
knows a public app exists can see and install it via `GET /apps`, which
returns the union of "apps this store owns" and "every public app").

## 3. OAuth 2.1 — Authorization Code + PKCE only

No implicit flow exists anywhere in this codebase (spec section 3).

1. **Consent** (`GET`/`POST /oauth/authorize`, authenticated in the
   admin under the merchant's own session and active store —
   `BeginAuthorization`): validates `client_id`, that `redirect_uri` is
   one of the App's own registered `redirect_urls`, that
   `code_challenge_method` is `S256` (OAuth 2.1 drops `plain` entirely —
   rejected outright, never silently downgraded), and that every
   requested scope is known. Installs the app for the active store (or
   reactivates a previously-uninstalled row) and grants exactly the
   scopes requested in *this* authorization — which may be a subset of
   the App's own declared `requested_scopes`. Creates a single-use
   `OAuthAuthorization` (code stored only as a SHA-256 hash) and returns
   a `redirect_url` for the admin SPA to send the browser to.
2. **Exchange** (`POST /oauth/token`, `grant_type=authorization_code` —
   called by the app's own server, no session at all —
   `ExchangeAuthorizationCode`): resolves tenant from the claimed
   `OAuthAuthorization`'s own `store_id` (`TenantContext::scope()`, the
   same pattern `ProcessPaymentWebhook` uses for inbound webhooks),
   locks the authorization row, verifies it's unused/unexpired, verifies
   `redirect_uri` matches, verifies PKCE
   (`base64url(sha256(code_verifier)) === code_challenge`, compared with
   `hash_equals`), marks it used, and issues an access+refresh token
   pair.
3. **Refresh** (`grant_type=refresh_token` — `RefreshAppToken`): OAuth
   2.1 refresh token rotation — every refresh consumes the presented
   token and issues a brand new pair, chained via `AppToken.rotated_from_id`.
4. **Revocation** (`POST /oauth/revoke` — `RevokeAppToken`): works for
   either an access or a refresh token; unknown-token requests still
   return success (RFC 7009 §2.2 — never lets a caller probe for which
   tokens exist).

### Refresh token reuse detection

If a refresh token that's **already revoked** (already spent by an
earlier rotation, or manually revoked) is presented again, that's a
theft signal — a stolen token being used alongside the legitimate one.
The response is to revoke *every* token the `InstalledApp` holds,
including ones issued moments earlier in the same race, forcing a fresh
authorization rather than trusting anything already issued. This
revocation must **commit even though the request itself ends in an
error** — `RefreshAppToken` returns an outcome value from inside
`DB::transaction()` and throws only *after* it returns, never from
inside the transaction (throwing inside would roll the revocation back
along with everything else — a real bug caught during this milestone's
own testing, see §8 below). Proven safe under two genuinely concurrent
requests for the same token in
`tests/Concurrency/AppTokenRefreshConcurrencyTest.php`.

## 4. Scopes

`AppScope::known()` — a plain string array
(`products.read`/`.write`, `orders.read`/`.write`, `customers.read`,
`inventory.read`/`.write`, `payments.read`, `shipping.read`,
`webhooks.read`/`.write`), not a hard enum: a new scope is a one-line
addition (spec section 4: "future scopes should be easy to add").
`AppPermission` records what's actually been *granted* to an
`InstalledApp` (revoked via `revoked_at`, never deleted); the token
itself carries its own frozen `scope` array from the moment it was
issued — `EnsureAppScope` checks the token's own scope list on every
gateway request, not a live re-read of `AppPermission` (a scope
change takes effect on the *next* token, not retroactively on one
already issued — the same "a credential's authority is fixed at
issuance" principle most OAuth providers follow).

## 5. Token model

Every `AppToken` row (access or refresh, distinguished by `type`) stores
only a SHA-256 hash (`token_hash`, unique) — the plaintext value is
returned exactly once, at issuance (`IssueAppTokenPair`), and is
unrecoverable afterward. `expires_at` bounds both types (access: short,
configurable via `apps.oauth.access_token_ttl_minutes`; refresh: long,
`apps.oauth.refresh_token_ttl_days`); `revoked_at` covers manual
revocation, rotation, and reuse-detection sweeps; `rotated_from_id`
chains a refresh token to the one it replaced, which is what makes reuse
detection possible. Admin visibility (`GET
/installed-apps/{id}/tokens`) is read-only and never exposes
`token_hash` — audit only (spec section 5/12), never a way to
reconstruct or use a token.

## 6. Event integration

Apps never subscribe directly to business domains (spec section 6).
`AppWebhook` is not a separate table — it's `WebhookSubscription`
(Milestone 11's Platform Events + Webhooks engine) with `owner_type =
'app'`, `owner_id = installed_app_id`, created by the app itself via
`POST /api/apps/v1/webhooks` (`webhooks.write` scope). Delivery, HMAC
signing, retry/backoff, and idempotent fan-out are all the exact same
mechanism a merchant-owned subscription uses — no second delivery
engine exists. See [docs/architecture/webhooks.md](./webhooks.md).

## 7. REST API Gateway — `/api/apps/v1`

A top-level namespace, deliberately **sibling to `/api/v1`, not nested
under it** — this is a third-party integration surface with its own
versioning lifecycle. Authenticated by `AuthenticateAppToken`
(hashes the bearer token, resolves the `InstalledApp` and its store,
sets `TenantContext` — an app never sends an `X-Store-Id` header, its
token is already scoped to exactly one store) — never by the merchant
admin's Sanctum session. Every route additionally requires its own
scope via `EnsureAppScope` (`->middleware('app-scope:orders.read')`).

Every response is a purpose-built `Gateway*Resource` — `GatewayProductResource`,
`GatewayOrderResource`, `GatewayCustomerResource`, `GatewayPaymentResource`,
`GatewayShippingMethodResource` — deliberately decoupled from the admin
UI's own resources (spec: "Use API Resources. No direct Eloquent
exposure."), so the gateway's public contract never accidentally
changes just because an admin-only field gets added elsewhere.
`InventoryGatewayController` is the one exception: it reuses the admin
domain's own `AdjustInventory` action and resources directly, since
inventory adjustment has exactly one correct implementation and
duplicating it would risk the two paths drifting apart.

## 8. Extension points

`ExtensionPoint` (`checkout`, `order`, `product`, `customer`,
`admin_navigation`, `admin_widget`, `dashboard_card`) — "pluggable"
(spec section 8) means a new point is one new enum case plus one new
`match` arm in `ExtensionPointRegistry::assertValidConfig()`, not a
schema change (`app_extensions.config` is jsonb regardless of point).
An app registers a contribution via `POST /api/apps/v1/extensions` (no
scope required beyond a valid token — this configures the app's own
presence, not store data access).

`AdminNavigation`/`AdminWidget`/`DashboardCard` are the points this
milestone's admin UI actually reads back
(`GET /admin-extensions?point=admin_navigation`, per spec section 9's
"do not hardcode menu changes") — the Nuxt admin can merge these into
its own sidebar/dashboard. `Checkout`/`Order`/`Product`/`Customer` are
registered and validated the same way but stay **backend-only
contracts** this milestone: no live storefront-embedding mechanism
exists yet (spec's own constraint: "must not contain storefront-
specific logic"), so a registered checkout/order/product/customer
extension is visible and queryable but has no runtime effect on the
storefront or admin order/product pages yet. Building that render path
is additive future work, not a redesign — the registration/validation
layer is already in place.

## 9. Secret management

Three kinds of secret exist, all handled the same way: generated
server-side, never accepted from a request, and returned by the API
**exactly once**:

- `OAuthClient.client_secret_hash` — `Hash::make()` (bcrypt, Laravel's
  password-hashing default, since it's checked interactively via
  `Hash::check()` on every token exchange — not a lookup key).
- `AppToken.token_hash` — `hash('sha256', ...)`, since tokens are looked
  up by exact value on every gateway request (a lookup key, not a
  password check).
- `WebhookSubscription.secret` (Milestone 11) — cast `encrypted`
  (Laravel's built-in cast), since the *plaintext* is needed later to
  compute each delivery's HMAC signature, unlike the other two which
  only ever need a hash comparison.

No `App*Resource` class ever includes a secret field — the one-time
reveal happens by the controller manually appending it to the resolved
resource array in the `store()` response only (`WebhookSubscriptionResource`/
`AppResource`/`AppTokenResource` all omit it structurally, not by
convention).

## 10. Tenant isolation

`InstalledApp`, `AppPermission`, `OAuthAuthorization`, `AppToken`,
`AppSetting`, and `AppExtension` all use `BelongsToTenant`. Verified
with dedicated cross-tenant tests
(`tests/Feature/Apps/AdminAppApiTest.php`): Store A can never read,
install, or list a Store B private App; can never read, list tokens
for, list webhooks for, or uninstall a Store B `InstalledApp`.

## 11. Audit log

Spec section 12's list (App installed/removed, permission granted/
revoked, token created/revoked, OAuth authorization) is recorded via
the existing `RecordOutboxEvent`/`OutboxEvent` mechanism (Milestone 11's
Platform Events) rather than a bespoke audit table —
`AppInstalled`/`AppUninstalled`/`OAuthAuthorizationGranted`/
`AppTokenCreated`/`AppTokenRevoked`/`AppTokenReuseDetected` are all real
event types a merchant can even subscribe a `WebhookSubscription` to for
their own compliance tooling, for free, since it's the same mechanism.

## 12. Explicitly not implemented

No Marketplace, no billing, no public store/listing, no revenue share,
no app review process, no GraphQL (spec section 15).

## 13. Future extensibility

- **Live storefront/admin extension rendering** for Checkout/Order/
  Product/Customer points — the registration and validation layer
  already exists (§8); wiring an actual render path is additive.
- **Multiple OAuth clients per App** — today it's strictly 1:1
  (`OAuthClient.app_id` is `unique()`); a marketplace-scale app wanting
  separate client credentials per environment (staging/production) would
  need this relationship to become one-to-many.
- **A delivery attempts log** for `AppWebhook` — same deliberate scope
  cut Milestone 11 already made for merchant-owned subscriptions; see
  docs/architecture/webhooks.md §8.
- **Scheduler wiring** for `outbox:process`/`webhooks:retry-failed` — an
  ops concern outside this milestone, consistent with `outbox:process`
  already being unwired before this milestone started.

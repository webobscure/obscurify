# Customer Accounts + Customer Portal

Milestone 16 — the customer identity layer: storefront authentication,
sessions, a customer portal (orders/addresses/returns/reorder), and
merchant-admin customer management. See
[ADR-022](../adr/022-customer-identity.md) for the key design decisions
and why several things reuse existing machinery instead of building
parallel systems.

Explicitly out of scope, per the milestone brief: social login, OAuth
customer login, loyalty programs, subscriptions, B2B accounts, customer
groups, CRM automation.

## 1. Customer identity model

A dedicated `App\Domain\Customers` module, extending the CRM `Customer`
record that has existed since Milestone 1 rather than replacing it.

```text
Customer               Customers\Models  the profile — email/name/phone/status/verified_at (pre-existing + this milestone's auth columns)
CustomerIdentity        Customers\Models  the credential — (store, type, identifier) unique, hashed secret, lockout state
CustomerSession          Customers\Models  one row per logged-in device/browser
CustomerAccessToken       Customers\Models  access/refresh bearer token pair, hashed, rotated
CustomerAddress          Customers\Models  the address book (pre-existing) + default billing/shipping flags (this milestone)
CustomerPreference        Customers\Models  free-form key/value store, ThemeSetting-shaped
CustomerActionToken       Customers\Models  single-use hashed token backing password reset + email verification
CurrentCustomerContext    Customers\Support the storefront account API's analogue of TenantContext/CurrentAppContext
```

`Customer.email` remains deliberately **non-unique** — that invariant
predates this milestone (guest checkouts can share an email) and stays
unchanged. The real authentication lookup key is
`CustomerIdentity`'s `unique(store_id, type, identifier)`. Registering
with an email that already has a guest `Customer` record in the same
store attaches the new identity to that existing record (via the
pre-existing `FindOrCreateCustomer`), carrying its order/return history
forward — it does not create a second, disconnected customer.

There is no `CustomerEvent` table. The milestone's core-entity list
names it, but section 11 ("Customer events must go through existing
Event Bus. Never call apps/webhooks directly") and Milestone 12's
identical precedent for its own audit requirement (see ADR-018) both
point the same direction: every customer event is a plain
`RecordOutboxEvent::handle(...)` call with `aggregate_type = 'Customer'`,
and the admin's activity timeline (§6 below) reads `OutboxEvent`
filtered by that aggregate — no bespoke table, no parallel dispatch
path.

Every model here is `BelongsToTenant` except none — unlike Apps'
`App`/`OAuthClient`, there is no "no tenant known yet" case for customer
auth: the store is always already resolved from the request hostname
(`storefront.tenant`) before any customer-auth code runs.

## 2. Authentication

Storefront customer auth is a **separate bearer-token guard**, entirely
independent of the merchant admin's Sanctum session — different
middleware alias (`customer-token` vs. `auth:sanctum`), different token
table, different context object
(`CurrentCustomerContext` vs. Sanctum's `$request->user()`).
`AuthenticateCustomerToken` does not set `TenantContext` itself; it only
ever runs inside a route group already scoped by `storefront.tenant`, so
every query it makes (`CustomerAccessToken`, `CustomerSession`,
`Customer`) is additionally constrained by `BelongsToTenant`'s global
scope — a Store-B token structurally cannot resolve anything on Store-A's
domain, not merely via an `if` check.

- **Register** (`POST /account/register`, `RegisterCustomer`): creates/
  reuses the `Customer` via `FindOrCreateCustomer`, creates its
  `CustomerIdentity` (rejecting a duplicate `(store, identifier)` with a
  clean validation error), auto-logs the customer in (issues a session +
  token pair, same as login), sends a verification email
  (`CustomerVerificationMail`), and records `CustomerCreated`.
- **Login** (`POST /account/login`, `AuthenticateCustomer`): looks up
  the `CustomerIdentity`, checks lockout, checks the password, resets
  the failed-attempt counter on success, issues a session + token pair,
  records `CustomerLoggedIn`. See §5 for the exact lockout/enumeration
  behavior.
- **Logout** (`POST /account/logout`, `LogoutCustomer`): revokes the
  calling session and every token issued under it.
- **Refresh** (`POST /account/refresh`, `RefreshCustomerToken`): OAuth-
  style refresh rotation — mirrors `RefreshAppToken` exactly, including
  reuse detection (§3).
- **Password reset**: `POST /account/password/forgot`
  (`RequestPasswordReset`, always reports success) →
  `CustomerPasswordResetMail` → `POST /account/password/reset`
  (`ResetPassword`, consumes the token, revokes every existing session/
  token for the customer, clears any lockout).
- **Email verification**: `POST /account/verify-email`
  (`VerifyCustomerEmail`, sets `Customer.verified_at`, records
  `CustomerVerified`) and `POST /account/verify-email/resend`
  (authenticated, `RequestEmailVerification`, invalidates any
  previously-issued unverified token first).

`CustomerActionToken` (one table, `purpose` column: `password_reset` |
`email_verification`) backs both reset and verification — single-use,
hashed, short-lived (60min / 48h, both env-tunable via
`config/customers.php`). Delivery is real `Mailable`s
(`CustomerPasswordResetMail`, `CustomerVerificationMail`); `MAIL_MAILER`
defaults to `log` (this app's existing default), so nothing new needed
configuring for these to work correctly in dev/test.

## 3. Sessions and tokens

`CustomerSession` (one per device/browser: `ip_address`, `user_agent`,
`last_used_at`, `expires_at`, `revoked_at`) and `CustomerAccessToken`
(access ~15min / refresh ~30d, both env-tunable, hashed, chained via
`rotated_from_id`) are separate on purpose: a session is what a
"your devices" screen (`GET /account/sessions`) lists and what
`DELETE /account/sessions/{id}` revokes; several tokens (an access/
refresh pair, then its rotated successors) belong to one session over
its lifetime. Revoking a session cascades to revoke every token issued
under it.

Refresh rotation mirrors `AppToken`/`RefreshAppToken` exactly, including
reuse detection: presenting an already-rotated refresh token is treated
as theft and revokes the **entire session** (every token issued under
it, including ones minted after the reuse but before detection) rather
than just that one token — verified under real concurrent PostgreSQL
connections
(`tests/Concurrency/CustomerTokenRefreshConcurrencyTest.php`, the same
fork-based harness as `AppTokenRefreshConcurrencyTest`). As with
`RefreshAppToken`, the rejection is thrown *after* the transaction
commits, never from inside it — throwing inside would roll back the
very revocation that's the point of the reuse response.

`AuthenticateCustomer`'s account-lockout increment has the same shape: a
failed attempt's counter increment must survive even though the login
request itself ends in a 401, so `handle()` returns a plain outcome
value from inside `DB::transaction()` and throws afterward, based on
that value.

## 4. Customer portal

Storefront pages (Nuxt, `apps/storefront/app/pages/account/`):
`login`, `register`, `forgot-password`, `reset-password`, `verify-email`
are public; `index` (profile + sessions), `addresses`, `orders/index`,
`orders/[id]` are behind a client-side `auth` route middleware that
redirects to `/account/login` when no valid customer session is
present.

Order detail (`GET /account/orders/{order}`, `CustomerOrderResource`)
surfaces everything spec section 7 asks for in one response: order/
financial/fulfillment status, items, addresses, shipping line, payments
(reusing the storefront's own `PaymentResource` shape), shipments
(tracking), returns (status/inspection), and refunds — reusing the
*admin* `ShipmentResource`/`ReturnResource`/`RefundResource` directly
rather than building a third near-identical resource per model (see
ADR-022's consequences for why that's a deliberate asymmetry, not an
oversight). The order list (`GET /account/orders`) uses a lighter
summary resource with none of those relations eager-loaded, keeping the
list endpoint at a fixed query count regardless of history length.

**Reorder** (`POST /account/orders/{order}/reorder`, `ReorderFromOrder`)
never trusts a past price. It reads only each `OrderItem`'s
`product_variant_id` + `quantity`, re-resolves the **live**
`ProductVariant`, and calls the same `AddCartItem` the regular
storefront "add to cart" uses — `CartItem` has no price column at all,
so pricing is structurally forced through `CompleteCheckout`'s existing
fresh-pricing path at actual checkout time. A variant that's been
deleted, deactivated, or gone out of stock is skipped and reported back
per-line (`skipped: [{order_item_id, product_title, reason}]`) rather
than failing the whole reorder. The response body carries the resulting
cart's `cart_token` directly (`{cart_token, skipped}`, matching
`ReorderResult` in `@obscurify/types`) rather than the full `CartResource`
shape the anonymous `/cart` endpoints use — the same `storefront_cart_token`
cookie is still set on the response either way, but a body-supplied
`cart_token` on the *next* reorder call takes precedence over it, since a
customer-portal caller may be tracking the cart client-side rather than
relying purely on the cookie.

**Return requests** (`POST /account/orders/{order}/returns`,
`RequestCustomerReturn`) wrap the pre-existing (Milestone 7)
`RequestReturn` service, adding the one check it doesn't itself make:
that the order actually belongs to the calling customer. Everything
else — the shipped-minus-already-returned quantity ceiling, row locking,
event recording — is the same code the admin return-creation endpoint
already uses and already had test coverage for.

## 5. Security

- **Password hashing**: `Hash::make()`/`Hash::check()` (bcrypt) on
  `CustomerIdentity.secret_hash`. Tokens are SHA-256 hashed — the same
  per-primitive match Milestone 12 established (bcrypt for interactively
  -checked human secrets, SHA-256 for high-entropy tokens looked up by
  exact hash on every request).
- **Enumeration resistance**: login (`InvalidCredentialsException`) and
  forgot-password (`RequestPasswordReset`, always reports success)
  respond identically whether or not the email/credential exists,
  verified by tests asserting byte-identical response messages. A
  failed login against an unknown email still runs a dummy
  `Hash::check()` so the response timing doesn't leak existence either.
- **Account lock protection**: 5 consecutive failed attempts (config:
  `customers.max_failed_attempts`) locks the `CustomerIdentity` for 15
  minutes (config: `customers.lockout_minutes`) — a locked identity
  rejects even a *correct* password until the lock expires. Lives on
  the credential, not the `Customer` profile, so a future second
  identity type is never affected by lockout state on a different one.
- **Rate limiting**: `throttle:customer-auth` (5/minute/IP) on register/
  login/refresh/password endpoints — a separate named limiter from the
  merchant admin's `throttle:auth`.
- **Session/token expiration + rotation**: see §3.
- **Password reset side effects**: consuming a valid reset token revokes
  every existing session/token for that customer and clears any active
  lockout — proving mailbox possession is treated as a strong enough
  signal for both.
- **Tenant isolation**: every model is `BelongsToTenant`; cross-store
  isolation (a Store-A token 401s on Store-B's domain, a Store-B order/
  customer 404s under Store-A's tenant scope) is covered by
  `tests/Feature/Customers/TenantIsolationTest.php` on top of every
  other test file's within-store ownership checks (address/order/return
  controllers reject access to another *same-store* customer's records
  with 403, not just 404-by-scope).
- **Audit events**: see §6 — every security-relevant transition
  (created, verified, logged in, address changed) is recorded via the
  same outbox mechanism as every other domain event.

## 6. Events

Every customer event is dispatched via the pre-existing
`RecordOutboxEvent`, `aggregate_type = 'Customer'`, `aggregate_id =
Customer.id` — the same mechanism every other domain in this codebase
uses, so a merchant's webhook subscription behaves identically whether
it's subscribed to `OrderCreated` or `CustomerCreated`.

Dispatched: `CustomerCreated`, `CustomerUpdated`, `CustomerLoggedIn`,
`CustomerVerified`, `CustomerAddressUpdated`, `CustomerOrderViewed`,
`CustomerReturnRequested` (the milestone's required list). The admin's
"activity timeline" (`GET /customers/{customer}/activity`,
`AdminCustomerController::activity()`) is a direct, paginated query
against `OutboxEvent` filtered by that aggregate — not a separate read
model.

## 7. Admin customer management

Read-only admin surface (`App\Domain\Customers\Http\Controllers\
AdminCustomerController`, `auth:sanctum` + `tenant`, same as every other
admin resource): customer list/detail, order history, returns history,
addresses, and the activity timeline described above. There is no admin
edit-customer action in this milestone — profile edits are the
customer's own, via the portal's `PATCH /account`.

## 8. Explicitly not implemented

Per the milestone brief: no social login, no OAuth customer login, no
loyalty system, no subscriptions, no B2B accounts, no customer groups,
no CRM automation. `CustomerIdentityType` and `CustomerPreference` exist
as extension points for some of this later (a second identity type, a
richer preferences surface) without a schema change, but nothing beyond
`email_password` and a flat key/value store is built now.

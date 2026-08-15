# ADR-022: Customer Identity — Layer Auth on the Existing CRM Record, Reuse the App Token Pattern, Fold Activity Into the Outbox

## Status
Accepted

## Context
Milestone 16 introduces the customer identity layer: email/password
registration, login, logout, password reset, email verification, session
management, and a customer portal for orders/addresses/returns. It
explicitly excludes social login, OAuth customer login, loyalty,
subscriptions, B2B accounts, customer groups, and CRM automation, and
requires customer events to flow through "the existing Event Bus" and
never call apps/webhooks directly.

Two facts about the existing codebase shaped every decision here:

1. **A `Customer` model already existed**, since Milestone 1's guest
   checkout — a CRM record (`email`, `phone`, `first_name`, `last_name`,
   soft-deletable), explicitly documented as "not a login identity — no
   auth on this model." `FindOrCreateCustomer` matches guests by
   store-scoped email with no DB-level unique constraint, since two
   different guests can legitimately share an email. `Order.customer_id`
   already points at this table.
2. **The Apps OAuth token system (Milestone 12)** already solved
   "hashed, rotating, access+refresh bearer tokens with reuse detection"
   for a different actor (installed apps). Its shape — `AppToken` with
   `type`/`token_hash`/`rotated_from_id`/`expires_at`/`revoked_at`,
   `IssueAppTokenPair`/`RefreshAppToken` — is a proven, tested pattern
   for exactly the auth primitive this milestone also needs.

Given those two facts, the design questions that mattered most: does
"Customer" in the milestone's core-entity list mean a new model, or
auth added onto the existing one; what "CustomerEvent" means given the
existing instruction to reuse the Event Bus rather than build a parallel
one; where account-lockout state should live; and how the customer
token/session split should be shaped.

## Options

**Customer model:**
1. Create a new, second `Customer`-shaped model for authenticated
   accounts, leaving the existing CRM `Customer` for guest/order
   snapshots — two tables for what a merchant would call "a customer."
2. Extend the existing `Customer` model with `status`/`verified_at`, and
   add a separate `CustomerIdentity` row (one per way a customer can
   prove who they are: `type`, `identifier`, `secret_hash`,
   `failed_attempts`, `locked_until`) belonging to it. Registration
   reuses `FindOrCreateCustomer`'s store-scoped email lookup, so a guest
   who checked out before registering gets their order history attached
   to the new identity instead of starting a second, disconnected
   record.

**"CustomerEvent" (spec's core-entity list) vs. "must go through the
existing Event Bus, never call apps/webhooks directly":**
1. Build `customer_events` as a new table, populated alongside (or
   instead of) `OutboxEvent` — a bespoke per-domain audit log, the same
   shape every other domain in this codebase deliberately avoided.
2. No new table. Every customer event type (`CustomerCreated`,
   `CustomerLoggedIn`, etc.) is dispatched via the existing
   `RecordOutboxEvent`/`OutboxEvent` mechanism with `aggregate_type =
   'Customer'`, exactly like every other domain since Milestone 11. The
   admin's "activity timeline" (spec section 12) queries `OutboxEvent`
   filtered by `aggregate_type`/`aggregate_id` rather than a separate
   table.

**Account lock protection — where does `failed_attempts`/`locked_until`
live?**
1. On `Customer` — one lockout state per customer account.
2. On `CustomerIdentity` — one lockout state per credential.

**Session vs. token shape:**
1. A single `CustomerAccessToken`-only model, no separate session
   concept — login just mints a token pair, logout just revokes it.
2. `CustomerSession` (one row per logged-in device/browser: IP, user
   agent, `last_used_at`, `expires_at`, `revoked_at`) plus
   `CustomerAccessToken` (access+refresh pair, mirroring `AppToken`,
   `customer_session_id`-linked). Logout revokes the session, which
   cascades to every token issued under it.

## Decision

**Customer model: Option 2.** Confirmed via investigation before writing
any migration that `Customer`/`CustomerAddress`/`FindOrCreateCustomer`
already existed and were referenced from `Checkout`, `Order`,
`PromotionUsage`, `ReturnRequest`, and the Apps gateway — a second
"Customer" table would have fractured all of that. `customers.email`
remains deliberately non-unique (guest semantics unchanged);
`customer_identities` gets the real uniqueness constraint —
`unique(store_id, type, identifier)` — since that is the actual
authentication lookup key. `RegisterCustomer` calls the existing
`FindOrCreateCustomer` so a guest's prior orders/returns attach to their
new account by email match, same store.

**CustomerEvent: Option 2.** This mirrors the identical resolution
Milestone 12 reached for its own "audit log" requirement (see
docs/adr/018-app-platform.md and docs/architecture/apps.md §11) — no new
table, `OutboxEvent` reused as the audit trail, `AdminCustomerController
::activity()` reading it filtered by `aggregate_type = 'Customer'`. This
directly satisfies "must go through existing Event Bus... never call
apps/webhooks directly": every dispatch is `RecordOutboxEvent::handle()`,
the same call every other domain already makes, so a merchant's webhook
subscription to `CustomerCreated` etc. works identically to a
subscription to `OrderCreated` with zero customer-specific dispatch code.

**Account lockout: Option 2, on `CustomerIdentity`.** Locking is a
property of the credential under attack, not the profile — a future
second identity type (e.g. a social login added later) on the same
`Customer` must never be locked out by failed attempts against a
different identity type, and vice versa. `AuthenticateCustomer` increments
`failed_attempts` and sets `locked_until` (config: 5 attempts / 15
minutes, both env-tunable) only on a wrong password against an
*unlocked* identity; a locked identity rejects even a correct password
until the lock expires, and a successful login resets the counter.

**Session/token: Option 2.** `CustomerAccessToken` is a near-exact port
of `AppToken`/`IssueAppTokenPair`/`RefreshAppToken`: SHA-256 hashed,
access (15min default) + refresh (30d default) pair, refresh rotation
chained via `rotated_from_id`, reuse of an already-rotated refresh token
treated as theft and answered by revoking the *entire session* (not just
that token) — every access/refresh token issued under it, including ones
minted after the reuse but before detection, exactly matching
`RefreshAppToken`'s "sweep up the winner's own newly-issued tokens too"
behavior, verified under real concurrent Postgres connections
(`tests/Concurrency/CustomerTokenRefreshConcurrencyTest.php`, same
fork-based harness as `AppTokenRefreshConcurrencyTest`). `CustomerSession`
is what a "your devices" screen lists and what "log out this device"
(`DELETE /account/sessions/{id}`) revokes — deliberately reachable for a
session other than the one making the request, unlike a bare
token-revoke-self action.

**Password reset / email verification: one `customer_action_tokens`
table**, `purpose` column (`password_reset` | `email_verification`)
rather than two near-identical tables — the same simplification
Laravel's own `password_reset_tokens` makes versus a bespoke table per
feature. Tokens are single-use (`used_at`), hashed, short-lived (60min /
48h defaults). Delivery is real `Illuminate\Mail\Mailable`s
(`CustomerPasswordResetMail`, `CustomerVerificationMail`) sent via
`Mail::queue()` — not a token returned in the API response, which would
defeat the entire point of proving mailbox possession. `MAIL_MAILER`
defaults to `log` (Laravel's own default in this app), so nothing new
had to be configured for this to work correctly in dev/test; a real
transport is a deploy-time config change, not a code change.

**Reorder ("buy again") never touches price.** `ReorderFromOrder` reads
only `OrderItem.product_variant_id` + `quantity`, re-resolves the *live*
`ProductVariant`, and hands it to the same `AddCartItem` the regular
storefront "add to cart" flow uses — `CartItem` has no price column at
all, so pricing is structurally forced to come from
`CompleteCheckout`'s existing fresh-pricing logic at actual checkout
time, the same guarantee that already exists for every other cart. A
variant that's deleted, deactivated, or out of stock is skipped (result
reported back per-line), never failing the whole reorder.

**Customer-portal return requests wrap the existing `RequestReturn`.**
Investigation found `RequestReturn::handle()` (Milestone 7) accepts an
arbitrary `customer_id` in its payload with no check that it matches the
order — correct for its only caller today (a trusted admin/staff user),
wrong for a customer-portal caller. `RequestCustomerReturn` adds the one
missing check (`$order->customer_id === $customer->id`, else 403) and
delegates the rest unchanged, rather than duplicating return-line
validation/quantity-ceiling logic a second time.

## Consequences

### Positive
- Zero duplicate CRM/order/return wiring — every place that already
  pointed at `Customer` (`Order`, `ReturnRequest`, `PromotionUsage`, the
  Apps `CustomerGatewayController`) keeps working unchanged; auth is
  purely additive.
- The activity-timeline decision means webhook subscribers automatically
  see customer events with zero new dispatch code, and the admin UI's
  timeline is trivially consistent with every other domain's audit
  story.
- Per-identity lockout, session-wide token revocation on refresh-reuse,
  and password-reset-revokes-all-sessions are all real, tested security
  properties (436 backend tests), not aspirational — including a
  concurrency test proving reuse detection survives real concurrent
  connections rather than only sequential simulation.
- Customer auth is architecturally impossible to confuse with merchant
  admin auth: different guard (`AuthenticateCustomerToken` vs. Sanctum),
  different token table, different middleware alias
  (`customer-token` vs. `auth:sanctum`), and `AuthenticateCustomerToken`
  never sets `TenantContext` itself — it only runs inside a
  `storefront.tenant`-resolved group, so a customer token is additionally
  constrained by `BelongsToTenant`'s global scope on every query, not
  just an application-level `if` check.

### Negative
- `CustomerIdentity` is deliberately over-built for a single identity
  type (`email_password` is the only case in `CustomerIdentityType`
  today) — the `type`/`identifier` split only pays for itself once a
  second identity type (social login) is actually added. Accepted as
  cheap, load-bearing groundwork rather than speculative: the milestone
  spec explicitly frames social login as "not yet," implying it is
  expected later, and the alternative (columns directly on `Customer`)
  would require a real migration + data move at that point instead of a
  new row shape.
- `CustomerOrderResource`'s `payments` reuses the storefront's own
  `PaymentResource` shape (`redirect_url`, no
  authorized/captured breakdown) while `shipments`/`returns`/`refunds`
  reuse the *admin* `ShipmentResource`/`ReturnResource`/`RefundResource`
  directly — a deliberate inconsistency (different resource lineage per
  relation) rather than three new bespoke portal resources, justified
  because all of the reused ones were already storefront/customer-safe
  (no internal-only fields) and duplicating them a third time bought
  nothing. Flagged here so a future reviewer doesn't "fix" the asymmetry
  without knowing it was intentional.
- No silent access-token refresh-on-401 was built into the frontend
  bearer flow — a dead access token surfaces as a 401 that the client
  reacts to (clear session, prompt re-login) rather than transparently
  retrying via the refresh token first. Acceptable for this milestone's
  scope; a future pass could add transparent refresh if session friction
  becomes a real complaint.

## Security Requirements
- Passwords are `Hash::make()`/`Hash::check()` (bcrypt) on
  `CustomerIdentity.secret_hash`; access/refresh tokens are SHA-256
  hashed (`CustomerAccessToken.token_hash`) — the same per-use-case
  match Milestone 12 established (bcrypt for human-chosen secrets
  checked interactively, SHA-256 for high-entropy tokens looked up by
  exact hash on every request).
- Login, registration, and password-reset-request all respond with the
  same message shape regardless of whether the email/credential exists
  (`InvalidCredentialsException::invalidCredentials()`, and
  `RequestPasswordReset::handle()` always returns success) — enumeration
  resistance verified by dedicated tests asserting byte-identical
  messages.
- `throttle:customer-auth` (5/minute/IP) rate-limits register/login/
  refresh/password endpoints, a separate named limiter from the merchant
  admin's `throttle:auth` so the two guards can never share a bucket.
- Account lockout: 5 consecutive failed attempts (config-tunable) locks
  a `CustomerIdentity` for 15 minutes (config-tunable); a locked identity
  rejects even a correct password until the lock naturally expires.
- Refresh token rotation with reuse detection, verified under real
  concurrent PostgreSQL connections
  (`tests/Concurrency/CustomerTokenRefreshConcurrencyTest.php`) — exactly
  one of two simultaneous refreshes of the same token succeeds, and zero
  usable tokens remain for that session afterward.
- Password reset revokes every existing session/token for the customer
  (forces re-login everywhere) and clears any active lockout.
- `Customer`, `CustomerIdentity`, `CustomerSession`, `CustomerAccessToken`,
  `CustomerAddress`, `CustomerPreference`, and `CustomerActionToken` all
  use `BelongsToTenant`; cross-store isolation is verified by
  `tests/Feature/Customers/TenantIsolationTest.php` (a Store-A customer
  token 401s on Store-B's domain; a Store-B order/customer 404s under
  Store-A's tenant scope) on top of every other test file's
  within-store ownership checks (one customer cannot read/edit/delete
  another customer's address/order/return in the *same* store).

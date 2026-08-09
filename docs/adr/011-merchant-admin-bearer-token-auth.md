# ADR-011: Merchant Admin Uses Sanctum Bearer Tokens, Not Cookie/Session Auth

## Status
Accepted

## Context
ARCHITECTURE.md §13 originally described merchant authentication in cookie/
session SPA terms (secure cookie config, CSRF protection) — the classic
Sanctum SPA pattern. The actual implementation that shipped in the
Foundation milestone never followed that pattern: `AuthController` issues a
Sanctum personal access token on login/register, and the Nuxt admin
(`apps/admin`) sends it as `Authorization: Bearer <token>`, storing it in
`localStorage`. `config/cors.php` documents this split explicitly — the
storefront's guest cart is cookie-based and needs credentialed CORS; the
admin never did. This was a deliberate, working choice, just never recorded
as one, which produced two problems:

1. Docs and code disagreed, so `config/sanctum.php`'s stateful-domain/CSRF
   plumbing looked load-bearing for the admin when it is actually dead
   weight there (harmless, but misleading during debugging).
2. Because the token model was never written down, the frontend half of it
   was never held to the same rigor: `useAuth.ts` defined `fetchMe()` but
   nothing called it, and there was no shared 401 handler. A stale/revoked
   token still rendered the UI as "logged in" while every real action
   failed with `Unauthenticated`, and a failed logout call could skip
   clearing local state entirely. Fixed alongside this ADR — see
   `packages/api-client/src/index.ts` (`onUnauthorized` hook),
   `apps/admin/app/composables/useAuth.ts` / `useApi.ts`, and
   `apps/admin/app/plugins/hydrate-auth.client.ts`.

## Options
1. Switch the admin to real Sanctum SPA cookie auth, matching the original
   docs.
2. Keep bearer tokens, but make the frontend actually verify them.
3. Do nothing; leave docs and code diverged.

## Decision
Keep Sanctum bearer tokens for the merchant admin (Option 2). The storefront
already owns the cookie/CSRF-credentialed pattern for its guest cart;
duplicating that for a separate first-party SPA on a different origin adds
CSRF-cookie plumbing for no isolation benefit the token model doesn't
already provide. `ARCHITECTURE.md` §13 is updated to describe the bearer
model as it actually works.

## Consequences
### Positive
- One fewer cross-origin cookie/CSRF surface to reason about; the admin's
  CORS config stays the simple non-credentialed case.
- Docs now match code — the next person debugging admin auth won't chase
  stateful-domain/CSRF config that the admin never uses.
- The frontend must now prove a token is still valid before trusting it
  (`fetchMe()` on boot) and reacts uniformly to any `401` from any
  endpoint, closing the class of bug this ADR was written to explain.

### Negative
- Token lives in `localStorage`, which is XSS-readable in a way an
  `HttpOnly` session cookie is not. Acceptable for the admin's current
  threat model (first-party dashboard, no third-party script inclusion);
  revisit if that changes.
- `config/sanctum.php`'s stateful/CSRF settings remain in the codebase for
  the storefront's cookie flow and are irrelevant to the admin — worth a
  comment pointer, not worth removing since the storefront still needs
  them.

## Security Requirements
- Every authenticated request from the admin must carry a fresh check
  against `401` — a rejected token clears local auth *and* tenant state
  together (they are separate concerns, but a dead session must never
  leave stale tenant selection behind).
- Session validity is verified against the backend at app boot, not
  inferred from token presence in `localStorage`.

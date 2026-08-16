# ADR-032: Localization — DB Catalog Over File-Based Runtime Source, Middleware-Priority Locale Resolution, One-Bundle-Per-Locale Frontend, Primary-Recipient Notification Locale

## Status
Accepted

## Context

Milestone 26 adds platform-wide internationalization (Russian default,
English/German secondary, extensible) spanning backend validation and
exceptions, GraphQL, notifications, transactional email, search
synonyms, and the Admin/Storefront frontends. Eight design questions
dominated the implementation: whether the new
Language/Locale/Translation database schema should itself be the
runtime source of truth or an index over files; how request-scoped
locale resolution should compose with the existing tenant-context
middleware without one clobbering the other; a real Symfony gotcha in
`Request::getPreferredLanguage()`; how frontend locale bundles should
be split; how a `Mailable`'s locale actually gets applied by Laravel;
how a `Notification` that fans out to multiple recipients should pick
"the" locale to render in; how far exception/page translation coverage
should extend this milestone; and whether the pre-existing Cyrillic
text-normalization gap in Search should be fixed as a side effect.

## Decision 1: The Translation/TranslationKey/TranslationNamespace tables are a queryable index, never read by `__()`/Vue I18n at request time

**Options considered:**

1. Make the database the actual source `__()` and Vue I18n read from —
   a `Translation` row per key per locale, queried (with caching) on
   every render.
2. Keep Laravel's file-based `lang/{locale}/*.php` and each frontend
   app's `i18n/locales/{locale}.json` as the real runtime source; the
   database tables are a queryable catalog/index populated by scanning
   those files, used only by developer tooling (`translations:scan`,
   `translations:missing`, `translations:unused`) and any future admin
   translation-management UI.

**Decision: option 2.** A DB-backed runtime translation lookup adds a
query (or a cache round-trip) to every single translated string on
every request — the opposite of what Laravel's and Vue I18n's own
file-based design already gives for free. The spec's requirement is a
"queryable translation architecture" for developer tooling and future
admin UI, not a request-time indirection. Treating the DB tables the
same way a search index treats its source-of-truth documents (queryable
projection, not authority) keeps the hot path entirely file-based while
still giving `translations:missing`/`translations:unused` a structured
place to diff against.

## Decision 2: `ResolveRequestLocale` must be listed in Laravel's middleware `priority()` array, not just `api()`

**Options considered:**

1. Register `ResolveRequestLocale` via `$middleware->api(append: [...])`
   only, relying on Laravel to run middleware in registration order.
2. Also add it to `$middleware->priority([...])`, positioned before
   `EnsureTenantContext`/`EnsureStorefrontTenantContext`.

**Decision: option 2 — and this was a real bug found and fixed during
implementation.** Registering via `api(append:)` alone was not
sufficient: `EnsureTenantContext` (itself prioritized) ran, correctly
resolved and set the store-aware locale via `resolveForStore()`, but
`ResolveRequestLocale` then ran *after* it — because appended,
non-prioritized middleware is not guaranteed to run in append order
relative to prioritized middleware — and clobbered the just-set locale
back to the global baseline via `resolveGlobal()`, which has no
knowledge of store-level settings. Root-caused via temporary
`Log::debug()` calls in both middleware showing the correct locale set,
then reverted, before validation ran. Fixed by adding
`ResolveRequestLocale::class` to the priority array ahead of both
tenant-context middleware classes. Verified via `LocaleResolutionTest`
going from 6/10 to 10/10 passing.

## Decision 3: `LocaleContext` is registered as an explicit singleton, mirroring `TenantContext`

Discovered as a byproduct of Decision 2's debugging (two different
object ids logged for what should have been one instance across a
request). Not the root cause of Decision 2's bug — `App::setLocale()`
is a facade call with global effect regardless of which wrapper
instance triggered it — but a real latent correctness issue for any
future code relying on `LocaleContext`'s own instance identity or
internal state. Fixed by registering `$this->app->singleton(LocaleContext::class)`
in `AppServiceProvider`, the same way `TenantContext` is registered.

## Decision 4: A private `preferredLanguage()` wrapper guards every call to `Request::getPreferredLanguage()`

**Problem:** `Symfony\Component\HttpFoundation\Request::getPreferredLanguage()`
returns the *first* candidate from its `$locales` argument — not
`null` — when no `Accept-Language` header is present at all. Naively
calling it as "the Accept-Language step" of the fallback chain silently
skipped the intended next fallback (explicit preference already
exhausted, or platform default) whenever a request had no header,
because Symfony always returned an answer.

**Decision:** `LocaleResolver::preferredLanguage()` checks
`$request->headers->has('Accept-Language')` before calling Symfony's
method at all, returning `null` (continue the chain) when the header
is genuinely absent. Root-caused by reading
`vendor/symfony/http-foundation/Request.php` directly after test
failures showed `de`-locale resolution silently falling to `en` with
no header set.

## Decision 5: One JSON bundle per locale per frontend app, not per namespace

**Options considered:**

1. Split each app's translations into one JSON file per namespace per
   locale (`i18n/locales/ru/nav.json`, `ru/chrome.json`, …), mirroring
   the backend's per-namespace `lang/*.php` split.
2. One JSON file per locale (`i18n/locales/ru.json`), with namespacing
   expressed as nested keys within that file.

**Decision: option 2.** `@nuxtjs/i18n` v10 lazy-loads bundles per
*active locale* by design — each locale entry declares its own `file:`,
and only the currently-selected locale's file is fetched. Splitting
further by namespace would multiply the number of files without
reducing what's loaded (a user viewing any Admin page still needs
`nav.*` and `chrome.*` together), so it would add build/import
complexity for no runtime benefit. This is also why the config has no
top-level `lazy: true` option in v10 — it was removed after a real
typecheck error (`'lazy' does not exist`) surfaced it as leftover
config from an older major version.

## Decision 6: A Mailable's locale must be set via `Mail::to(...)->locale(...)`, never from inside `build()`

**Problem, found and fixed during implementation:** the first
implementation called `$this->locale($resolvedLocale)` from inside
`CustomerVerificationMail::build()`, before calling `__()`. This had no
effect — `__()` still used whatever locale was ambient when `build()`
ran, not the one just set.

**Root cause**, read directly from
`vendor/laravel/framework/src/Illuminate/Mail/Mailable.php`: `send()`
evaluates `$this->locale` as an argument to `withLocale()` *before*
`build()` is invoked, so setting it from within `build()` is always too
late. The correct mechanism, from `PendingMail.php`: a fluently chained
`Mail::to($x)->locale($resolvedLocale)->queue(new Mailable(...))` call
has `PendingMail::fill()` copy the chained `.locale()` value onto the
Mailable instance *before* `send()`/`queue()` invokes it, so `withLocale()`
wraps `build()` with the correct locale already in place.

**Decision:** the locale is resolved and set at the call site
(`RegisterCustomer::handle()`, `RequestPasswordReset::handle()`) via
the fluent `Mail::to()->locale()` chain, and Mailables carry no
`locale` constructor parameter or internal `.locale()` call at all —
this makes the correct-vs-incorrect pattern structurally obvious rather
than relying on every future Mailable author rediscovering the same
bug. A corresponding test-methodology fix was needed too: asserting
`$mail->build()->subject` directly inside a `Mail::fake()` closure
bypasses `send()`'s `withLocale()` wrapper entirely, so
`EmailLocalizationTest` instead asserts `$mail->locale === 'xx'` (proves
the app code) plus a separate, `Mail`-independent assertion that
`__('emails.x.subject', [...], 'xx')` produces the right text (proves
the lang files) — two independent, sound checks rather than one
unsound combined one.

## Decision 7: Notification locale is resolved from the first customer recipient, not genuinely per-recipient

`NotificationDispatcher` renders a `Notification` **once** and shares
that single rendering across every recipient's delivery — a
pre-existing architectural constraint from the Notification Center
milestone (ADR-027), not something this milestone changes.
`primaryRecipientLocale()` therefore resolves locale from the first
customer recipient only. A genuinely per-recipient-locale rendering
(one render per recipient, in that recipient's own locale) would
require restructuring `NotificationDispatcher` to render N times
instead of once — out of scope here, and not requested by the spec,
which asks for "notifications render using the recipient's locale," not
"each recipient receives a rendering in their own distinct locale
within one fan-out batch." Left as a known simplification, not fixed.

## Decision 8: Representative, not exhaustive, translation/localization coverage

Given the platform's size (42 domain exception classes, dozens of
Admin pages, the full Storefront), full exhaustive coverage of every
literal string was not attempted this milestone. What was translated:

- Laravel's own framework namespaces (`validation`, `auth`, `passwords`,
  `pagination`) — all three locales, fully.
- ~15 of 42 domain exception classes across Payments, Shipping, Search,
  Notifications, Customers, and Promotions — chosen to cover every
  domain that has user-facing exceptions, not every individual
  exception class in each domain. (`AppHealthExtension`'s literal
  message, an example/reference GraphQL extension rather than core
  platform code, was deliberately left un-migrated.)
- GraphQL error/authorization messages platform-wide (`GraphQLUserError`,
  `DirectiveEnforcer`, and every customer-facing mutation/query's
  "must be logged in" message).
- Admin: full navigation (51 items), full chrome (topbar, search,
  store switcher, user menu), and two representative full pages
  (login, stores list) — proving the translation mechanism end-to-end
  rather than translating all ~40+ Admin pages.
- Storefront: header/footer chrome and the language switcher.

This mirrors the pattern already established in prior milestones
(e.g. ADR-030's representative OGRN/OGRNIP format-only validation): a
milestone proves the *mechanism* is real and correctly wired, rather
than exhaustively re-touching every string in the codebase. Remaining
untranslated strings are technical debt, not a broken mechanism — any
new page or exception added going forward follows the same
`__()`/`t()` pattern already established.

## Decision 9: The pre-existing Cyrillic search-normalization gap is surfaced but not fixed

While building `SearchSynonymLocaleTest`, a genuine, pre-existing
(Milestone 22) gap was found: `SearchTextNormalizer::stripAccents()`
uses `iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text)`, which silently
discards Cyrillic (and other non-Latin-transliterable) characters
entirely, so a Cyrillic search query currently normalizes to an
empty/near-empty token. This is explicitly out of scope per the
spec's "do not implement stemming or language-specific analyzers yet"
boundary for this milestone. The test itself was written with
Latin-script terms to isolate and prove the locale-*filtering* logic
(Decision 7 of the synonym system) without tripping this unrelated,
already-documented limitation. Left as known technical debt for a
future search-quality milestone.

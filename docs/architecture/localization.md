# Internationalization & Localization

## 1. Overview

Milestone 26 adds platform-wide internationalization: Russian (`ru`,
platform default), English (`en`), and German (`de`), extensible to
more languages without hardcoded language checks anywhere in the
codebase. Every layer — backend validation/exceptions/API errors,
GraphQL, notifications, transactional emails, the Admin UI, and the
Storefront UI — resolves and renders text in a locale determined by an
explicit, documented fallback chain, scoped per store (each store has
its own default/supported/admin/storefront locale settings).

Explicitly **not** in scope, per the milestone spec: multilingual
product descriptions, multilingual CMS pages, machine/AI translation,
and RTL support. The Localization domain's schema is built to be
architecture-ready for future multilingual content (CMS pages in
particular), but no translated-content feature is implemented yet.

Core entities, all under `App\Domain\Localization`:

| Entity | Purpose |
|---|---|
| `Language` | A language (`code`, `name`, `native_name`, `is_active`, `sort_order`) — platform-wide, not tenant-scoped. |
| `Locale` | A concrete locale (`code`, `language_code`, `fallback_locale_code`, `is_default`, `is_active`) — self-referencing FK for the fallback chain (e.g. `de` falls back to `en`). |
| `TranslationNamespace` | A translation namespace (`auth`, `catalog`, `orders`, `payments`, `shipping`, `notifications`, `automation`, `analytics`, `cms`, `themes`, `b2b`, `search`, …). |
| `TranslationKey` | A key within a namespace, unique per namespace. |
| `Translation` | One namespace-key's value in one locale, tagged with a `TranslationSource` (`Scan`/`Seed`/`Manual`). |
| `StoreSupportedLocale` | Which locales a given store supports — the one genuinely tenant-scoped table in this domain (`store_id` + `locale_code`). |

## 2. The DB tables are an index, not the runtime source

`Language`/`Locale`/`Translation`/`TranslationKey`/`TranslationNamespace`
are a **queryable catalog** over the real runtime sources — they are
populated by `translations:scan` and used by the admin translation
tooling, but they are **never read by `__()` or Vue I18n at
request/render time**. The actual single source of truth for rendered
text is:

- Backend: `lang/{locale}/*.php` (Laravel's own localization files).
- Admin/Storefront UI: `i18n/locales/{locale}.json` in each app.

This avoids a database round-trip per translated string on every
request — the hot path stays entirely file-based, which is also how
Laravel and Vue I18n are designed to work. See ADR-032 Decision 1.

## 3. Locale resolution — the fallback chain

`App\Shared\Localization\LocaleContext` mirrors `TenantContext`
exactly (`set()`/`clear()`/`current()`/`scope()`), registered as a
singleton, with one difference: it is never "missing" — `current()`
always resolves to at least `config('app.locale')`.

`App\Domain\Localization\Support\LocaleResolver` implements two
resolution chains:

**`resolveGlobal(Request, ?explicitPreference)`** — used before any
store context exists (e.g. the public `/languages` endpoint):

1. Explicit `?locale=` query override.
2. `$explicitPreference` (caller-supplied, e.g. an authenticated user's saved preference).
3. `Accept-Language` header, via a `preferredLanguage()` wrapper that only
   calls `$request->getPreferredLanguage()` when the header is actually
   present (Symfony returns the first candidate locale, not `null`,
   when no header exists — see ADR-032 Decision 4).
4. Platform default (`Locale::where('is_default', true)`, cached).

**`resolveForStore(Request, Store, 'admin'|'storefront', ?explicitPreference)`**
— the same chain, but `$active` becomes the store's own
`StoreSupportedLocale` codes (falling back to platform-active codes if
the store has none configured), and the final fallback before platform
default is the store's own `admin_locale`/`storefront_locale` (falling
back to `store.default_locale`).

`ResolveRequestLocale` middleware establishes the request-wide
baseline via `resolveGlobal()` and is registered **before**
`EnsureTenantContext`/`EnsureStorefrontTenantContext` in Laravel's
middleware priority list — both of those, once a store is resolved,
call `resolveForStore()` and refine `LocaleContext` with the
store-aware result (admin: `$user->locale` as explicit preference;
storefront: the `storefront_locale` cookie). See ADR-032 Decision 2 for
why the priority ordering matters.

## 4. Store language settings

Each store has four locale-related fields (spec section 8):

- `default_locale` — the store's baseline locale.
- Supported locales — `StoreSupportedLocale` rows, always kept a
  superset containing `default_locale` (enforced atomically by
  `UpdateStoreLocaleSettings`).
- `admin_locale` — preferred locale for the store's admin surface.
- `storefront_locale` — preferred locale for the store's storefront.

Managed via `GET|PATCH /store-locale-settings` (tenant-scoped). Public
storefront visitors set their own preference with
`POST /storefront/locale`, which sets a non-httpOnly `storefront_locale`
cookie (read by `EnsureStorefrontTenantContext` on every subsequent
request).

## 5. Backend, exceptions, and GraphQL

Laravel's own localization is the single source of truth for
validation messages, framework exceptions (`auth`, `passwords`,
`pagination`), and custom domain exceptions. `lang/{ru,en,de}/*.php`
covers Laravel's framework namespaces plus platform-specific
namespaces (`exceptions`, `payments`, `shipping`, `search`,
`notifications`, `orders`, `graphql`, `emails`). A representative,
not-exhaustive, set of domain exception classes (~15 of 42) across
Payments, Shipping, Search, Notifications, Customers, and Promotions
were converted from hardcoded interpolated strings to `__('namespace.key', [...])`
calls; the remainder is tracked as technical debt (§8).

GraphQL error messages respect the current locale the same way:
`GraphQLUserError::notFound()`/`forbidden()`, `DirectiveEnforcer::requireRole()`,
and the "must be logged in" messages across the customer-facing
mutations/queries all resolve through the `graphql.*` namespace.
Validation errors surfaced through GraphQL inputs go through the same
Laravel validator as REST, so they are localized identically.

Notification templates carry their own `locale` column and are
resolved per-recipient (see §5 of `notifications.md`) via
`ResolveLocalizedNotificationTemplate`: given a base template, it tries
`[recipientLocale, store.default_locale]` in order, falling back to
the base template if neither has a matching sibling row (same `key` +
`channel` + `locale`, `is_active`). `NotificationDispatcher` resolves
the recipient locale from the **first** customer recipient — a
pre-existing architectural constraint (one `Notification` renders once
and shares that rendering across all its delivery recipients), not
something this milestone changes. See ADR-032 Decision 5.

## 6. Emails

`CustomerVerificationMail`/`CustomerPasswordResetMail` call `__()`
inside `build()` relying on the **ambient** locale, with no `locale`
constructor parameter on the Mailable itself. The caller sets the
locale by chaining `Mail::to($x)->locale($resolvedLocale)->queue(new Mailable(...))` —
Laravel's `PendingMail::fill()` copies the fluent `.locale()` call onto
the Mailable before `send()`/`queue()`, and `Mailable::send()` wraps
the whole `build()` call in `withLocale()`. Setting `.locale()` from
*inside* `build()` has no effect (see ADR-032 Decision 6 for the bug
this caused and how it was diagnosed).

`RegisterCustomer::handle()` resolves the mail locale as
`$customer->locale ?? LocaleContext::current()`;
`RequestPasswordReset::handle()` resolves it as the target customer's
own `locale` (looked up independently, since the reset flow doesn't
already have the customer loaded) with the same fallback.

## 7. Search locale-awareness

Per spec section 13, only the architecture is made locale-aware this
milestone — no stemming or language-specific analyzers. `SearchSynonym`
gained a `locale` column (nullable — `null` means locale-agnostic).
`SynonymExpander::expand()` accepts the current locale (from
`LocaleContext`, injected into `ExecuteSearch`) and only matches
synonym rows where `locale` is `null` or equals the current locale.

A pre-existing (Milestone 22) limitation was surfaced but deliberately
**not** fixed here, per the explicit "do not implement stemming or
language-specific analyzers" scope boundary:
`SearchTextNormalizer::stripAccents()` uses
`iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text)`, which silently
discards Cyrillic (and other non-Latin-transliterable) text —
Cyrillic search queries currently normalize to an empty token. See
`technical-debt.md`-adjacent note in ADR-032 Decision 7.

## 8. Frontend — Admin and Storefront

Both Nuxt apps use `@nuxtjs/i18n` v10. Locale bundles are one JSON file
per locale per app (`i18n/locales/{ru,en,de}.json`), not one file per
namespace — v10 lazy-loads per active locale by design once each
locale declares its own `file:` entry, so a per-namespace split would
add complexity without a loading-performance benefit. Namespacing
within a bundle is nested JSON keys (`nav.orders`, `chrome.switch_store`),
read via `t('nav.orders')`.

`LanguageSwitcher.vue` (separate implementations in Admin and
Storefront, same pattern) drives `useI18n().setLocale()` and then
best-effort persists the choice server-side (Admin:
`PATCH /me/locale`; Storefront: `POST /storefront/locale`) — a failed
persistence call never blocks the already-applied client-side switch.

Admin: full interface translated (Russian default), covering
navigation (51 items), chrome (topbar, user menu, store switcher,
global search), and representative full pages (login, stores list).
Storefront: header/footer chrome and the language switcher itself.
Both are representative-coverage translations for this milestone (see
ADR-032 Decision 8), not an exhaustive string-by-string audit of every
page.

## 9. Themes

Theme section/block content (headings, button text, etc.) is
merchant-authored per-store content stored in `ThemeVersion` template
JSON, not developer-maintained UI chrome — it is out of scope for the
`lang/*.php` / `i18n/locales/*.json` mechanism, the same way CMS page
content is (spec section 10's explicit carve-out). No hardcoded
*developer*-authored text exists in the Theme Engine's own rendering
pipeline (section renderers surface only merchant-supplied `settings`
values); localized editor chrome for the Theme Customizer is covered
by the Admin's own `i18n/locales/*.json` bundle like any other Admin
page.

## 10. Developer tooling

Three console commands, backed by `TranslationFileScanner`:

- `translations:scan` — upserts the DB translation index from the
  live `lang/*.php` + `i18n/locales/*.json` files. Idempotent.
- `translations:missing` — live-scans files fresh (does not trust the
  DB index) and reports keys present in one locale's namespace but
  missing in another.
- `translations:unused` — reports DB-indexed keys no longer present in
  a live re-scan.

`localization:install` seeds the default `ru`(default)/`en`/`de`
`Language`/`Locale` rows (`de` falls back to `en`), idempotently, the
same convention as `search:install`/`notifications:install`. Required
once per environment (including test databases exercising real locale
resolution — the dev DB's seed does not carry into `RefreshDatabase`
test runs).

## 11. Tenant isolation

Language/Locale/Translation/TranslationKey/TranslationNamespace are
platform-wide shared reference data (the same status as a currency
code), not tenant-scoped. `StoreSupportedLocale` is the one genuinely
tenant-scoped table, scoped by its own `store_id` column. Store-level
settings (`default_locale`, `admin_locale`, `storefront_locale`) live
directly on the `stores` row and are managed exclusively through the
tenant-scoped `store-locale-settings` endpoint.

## 12. Tests

`tests/Feature/Localization/` (34 tests): locale resolution through
the full fallback chain (`LocaleResolutionTest`), store locale
settings CRUD and supported-locale invariants
(`StoreLocaleSettingsTest`), notification template locale fallback
(`NotificationLocalizationTest`), email locale threading
(`EmailLocalizationTest`), GraphQL error localization
(`GraphQLLocalizationTest`), search synonym locale filtering
(`SearchSynonymLocaleTest`), and the translation tooling commands
(`TranslationToolingTest`).

`phpunit.xml` pins `APP_LOCALE=en`/`APP_FALLBACK_LOCALE=en` so the bulk
of the existing suite stays locale-stable regardless of production's
`ru` default; localization itself is verified by tests that explicitly
switch locale per-test.

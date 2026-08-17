# Merchant Admin — UI/UX & Frontend Architecture Audit

**Scope:** `apps/admin` (Nuxt 3 SPA, `ssr:false`). Read-only audit, no code changed. Storefront (`apps/storefront`) and platform-admin excluded per instructions.

**Method:** Full structural review — `nuxt.config.ts`, `layouts/`, `config/navigation.ts`, all 17 shared components, all 76 page routes, `assets/css/tokens.css`, and grep sweeps for `@media`, `aria-*`, hardcoded hex, `confirm(`, `skeleton`, `overflow-x`. References below use `file:line`.

---

## 1. Current State Summary

The admin is a hand-built Vue/Nuxt SPA with **no UI/component library** (no Tailwind, no Vuetify/PrimeVue/etc., no Pinia — state via `useState` composables) and **no Storefront reuse**. It has one genuine strength: a real design-token file (`assets/css/tokens.css`) and a small, consistently-used shell (sidebar, topbar, breadcrumb, page header, icon set) shared by all 76 pages via `layouts/default.vue`.

Below that shell, every page is authored from raw HTML primitives (`<table>`, `<input>`, `<select>`) independently. There is no `DataTable`, `Badge`, `Modal`, `Pagination`, `EmptyState`, or `Skeleton` component anywhere in the codebase. This is the root cause of nearly every inconsistency in this report: 63 hand-rolled tables, 4 incompatible status-badge patterns, pagination on 2 of 76 pages, and responsive handling confined to two shell components while all 76 page bodies have zero `@media` rules.

The product surface is broad and mostly complete (orders, products, customers, fulfillment, shipping, payments, refunds/returns, analytics, automation, notifications, search, CMS, theme builder, Russian Commerce compliance) — 76 routes across ~30 feature areas. Notably, there is **no dashboard** (`/` redirects straight to `/stores` with no overview content) and **no general Settings page** (only a Russian-commerce settings cluster) — both documented as deliberate ("no fake pages") rather than oversights, per a comment in `config/navigation.ts:6-9`.

## 2. Strengths

- **Single source of truth for navigation** — `config/navigation.ts` drives both the sidebar and the command palette (`GlobalSearch.vue` reuses it via `flattenNavigationItems()`), so there is no drift between nav surfaces.
- **Real design tokens** — `tokens.css` defines a coherent 4px spacing scale, type scale, radii, shadows, and a semantic color palette. Not a Figma-export stub; it's actually structured like a token system.
- **Centralized icons** — one `AppIcon.vue` SVG lookup table, no per-component icon imports, no library dependency to manage.
- **Consistent focus/keyboard baseline in the shell** — global `:focus-visible` outline, correct `aria-current`/`role=listbox`/`role=menu` usage in `SidebarSection`, `StoreSwitcher`, `UserMenu`, `GlobalSearch`.
- **i18n is wired through the whole shell** (ru/en/de) and used correctly on nearly every page except one (`register.vue`, see §8).
- The team has already demonstrated the fix pattern once: `section`-as-card styling was consolidated globally in `app.vue` with a comment noting it replaced per-page duplication. The same move simply hasn't been made yet for tables, badges, pagination, or loading states.

## 3. Weaknesses

- No page-content component library — every list, form, and detail page reinvents table/badge/pagination/empty/loading markup from scratch.
- Token adoption is inconsistent: three different hardcoded reds (`#c00`, `#b00020`, `#c0392b`) coexist with the actual `--color-danger` token; 13+ files reference tokens (`--color-surface-muted`, `--color-info-muted`, `--color-warning-muted`) that are **never defined** in `tokens.css` and silently fall back to hardcoded hex.
- Responsive design is shell-only. The sidebar/topbar handle a single 900px breakpoint; the other 76 page bodies have no `@media` rules at all, and 57 of 63 tables have no horizontal-scroll wrapper.
- Accessibility discipline drops off sharply outside the shell: `aria-*` appears in only 4 of ~93 files; no `aria-live` region exists anywhere for async success/error feedback; two of the most exposed pages (`login.vue`, `register.vue`) have unlabeled inputs.
- No dashboard/overview — first authenticated screen is a client-side redirect with a bare "Loading…" state.

## 4. Critical UX Issues

| # | Problem | Why it matters | Severity | Recommended solution | Scope |
|---|---|---|---|---|---|
| 1 | No shared status badge component; 4 incompatible patterns coexist (colored, broken/unstyled, plain text, undefined-token) | Order/payment/fulfillment status is the single most-scanned piece of information on list and detail pages across the whole app (orders alone has 10 distinct status fields). Merchants currently can't visually triage state at a glance on `orders`, `products`, `customers`, or the three pages with the broken `status-${x}` binding (`fulfillments`, `refunds`, `returns`) | **Critical** | Build one `StatusBadge.vue` with a variant map (success/warning/danger/neutral/info), backed by tokens that actually exist. Migrate all status renders to it. | Global (component), then page-specific rollout |
| 2 | Pagination exists on only 2 of 76 pages; the rest silently truncate to the API's default page size | Merchants with >1 page of orders/products/customers/payments have no way to know data is missing — this is a data-integrity-perception bug, not just a polish gap | **Critical** | Shared `Pagination.vue` + a `usePagination`/list-fetch composable, applied to every list page | Global pattern, page-specific rollout |
| 3 | 57 of 63 tables have no horizontal-scroll wrapper; page bodies have zero `@media` rules | On any viewport narrower than the widest table, the layout visibly breaks (overflow, no affordance). Given the desktop-first 250px fixed sidebar, the app is effectively unusable below ~1100px despite having a working mobile nav drawer | **Critical** | Wrap tables in a shared `TableContainer` with `overflow-x:auto`; establish real content-area breakpoints, not just shell breakpoints | Global |
| 4 | `login.vue`/`register.vue` inputs have no `<label>`, rely on `placeholder` only | Fails basic label association for assistive tech on the two pages every user must pass through, including first-time/unauthenticated users | **Critical** | Add visually-associated `<label>` (can be visually-hidden) to all auth inputs | Page-specific (2 files), but indicates a rule that should be enforced app-wide |

## 5. Design Inconsistencies

| # | Problem | Why it matters | Severity | Recommended solution | Scope |
|---|---|---|---|---|---|
| 5 | Danger red drifts across 3 hardcoded hex values (`#c00` ×16, `#b00020` ×9, `#c0392b` ×2) plus the actual `--color-danger` token | Same semantic color rendered as visibly different reds depending on which page you're on; erodes the token system's value | High | Sweep-replace with `var(--color-danger)`; add a lint rule (stylelint `declaration-property-value-disallowed-list` or similar) banning raw hex in `<style>` blocks | Global |
| 6 | Three CSS custom properties used but never defined (`--color-surface-muted`, `--color-info-muted`, `--color-warning-muted`) — 13+ files silently rely on `var(x, #fallback)` | Token system appears more complete than it is; anyone extending a token later without checking usage sites will not notice these are dead references | Medium | Define the missing tokens in `tokens.css`, or remove the `var()` wrapper and just use the intended hex directly with a comment | Global (tokens.css) |
| 7 | Product editor (`pages/products/[id].vue`) uses raw rem/px throughout instead of `--space-*`/`--radius-*`, while most other pages use tokens consistently | The single most complex page in the app is also the least token-compliant, meaning it will drift furthest from any future design update | Medium | Retrofit token usage during the Products phase of the redesign | Page-specific (products/[id].vue) |
| 8 | `h1` has no global style; `PageHeader.vue` sets it per-instance, but `login.vue`/`register.vue` bypass `PageHeader` and render browser-default `h1` | Visually inconsistent heading scale between the auth pages and everything else | Medium | Give `h1` a global base rule in `app.vue`, let `PageHeader` override only what's contextual | Global |
| 9 | `h3` has a weight but no defined font-size — can render larger than the styled `h2`, inverting hierarchy | Confuses visual hierarchy anywhere `h3` is used (e.g. `customers/[id].vue` timeline section) | Medium | Add `--text-lg`/`--text-base` sizing to the global `h3` rule | Global |
| 10 | `StoreSwitcher.vue` and `UserMenu.vue` each independently re-implement identical click-outside-to-close logic | Straightforward duplication; any future accessibility or behavior fix (e.g. Escape-to-close) has to be applied twice and will drift | Low | Extract a `useClickOutside` composable | Global (shell components) |
| 11 | Label presence in forms correlates with an unstated rule ("data-entry form" gets `<label>`, "filter bar" gets `placeholder`-only) — applied inconsistently | Filter bars (`customers/index.vue`) and auth forms share the same gap for different reasons; without a stated rule, new pages will guess wrong either way | Medium | Establish and document: all inputs get an associated label, visually-hidden where a placeholder is the intended visual affordance | Global |

## 6. Component Duplication

| Category | Current state | Files (representative) |
|---|---|---|
| Table markup | 63 independent `<table>` implementations, no shared component | `orders/index.vue:10-33`, `customers/index.vue:32-53`, `products/index.vue:10-29` |
| Status badge | 4 incompatible patterns, no shared component | see §4.1, §6 detail in audit evidence |
| Pagination | 2 independent implementations, no shared component | `customers/index.vue:56-60`, `search/settings.vue` |
| Empty state | ~75 independently authored `<p>No X yet.</p>` variants | `orders/index.vue:34`, `products/index.vue:30`, `fulfillments/index.vue:30` |
| Error display | Consistent *visual* convention but the try/catch/`withError` boilerplate is copy-pasted into nearly every page's `<script setup>` | `products/[id].vue:230-237` (local `withError()`), repeated shape across dozens of pages |
| Custom dropdown/listbox | 3 independent implementations (`StoreSwitcher`, `UserMenu`, plus click-outside logic) instead of one shared primitive | `StoreSwitcher.vue:73-77`, `UserMenu.vue:45-49` |
| Modal/dialog | Only `GlobalSearch.vue` implements a real dialog; all other destructive confirmations use native `window.confirm()` (15 files) | `customer-tags/index.vue`, `automation/[id].vue`, `analytics/index.vue` |
| Card | Actually **consolidated** already — global `section` styling in `app.vue:118-136`, cited by its own code comment as replacing prior duplication. Cite as the model to replicate for the categories above. | `app/app.vue:118-136` |

## 7. Navigation Issues

- No structural issues found — `config/navigation.ts` as single source of truth, reused by both sidebar and command palette, is genuinely solid.
- Sidebar sections are always-expanded with no collapse/expand interaction (explicitly deferred per in-code comment) — not wrong, but as nav grows past ~30 feature areas it will get long; worth revisiting once IA work (Phase 2) starts.
- `--sidebar-width-collapsed` token exists but is unused (no collapse feature implemented) — either build it or remove the dead token.
- Minor: root `/` is a redirect-only route with no dashboard; this is a navigation/IA gap more than a bug (see Critical Issue candidate for Phase 2 — whether a real dashboard belongs in scope is a product decision, flagged here, not decided).

## 8. Page-by-Page Findings

**Orders (`orders/index.vue`, `orders/[id]`)** — no pagination, no filters, no loading state, all 10 status fields render as plain text. Highest-severity page for the badge/pagination fixes in §4.

**Products (`products/index.vue`, `products/[id]`)** — list page has no filters/pagination; editor page (`[id].vue`) is a 422-line, 7-form mega-page (details/options/values/variants/images/collections/inventory) with only page-level error attribution (a failure in any of the 7 forms produces the same undifferentiated error message) and the weakest token compliance in the app (§5.7). Variants table uses a legitimately different inline-edit interaction model, unsignposted as intentional.

**Customers (`customers/index.vue`, `[id]`, groups/segments/tags)** — the most complete list page in the app: has real filters (search + 3 selects + numeric range) and real pagination. Should be the reference implementation when building shared Table/Filter/Pagination components, not an outlier to fix.

**Fulfillment / Refunds / Returns** — all three bind a `status-${x}` class that is never styled (§4.1 pattern 2) — a broken feature masquerading as a working one; higher priority than a plain-text gap because it looks intentional in the template but silently does nothing.

**Payments, Shipping, Automation, Notifications, Search, CMS/Themes** — not individually broken, but inherit every global gap: no pagination, no badge component, no responsive table wrapper (exceptions: `automation/executions/[id]`, `pages/[id]`, `themes/[id]`, `analytics/reports/[id]`, `block-library`, `section-library` do wrap tables in `overflow-x:auto` — use these six as the reference pattern).

**Analytics** — `AnalyticsWidget.vue` is a real, purpose-built dashboard-widget renderer; worth reusing/generalizing as the future dashboard's building block once a real `/` dashboard is scoped.

**Auth (`login.vue`, `register.vue`)** — unlabeled inputs (§4.4); `register.vue` additionally has hardcoded English strings, unlike every other page which is i18n-driven — breaks the stated Russian-default requirement.

**Theme Builder (`builder/pages/[id]`, `BuilderBlockTree.vue`)** — drag-and-drop section reordering has no keyboard equivalent (no `tabindex`, no arrow-key handler); `MenuItemTree.vue` solves the same conceptual problem (reorder items in a tree) with accessible Edit/Delete buttons instead — inconsistent interaction model for near-identical use cases, and the builder's approach is the less accessible of the two.

**Settings** — no general settings page exists; only Russian-commerce compliance settings (`legal-profile`, `tax-settings`, `fiscalization-settings`, `payment-methods`). Documented as intentional (no fake pages), but worth a product conversation before Phase 9 — is a general settings shell coming, or does every settings-like feature get its own top-level route permanently?

## 9. Accessibility Findings

- `aria-*` attributes exist in only 4 of ~93 files app-wide (3 shell components + one builder page) — everything below the shell is unannotated.
- No `aria-live` region anywhere — async save/error/success feedback is silent to screen readers app-wide.
- No form field uses `aria-invalid`/`aria-describedby` for error association.
- `login.vue`/`register.vue` — unlabeled inputs (Critical, §4.4).
- Theme builder drag-reorder has no keyboard path (Medium-High — blocks a core authoring workflow for keyboard-only users).
- No accessibility lint plugin configured (`eslint.config.mjs` has no `eslint-plugin-vuejs-accessibility` equivalent) — nothing currently prevents regression.
- Positives to preserve: global `:focus-visible`, correct `role=dialog`/`aria-modal` on `GlobalSearch`, correct `role=listbox`/`aria-selected` on `StoreSwitcher`, correct `role=menu`/`aria-haspopup` on `UserMenu`, correct `aria-current="page"` pairing in nav.

## 10. Recommended Design Direction

Do not adopt a full third-party component library wholesale (Shopify Polaris, Vuetify, etc.) — the existing token file is good enough, and a heavy library would fight the app's from-scratch CSS architecture and `ssr:false` bundle profile. Instead: **build a small first-party component layer on top of the existing tokens**, sized to what this app actually needs — `DataTable` (with built-in pagination/sort/filter slots and scroll wrapper), `StatusBadge`, `Pagination`, `Modal`, `EmptyState`, `Skeleton`, `FormField`. Use Shopify Admin and Linear as density/hierarchy references (data-dense tables, clear status coloring), Stripe Dashboard for form/error-state patterns, GitHub for keyboard-accessible custom interactions (the theme-builder reorder gap) — reference only, no direct copying, per constraints.

Fix the token gaps (§5.5, §5.6) before or alongside building components, since every new component will consume those tokens — building `StatusBadge` against undefined `--color-*-muted` tokens would just re-encode the current bug into a "shared" component.

## 11. Prioritized Redesign Roadmap

**Phase 1 — Design System**
Fix token gaps (undefined `--color-*-muted`, red drift). Define missing scale entries. Add a stylelint rule banning raw hex/px in favor of tokens. This is the foundation every later phase depends on.

**Phase 2 — Admin Shell**
Address the dashboard/settings IA gap (product decision needed — flagged in §8/§7), reconsider sidebar collapse (unused token exists), confirm responsive strategy for the shell beyond the current 900px drawer breakpoint.

**Phase 3 — Shared Components**
Build `DataTable` (with scroll wrapper + pagination + sort slot), `StatusBadge`, `Pagination`, `Modal`, `EmptyState`, `Skeleton`, `FormField`, `useClickOutside`. This phase is the highest-leverage phase — every subsequent page-area phase consumes it.

**Phase 4 — Products**
Migrate list to `DataTable`/`Pagination`, migrate editor to `FormField` + tokenized spacing (currently the least token-compliant page), attribute errors per-form instead of page-wide.

**Phase 5 — Orders**
Migrate to `DataTable`, apply `StatusBadge` to all 10 status fields, add pagination and a loading state (currently has none).

**Phase 6 — Customers**
Already the best-built list page — migrate to shared components as the reference case, verify no regression in filter/pagination behavior.

**Phase 7 — Operations** (Fulfillment, Shipping, Payments, Refunds, Returns, Inventory)
Fix the three broken `status-${x}` bindings (fulfillments/refunds/returns) as part of this migration — currently a silently non-functional feature. Add scroll wrappers and pagination app-wide.

**Phase 8 — Analytics / Automation**
Generalize `AnalyticsWidget.vue` if a real dashboard is scoped in Phase 2. Add keyboard-accessible reordering to `BuilderBlockTree.vue` (align with `MenuItemTree.vue`'s accessible pattern) as part of Automation/Theme-builder work.

**Phase 9 — Settings**
Resolve the "no general settings page" product question from §8 before building. Migrate Russian Commerce settings pages to `FormField`.

**Phase 10 — Final Polish**
Accessibility sweep app-wide (`aria-live` for async feedback, `aria-invalid`/`aria-describedby` on forms, label the two auth pages, add an a11y lint plugin to prevent regression). Cross-page visual QA against the token system.

---

## Report Summary

**Files inspected:** `nuxt.config.ts`; `app/layouts/{default,auth}.vue`; `app/config/navigation.ts`; all 17 files in `app/components/`; all 76 route files in `app/pages/`; `app/assets/css/tokens.css`; `app/app.vue`; `app/composables/*`; `app/middleware/auth.global.ts`; `eslint.config.mjs`. Plus targeted greps across `pages/**` for `@media`, `aria-*`, hex colors, `confirm(`, `skeleton`, `overflow-x`.

**Key findings:** Solid token foundation and shell (nav/sidebar/topbar/breadcrumb/icons), but zero page-content component library — every table, badge, pagination control, and empty/loading state is hand-authored per page, producing widespread inconsistency at scale (63 tables, 4 badge patterns, 2/76 paginated pages).

**Critical issues:** no shared status badge (4 incompatible patterns, 3 pages with a silently broken one); pagination missing on 74/76 list pages; no responsive table handling on 57/63 tables; unlabeled auth-page inputs.

**Proposed design direction:** first-party lightweight component layer on existing tokens (not a third-party library swap); fix token gaps before building components.

**Recommended first implementation phase:** Phase 1 (Design System — token fixes), immediately followed by Phase 3 (Shared Components) as the highest-leverage phase; Phase 2 (Admin Shell) can run in parallel since it mostly needs product decisions rather than heavy component work.

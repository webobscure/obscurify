# Admin Navigation Architecture

Implementation record for the "daily work + Settings" navigation refactor (approved proposal, then implemented in two passes — the initial split and this follow-up covering active-state, breadcrumbs, store context, settings search, and the operations-vs-configuration review). Single source of truth for the trees below: `apps/admin/app/config/navigation.ts`.

Related docs: [`ADMIN_DESIGN_SYSTEM.md`](./ADMIN_DESIGN_SYSTEM.md) (component/token layer this navigation is built from — unchanged by this refactor), [`UI_AUDIT_M27.md`](./UI_AUDIT_M27.md) (the audit that predates and motivated the Settings split).

## 1. Main (daily) navigation

`primaryNavigation` — rendered by `AdminSidebar.vue` / `AdminNavigation.vue`. 10 top-level entries, each an accordion branch (only the branch containing the active route auto-expands — `useNavigationExpansion.ts`, unchanged from the earlier sidebar-accordion milestone).

```
Orders            /orders
  Fulfillments    /fulfillments
  Shipments       /shipments
  Returns         /returns
  Refunds         /refunds
  Payments        /payments
  Fiscal Receipts /russian-commerce/fiscal-receipts
Products          /products
  Inventory       /inventory
Collections       /collections
Customers         /customers
  Customer Groups /customer-groups
  Customer Segments /customer-segments
  Customer Tags   /customer-tags
Content           /pages
  Pages           /pages
  Page Templates  /page-templates
  Blogs           /blogs
  Authors         /authors
  Menus           /menus
Themes            /themes
  Theme Customizer /theme-customizer
  Section Library /section-library
  Block Library   /block-library
Marketing         /promotions
Analytics         /analytics
  Reports         /analytics/reports
  Saved Reports   /analytics/saved-reports
  Search Analytics /search/analytics
Automation        /automation
  Executions      /automation/executions
  Templates       /automation/templates
Apps              /apps
```

Plus the secondary-nav Settings entry (§2). No "Home"/"Dashboard" entry exists — there is no dashboard page in this app (the pre-refactor root already redirected straight to a list page); inventing one would violate "do not add fake pages." The root route now redirects to `/orders` instead (§5).

## 2. Settings entry

Bottom of the sidebar, below a hairline (`secondaryNavigation`, reusing the same visual slot `/stores` occupied pre-refactor) — visually separated from the 10 daily entries by the existing `.bottom { border-top: ...; margin-top: ... }` rule in `AdminSidebar.vue`, unchanged.

Clicking it enters the Settings workspace (§3); the main Admin sidebar is replaced by `SettingsSidebar.vue` for the duration (per the approved proposal — "main Admin shell remains visible" means the same topbar/store-switcher/theme/locale controls stay reachable, not that the daily sidebar tree stays on screen simultaneously with a second one).

## 3. Settings workspace

One reusable shell — `SettingsShell.vue` — not a page-specific layout per settings page:

```
layouts/settings.vue → SettingsShell.vue
  ├─ SettingsSidebar.vue   (left: store switcher, back-link, search, grouped nav)
  ├─ AdminTopbar.vue       (same topbar as the daily shell — search, locale, user menu)
  ├─ AdminBreadcrumbs      ("Settings / {Section}", computed from navigation.ts)
  └─ <slot />              (the actual settings page — unchanged component, unchanged route)
```

Every settings-destination page opts in via `definePageMeta({ layout: 'settings' })` — one line, no route change, no page-content change. `SettingsShell.vue` mirrors `AdminShell.vue` exactly (same topbar, same content padding, same `<Toast />` mount) so the workspace reads as "the same app, different navigation context," not a different product.

## 4. Settings tree

`settingsNavigation` — grouped, flat `NavigationSection`s (not accordion branches: Settings sections stay simultaneously visible for orientation, matching the reference Shopify IA, since there's no daily-vs-rare distinction *within* Settings the way there is between the two top-level trees).

```
Store
  Stores              /stores
  Locations           /locations
Shipping
  Shipping Methods    /shipping-methods
  Shipping Zones      /shipping-zones
Notifications
  Notifications       /notifications
  Templates           /notifications/templates
  Channels            /notifications/channels
  Providers           /notifications/providers
  Delivery Log        /notifications/deliveries
Search
  Search              /search
  Synonyms            /search/synonyms
  Rules & Ranking     /search/rules
  Pinned Products     /search/pinned
  Search Settings     /search/settings
SEO
  Redirects           /redirects
Russian Commerce
  Legal Details       /russian-commerce/legal-profile
  Tax / VAT Settings  /russian-commerce/tax-settings
  Fiscalization Settings /russian-commerce/fiscalization-settings
  Payment Methods     /russian-commerce/payment-methods
```

**Sections in the brief with no real page today — not built, not listed:** General, Domains, Localization/Languages/Currencies (a locale switcher exists in the topbar, not a dedicated settings page), Users, Roles, API, Webhooks, Integrations, Security, Developer. Per the standing "do not add fake pages" rule, none of these appear in `settingsNavigation`. When a real page for one of them ships, it's added the same way every other entry was — a `to` pointing at a real route, translated in all three locales, nothing invented ahead of the backend.

## 5. Operations vs. configuration

The rule this refactor actually enforces, module by module — **don't move a whole domain because its name matches a Settings module; move the specific pages that are configuration.**

| Domain | Operational (stays daily) | Configuration (moved to Settings) |
|---|---|---|
| Orders | Fulfillments, Shipments, Returns, Refunds, Payments (transaction history) | — |
| Shipping | Shipments (the actual shipment records, under Orders) | Shipping Methods, Shipping Zones (rate config) |
| Notifications | Delivery Log (monitoring — did it actually send) | Templates, Channels, Providers, and the Notifications composer itself |
| Search | Search Analytics (a report — moved to daily **Analytics**, not left orphaned in Settings) | Synonyms, Rules & Ranking, Pinned Products, Search Settings |
| Russian Commerce | Fiscal Receipts (issued-receipt records, under Orders) | Legal Details, Tax/VAT Settings, Fiscalization Settings, Payment Methods |
| Inventory | Inventory itself (stock levels merchants check daily — under Products) | Locations (physical setup, done once — moved to Settings) |

Delivery Log is the one genuinely ambiguous call (the brief itself hedges "operational or monitoring") — kept in Settings > Notifications since it's a diagnostic view of the notification *system*, not a daily merchant task like checking new orders. Revisit if usage data ever says otherwise.

## 6. Active-state behavior

- **Daily branch highlighting** — unchanged accordion logic (`isBranchActive`/`findActiveBranch`/`useNavigationExpansion`).
- **Settings stays active in the main sidebar for every route inside it**, not just the literal `/settings` redirect page. `AdminSidebar.vue` computes this: `isRouteInSection(route.path, settingsNavigation)` (new helper) checks membership across the whole flattened Settings tree; when true, the Settings item's `activePattern` is swapped to the *current* path before rendering, so `isNavItemActive` matches regardless of which of the ~20 Settings routes is actually open. The static `activePattern: '/settings'` on the item still covers the redirect page itself for the instant before it forwards.
- **The correct Settings subsection is active in the Settings sidebar** — `SidebarSection.vue`'s existing `isNavItemActive` check, unchanged; works automatically since every settings item is a real route.
- **Nested/detail routes preserve active state in both layers** — `isNavItemActive`'s existing prefix-match (`currentPath.startsWith(pattern + '/')`) already covers this; verified by test for a hypothetical `/notifications/deliveries/{id}` route.

## 7. Route ownership — one source of truth

`apps/admin/app/config/navigation.ts` is the only place a route/label pairing is declared. Every consumer reads from it, none hardcode a second copy:

- **Sidebar** (`AdminNavigation.vue`, `SettingsSidebar.vue`) — `primaryNavigation` / `settingsNavigation` directly.
- **Command palette** (`GlobalSearch.vue`) — `flattenNavigationItems(primaryNavigation)` + `flattenNavigationItems(settingsNavigation)`, tagged `isSettings` at render time only (a boolean derived from *which* array an item came from, not a duplicated field) so Settings results show a small "Settings" tag.
- **Breadcrumbs** (`SettingsShell.vue`) — `findSettingsSection(route.path)`, a pure lookup into the same `settingsNavigation` array. No settings page hand-rolls its own breadcrumb array; the one pre-refactor page that did (`russian-commerce/legal-profile.vue`, with a hardcoded, un-translated `breadcrumbs` prop) had it removed in favor of the shared computation.
- **Settings search** (`SettingsSidebar.vue`) — filters the same `settingsNavigation` array client-side; no second index, no backend.

## 8. Responsive behavior

Unchanged breakpoint (900px) and pattern for both sidebars — `SettingsSidebar.vue` reuses `useAdminSidebar()` and the identical fixed-position/backdrop/transform CSS `AdminSidebar.vue` already had, so Settings gets the same drawer-on-mobile behavior for free rather than inventing a second responsive pattern. Desktop layout (fixed 250px settings rail + fluid content) is stable from `--bp-lg` (1200px) up, comfortably covering the ~1280px+ target.

## 9. Store context

`SettingsSidebar.vue` renders the existing `<StoreSwitcher />` at the top, identical to `AdminSidebar.vue` — no second tenant-selection mechanism. Settings pages are store-scoped through the same `useActiveStore()`/`X-Store-Id` mechanism every other page already uses; nothing in this refactor touches that.

## 10. Localization

Russian stays the default locale. Every label introduced or moved by this refactor exists in `ru`/`en`/`de` (`i18n/locales/*.json`) — verified by the "every nav labelKey has a translation in all three locales" test, which also covers `settingsNavigation` section-group labels and the new settings-search placeholder string. No new user-visible English string was hardcoded; the one pre-existing hardcoded breadcrumb (`legal-profile.vue`) was removed rather than translated, since the shared Settings breadcrumb now covers that position.

## 11. Future navigation rules

**New Admin modules must explicitly declare whether they are:**

- **Daily Operations** — a merchant opens it most days; add it to `primaryNavigation`, nested under the closest existing daily module if it's a record/report of that module rather than a standalone concern (the Fiscal Receipts / Search Analytics pattern in §5).
- **Settings** — configured once, tuned occasionally; add it to `settingsNavigation` under the closest matching section, or a new section only if none fit.
- **Developer** — API keys, webhooks, GraphQL playground-adjacent tooling; belongs in Settings once it exists, under its own section (not built today — §4).
- **App Extension** — a third-party or platform app surface reached via the existing Apps page, not a sidebar entry of its own.

**A module must not add itself directly to the main sidebar without this classification.** In practice: before adding a `NavigationItem` to `primaryNavigation`, confirm it's actually daily-use per §5's operations-vs-configuration test, not just conveniently top-of-mind. The 10-entry cap (§1) is a forcing function for this, not an arbitrary limit — if adding a module would push past it, the module almost certainly belongs in Settings or nested under an existing daily branch instead.

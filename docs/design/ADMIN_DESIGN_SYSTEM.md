# Merchant Admin Design System

**Status: Milestone 27 — stabilized.** This is the canonical, implemented design system for the Merchant Admin. It supersedes [`DESIGN_SYSTEM.md`](./DESIGN_SYSTEM.md) as the source of truth for what actually exists in code today; that document remains as the original v2 specification and design rationale (read it for the *why* behind sizes/states/a11y contracts — this document is the *what's built, where, and what's next*).

**Reference implementation:** the Product module (`apps/admin/app/pages/products/[id].vue`, `products/index.vue`). Every shared component's spacing, density, and interaction pattern is lifted from what's already shipped and approved there — this document does not invent a second visual language.

---

## 1. Design philosophy

- **Compact, information-dense, Linear/Shopify-Admin-grade** — merchants scan 50-200+ rows routinely; density beats whitespace.
- **Tokens only, no per-page invention.** Every color, spacing value, radius, shadow, icon size, and duration a component uses must resolve to a token in `apps/admin/app/assets/css/tokens.css`. No hardcoded hex, no bare `px`/`rem` in a component's `<style>` block.
- **Russian is the default locale** (`defaultLocale: 'ru'`). Cyrillic labels run 15-40% longer than their English equivalent — no component assumes a fixed pixel width fits a label; buttons/labels grow, they never truncate primary actions.
- **Native elements first.** `Select` wraps a real `<select>`, `Checkbox`/`Radio`/`Switch` wrap real `<input>`s — free keyboard/mobile/AT support beats a hand-rolled listbox for anything that doesn't need it (Combobox is the deliberate exception, and it's deferred — see §9).
- **Zero new runtime dependencies.** Every component below is hand-built against CSS custom properties, matching the app's existing from-scratch architecture.

## 2. Spacing scale

4px base unit, defined in `tokens.css`:

| Token | Value | Token | Value |
|---|---|---|---|
| `--space-0` | 0 | `--space-6` | 24px |
| `--space-1` | 4px | `--space-7` | 28px |
| `--space-2` | 8px | `--space-8` | 32px |
| `--space-3` | 12px | `--space-10` | 40px |
| `--space-4` | 16px | `--space-12` | 48px |
| `--space-5` | 20px | | |

Every `margin`/`padding`/`gap` in a component resolves to one of these — the `--space-7`/`--space-10`/`--space-12` steps were added this milestone specifically because their absence was why so many legacy pages fell back to un-tokenized `0.5rem`/`0.75rem`/`1rem` (see [`UI_AUDIT_M27.md`](./UI_AUDIT_M27.md)).

## 3. Typography

```
--font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, system-ui, sans-serif;
--text-xs: 0.75rem     /* table meta, timestamps, helper text */
--text-sm: 0.8125rem   /* secondary body, table headers, labels */
--text-base: 0.875rem  /* default body, inputs, buttons */
--text-lg: 1rem        /* h2 */
--text-xl: 1.25rem     /* h1 */
--text-2xl: 1.5rem     /* PageHeader title */

--font-weight-regular: 400
--font-weight-medium: 500
--font-weight-semibold: 600

--leading-tight: 1.25    /* headings */
--leading-normal: 1.5    /* body text, table cells — Cyrillic ascenders/descenders need the room */
--leading-relaxed: 1.65  /* wrapped paragraph copy in Alert/EmptyState bodies */
```

Global heading rules (`app.vue`) now cover `h1`/`h2`/`h3` (previously only `h2` had an explicit rule — `h1`/`h3` relied on the browser default, an audit-flagged gap).

**Label hierarchy rule** (from the Product module): section labels are `--text-xs`, uppercase, `--color-text-subtle` — always visually quieter than the content they introduce. Never make a label louder than its data.

## 4. Color

Two layers: **primitives** (`--gray-*`, `--indigo-*`, `--green-*`, `--amber-*`, `--red-*`, `--blue-*` ramps — internal only) and **semantic tokens** (`--color-*` — the only names components reference). Full ramp and every semantic pair are defined in `tokens.css`; see [`DESIGN_SYSTEM.md` §1](./DESIGN_SYSTEM.md#1-color-tokens) for the complete table and the rationale behind each dark-mode value.

| Semantic token pair | Use for |
|---|---|
| `--color-danger` / `-hover` / `-bg` / `-border` | Destructive actions, errors, cancelled/failed statuses |
| `--color-success` / `-hover` / `-bg` / `-border` | Completed/paid/active statuses, success toasts |
| `--color-warning` / `-hover` / `-bg` / `-border` / `-muted` | Pending/processing statuses, non-blocking alerts |
| `--color-info` / `-hover` / `-bg` / `-border` / `-muted` | Draft/new/system badges, info toasts |
| `--color-accent` / `-hover` / `-active` / `-bg` | Primary actions, active nav/tab state, links, focus ring |
| `--color-surface-muted` | Table header bg, disabled field bg, hover row bg |
| `--color-surface-raised` | Modal/Drawer/Popover/Dropdown surfaces |

**Dark theme:** `[data-theme='dark']` on `<html>` (explicit choice, `useColorMode()`) or `@media (prefers-color-scheme: dark)` when the preference is `system` (the default). Every dark-mode value is hand-tuned against its own background, not a mechanical filter-invert.

## 5. Layout

```
--sidebar-width: 250px       --bp-sm: 640px    (single-column forms, filter bar wraps)
--sidebar-width-collapsed: 68px  --bp-md: 900px    (sidebar drawer breakpoint)
--topbar-height: 60px        --bp-lg: 1200px   (table density can increase)
                              --bp-xl: 1440px   (max content width before centering)
--content-max-width: 1440px
--content-padding-x: var(--space-8)      (var(--content-padding-x-sm) = var(--space-4) below --bp-sm)
```

Page body convention: `.page { max-width: var(--content-max-width); margin-inline: auto; padding-inline: var(--content-padding-x); }` wrapping `PageHeader` → `FilterBar` (list pages) → content. See `apps/admin/app/pages/design-system.vue` for a working example of this wrapper.

Border/radius/shadow/icon-size/motion tables live in `tokens.css`, mirrored in [`DESIGN_SYSTEM.md` §5-9](./DESIGN_SYSTEM.md).

## 6. Component catalog

All components live in `apps/admin/app/components/ui/` (flat, Nuxt auto-imported — no manual registration). Shell chrome (`AdminSidebar`, `AdminNavigation`, `AdminBreadcrumbs`, `AppIcon`, `PageHeader`, `StoreSwitcher`, `UserMenu`, `GlobalSearch`) stays in `apps/admin/app/components/` since its contract predates and sits above this component layer.

**Status legend:** ✅ Ready (built, tokenized, matches the spec) · 🆕 New this milestone · 🔧 Extended this milestone (pre-existing, additive changes only — see notes) · 🕗 Deferred (deliberate build-vs-buy decision, not built yet) · 🗑 Legacy (global CSS a real component should eventually replace — not removed yet, still load-bearing for un-migrated pages).

| Component | Status | File | Notes |
|---|---|---|---|
| Button | 🆕 | `ui/Button.vue` | primary/secondary/danger/ghost × sm/md/lg, loading (width-locked), icon slot |
| IconButton | 🆕 | `ui/IconButton.vue` | ghost/danger-ghost, mandatory `ariaLabel` |
| Input | 🆕 | `ui/Input.vue` | leading icon, clearable, `FormField`-backed label/help/error |
| Textarea | 🆕 | `ui/Textarea.vue` | vertical-resize only, `FormField`-backed |
| Select | 🆕 | `ui/Select.vue` | native `<select>`, `FormField`-backed |
| FormField | 🆕 | `ui/FormField.vue` | label/required-marker/help/error wrapper underneath Input/Textarea/Select |
| Checkbox | 🆕 | `ui/Checkbox.vue` | indeterminate support (DataTable header checkbox) |
| Radio | 🆕 | `ui/Radio.vue` | default + `card` variant |
| Switch | 🆕 | `ui/Switch.vue` | immediate-effect toggle, loading state |
| Badge | 🆕 | `ui/Badge.vue` | neutral/accent/outline, optional removable chip |
| StatusBadge | ✅ | `ui/StatusBadge.vue` | pre-existing, matches spec exactly — the domain→bucket pattern `ProductStatusBadge.vue` already demonstrates is the template every other domain's status wrapper should follow (see §9) |
| ProductStatusBadge | ✅ | `ui/ProductStatusBadge.vue` | reference implementation of the domain-wrapper pattern |
| Card | 🆕 | `ui/Card.vue` | formalizes the `section` global CSS in `app.vue`, header/body/footer slots, default/raised/interactive |
| Alert | 🆕 | `ui/Alert.vue` | danger/warning/success/info, dismissible |
| Banner | 🆕 | `ui/Banner.vue` | full-bleed page-level Alert |
| Toast + `useToast()` | 🆕 | `ui/Toast.vue`, `composables/useToast.ts` | single live region mounted in `AdminShell`; danger toasts never auto-dismiss |
| Tabs | 🆕 | `ui/Tabs.vue` | line/segmented, roving-tabindex arrow-key navigation |
| Breadcrumb | ✅ | `AdminBreadcrumbs.vue` | pre-existing, correct — kept as-is per spec |
| Dropdown | 🆕 | `ui/Dropdown.vue` | menu/listbox variants on `Popover` — **not yet consumed** by `StoreSwitcher`/`UserMenu` (Phase 1, see §10) |
| Popover | 🆕 | `ui/Popover.vue` | positioning primitive, flips above trigger when short on room below |
| Tooltip | 🆕 | `ui/Tooltip.vue` | hover + focus, dark bg regardless of theme |
| Modal | ✅ | `ui/Modal.vue` | pre-existing, sm/md/lg, `useDismissable` focus trap — **not yet extended** with the `danger` variant styling from the spec (Phase 1) |
| Drawer | ✅ | `ui/Drawer.vue` | pre-existing, right-anchored, same `useDismissable` contract as Modal |
| Command Palette | ✅ | `GlobalSearch.vue` | pre-existing, correct — kept as-is per spec, reference implementation other overlays structurally match |
| Pagination | ✅ | `ui/Pagination.vue` | pre-existing, "simple" variant (Prev/Next + summary) rather than spec's numbered-pages "full" variant — sufficient, not extended this milestone |
| DataTable | 🆕 | `ui/DataTable.vue` | compact/comfortable density, sortable headers (3-state), selection + indeterminate, bulk-action bar, sticky header, mandatory horizontal scroll, loading/empty/error states — see §7 |
| EmptyState | 🔧 | `ui/EmptyState.vue` | pre-existing; added an `#icon` slot (additive — no existing usage passes one, zero visual change) |
| Skeleton | 🔧 | `ui/Skeleton.vue` | pre-existing; added a `table-row` variant (additive) |
| Spinner | 🔧 | `ui/Spinner.vue` | pre-existing; added `lg` size and `on-accent` variant (additive — default/unset variant behavior unchanged) |
| PageHeader | 🔧 | `PageHeader.vue` | pre-existing; added a `#status` slot for an inline `StatusBadge` next to the title (additive, empty when unused) |
| FilterBar | ✅ | `ui/FilterBar.vue` | pre-existing, matches spec's anatomy; the "chip row when >2 filters active" enhancement is not built (low priority, deferred) |
| SearchInput | 🆕 | `ui/SearchInput.vue` | debounced (300ms default), clear button, searching state |
| KeyValueTable | 🆕 | `ui/KeyValueTable.vue` | formalizes the `.kv` global class — used ad hoc by ~15 legacy detail pages (§`UI_AUDIT_M27.md`) |
| Avatar | 🆕 | `ui/Avatar.vue` | image or initial, sm/md/lg |
| KeyboardShortcutHint | 🆕 | `ui/KeyboardShortcutHint.vue` | `<kbd>`-styled shortcut chip |
| MediaGrid | ✅ | `ui/MediaGrid.vue` | pre-existing, drag-and-drop dropzone + compact grid — the reference drag/drop pattern |
| MoneyInput | ✅ | `ui/MoneyInput.vue` | pre-existing |
| EntitySearchSelect | ✅ | `ui/EntitySearchSelect.vue` | pre-existing — chips + searchable dropdown; functionally covers most of what a `Combobox` multi-select would need (see §9) |
| SaveStateIndicator | ✅ | `ui/SaveStateIndicator.vue` | pre-existing, Product-editor-specific autosave pill |
| VariantTable | ✅ | `ui/VariantTable.vue` | pre-existing — proved out the density/sticky-header/tabular-nums pattern `DataTable` generalizes; stays as-is (Product-specific columns), not replaced by `DataTable` |
| VariantDetailDrawer | ✅ | `ui/VariantDetailDrawer.vue` | pre-existing, Product-specific `Drawer` consumer |
| Combobox | 🕗 | — | deferred — see §9 |
| DatePicker | 🕗 | — | deferred — see §9 |
| `.kv` global class (`app.vue`) | 🗑 | — | superseded by `KeyValueTable`; not removed (≈15 pages still depend on it — removal is a migration-phase task, not this milestone) |
| `section` global class (`app.vue`) | 🗑 | — | superseded by `Card`; not removed (≈40 pages still depend on it) |
| `button[type=submit]` global reset (`app.vue`) | 🗑 | — | superseded by `Button`; not removed (every un-migrated page's primary action depends on it) |
| Per-domain hand-rolled status pills | 🗑 duplicate | 7+ files, see `UI_AUDIT_M27.md` §1 | superseded by `StatusBadge` + a per-domain bucket map (the `ProductStatusBadge` pattern) |
| `block-library/index.vue` ↔ `section-library/index.vue` | 🗑 duplicate | near-byte-identical pages | candidate for one shared `PresetLibraryGrid`-style component in a later phase |

No component was removed this milestone — see §11 for why.

## 7. Table rules (DataTable)

- Two density modes: `compact` (default, 40px rows) and `comfortable` (48px rows, opt-in for low-cardinality/high-stakes views).
- `overflow-x: auto` wrapper is **mandatory**, not optional.
- Sortable columns are 3-state (asc → desc → none), never a forced 2-state toggle — "default API order" is a legitimate state to return to.
- Selection: header `Checkbox` (indeterminate when some-but-not-all rows selected) + per-row `Checkbox`; selecting ≥1 row swaps the region above the table for a bulk-action bar (`#bulk-actions` slot) showing the selection count.
- States are mutually exclusive and handled internally: `loading` (per-cell `Skeleton` rows, not a spinner overlay — keeps the column structure visible), `error` (`Alert` variant="danger" with a retry action), empty (`EmptyState`, with an optional `#empty-action` slot), populated (normal rows).
- Numeric columns (`align: 'right'`) get `font-variant-numeric: tabular-nums`.
- Row-level actions render via the `#row-actions` slot; cell content is fully customizable via `#cell-{key}` scoped slots (falls back to the raw field value).

## 8. Form rules

- Every field goes through `FormField` (directly, or via `Input`/`Textarea`/`Select` which wrap it internally): label, optional `*` required-marker, help text OR error text (error takes precedence, both never show at once), consistent `--space-1` gap between control and its message.
- Errors set `aria-invalid="true"` on the control and `aria-describedby` pointing at the error's id — never a page-level-only error message with no field attribution.
- Disabled fields get `background: var(--color-surface-muted)` (visually distinct from read-only) plus the global `[disabled]` rule (`opacity: var(--disabled-opacity); cursor: not-allowed; pointer-events: none`).
- Placeholder text is never the only label — `FormField`'s `label` prop is required for anything the user must fill in; placeholder-only inputs are a Critical audit finding this closes, not a pattern to repeat.

## 9. Build-vs-buy: Combobox and DatePicker (deferred, deliberately)

Both have substantial APG accessibility contracts (full combobox pattern with `aria-activedescendant`/typeahead/`aria-live` result counts; full date-grid pattern with arrow-key day navigation). [`DESIGN_SYSTEM.md` §14.6](./DESIGN_SYSTEM.md#14-frontend-token-implementation-strategy-nuxt) already flagged these as the two components most likely to ship subtly-broken keyboard/AT behavior if hand-built under time pressure, and recommended resolving build-vs-buy (a small headless a11y dependency, e.g. Floating UI for positioning) as a **deliberate decision before either is consumed**, not a default.

This milestone did not make that decision — building either one without it would be exactly the "fake completion" risk this system exists to prevent. In the meantime:

- **Combobox's multi-select use case is already covered** by `EntitySearchSelect.vue` (chips + searchable dropdown, proven on Products' Collections field) for anywhere a multi-select doesn't need full ARIA-combobox typeahead semantics.
- **DatePicker's single-date case** should fall back to a native `<input type="date">` (already themed via the global `input` reset) until the range/preset variant is actually needed by a specific page — no page in the current 72-route audit requires date-range selection today.

## 10. Motion, accessibility, dark/light — summary

Full detail in [`DESIGN_SYSTEM.md` §9-11](./DESIGN_SYSTEM.md). As implemented in `tokens.css`/`app.vue` this milestone:

- `--duration-fast` (100ms, hover/toggle) / `--duration-base` (150ms, `--transition-fast` alias) / `--duration-slow` (220ms, modal/drawer/dropdown) with `--ease-standard`/`-decelerate`/`-accelerate`; a root `prefers-reduced-motion: reduce` rule collapses every animation/transition to near-zero globally, not per-component.
- Focus ring generalized from `:is(button,a,input,select,textarea):focus-visible` to also cover `[tabindex]` (custom interactive elements — Dropdown items, Tabs, DataTable rows in select mode).
- `[disabled]`/`[aria-disabled='true']` generalized globally (`opacity: var(--disabled-opacity); cursor: not-allowed; pointer-events: none`) — previously only `button[type=submit]:disabled` had any rule at all.
- Both themes (`data-theme='light'|'dark'`, or system preference when unset) are driven entirely by semantic tokens; no component may reference a primitive or raw hex directly.

## 11. Migration roadmap

**No page listed in [`UI_AUDIT_M27.md`](./UI_AUDIT_M27.md) was migrated this milestone** — Products remains the only reference implementation, per the milestone brief. This is the prioritized plan for actually doing it, phase by phase. Each phase is independently shippable and independently reviewable; phases are ordered by (a) how broken the current UI is per the audit and (b) how many pages a fix unblocks at once.

**Phase 0 — foundation (no visible change, unblocks everything else):**
Nothing further needed — tokens/global CSS extensions and the component library itself shipped this milestone are Phase 0. Every phase below builds on it.

**Phase 1 — highest audit severity, highest shared-component leverage:**
- Orders (`index.vue` + `[id].vue`) — worst offender: 15+ raw tables, ~10 unbadged status fields on one page. Migrate to `DataTable` + a new `orderStatusMap`/`paymentStatusMap`/`fulfillmentStatusMap` feeding `StatusBadge` (the `ProductStatusBadge` pattern, one new tiny wrapper per status field).
- Fulfillments, Shipments, Returns, Refunds — migrate the 4x-duplicated `.status-${x}` pattern to `StatusBadge` together (they share the same duplicated CSS today, so they should share the same fix); replace the 3x-duplicated `button.danger` with `Button variant="danger"`.
- **Regression risk:** these are the three pages [`DESIGN_SYSTEM.md` §15](./DESIGN_SYSTEM.md#15-migration-risks) already flagged — the status colors these pages *should* show were never actually correct anywhere, so this migration is a visible fix, and QA must confirm the newly-visible colors match the intended semantic bucket per status value, not just "looks different."

**Phase 2 — Customers + the automation/notifications/search/analytics hex-badge cluster:**
- Customers (`index.vue` + `[id].vue`) — replace hand-rolled `FilterBar`/`Pagination` equivalents with the real components; fix hex-fallback badges.
- Automation, Notifications, Search, Analytics/Reports — the 7-file hardcoded-hex `StatusBadge` duplication (`UI_AUDIT_M27.md` §1) is the single most repeated defect in the app; fixing it once per domain (not per file) closes all 7 at once.

**Phase 3 — Inventory + Payments + the `.builder`/`.kv` cluster:**
- Inventory — adopt `DataTable` + low-stock coloring (the `VariantTable`/`.num.ok/.low/.out` pattern already proven on Products).
- Payments — first real `StatusBadge` usage (currently has none, not even hand-rolled).
- Russian Commerce, remaining Search/Notifications settings forms — collapse the 6+ file `.builder`/`.grid`/`.hint` duplication into `Card` + `FormField`/`Input`/`Select`.
- Every `.kv`-using detail page (Orders/Customers already covered by Phase 1/2; remaining: Menus, Pages, Apps, Themes) — swap to `KeyValueTable`.

**Phase 4 — remainder + cleanup:**
- Collections, Blogs, Authors, Menus, Redirects, Page Templates, Locations, Stores, Apps, Themes/Theme Customizer, Promotions, Shipping Zones/Methods, Block/Section Library.
- Theme Customizer's asset picker → real `Modal`/`Drawer` (currently no focus trap/Escape — an actual a11y regression relative to the rest of the app, not just a styling gap).
- Merge `block-library/index.vue` and `section-library/index.vue` into one shared component (`UI_AUDIT_M27.md` §6).
- Replace every remaining `confirm()` call (7+ files) with `Modal variant="danger"` — per-file copy rewrite, not a mechanical batch ([`DESIGN_SYSTEM.md` §15](./DESIGN_SYSTEM.md#15-migration-risks)).
- Fix the invalid `var(--text-md)` reference in Block/Section Library.

**Not scheduled — needs its own decision first:** Combobox/DatePicker build-vs-buy (§9); StoreSwitcher/UserMenu migration onto `Dropdown`+`useClickOutside` (currently still hand-rolled, functionally fine, low urgency since they're not audit-flagged as broken — just duplicated logic, not duplicated *bugs*).

## 12. Living reference

`/design-system` (`apps/admin/app/pages/design-system.vue`) — every component above with its documented states, one screen. Not in the sidebar nav (internal developer tool, not a merchant-facing feature). Update it whenever a component's props/states change; it is the fastest way to visually catch a token-drift regression across the whole set, which is otherwise a real risk when this many components share one token file (see [`DESIGN_SYSTEM.md` §15](./DESIGN_SYSTEM.md#15-migration-risks) on the lack of visual regression tooling — still true, still unaddressed, still a process gap for whoever picks up Phase 1).

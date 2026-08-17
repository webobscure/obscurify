# Milestone 27 — Legacy UI Audit

Scope: every route under `apps/admin/app/pages/` **except** `products/[id].vue`, `products/index.vue`, `login.vue`, `register.vue` — 72 files. Reference baseline: the Product module + `apps/admin/app/assets/css/tokens.css` + the shared components under `apps/admin/app/components/`.

This is a **read-only findings report** — no page listed below was modified as part of this audit. It feeds the migration roadmap in [`ADMIN_DESIGN_SYSTEM.md`](./ADMIN_DESIGN_SYSTEM.md#migration-roadmap).

## Headline finding

None of the 72 audited pages use `StatusBadge.vue`, `ProductStatusBadge.vue`, `EmptyState.vue`, `Modal.vue`, `Drawer.vue`, `FilterBar.vue`, `Pagination.vue`, `MediaGrid.vue`, `Skeleton.vue`, or `VariantTable.vue` — those were exclusive to the Product module before this milestone. Every other page still uses the pre-design-system pattern: raw `<table>`, hand-rolled status pill CSS (often hardcoded hex), hand-rolled `<p>No X yet</p>` empty states, and native inputs/buttons styled only by the global `app.vue` reset.

`PageHeader` is the one component already rolled out everywhere (71/72 files, only the `/` redirect stub doesn't use it) and is fully compliant.

## Systemic patterns (appear across many files, not one-off)

1. **Hardcoded-hex status badges, duplicated 7+ times.** `automation/index.vue`, `automation/[id].vue`, `automation/executions.vue`, `automation/executions/[id].vue`, `notifications/index.vue`, `notifications/[id].vue`, `notifications/deliveries.vue`, `search/index.vue`, `search/rules.vue`, `analytics/reports/index.vue`, `analytics/reports/[id].vue` each independently redeclare a `.badge.{status}` block with literal hex colors (`#e6f4ea`, `#fdeaea`, `#fdf3e0`, `#f3f3f3`...) instead of `--color-success-bg`/`--color-danger-bg`/`--color-warning-bg`/`--color-surface-muted`, despite those tokens existing. This is the exact problem `StatusBadge` (already built, already proven on Products) solves.
2. **`<span class="status" :class="`status-${x}`">` with no color at all**, duplicated across `fulfillments/index.vue`, `fulfillments/[id].vue`, `shipments/index.vue`, `shipments/[id].vue`, `returns/index.vue`, `returns/[id].vue`, `refunds/index.vue`, `refunds/[id].vue` — four independent copies of the identical block, `text-transform: capitalize` only, no bucket mapping.
3. **`button.danger` reimplemented verbatim 3 times** (`fulfillments/[id].vue`, `returns/[id].vue`, `refunds/[id].vue`) — exactly what `Button` variant="danger" now covers.
4. **The `.kv` global convention** (key/value detail table) used by ~15 detail pages (orders, customers, fulfillments, shipments, payments, refunds, returns, menus, pages, apps, themes, russian-commerce receipts) with no backing component — now covered by `KeyValueTable.vue`.
5. **A `.builder`/`.grid`/`.hint` card+form CSS block copy-pasted near-verbatim across 6+ files**: `search/*` (6 files), `notifications/index.vue`, `notifications/providers.vue`, `notifications/templates.vue`, `promotions/index.vue`, `russian-commerce/*` (6 files), `blogs/*`, `menus/*`, `authors/index.vue`, `pages/*`, `locations/index.vue` — the same hand-rolled `label { display:flex column }` + `input,select,textarea { padding/border/radius }` pattern, now covered by `FormField` + `Input`/`Textarea`/`Select`.
6. **`block-library/index.vue` and `section-library/index.vue` are near-byte-identical** (same grid/card/`pre{}` CSS, same structure, only the API endpoint differs) — a textbook case for one shared component instead of two copies.
7. **Native `confirm()`** used for delete confirmation in `customer-groups`, `customer-segments`, `customer-tags`, `redirects`, `notifications`, `search`, `analytics` — 7+ files — instead of `Modal` variant="danger".
8. **Bare, unclassed `<button type="button">`** for every non-submit action (Delete/Cancel/Enable/Disable/status toggles) across effectively every audited file — the global `app.vue` reset only styles `button[type=submit]`, so these get zero styling beyond `font-family: inherit`, which is why so many pages hand-roll their own `.link`/`.remove`/`.status-toggle` class instead. `Button`/`IconButton` close this gap.
9. **Invalid token reference**: `block-library/index.vue` and `section-library/index.vue` use `font-size: var(--text-md)` — not a token that exists (`--text-sm`/`--text-base` are the neighbors); currently resolves to nothing.
10. **Hex fallback pattern** (`var(--color-danger, #c00)`, `var(--color-surface-muted, #f0f0f0)`) in `customers/[id].vue`, `customer-groups/[id].vue`, `customer-segments/[id].vue`, `customer-tags/index.vue`, `fiscalization-settings.vue` — not the documented "sits on uploaded photo" exception, just a hardcoded value masquerading as a fallback.

## Per-area findings

- **Orders** (`orders/index.vue`, `orders/[id].vue`): raw tables (15+ on the detail page alone), zero `StatusBadge` despite ~10 distinct status fields on the order detail page, 9+ hand-rolled empty states, magic-number spacing (`gap: 3rem`, `width: 5rem`).
- **Customers** (`customers/index.vue`, `customers/[id].vue`): hand-rolled `FilterBar`-equivalent and `Pagination`-equivalent instead of reusing either; hex-fallback badges.
- **Inventory**: raw table, no low-stock visual cue at all despite `VariantTable`'s `.num.ok/.low/.out` pattern already solving exactly this on Products.
- **Fulfillments / Shipments / Returns / Refunds**: the `.status-${x}` duplication (#2 above) plus `button.danger` duplication (#3); `shipments/[id].vue` has 12+ entirely unstyled dev-only buttons.
- **Payments**: no status badge at all (not even hand-rolled).
- **Collections / Blogs / Authors / Menus / Pages / Redirects / Page Templates / Locations**: the `.grid`/`.hint` duplication (#5); several "inline-edit table row" patterns (`authors/index.vue`, `redirects/index.vue`, `page-templates/index.vue`) copy-pasted rather than shared.
- **Block Library / Section Library**: near-duplicate files (#6).
- **Builder** (`builder/pages/[id].vue`): the most complex non-Products page; correctly reuses `BuilderBlockTree`/`BuilderPresetPicker`/`BuilderSectionPreview` (compliant), but `BuilderPresetPicker` renders an ad hoc inline panel instead of `Modal`/`Drawer` (no focus trap, no Escape).
- **Stores**: zero scoped CSS — 100% dependent on the global `table`/`button[type=submit]` reset, the most "legacy" page structurally.
- **Customer Groups / Segments / Tags**: correctly reuse `SegmentRuleBuilder` (compliant); `confirm()` instead of `Modal` (#7); hex-fallback danger buttons (#10).
- **Promotions / Shipping Zones / Shipping Methods**: a `<button class="status-toggle">` doubling as both a button and a status pill — neither a real `Button` nor a real `StatusBadge`.
- **Apps**: a locally re-declared `table{}`/`th,td{}` block duplicating the global reset almost exactly; a hand-rolled alert box with no `Alert`/`Banner` to reuse.
- **Themes / Theme Customizer**: `theme-customizer`'s asset picker is a hand-rolled modal-like panel with no focus trap/Escape/`Teleport` — a real Modal-pattern violation, not just a styling gap.
- **Automation / Notifications / Search / Analytics / Russian Commerce**: the hex-badge duplication (#1) and `.builder` form-card duplication (#5) are concentrated here.

## What's already compliant

- `PageHeader` — 71/72 pages.
- `SegmentRuleBuilder` — `customer-groups/[id].vue`, `customer-segments/[id].vue`.
- `WorkflowConditionBuilder` / `WorkflowActionBuilder` — `automation/[id].vue`.
- `MenuItemTree` — `menus/[id].vue`.
- `BuilderBlockTree` / `BuilderPresetPicker` / `BuilderSectionPreview` — `builder/pages/[id].vue`.
- `AnalyticsWidget` — `analytics/index.vue` (though the widget's own chart palette is 10 raw hex values, not tokens — flagged for that component's own future pass).
- Shell chrome (`AdminShell`, `AdminSidebar`, `AdminTopbar`, `AdminNavigation`, `SidebarSection`, `AdminBreadcrumbs`, `StoreSwitcher`, `UserMenu`, `LanguageSwitcher`, `GlobalSearch`, `AppIcon`) — consistent everywhere, not page-specific drift.

See [`ADMIN_DESIGN_SYSTEM.md`](./ADMIN_DESIGN_SYSTEM.md) for the component catalog these findings map onto and the phased migration roadmap.

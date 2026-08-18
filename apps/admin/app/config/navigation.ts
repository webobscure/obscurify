/**
 * Single source of truth for sidebar navigation — see AdminNavigation.vue
 * and SidebarSection.vue. Every `to` here must be a route that actually
 * exists under apps/admin/app/pages; do not add an entry for a feature
 * that has no page yet (spec: "do not add fake pages" / "do not invent
 * missing backend features").
 *
 * Milestone 27 (Navigation Refactor): the admin moved from one flat,
 * ~20-item sidebar to a "daily work + settings" model (approved proposal:
 * docs/design/ADMIN_DESIGN_SYSTEM.md's navigation-refactor note). Two
 * separate trees now exist:
 *
 * - `primaryNavigation` — the daily sidebar (AdminSidebar.vue). Capped at
 *   ~10 top-level entries: screens a merchant opens most days. Records
 *   that "hang off" a daily module the same way Locations hung off
 *   Inventory pre-refactor (Fulfillments/Shipments/Returns/Refunds/
 *   Payments off Orders, Inventory off Products) nest as children.
 * - `settingsNavigation` — the Settings workspace (SettingsSidebar.vue,
 *   layouts/settings.vue). Configured-once, rarely-touched surfaces:
 *   store/location setup, shipping rate config, notification templates,
 *   search tuning, SEO redirects, Russian Commerce compliance. Grouped as
 *   flat `NavigationSection`s (a labeled group of sibling items, the same
 *   shape `primaryNavigation`'s own unlabeled/labeled sections already
 *   use) rather than accordion branches — Settings sections stay visible
 *   together for orientation, matching the Shopify reference IA the
 *   proposal named, rather than collapsing like the daily sidebar's
 *   per-module branches.
 *
 * No route moved as part of this refactor — every `to` below is
 * byte-identical to its pre-refactor value. Only which tree an item
 * belongs to, and which parent it nests under, changed. Pages that moved
 * into `settingsNavigation` render through `layouts/settings.vue` via
 * `definePageMeta({ layout: 'settings' })` on the page itself — a layout
 * swap, not a route change.
 *
 * `activePattern` decides which item stays highlighted on a nested route
 * (e.g. /products/{id} keeps "Products" active) — a simple path-prefix
 * check in isNavItemActive() below, not a literal string match.
 *
 * `labelKey` is a Vue I18n key (`i18n/locales/*.json`), not display text —
 * every consumer must render it through `$t()`.
 */
export interface NavigationItem {
  labelKey: string
  to: string
  icon: string
  /** Defaults to an exact match for '/', a prefix match otherwise. */
  activePattern?: string
  children?: NavigationItem[]
}

export interface NavigationSection {
  labelKey?: string
  items: NavigationItem[]
}

export const primaryNavigation: NavigationSection[] = [
  {
    labelKey: 'nav.commerce',
    items: [
      {
        labelKey: 'nav.orders',
        to: '/orders',
        icon: 'orders',
        children: [
          // All five hang off Orders for the same reason Locations hung
          // off Inventory pre-refactor: they're records/operations a
          // merchant checks daily but that only make sense in an order's
          // context, not independent modules in their own right.
          { labelKey: 'nav.fulfillments', to: '/fulfillments', icon: 'fulfillment' },
          { labelKey: 'nav.shipments', to: '/shipments', icon: 'shipping' },
          { labelKey: 'nav.returns', to: '/returns', icon: 'redirects' },
          { labelKey: 'nav.refunds', to: '/refunds', icon: 'payments' },
          { labelKey: 'nav.payments', to: '/payments', icon: 'payments' },
          // Fiscal Receipts is the *operation* (records of receipts already
          // issued) — distinct from Legal/Tax/Fiscalization/Payment Methods
          // in settingsNavigation below, which are the compliance
          // *configuration* those receipts get generated from. Same
          // operations-vs-configuration split Milestone 27b's brief asked
          // for explicitly (§15): don't move a whole domain just because
          // its name matches a Settings module.
          { labelKey: 'nav.fiscal_receipts', to: '/russian-commerce/fiscal-receipts', icon: 'russian-commerce' },
        ],
      },
      {
        labelKey: 'nav.products',
        to: '/products',
        icon: 'products',
        children: [
          // Inventory nests under Products the same way Shopify's own
          // real IA does (Products > Inventory) — stock levels are a
          // property of the catalog, not a standalone daily module.
          { labelKey: 'nav.inventory', to: '/inventory', icon: 'inventory' },
        ],
      },
      { labelKey: 'nav.collections', to: '/collections', icon: 'collections' },
      {
        labelKey: 'nav.customers',
        to: '/customers',
        icon: 'customers',
        children: [
          { labelKey: 'nav.customer_groups', to: '/customer-groups', icon: 'customers' },
          { labelKey: 'nav.customer_segments', to: '/customer-segments', icon: 'customers' },
          { labelKey: 'nav.customer_tags', to: '/customer-tags', icon: 'customers' },
        ],
      },
    ],
  },
  {
    // No section label here (matches the pre-refactor unlabeled 3rd
    // section) — the "Content" branch item below is itself the only
    // thing in this group, so a group header of the same name would sit
    // directly on top of an identically-labeled clickable row.
    items: [
      {
        // Content merges the pre-refactor Pages/Blogs/Menus top-level
        // items into one branch (the same "hangs off its parent feature"
        // relationship each already had to its own children) — this is
        // the one place this refactor asked to consolidate multiple
        // former top-level items into a single daily entry.
        labelKey: 'nav.content',
        to: '/pages',
        icon: 'pages',
        children: [
          { labelKey: 'nav.pages', to: '/pages', icon: 'pages' },
          { labelKey: 'nav.page_templates', to: '/page-templates', icon: 'pages' },
          { labelKey: 'nav.blogs', to: '/blogs', icon: 'blogs' },
          { labelKey: 'nav.authors', to: '/authors', icon: 'authors' },
          { labelKey: 'nav.menus', to: '/menus', icon: 'menus' },
        ],
      },
      {
        labelKey: 'nav.themes',
        to: '/themes',
        icon: 'themes',
        children: [
          { labelKey: 'nav.theme_customizer', to: '/theme-customizer', icon: 'themes' },
          { labelKey: 'nav.section_library', to: '/section-library', icon: 'themes' },
          { labelKey: 'nav.block_library', to: '/block-library', icon: 'themes' },
        ],
      },
    ],
  },
  {
    items: [
      {
        // "Marketing" is a relabel of the pre-refactor "Promotions" entry
        // (approved: rename now, room to add Discounts/Gift Cards under
        // it later without another nav restructure) — /promotions is
        // still the one real page behind it today.
        labelKey: 'nav.marketing',
        to: '/promotions',
        icon: 'promotions',
      },
      {
        labelKey: 'nav.analytics',
        to: '/analytics',
        icon: 'analytics',
        children: [
          { labelKey: 'nav.reports', to: '/analytics/reports', icon: 'analytics' },
          { labelKey: 'nav.saved_reports', to: '/analytics/saved-reports', icon: 'analytics' },
          // Search Analytics is a report (operational — "how did search
          // perform"), not search configuration; the config surfaces
          // (Synonyms/Rules/Pinned/Settings) stay in settingsNavigation.
          { labelKey: 'nav.search_analytics', to: '/search/analytics', icon: 'search' },
        ],
      },
      {
        labelKey: 'nav.automation',
        to: '/automation',
        icon: 'automation',
        children: [
          { labelKey: 'nav.executions', to: '/automation/executions', icon: 'automation' },
          { labelKey: 'nav.templates', to: '/automation/templates', icon: 'automation' },
        ],
      },
      { labelKey: 'nav.apps', to: '/apps', icon: 'apps' },
    ],
  },
]

/**
 * The Settings workspace's own left-nav (SettingsSidebar.vue) — grouped,
 * flat sections rather than accordion branches (see the module docblock
 * above). Every route here is unchanged from its pre-refactor value; only
 * `primaryNavigation` stopped linking to it and this tree started.
 */
export const settingsNavigation: NavigationSection[] = [
  {
    labelKey: 'nav.settings_store',
    items: [
      { labelKey: 'nav.stores', to: '/stores', icon: 'stores' },
      { labelKey: 'nav.locations', to: '/locations', icon: 'locations' },
    ],
  },
  {
    labelKey: 'nav.settings_shipping',
    items: [
      { labelKey: 'nav.shipping_methods', to: '/shipping-methods', icon: 'shipping' },
      { labelKey: 'nav.shipping_zones', to: '/shipping-zones', icon: 'shipping' },
    ],
  },
  {
    labelKey: 'nav.settings_notifications',
    items: [
      { labelKey: 'nav.notifications', to: '/notifications', icon: 'notifications' },
      { labelKey: 'nav.templates', to: '/notifications/templates', icon: 'notifications' },
      { labelKey: 'nav.channels', to: '/notifications/channels', icon: 'notifications' },
      { labelKey: 'nav.providers', to: '/notifications/providers', icon: 'notifications' },
      { labelKey: 'nav.delivery_log', to: '/notifications/deliveries', icon: 'notifications' },
    ],
  },
  {
    labelKey: 'nav.settings_search',
    items: [
      { labelKey: 'nav.search', to: '/search', icon: 'search' },
      { labelKey: 'nav.synonyms', to: '/search/synonyms', icon: 'search' },
      { labelKey: 'nav.rules_ranking', to: '/search/rules', icon: 'search' },
      { labelKey: 'nav.pinned_products', to: '/search/pinned', icon: 'search' },
      { labelKey: 'nav.search_settings', to: '/search/settings', icon: 'search' },
    ],
  },
  {
    labelKey: 'nav.settings_seo',
    items: [
      { labelKey: 'nav.redirects', to: '/redirects', icon: 'redirects' },
    ],
  },
  {
    labelKey: 'nav.settings_russian_commerce',
    items: [
      { labelKey: 'nav.legal_details', to: '/russian-commerce/legal-profile', icon: 'russian-commerce' },
      { labelKey: 'nav.tax_vat_settings', to: '/russian-commerce/tax-settings', icon: 'russian-commerce' },
      { labelKey: 'nav.fiscalization_settings', to: '/russian-commerce/fiscalization-settings', icon: 'russian-commerce' },
      { labelKey: 'nav.payment_methods', to: '/russian-commerce/payment-methods', icon: 'russian-commerce' },
      // Fiscal Receipts itself moved to primaryNavigation (Orders) — it's
      // the operational record, not compliance configuration. See the
      // comment at its new location.
    ],
  },
]

/**
 * Bottom-of-sidebar entry point into the Settings workspace — the only
 * item left in the daily sidebar's secondary slot post-refactor. /stores
 * (the pre-refactor occupant of this slot) moved into
 * `settingsNavigation` above; Settings itself is the new secondary item.
 */
export const secondaryNavigation: NavigationSection = {
  items: [
    { labelKey: 'nav.settings', to: '/settings', icon: 'settings', activePattern: '/settings' },
  ],
}

export function isNavItemActive(currentPath: string, item: NavigationItem): boolean {
  const pattern = item.activePattern ?? item.to

  if (pattern === '/') {
    return currentPath === '/'
  }

  return currentPath === pattern || currentPath.startsWith(`${pattern}/`)
}

/**
 * True when `item` is a collapsible branch (has `children`) and either it
 * or one of its children is the active route — i.e. the branch that should
 * be auto-expanded and shown as "parent active" in the sidebar. Pure and
 * route-driven (no component state) so it's shared, unduplicated logic
 * between the accordion composable and its tests.
 */
export function isBranchActive(currentPath: string, item: NavigationItem): boolean {
  if (!item.children?.length) return false
  return isNavItemActive(currentPath, item) || item.children.some(child => isNavItemActive(currentPath, child))
}

/** First branch among `items` that contains the active route, if any. */
export function findActiveBranch(currentPath: string, items: NavigationItem[]): NavigationItem | undefined {
  return items.find(item => isBranchActive(currentPath, item))
}

/**
 * Flat list of every navigable item, own and nested — used by
 * GlobalSearch, which searches over real routes regardless of how deep
 * they are nested in the sidebar tree, or which tree (daily/Settings)
 * they live in.
 */
export function flattenNavigationItems(sections: NavigationSection[]): NavigationItem[] {
  return sections.flatMap(section =>
    section.items.flatMap(item => [item, ...(item.children ?? [])]),
  )
}

/**
 * True when `currentPath` matches any route (own or nested) inside
 * `sections` — used by AdminSidebar to keep the bottom "Settings" entry
 * visually active for every route in `settingsNavigation`, not just the
 * literal `/settings` redirect page itself (spec §7: "Settings remains
 * active in the main sidebar" for the whole time the user is inside the
 * workspace, e.g. on `/notifications` or `/russian-commerce/tax-settings`).
 */
export function isRouteInSection(currentPath: string, sections: NavigationSection[]): boolean {
  return flattenNavigationItems(sections).some(item => isNavItemActive(currentPath, item))
}

/**
 * The settingsNavigation section (group) that owns `currentPath`, if any —
 * the single source SettingsShell's "Settings / {Section}" breadcrumb
 * reads from (spec §6/§13: breadcrumbs must be derived from the same
 * navigation metadata as the sidebar, never a second hardcoded copy).
 */
export function findSettingsSection(currentPath: string): NavigationSection | undefined {
  return settingsNavigation.find(section => section.items.some(item => isNavItemActive(currentPath, item)))
}

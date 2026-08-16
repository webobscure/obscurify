/**
 * Single source of truth for sidebar navigation — see SidebarNavigation.vue
 * and SidebarSection.vue. Every `to` here must be a route that actually
 * exists under apps/admin/app/pages; do not add an entry for a feature
 * that has no page yet (spec: "do not add fake pages" / "do not invent
 * missing backend features"). No Storefront/Categories/Settings/Overview
 * sections — none of those have a real admin page in this app; "/" itself
 * is just a redirect to /stores, not real content. Customers became real
 * in Milestone 16 (read-only list + detail) and is listed below.
 *
 * `activePattern` decides which item stays highlighted on a nested route
 * (e.g. /products/{id} keeps "Products" active) — a simple path-prefix
 * check in isNavItemActive() below, not a literal string match.
 *
 * `children` is genuine nested-item support (spec section 2), not an
 * unused capability: Locations is nested under Inventory because it
 * exists specifically to support inventory tracking, a real information-
 * architecture choice rather than an arbitrary one. Always rendered
 * expanded — a collapse/expand interaction is future scope (see
 * SidebarSection.vue). Customer Groups/Segments/Tags (Milestone 18)
 * nest under Customers for the identical reason — they exist only to
 * segment customers, the same relationship Locations has to Inventory.
 *
 * `labelKey` (Milestone 26) is a Vue I18n key (`i18n/locales/*.json`),
 * not display text — every consumer must render it through `$t()`.
 * Renamed from the pre-i18n `label` field specifically so a component
 * that forgets to translate renders an obviously-wrong raw key like
 * "nav.orders" instead of silently showing English to a Russian user.
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
      { labelKey: 'nav.orders', to: '/orders', icon: 'orders' },
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
      { labelKey: 'nav.fulfillments', to: '/fulfillments', icon: 'fulfillment' },
      { labelKey: 'nav.products', to: '/products', icon: 'products' },
      { labelKey: 'nav.collections', to: '/collections', icon: 'collections' },
      {
        labelKey: 'nav.inventory',
        to: '/inventory',
        icon: 'inventory',
        children: [
          { labelKey: 'nav.locations', to: '/locations', icon: 'locations' },
        ],
      },
    ],
  },
  {
    labelKey: 'nav.content',
    items: [
      {
        labelKey: 'nav.pages',
        to: '/pages',
        icon: 'pages',
        children: [
          // Page Templates are a preset library that exists only to seed a
          // new page's sections — nested for the same reason Locations is
          // nested under Inventory, not as an arbitrary grouping.
          { labelKey: 'nav.page_templates', to: '/page-templates', icon: 'pages' },
        ],
      },
      {
        labelKey: 'nav.blogs',
        to: '/blogs',
        icon: 'blogs',
        children: [
          // Authors exist only to attribute blog posts; they have no
          // meaning outside a blog, so they hang off it.
          { labelKey: 'nav.authors', to: '/authors', icon: 'authors' },
        ],
      },
      { labelKey: 'nav.menus', to: '/menus', icon: 'menus' },
      { labelKey: 'nav.redirects', to: '/redirects', icon: 'redirects' },
    ],
  },
  {
    items: [
      { labelKey: 'nav.payments', to: '/payments', icon: 'payments' },
      { labelKey: 'nav.promotions', to: '/promotions', icon: 'promotions' },
      {
        labelKey: 'nav.analytics',
        to: '/analytics',
        icon: 'analytics',
        children: [
          // Reports/Saved Reports are both views onto the analytics
          // pipeline's report builder — the same "hangs off its parent
          // feature" relationship Executions/Templates has to Automation.
          { labelKey: 'nav.reports', to: '/analytics/reports', icon: 'analytics' },
          { labelKey: 'nav.saved_reports', to: '/analytics/saved-reports', icon: 'analytics' },
        ],
      },
      {
        labelKey: 'nav.notifications',
        to: '/notifications',
        icon: 'notifications',
        children: [
          // Templates/Channels/Providers/Delivery Log are all
          // configuration or read surfaces of the Notification Center
          // itself — the same "hangs off its parent feature"
          // relationship Reports/Saved Reports has to Analytics.
          { labelKey: 'nav.templates', to: '/notifications/templates', icon: 'notifications' },
          { labelKey: 'nav.channels', to: '/notifications/channels', icon: 'notifications' },
          { labelKey: 'nav.providers', to: '/notifications/providers', icon: 'notifications' },
          { labelKey: 'nav.delivery_log', to: '/notifications/deliveries', icon: 'notifications' },
        ],
      },
      {
        labelKey: 'nav.search',
        to: '/search',
        icon: 'search',
        children: [
          // Synonyms/Rules/Pinned Products/Settings/Analytics are all
          // configuration or read surfaces of the Search & Discovery
          // Platform itself — the same "hangs off its parent feature"
          // relationship Templates/Channels/Providers has to
          // Notifications. Ranking has no distinct page: it's the same
          // Rules resource sorted by position (see Rules.vue), and
          // Reindex lives on the Dashboard itself.
          { labelKey: 'nav.synonyms', to: '/search/synonyms', icon: 'search' },
          { labelKey: 'nav.rules_ranking', to: '/search/rules', icon: 'search' },
          { labelKey: 'nav.pinned_products', to: '/search/pinned', icon: 'search' },
          { labelKey: 'nav.search_settings', to: '/search/settings', icon: 'search' },
          { labelKey: 'nav.search_analytics', to: '/search/analytics', icon: 'search' },
        ],
      },
      {
        labelKey: 'nav.automation',
        to: '/automation',
        icon: 'automation',
        children: [
          // Executions/Templates are both views onto workflows — the
          // same "hangs off its parent feature" relationship Locations
          // has to Inventory.
          { labelKey: 'nav.executions', to: '/automation/executions', icon: 'automation' },
          { labelKey: 'nav.templates', to: '/automation/templates', icon: 'automation' },
        ],
      },
      { labelKey: 'nav.apps', to: '/apps', icon: 'apps' },
      {
        labelKey: 'nav.themes',
        to: '/themes',
        icon: 'themes',
        children: [
          // Theme Customizer/Section Library/Block Library are all
          // design-time concerns of the active theme, the same reason
          // Page Templates hangs off Pages rather than sitting flat.
          { labelKey: 'nav.theme_customizer', to: '/theme-customizer', icon: 'themes' },
          { labelKey: 'nav.section_library', to: '/section-library', icon: 'themes' },
          { labelKey: 'nav.block_library', to: '/block-library', icon: 'themes' },
        ],
      },
      {
        labelKey: 'nav.shipping',
        to: '/shipments',
        icon: 'shipping',
        children: [
          { labelKey: 'nav.shipping_methods', to: '/shipping-methods', icon: 'shipping' },
          { labelKey: 'nav.shipping_zones', to: '/shipping-zones', icon: 'shipping' },
        ],
      },
      {
        labelKey: 'nav.russian_commerce',
        to: '/russian-commerce/legal-profile',
        icon: 'russian-commerce',
        children: [
          // Legal Details/Tax-VAT/Fiscalization/Payment Methods are the
          // 4 settings pages spec section 17 asks for; Fiscal Receipts is
          // the read-only list backing the same "fiscalization status"
          // requirement Order/Payment detail pages also surface inline —
          // same "hangs off its parent feature" relationship
          // Templates/Channels/Providers has to Notifications.
          { labelKey: 'nav.legal_details', to: '/russian-commerce/legal-profile', icon: 'russian-commerce' },
          { labelKey: 'nav.tax_vat_settings', to: '/russian-commerce/tax-settings', icon: 'russian-commerce' },
          { labelKey: 'nav.fiscalization_settings', to: '/russian-commerce/fiscalization-settings', icon: 'russian-commerce' },
          { labelKey: 'nav.payment_methods', to: '/russian-commerce/payment-methods', icon: 'russian-commerce' },
          { labelKey: 'nav.fiscal_receipts', to: '/russian-commerce/fiscal-receipts', icon: 'russian-commerce' },
        ],
      },
    ],
  },
]

/**
 * Kept separate from `primaryNavigation` — rendered at the bottom of the
 * sidebar (spec section 2/4: "secondary workspace actions"). /stores is a
 * real page (list/create stores), distinct from the StoreSwitcher's own
 * quick-activate dropdown at the top of the sidebar.
 */
export const secondaryNavigation: NavigationSection = {
  items: [
    { labelKey: 'nav.stores', to: '/stores', icon: 'stores' },
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
 * Flat list of every navigable item, own and nested — used by
 * GlobalSearch, which searches over real routes regardless of how deep
 * they are nested in the sidebar tree.
 */
export function flattenNavigationItems(sections: NavigationSection[]): NavigationItem[] {
  return sections.flatMap(section =>
    section.items.flatMap(item => [item, ...(item.children ?? [])]),
  )
}

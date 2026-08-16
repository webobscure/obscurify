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
 */
export interface NavigationItem {
  label: string
  to: string
  icon: string
  /** Defaults to an exact match for '/', a prefix match otherwise. */
  activePattern?: string
  children?: NavigationItem[]
}

export interface NavigationSection {
  label?: string
  items: NavigationItem[]
}

export const primaryNavigation: NavigationSection[] = [
  {
    label: 'Commerce',
    items: [
      { label: 'Orders', to: '/orders', icon: 'orders' },
      {
        label: 'Customers',
        to: '/customers',
        icon: 'customers',
        children: [
          { label: 'Customer Groups', to: '/customer-groups', icon: 'customers' },
          { label: 'Customer Segments', to: '/customer-segments', icon: 'customers' },
          { label: 'Customer Tags', to: '/customer-tags', icon: 'customers' },
        ],
      },
      { label: 'Fulfillments', to: '/fulfillments', icon: 'fulfillment' },
      { label: 'Products', to: '/products', icon: 'products' },
      { label: 'Collections', to: '/collections', icon: 'collections' },
      {
        label: 'Inventory',
        to: '/inventory',
        icon: 'inventory',
        children: [
          { label: 'Locations', to: '/locations', icon: 'locations' },
        ],
      },
    ],
  },
  {
    label: 'Content',
    items: [
      {
        label: 'Pages',
        to: '/pages',
        icon: 'pages',
        children: [
          // Page Templates are a preset library that exists only to seed a
          // new page's sections — nested for the same reason Locations is
          // nested under Inventory, not as an arbitrary grouping.
          { label: 'Page Templates', to: '/page-templates', icon: 'pages' },
        ],
      },
      {
        label: 'Blogs',
        to: '/blogs',
        icon: 'blogs',
        children: [
          // Authors exist only to attribute blog posts; they have no
          // meaning outside a blog, so they hang off it.
          { label: 'Authors', to: '/authors', icon: 'authors' },
        ],
      },
      { label: 'Menus', to: '/menus', icon: 'menus' },
      { label: 'Redirects', to: '/redirects', icon: 'redirects' },
    ],
  },
  {
    items: [
      { label: 'Payments', to: '/payments', icon: 'payments' },
      { label: 'Promotions', to: '/promotions', icon: 'promotions' },
      {
        label: 'Analytics',
        to: '/analytics',
        icon: 'analytics',
        children: [
          // Reports/Saved Reports are both views onto the analytics
          // pipeline's report builder — the same "hangs off its parent
          // feature" relationship Executions/Templates has to Automation.
          { label: 'Reports', to: '/analytics/reports', icon: 'analytics' },
          { label: 'Saved Reports', to: '/analytics/saved-reports', icon: 'analytics' },
        ],
      },
      {
        label: 'Notifications',
        to: '/notifications',
        icon: 'notifications',
        children: [
          // Templates/Channels/Providers/Delivery Log are all
          // configuration or read surfaces of the Notification Center
          // itself — the same "hangs off its parent feature"
          // relationship Reports/Saved Reports has to Analytics.
          { label: 'Templates', to: '/notifications/templates', icon: 'notifications' },
          { label: 'Channels', to: '/notifications/channels', icon: 'notifications' },
          { label: 'Providers', to: '/notifications/providers', icon: 'notifications' },
          { label: 'Delivery Log', to: '/notifications/deliveries', icon: 'notifications' },
        ],
      },
      {
        label: 'Search',
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
          { label: 'Synonyms', to: '/search/synonyms', icon: 'search' },
          { label: 'Rules & Ranking', to: '/search/rules', icon: 'search' },
          { label: 'Pinned Products', to: '/search/pinned', icon: 'search' },
          { label: 'Search Settings', to: '/search/settings', icon: 'search' },
          { label: 'Search Analytics', to: '/search/analytics', icon: 'search' },
        ],
      },
      {
        label: 'Automation',
        to: '/automation',
        icon: 'automation',
        children: [
          // Executions/Templates are both views onto workflows — the
          // same "hangs off its parent feature" relationship Locations
          // has to Inventory.
          { label: 'Executions', to: '/automation/executions', icon: 'automation' },
          { label: 'Templates', to: '/automation/templates', icon: 'automation' },
        ],
      },
      { label: 'Apps', to: '/apps', icon: 'apps' },
      {
        label: 'Themes',
        to: '/themes',
        icon: 'themes',
        children: [
          // Theme Customizer/Section Library/Block Library are all
          // design-time concerns of the active theme, the same reason
          // Page Templates hangs off Pages rather than sitting flat.
          { label: 'Theme Customizer', to: '/theme-customizer', icon: 'themes' },
          { label: 'Section Library', to: '/section-library', icon: 'themes' },
          { label: 'Block Library', to: '/block-library', icon: 'themes' },
        ],
      },
      {
        label: 'Shipping',
        to: '/shipments',
        icon: 'shipping',
        children: [
          { label: 'Shipping Methods', to: '/shipping-methods', icon: 'shipping' },
          { label: 'Shipping Zones', to: '/shipping-zones', icon: 'shipping' },
        ],
      },
      {
        label: 'Russian Commerce',
        to: '/russian-commerce/legal-profile',
        icon: 'russian-commerce',
        children: [
          // Legal Details/Tax-VAT/Fiscalization/Payment Methods are the
          // 4 settings pages spec section 17 asks for; Fiscal Receipts is
          // the read-only list backing the same "fiscalization status"
          // requirement Order/Payment detail pages also surface inline —
          // same "hangs off its parent feature" relationship
          // Templates/Channels/Providers has to Notifications.
          { label: 'Legal Details', to: '/russian-commerce/legal-profile', icon: 'russian-commerce' },
          { label: 'Tax / VAT Settings', to: '/russian-commerce/tax-settings', icon: 'russian-commerce' },
          { label: 'Fiscalization Settings', to: '/russian-commerce/fiscalization-settings', icon: 'russian-commerce' },
          { label: 'Payment Methods', to: '/russian-commerce/payment-methods', icon: 'russian-commerce' },
          { label: 'Fiscal Receipts', to: '/russian-commerce/fiscal-receipts', icon: 'russian-commerce' },
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
    { label: 'Stores', to: '/stores', icon: 'stores' },
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

/**
 * Single source of truth for sidebar navigation — see SidebarNavigation.vue
 * and SidebarSection.vue. Every `to` here must be a route that actually
 * exists under apps/admin/app/pages; do not add an entry for a feature
 * that has no page yet (spec: "do not add fake pages" / "do not invent
 * missing backend features"). No Customers/Storefront/Categories/
 * Settings/Overview sections — none of those have a real admin page in
 * this app; "/" itself is just a redirect to /stores, not real content.
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
 * SidebarSection.vue).
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
      { label: 'Apps', to: '/apps', icon: 'apps' },
      { label: 'Themes', to: '/themes', icon: 'themes' },
      {
        label: 'Shipping',
        to: '/shipments',
        icon: 'shipping',
        children: [
          { label: 'Shipping Methods', to: '/shipping-methods', icon: 'shipping' },
          { label: 'Shipping Zones', to: '/shipping-zones', icon: 'shipping' },
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

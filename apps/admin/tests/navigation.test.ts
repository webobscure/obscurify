import { describe, expect, it } from 'vitest'
import de from '../i18n/locales/de.json'
import en from '../i18n/locales/en.json'
import ru from '../i18n/locales/ru.json'
import { findActiveBranch, findSettingsSection, flattenNavigationItems, isBranchActive, isNavItemActive, isRouteInSection, primaryNavigation, secondaryNavigation, settingsNavigation } from '../app/config/navigation'

describe('isNavItemActive', () => {
  const products = { labelKey: 'nav.products', to: '/products', icon: 'products' }
  const orders = { labelKey: 'nav.orders', to: '/orders', icon: 'orders' }
  const locations = { labelKey: 'nav.locations', to: '/locations', icon: 'locations' }

  it('matches the exact path', () => {
    expect(isNavItemActive('/products', products)).toBe(true)
  })

  it('keeps Products active on a nested product detail route', () => {
    expect(isNavItemActive('/products/abc123', products)).toBe(true)
  })

  it('keeps Orders active on a nested order detail route', () => {
    expect(isNavItemActive('/orders/abc123', orders)).toBe(true)
  })

  it('does not match an unrelated path that merely shares a prefix', () => {
    expect(isNavItemActive('/productsfoo', products)).toBe(false)
  })

  it('does not cross-match a different section', () => {
    expect(isNavItemActive('/orders/abc123', products)).toBe(false)
  })

  it('matches a nested navigation item (Locations) the same way as a top-level one', () => {
    expect(isNavItemActive('/locations', locations)).toBe(true)
  })
})

describe('isBranchActive / findActiveBranch (sidebar accordion)', () => {
  const orders = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.orders')!
  const customers = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.customers')!
  const collections = { labelKey: 'nav.collections', to: '/collections', icon: 'collections' }

  it('is false for a leaf item (no children)', () => {
    expect(isBranchActive('/collections', collections)).toBe(false)
  })

  it('is true when the branch\'s own route is active', () => {
    expect(isBranchActive('/orders', orders)).toBe(true)
  })

  it('is true when a child route is active, even though the child path is not nested under the parent path', () => {
    expect(isBranchActive('/fulfillments', orders)).toBe(true)
  })

  it('is true on a nested detail route under a child', () => {
    expect(isBranchActive('/customer-groups/abc123', customers)).toBe(true)
  })

  it('is false when neither the branch nor any child matches the route', () => {
    expect(isBranchActive('/collections', orders)).toBe(false)
  })

  it('findActiveBranch picks the one branch among items that contains the current route', () => {
    const commerceItems = primaryNavigation[0]!.items
    expect(findActiveBranch('/fulfillments', commerceItems)).toBe(orders)
    expect(findActiveBranch('/customer-tags', commerceItems)).toBe(customers)
    expect(findActiveBranch('/collections', commerceItems)).toBeUndefined()
  })
})

/**
 * Reads a dot-path key ('nav.orders') out of a locale JSON object —
 * mirrors how Vue I18n itself resolves the same key at runtime.
 */
function resolveKey(bundle: Record<string, unknown>, key: string): unknown {
  return key.split('.').reduce<unknown>((node, segment) => {
    return node && typeof node === 'object' ? (node as Record<string, unknown>)[segment] : undefined
  }, bundle)
}

describe('navigation source of truth', () => {
  const realRoutes = new Set([
    '/orders', '/fulfillments', '/shipments', '/returns', '/refunds', '/payments',
    '/products', '/inventory', '/collections',
    '/customers', '/customer-groups', '/customer-segments', '/customer-tags',
    '/pages', '/page-templates', '/blogs', '/authors', '/menus',
    '/themes', '/theme-customizer', '/section-library', '/block-library',
    '/promotions', '/apps',
    '/analytics', '/analytics/reports', '/analytics/saved-reports',
    '/automation', '/automation/executions', '/automation/templates',
    '/stores', '/locations',
    '/shipping-methods', '/shipping-zones',
    '/notifications', '/notifications/templates', '/notifications/channels', '/notifications/providers', '/notifications/deliveries',
    '/search', '/search/synonyms', '/search/rules', '/search/pinned', '/search/settings', '/search/analytics',
    '/redirects',
    '/russian-commerce/legal-profile', '/russian-commerce/tax-settings', '/russian-commerce/fiscalization-settings', '/russian-commerce/payment-methods', '/russian-commerce/fiscal-receipts',
    '/settings',
  ])

  it('only references routes that exist as real pages in this app, including nested items', () => {
    // Every one of these must have a corresponding apps/admin/app/pages
    // file — see navigation.ts's own docblock. Listed explicitly here so
    // adding a nav entry without a real page fails this test.
    const allItems = flattenNavigationItems(primaryNavigation)
      .concat(flattenNavigationItems(settingsNavigation))
      .concat(secondaryNavigation.items)

    for (const item of allItems) {
      expect(realRoutes.has(item.to), `${item.to} is not a real route`).toBe(true)
    }
  })

  it('has a real, non-empty translation for every nav labelKey in all three locales (ru default, en, de)', () => {
    const allItems = flattenNavigationItems(primaryNavigation)
      .concat(flattenNavigationItems(settingsNavigation))
      .concat(secondaryNavigation.items)

    for (const item of allItems) {
      for (const [localeName, bundle] of [['ru', ru], ['en', en], ['de', de]] as const) {
        const value = resolveKey(bundle, item.labelKey)
        expect(value, `${item.labelKey} missing in ${localeName}.json`).toEqual(expect.any(String))
        expect(value, `${item.labelKey} empty in ${localeName}.json`).not.toBe('')
      }
    }

    // Section group labels (settingsNavigation) go through the same
    // labelKey/$t() contract as item labels — verify those too.
    for (const section of settingsNavigation) {
      if (!section.labelKey) continue
      for (const [localeName, bundle] of [['ru', ru], ['en', en], ['de', de]] as const) {
        const value = resolveKey(bundle, section.labelKey)
        expect(value, `${section.labelKey} missing in ${localeName}.json`).toEqual(expect.any(String))
      }
    }
  })

  it('caps the daily sidebar at 10 top-level entries (spec: "never exceed about 10")', () => {
    const topLevelCount = primaryNavigation.reduce((sum, section) => sum + section.items.length, 0)
    expect(topLevelCount).toBeLessThanOrEqual(10)
  })

  it('nests Fulfillments/Shipments/Returns/Refunds/Payments/Fiscal Receipts under Orders, not as flat top-level items', () => {
    const orders = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.orders')

    expect(orders?.children?.map(c => c.labelKey)).toEqual(['nav.fulfillments', 'nav.shipments', 'nav.returns', 'nav.refunds', 'nav.payments', 'nav.fiscal_receipts'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.fulfillments')).toBe(false)
  })

  it('operations vs configuration: Fiscal Receipts (a record) stays daily under Orders, while Legal/Tax/Fiscalization/Payment Methods (configuration) stay in Settings', () => {
    const orders = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.orders')
    expect(orders?.children?.some(c => c.labelKey === 'nav.fiscal_receipts')).toBe(true)

    const rcSection = settingsNavigation.find(s => s.labelKey === 'nav.settings_russian_commerce')
    expect(rcSection?.items.some(i => i.labelKey === 'nav.fiscal_receipts')).toBe(false)
  })

  it('operations vs configuration: Search Analytics (a report) moves to daily Analytics, while Synonyms/Rules/Pinned/Settings (configuration) stay in Settings', () => {
    const analytics = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.analytics')
    expect(analytics?.children?.some(c => c.labelKey === 'nav.search_analytics')).toBe(true)

    const searchSection = settingsNavigation.find(s => s.labelKey === 'nav.settings_search')
    expect(searchSection?.items.some(i => i.labelKey === 'nav.search_analytics')).toBe(false)
  })

  it('nests Inventory under Products, not as a flat top-level item', () => {
    const products = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.products')

    expect(products?.children?.map(c => c.labelKey)).toEqual(['nav.inventory'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.inventory')).toBe(false)
  })

  it('nests Customer Groups/Segments/Tags under Customers, not as flat top-level items', () => {
    const customers = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.customers')

    expect(customers?.children?.map(c => c.labelKey)).toEqual(['nav.customer_groups', 'nav.customer_segments', 'nav.customer_tags'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.customer_groups')).toBe(false)
  })

  it('merges Pages/Page Templates/Blogs/Authors/Menus under one Content branch, not flat top-level items', () => {
    const content = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.content')

    expect(content?.children?.map(c => c.labelKey)).toEqual(['nav.pages', 'nav.page_templates', 'nav.blogs', 'nav.authors', 'nav.menus'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.blogs')).toBe(false)
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.menus')).toBe(false)
  })

  it('nests Theme Customizer/Section Library/Block Library under Themes, not as flat top-level items', () => {
    const themes = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.themes')

    expect(themes?.children?.map(c => c.labelKey)).toEqual(['nav.theme_customizer', 'nav.section_library', 'nav.block_library'])
  })

  it('nests Reports/Saved Reports/Search Analytics under Analytics, not as flat top-level items', () => {
    const analytics = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.analytics')

    expect(analytics?.children?.map(c => c.labelKey)).toEqual(['nav.reports', 'nav.saved_reports', 'nav.search_analytics'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.reports')).toBe(false)
  })

  it('nests Executions/Templates under Automation, not as flat top-level items', () => {
    const automation = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.automation')

    expect(automation?.children?.map(c => c.labelKey)).toEqual(['nav.executions', 'nav.templates'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.executions')).toBe(false)
  })

  it('Marketing points at the real /promotions route (relabel, not a new page)', () => {
    const marketing = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.marketing')
    expect(marketing?.to).toBe('/promotions')
  })

  it('moved Notifications, Search, Redirects, Shipping config, Russian Commerce, Stores, and Locations out of the daily sidebar entirely', () => {
    const dailyLabelKeys = new Set(flattenNavigationItems(primaryNavigation).map(i => i.labelKey))

    for (const movedKey of ['nav.notifications', 'nav.search', 'nav.redirects', 'nav.shipping_methods', 'nav.shipping_zones', 'nav.russian_commerce', 'nav.stores', 'nav.locations']) {
      expect(dailyLabelKeys.has(movedKey), `${movedKey} should not be in the daily sidebar`).toBe(false)
    }
  })

  it('groups Settings sections as flat lists (no accordion children) — Shopify-style grouped nav, not a second accordion tree', () => {
    for (const section of settingsNavigation) {
      for (const item of section.items) {
        expect(item.children, `${item.labelKey} should not have children in settingsNavigation`).toBeUndefined()
      }
    }
  })

  it('groups Notifications/Search/Russian Commerce children under their own Settings section', () => {
    const notifSection = settingsNavigation.find(s => s.labelKey === 'nav.settings_notifications')
    expect(notifSection?.items.map(i => i.labelKey)).toEqual(['nav.notifications', 'nav.templates', 'nav.channels', 'nav.providers', 'nav.delivery_log'])

    const searchSection = settingsNavigation.find(s => s.labelKey === 'nav.settings_search')
    expect(searchSection?.items.map(i => i.labelKey)).toEqual(['nav.search', 'nav.synonyms', 'nav.rules_ranking', 'nav.pinned_products', 'nav.search_settings'])

    const rcSection = settingsNavigation.find(s => s.labelKey === 'nav.settings_russian_commerce')
    expect(rcSection?.items.map(i => i.labelKey)).toEqual(['nav.legal_details', 'nav.tax_vat_settings', 'nav.fiscalization_settings', 'nav.payment_methods'])
  })

  it('the daily sidebar and Settings tree never reference the same route twice', () => {
    const dailyRoutes = flattenNavigationItems(primaryNavigation).map(i => i.to)
    const settingsRoutes = flattenNavigationItems(settingsNavigation).map(i => i.to)
    const overlap = dailyRoutes.filter(r => settingsRoutes.includes(r))
    expect(overlap).toEqual([])
  })

  it('Settings is the sole secondary-nav entry point, pointing at the real /settings redirect page', () => {
    expect(secondaryNavigation.items).toEqual([{ labelKey: 'nav.settings', to: '/settings', icon: 'settings', activePattern: '/settings' }])
  })
})

describe('isRouteInSection (Settings stays active in the main sidebar)', () => {
  it('is false for the bare /settings redirect page — it is not itself a settingsNavigation item, it is the landing page that immediately forwards into one', () => {
    // AdminSidebar.vue handles this case with the *static* activePattern
    // ('/settings') already on the secondaryNavigation item, matched via
    // plain isNavItemActive — isRouteInSection only needs to cover every
    // route *past* that redirect, which is what the rest of this
    // describe block asserts.
    expect(isRouteInSection('/settings', settingsNavigation)).toBe(false)
    const settingsItem = secondaryNavigation.items.find(i => i.to === '/settings')!
    expect(isNavItemActive('/settings', settingsItem)).toBe(true)
  })

  it('is true for any real Settings destination, not just the literal /settings path', () => {
    for (const path of ['/stores', '/locations', '/notifications', '/notifications/templates', '/search/synonyms', '/russian-commerce/tax-settings', '/shipping-methods']) {
      expect(isRouteInSection(path, settingsNavigation), `${path} should be recognized as inside Settings`).toBe(true)
    }
  })

  it('is true on a nested detail route under a Settings item', () => {
    expect(isRouteInSection('/notifications/deliveries/abc123', settingsNavigation)).toBe(true)
  })

  it('is false for daily-sidebar routes', () => {
    for (const path of ['/orders', '/products', '/fulfillments', '/analytics']) {
      expect(isRouteInSection(path, settingsNavigation), `${path} should not be inside Settings`).toBe(false)
    }
  })
})

describe('findSettingsSection (Settings / {Section} breadcrumb source)', () => {
  it('resolves the owning section for a Settings route', () => {
    expect(findSettingsSection('/stores')?.labelKey).toBe('nav.settings_store')
    expect(findSettingsSection('/notifications/templates')?.labelKey).toBe('nav.settings_notifications')
    expect(findSettingsSection('/russian-commerce/tax-settings')?.labelKey).toBe('nav.settings_russian_commerce')
  })

  it('resolves the section on a nested detail route under a Settings item', () => {
    expect(findSettingsSection('/notifications/deliveries/abc123')?.labelKey).toBe('nav.settings_notifications')
  })

  it('is undefined for a route outside Settings entirely', () => {
    expect(findSettingsSection('/orders')).toBeUndefined()
  })
})

/**
 * Regression guard for the i18n navigation investigation: this app uses
 * `strategy: 'no_prefix'` (see nuxt.config.ts) — Russian, English, and
 * German all resolve at the *same* plain path (`/products`, never
 * `/ru/products` or `/en/products`). Routes are language-independent;
 * only labels are translated. `isNavItemActive` and every `to`/
 * `activePattern` must stay locale-agnostic strings so a future change
 * can't silently reintroduce a locale segment into route matching.
 */
describe('locale-agnostic routing (no_prefix strategy)', () => {
  const localePrefixPattern = /^\/(ru|en|de)(\/|$)/

  it('no navigation item path carries a locale segment', () => {
    const allItems = flattenNavigationItems(primaryNavigation)
      .concat(flattenNavigationItems(settingsNavigation))
      .concat(secondaryNavigation.items)

    for (const item of allItems) {
      expect(item.to, `${item.to} looks locale-prefixed`).not.toMatch(localePrefixPattern)
      if (item.activePattern) {
        expect(item.activePattern, `${item.activePattern} looks locale-prefixed`).not.toMatch(localePrefixPattern)
      }
    }
  })

  it('active-route matching is identical regardless of which locale is currently selected', () => {
    // isNavItemActive takes no locale argument by design — the same
    // route.path drives active-state matching under every locale. This
    // test documents that invariant so a future change can't couple
    // active-state matching to the current locale without this test
    // failing loudly.
    const products = { labelKey: 'nav.products', to: '/products', icon: 'products' }

    for (const currentPath of ['/products', '/products/abc123']) {
      const results = [ru, en, de].map(() => isNavItemActive(currentPath, products))
      expect(new Set(results).size).toBe(1)
      expect(results[0]).toBe(true)
    }
  })
})

import { describe, expect, it } from 'vitest'
import de from '../i18n/locales/de.json'
import en from '../i18n/locales/en.json'
import ru from '../i18n/locales/ru.json'
import { flattenNavigationItems, isNavItemActive, primaryNavigation, secondaryNavigation } from '../app/config/navigation'

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
    '/orders', '/customers', '/customer-groups', '/customer-segments', '/customer-tags', '/fulfillments', '/products', '/collections', '/inventory', '/locations', '/payments', '/stores',
    '/shipments', '/shipping-methods', '/shipping-zones', '/promotions', '/apps', '/themes',
    '/pages', '/page-templates', '/menus', '/blogs', '/authors', '/redirects',
    '/theme-customizer', '/section-library', '/block-library',
    '/automation', '/automation/executions', '/automation/templates',
    '/analytics', '/analytics/reports', '/analytics/saved-reports',
    '/notifications', '/notifications/templates', '/notifications/channels', '/notifications/providers', '/notifications/deliveries',
    '/search', '/search/synonyms', '/search/rules', '/search/pinned', '/search/settings', '/search/analytics',
    '/russian-commerce/legal-profile', '/russian-commerce/tax-settings', '/russian-commerce/fiscalization-settings', '/russian-commerce/payment-methods', '/russian-commerce/fiscal-receipts',
  ])

  it('only references routes that exist as real pages in this app, including nested items', () => {
    // Every one of these must have a corresponding apps/admin/app/pages
    // file — see navigation.ts's own docblock. Listed explicitly here so
    // adding a nav entry without a real page fails this test.
    const allItems = flattenNavigationItems(primaryNavigation).concat(secondaryNavigation.items)

    for (const item of allItems) {
      expect(realRoutes.has(item.to)).toBe(true)
    }
  })

  it('has a real, non-empty translation for every nav labelKey in all three locales (ru default, en, de)', () => {
    const allItems = flattenNavigationItems(primaryNavigation).concat(secondaryNavigation.items)

    for (const item of allItems) {
      for (const [localeName, bundle] of [['ru', ru], ['en', en], ['de', de]] as const) {
        const value = resolveKey(bundle, item.labelKey)
        expect(value, `${item.labelKey} missing in ${localeName}.json`).toEqual(expect.any(String))
        expect(value, `${item.labelKey} empty in ${localeName}.json`).not.toBe('')
      }
    }
  })

  it('nests Locations under Inventory as a genuine information-architecture choice, not a flat top-level item', () => {
    const inventory = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.inventory')

    expect(inventory?.children).toEqual([{ labelKey: 'nav.locations', to: '/locations', icon: 'locations' }])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.locations')).toBe(false)
  })

  it('nests Customer Groups/Segments/Tags under Customers, not as flat top-level items', () => {
    const customers = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.customers')

    expect(customers?.children?.map(c => c.labelKey)).toEqual(['nav.customer_groups', 'nav.customer_segments', 'nav.customer_tags'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.customer_groups')).toBe(false)
  })

  it('nests Executions/Templates under Automation, not as flat top-level items', () => {
    const automation = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.automation')

    expect(automation?.children?.map(c => c.labelKey)).toEqual(['nav.executions', 'nav.templates'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.executions')).toBe(false)
  })

  it('nests Reports/Saved Reports under Analytics, not as flat top-level items', () => {
    const analytics = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.analytics')

    expect(analytics?.children?.map(c => c.labelKey)).toEqual(['nav.reports', 'nav.saved_reports'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.reports')).toBe(false)
  })

  it('nests Templates/Channels/Providers/Delivery Log under Notifications, not as flat top-level items', () => {
    const notifications = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.notifications')

    expect(notifications?.children?.map(c => c.labelKey)).toEqual(['nav.templates', 'nav.channels', 'nav.providers', 'nav.delivery_log'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.delivery_log')).toBe(false)
  })

  it('nests Synonyms/Rules & Ranking/Pinned Products/Settings/Analytics under Search, not as flat top-level items', () => {
    const search = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.search')

    expect(search?.children?.map(c => c.labelKey)).toEqual(['nav.synonyms', 'nav.rules_ranking', 'nav.pinned_products', 'nav.search_settings', 'nav.search_analytics'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.synonyms')).toBe(false)
  })

  it('nests Legal Details/Tax-VAT/Fiscalization/Payment Methods/Fiscal Receipts under Russian Commerce, not as flat top-level items', () => {
    const russianCommerce = primaryNavigation.flatMap(s => s.items).find(i => i.labelKey === 'nav.russian_commerce')

    expect(russianCommerce?.children?.map(c => c.labelKey)).toEqual(['nav.legal_details', 'nav.tax_vat_settings', 'nav.fiscalization_settings', 'nav.payment_methods', 'nav.fiscal_receipts'])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.labelKey === 'nav.legal_details')).toBe(false)
  })
})

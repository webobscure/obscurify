import { describe, expect, it } from 'vitest'
import { flattenNavigationItems, isNavItemActive, primaryNavigation, secondaryNavigation } from '../app/config/navigation'

describe('isNavItemActive', () => {
  const products = { label: 'Products', to: '/products', icon: 'products' }
  const orders = { label: 'Orders', to: '/orders', icon: 'orders' }
  const locations = { label: 'Locations', to: '/locations', icon: 'locations' }

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

describe('navigation source of truth', () => {
  const realRoutes = new Set([
    '/orders', '/products', '/collections', '/inventory', '/locations', '/payments', '/stores',
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

  it('does not include Customers, Storefront, Categories, Settings, or a fake Overview/Dashboard page', () => {
    const allLabels = flattenNavigationItems(primaryNavigation).concat(secondaryNavigation.items).map(i => i.label)

    for (const fake of ['Customers', 'Storefront', 'Categories', 'Settings', 'Dashboard', 'Overview']) {
      expect(allLabels).not.toContain(fake)
    }
  })

  it('nests Locations under Inventory as a genuine information-architecture choice, not a flat top-level item', () => {
    const inventory = primaryNavigation.flatMap(s => s.items).find(i => i.label === 'Inventory')

    expect(inventory?.children).toEqual([{ label: 'Locations', to: '/locations', icon: 'locations' }])
    expect(primaryNavigation.flatMap(s => s.items).some(i => i.label === 'Locations')).toBe(false)
  })
})

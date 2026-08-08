import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Covers the admin nav/layout redesign: left sidebar replaces the old top
 * nav, active-state highlighting (including on nested detail routes),
 * the Store Switcher, global search palette, and the user menu/sign-out.
 * Requires `php artisan e2e:seed-storefront` — same fixture as
 * checkout.spec.ts/payment.spec.ts (e2e@example.test / password, "E2E
 * Store").
 *
 * Deliberately just two tests, not one per behaviour: `/api/v1/auth/login`
 * is rate-limited (5/minute/IP, see AppServiceProvider) — this whole
 * suite shares that budget with checkout.spec.ts and payment.spec.ts's
 * own admin logins, so each additional `test()` here that logs in again
 * is a real cost, not a free structural choice.
 */
async function login(page: import('@playwright/test').Page) {
  await page.goto(`${ADMIN_BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Email').fill('e2e@example.test')
  await page.getByPlaceholder('Password').fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/stores`)
}

test('sidebar replaces the old top nav, Store Switcher works, and active state follows nested routes', async ({ page }) => {
  await login(page)

  // The old design put nav links directly in the header; the new one
  // must not — the header may still exist (search/user menu live there)
  // but it must carry no primary nav links.
  await expect(page.locator('header').getByRole('link', { name: 'Products', exact: true })).toHaveCount(0)
  await expect(page.locator('aside').getByRole('link', { name: 'Products', exact: true })).toBeVisible()

  for (const label of ['Orders', 'Products', 'Collections', 'Inventory', 'Locations', 'Payments', 'Stores']) {
    await expect(page.locator('aside').getByRole('link', { name: label, exact: true })).toBeVisible()
  }

  // Store Switcher (sidebar top) — independent of the /stores page's own
  // Activate button, already covered by checkout.spec.ts/payment.spec.ts.
  await page.getByRole('button', { name: 'Switch store' }).click()
  const option = page.getByRole('option', { name: /E2E Store/ })
  await expect(option).toBeVisible()
  await option.click()
  await expect(page.getByRole('button', { name: 'Switch store' })).toContainText('E2E Store')

  const sidebar = page.locator('aside')

  await sidebar.getByRole('link', { name: 'Products', exact: true }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/products`)
  await expect(sidebar.getByRole('link', { name: 'Products', exact: true })).toHaveAttribute('aria-current', 'page')

  // A nested detail route (id doesn't need to exist — nav active-state is
  // purely a function of the current path, see isNavItemActive()). Scoped
  // to the sidebar specifically: a real product/order's own PageHeader
  // breadcrumb also renders a "Products"/"Orders" link in the main
  // content area, which would otherwise make this ambiguous.
  await page.goto(`${ADMIN_BASE}/products/does-not-exist`)
  await expect(sidebar.getByRole('link', { name: 'Products', exact: true })).toHaveAttribute('aria-current', 'page')
  await expect(sidebar.getByRole('link', { name: 'Orders', exact: true })).not.toHaveAttribute('aria-current', 'page')

  await page.goto(`${ADMIN_BASE}/orders/does-not-exist`)
  await expect(sidebar.getByRole('link', { name: 'Orders', exact: true })).toHaveAttribute('aria-current', 'page')
  await expect(sidebar.getByRole('link', { name: 'Products', exact: true })).not.toHaveAttribute('aria-current', 'page')
})

test('global search opens (trigger and keyboard shortcut) and navigates, and the user menu signs out', async ({ page }) => {
  await login(page)

  await page.getByRole('button', { name: /Open navigation search/ }).click()
  const dialog = page.getByRole('dialog', { name: 'Navigation search' })
  await expect(dialog).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()

  await page.keyboard.press('Control+k')
  await expect(dialog).toBeVisible()
  await dialog.getByRole('textbox').fill('Inventory')
  await dialog.getByRole('button', { name: 'Inventory' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/inventory`)

  await page.getByRole('button', { name: 'User menu' }).click()
  await expect(page.getByText('e2e@example.test')).toBeVisible()

  await page.getByRole('menuitem', { name: 'Sign out' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/login`)
})

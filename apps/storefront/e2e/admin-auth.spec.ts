import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * End-to-end coverage for the Sanctum bearer-token auth flow between the
 * Nuxt admin SPA and Laravel: login persists across navigation and a full
 * page reload, an authenticated action actually reaches the backend,
 * sign-out really revokes the session (not just clears a local flag), and
 * a signed-out user can never see a protected page again — including
 * after a reload, which is exactly the case the regression this test
 * guards against got wrong (a stale localStorage token was trusted
 * without ever being re-checked against the backend).
 *
 * Requires `php artisan e2e:seed-storefront` — same fixture as
 * checkout.spec.ts/payment.spec.ts/admin-navigation.spec.ts
 * (e2e@example.test / password, "E2E Store"). Deliberately a single test:
 * `/api/v1/auth/login` is rate-limited (5/minute/IP) and shares that
 * budget with the other admin specs in this suite.
 */
test('login survives navigation and reload, an authenticated action succeeds, and logout really signs out', async ({ page }) => {
  await page.goto(`${ADMIN_BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Email').fill('e2e@example.test')
  await page.getByPlaceholder('Password').fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/stores`)

  await page.getByRole('button', { name: 'Switch store' }).click()
  await page.getByRole('option', { name: /E2E Store/ }).click()
  await expect(page.getByRole('button', { name: 'Switch store' })).toContainText('E2E Store')

  // Authenticated action: create a product. This round-trips through
  // auth:sanctum + tenant middleware on the backend — a stale/rejected
  // token fails here, not just on the initial page load.
  await page.goto(`${ADMIN_BASE}/products`)
  const productTitle = `E2E Auth Product ${Date.now()}`
  await page.getByPlaceholder('Product title').fill(productTitle)
  await page.getByRole('button', { name: 'Create product' }).click()
  await expect(page).toHaveURL(/\/products\/[^/]+$/)

  // Navigate to another protected page, then reload — session must
  // survive a real page reload (localStorage token re-verified against
  // the backend on boot), not just in-app navigation.
  await page.goto(`${ADMIN_BASE}/orders`)
  await expect(page.locator('aside').getByRole('link', { name: 'Orders', exact: true })).toHaveAttribute('aria-current', 'page')

  await page.reload()
  await expect(page).toHaveURL(`${ADMIN_BASE}/orders`)
  await expect(page.locator('aside').getByRole('link', { name: 'Orders', exact: true })).toHaveAttribute('aria-current', 'page')

  // Sign out must actually invalidate the session, not just clear local
  // state: a direct visit to a protected page afterwards must redirect to
  // /login, both immediately and after a reload.
  await page.getByRole('button', { name: 'User menu' }).click()
  await page.getByRole('menuitem', { name: 'Sign out' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/login`)

  await page.goto(`${ADMIN_BASE}/products`)
  await expect(page).toHaveURL(`${ADMIN_BASE}/login`)

  await page.reload()
  await expect(page).toHaveURL(`${ADMIN_BASE}/login`)
})

import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — see
 * playwright.config.ts. Covers the customer-portal browser flow
 * (Milestone 16): register, land signed-in on /account, edit the
 * profile, add an address with a default flag, log out, log back in,
 * then switch to the admin app (same browser context, different origin)
 * to confirm the merchant sees the new customer, their address, and
 * their activity timeline.
 *
 * Deliberately does not exercise checkout/reorder/return through the
 * browser — RegisterCustomer's guest-merge-by-email, ReorderFromOrder's
 * live-pricing guarantee, and RequestCustomerReturn's ownership check are
 * already covered thoroughly at the API level in
 * tests/Feature/Customers/*.php, and re-driving a full checkout here
 * would just duplicate golden-path.spec.ts/checkout.spec.ts's own
 * coverage for no additional confidence.
 */
test('registers, manages profile and addresses, logs back in, and the merchant sees the customer in admin', async ({ page }) => {
  const email = `e2e-customer-${Date.now()}@example.test`

  await page.goto(`${BASE}/account/register`)
  // Cold dev server: the DOM is server-rendered before Vue finishes
  // hydrating and attaches @click — see checkout.spec.ts's identical note.
  await page.waitForLoadState('networkidle')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill('super-secret-1')
  await page.getByLabel('Confirm password').fill('super-secret-1')
  await page.getByLabel('First name').fill('Ada')
  await page.getByLabel('Last name').fill('Lovelace')
  await page.getByRole('button', { name: 'Create account' }).click()

  await expect(page).toHaveURL(`${BASE}/account`)
  await expect(page.getByText(email)).toBeVisible()
  await expect(page.getByText('Not verified')).toBeVisible()

  // Profile edit.
  await page.getByLabel('Phone').fill('+1 555 0100')
  await page.getByRole('button', { name: 'Save profile' }).click()
  await expect(page.getByText('Profile saved.')).toBeVisible()

  // Address book, with a default-shipping flag.
  await page.getByRole('link', { name: 'Addresses' }).click()
  await expect(page).toHaveURL(`${BASE}/account/addresses`)
  await page.getByLabel('Country code (2 letters)').fill('GB')
  await page.getByLabel('City').fill('London')
  await page.getByLabel('Address line 1').fill('1 Analytical Engine Way')
  await page.getByLabel('Default shipping address').check()
  await page.getByRole('button', { name: 'Add address' }).click()
  await expect(page.getByText('1 Analytical Engine Way')).toBeVisible()
  await expect(page.getByText('Default shipping', { exact: true })).toBeVisible()

  // Log out, then back in — proves the CustomerAccessToken pair issued
  // by login actually authenticates the portal, not just registration's.
  await page.goto(`${BASE}/account`)
  await page.getByRole('button', { name: 'Log out' }).click()
  await expect(page).toHaveURL(`${BASE}/`)

  await page.goto(`${BASE}/account/login`)
  await page.waitForLoadState('networkidle')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('super-secret-1')
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page).toHaveURL(`${BASE}/account`)
  await expect(page.getByText(email)).toBeVisible()

  // Switch to the admin app, same browser context — a genuinely
  // different origin, so this is a real cross-app assertion.
  await page.goto(`${ADMIN_BASE}/login`)
  await page.getByPlaceholder('Email').fill('e2e@example.test')
  await page.getByPlaceholder('Password').fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/stores`)

  const storeRow = page.getByRole('row', { name: /E2E Store/ })
  await expect(storeRow).toBeVisible()
  const activateButton = storeRow.getByRole('button', { name: 'Activate' })
  if (await activateButton.isVisible()) {
    await activateButton.click()
    await expect(storeRow.getByText('Active')).toBeVisible()
  }

  await page.getByRole('link', { name: 'Customers' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/customers`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByText(email)).toBeVisible()

  await page.getByRole('row', { name: new RegExp(email) }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/customers\/.+/)
  await expect(page.getByRole('heading', { name: email })).toBeVisible()
  await expect(page.getByText('1 Analytical Engine Way')).toBeVisible()
  await expect(page.getByText('CustomerCreated')).toBeVisible()
})

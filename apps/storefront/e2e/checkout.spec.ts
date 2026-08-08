import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) to have been run
 * — see playwright.config.ts. Covers checkout through order confirmation,
 * then switches to the admin app (same browser context, different
 * origin) to confirm the merchant who owns e2e-store can see the order
 * that was just placed. No payment step: financial_status stays
 * "pending" — this milestone never marks an order paid.
 */
test('places an order through checkout, shows the confirmation, and the merchant sees it in admin', async ({ page }) => {
  await page.goto(`${BASE}/products/e2e-shirt`)
  // On a cold dev server this is the first-ever load of this route:
  // the DOM is already server-rendered (so the button is "visible" to
  // Playwright) before Vue finishes hydrating and attaches its @click
  // handler — clicking in that gap is a real no-op, not a product bug.
  // networkidle is a reasonable proxy for "hydration has settled".
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('button', { name: 'M', exact: true })).toHaveClass(/selected/)
  await page.getByRole('button', { name: 'Add to cart' }).click()
  await expect(page.getByText('Cart(1)')).toBeVisible()

  await page.getByRole('link', { name: /Cart/ }).click()
  await expect(page).toHaveURL(`${BASE}/cart`)

  await page.getByRole('link', { name: 'Proceed to checkout' }).click()
  await expect(page).toHaveURL(`${BASE}/checkout`)

  await expect(page.getByLabel('Email')).toBeVisible()
  await page.getByLabel('Email').fill('e2e-buyer@example.com')
  await page.getByLabel('First name').fill('Ada')
  await page.getByLabel('Last name').fill('Lovelace')
  await page.getByLabel('Country code').fill('US')
  await page.getByLabel('City').fill('Testville')
  await page.getByLabel('Address line 1').fill('1 E2E Street')

  await page.getByRole('button', { name: 'Place order' }).click()

  await page.waitForURL(/\/order-confirmation\//)
  await expect(page.getByRole('heading', { name: 'Thank you for your order!' })).toBeVisible()

  // Narrowed past "has been placed": the route announcer's aria-live
  // status region also renders "Order #N — E2E Store" (from the page
  // title), which would otherwise make this a strict-mode-ambiguous match.
  const orderText = await page.getByText(/Order #\d+ has been placed/).textContent()
  const orderNumber = orderText?.match(/#(\d+)/)?.[1]
  expect(orderNumber).toBeTruthy()

  await expect(page.getByText('E2E Shirt')).toBeVisible()
  await expect(page.getByText('Testville')).toBeVisible()

  // Switch to the admin app, same browser context — a genuinely
  // different origin, so this is a real cross-app assertion, not a
  // simulated one.
  await page.goto(`${ADMIN_BASE}/login`)
  await page.getByPlaceholder('Email').fill('e2e@example.test')
  await page.getByPlaceholder('Password').fill('password')
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/stores`)

  const storeRow = page.getByRole('row', { name: /E2E Store/ })
  // isVisible() is a point-in-time check, not an auto-waiting assertion —
  // without first waiting for the row itself, a still-loading stores list
  // makes this false negative (button "not visible" because it isn't
  // rendered *yet*), silently skipping activation.
  await expect(storeRow).toBeVisible()
  const activateButton = storeRow.getByRole('button', { name: 'Activate' })
  if (await activateButton.isVisible()) {
    await activateButton.click()
    await expect(storeRow.getByText('Active')).toBeVisible()
  }

  await page.getByRole('link', { name: 'Orders' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/orders`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByText(`#${orderNumber}`)).toBeVisible()

  await page.getByRole('row', { name: new RegExp(`#${orderNumber}`) }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/orders\/.+/)
  await expect(page.getByRole('heading', { name: `Order #${orderNumber}` })).toBeVisible()
  await expect(page.getByText('E2E Shirt')).toBeVisible()
  await expect(page.getByText('pending')).toBeVisible()
  // Billing defaulted to shipping, so "Testville" legitimately appears
  // in both address blocks on this page — .first() is enough to confirm
  // it rendered at all.
  await expect(page.getByText('Testville').first()).toBeVisible()
})

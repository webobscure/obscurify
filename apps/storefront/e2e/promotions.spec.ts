import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — see
 * playwright.config.ts and SeedE2EStorefrontCommand's "E2E 500 off"
 * Promotion/DiscountCode fixture (code E2E10OFF, no rules, always
 * eligible). Covers the Discount & Promotion Engine (Milestone 10) end
 * to end: enter a discount code on checkout -> totals update server-side
 * -> remove it -> re-apply -> place the order -> the confirmation page
 * and the admin order page both show the same snapshot (spec section 8).
 * One test, deliberately: shares the platform's 5/minute/IP login rate
 * limit with checkout.spec.ts/payment.spec.ts/shipping.spec.ts/
 * admin-*.spec.ts (see those files' comments on the same constraint).
 */
test('applies a discount code on checkout, allows removing it, and snapshots it onto the order', async ({ page }) => {
  await page.goto(`${BASE}/products/e2e-shirt`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('button', { name: 'M', exact: true })).toHaveClass(/selected/)
  await page.getByRole('button', { name: 'Add to cart' }).click()
  await expect(page.getByText('Cart(1)')).toBeVisible()

  await page.getByRole('link', { name: /Cart/ }).click()
  await page.getByRole('link', { name: 'Proceed to checkout' }).click()
  await expect(page).toHaveURL(`${BASE}/checkout`)

  await expect(page.getByLabel('Email')).toBeVisible()
  await page.getByLabel('Email').fill('promo-e2e@example.com')
  await page.getByLabel('First name').fill('Grace')
  await page.getByLabel('Last name').fill('Hopper')
  await page.getByLabel('Country code').fill('US')
  await page.getByLabel('City').fill('Testville')
  await page.getByLabel('Address line 1').fill('1 Promotion Street')

  // An unknown code is rejected without applying anything (spec section
  // 10) — no "Discount:" line ever appears.
  await page.getByPlaceholder('Enter discount code').fill('NOTREAL')
  await page.getByRole('button', { name: 'Apply' }).click()
  await expect(page.getByText('Invalid discount code')).toBeVisible()
  await expect(page.getByText(/Discount: -/)).not.toBeVisible()

  // Case-insensitive lookup (spec section 6). The discount line reflects
  // PromotionEngine's own recalculation, not a frontend guess.
  await page.getByPlaceholder('Enter discount code').fill('e2e10off')
  await page.getByRole('button', { name: 'Apply' }).click()
  await expect(page.getByText('Code E2E10OFF applied')).toBeVisible()
  await expect(page.getByText(/Discount: -/)).toBeVisible()

  // Removing it reverts the total server-side, not just hides the UI.
  await page.getByRole('button', { name: 'Remove' }).click()
  await expect(page.getByPlaceholder('Enter discount code')).toBeVisible()
  await expect(page.getByText(/Discount: -/)).not.toBeVisible()

  // Re-apply before completing — this is the code that must survive onto
  // the Order snapshot.
  await page.getByPlaceholder('Enter discount code').fill('E2E10OFF')
  await page.getByRole('button', { name: 'Apply' }).click()
  await expect(page.getByText('Code E2E10OFF applied')).toBeVisible()

  await page.getByRole('button', { name: 'Place order' }).click()
  await page.waitForURL(/\/order-confirmation\//)

  const orderText = await page.getByText(/Order #\d+ has been placed/).textContent()
  const orderNumber = orderText?.match(/#(\d+)/)?.[1]
  expect(orderNumber).toBeTruthy()

  await expect(page.getByText(/Discount: -/)).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Discounts applied' })).toBeVisible()
  await expect(page.getByText('E2E 500 off (E2E10OFF)', { exact: false })).toBeVisible()

  // Admin: same browser context, different origin — a real cross-app
  // assertion (see checkout.spec.ts for the same pattern).
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

  await page.getByRole('link', { name: 'Orders' }).click()
  await page.waitForLoadState('networkidle')
  await page.getByRole('row', { name: new RegExp(`#${orderNumber}`) }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/orders\/.+/)

  // The Order snapshot (spec section 8) is what the admin page renders —
  // never a live Promotion/DiscountCode lookup.
  await expect(page.getByText('E2E 500 off (E2E10OFF)', { exact: false })).toBeVisible()
})

import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — see
 * playwright.config.ts and SeedE2EStorefrontCommand's shipping zone/
 * method fixture. Full Shipping Foundation flow (milestone spec section
 * 39): storefront cart -> checkout -> shipping address -> choose fake
 * shipping rate -> complete order -> fake payment -> paid; admin -> open
 * order -> create shipment -> fake provider in_transit -> delivered;
 * verify the tracking timeline throughout. One test, deliberately: this
 * shares the platform's 5/minute/IP login rate limit with
 * checkout.spec.ts/payment.spec.ts/admin-*.spec.ts (see those files'
 * comments on the same constraint).
 */
test('storefront checkout with a selected shipping rate, through fake payment, to a delivered shipment in admin', async ({ page }) => {
  await page.goto(`${BASE}/products/e2e-shirt`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('button', { name: 'M', exact: true })).toHaveClass(/selected/)
  await page.getByRole('button', { name: 'Add to cart' }).click()
  await expect(page.getByText('Cart(1)')).toBeVisible()

  await page.getByRole('link', { name: /Cart/ }).click()
  await page.getByRole('link', { name: 'Proceed to checkout' }).click()
  await expect(page).toHaveURL(`${BASE}/checkout`)

  await expect(page.getByLabel('Email')).toBeVisible()
  await page.getByLabel('Email').fill('shipping-e2e@example.com')
  await page.getByLabel('First name').fill('Grace')
  await page.getByLabel('Last name').fill('Hopper')
  await page.getByLabel('Country code').fill('US')
  await page.getByLabel('City').fill('Testville')
  await page.getByLabel('Address line 1').fill('1 Shipping Street')

  // Shipping rates are calculated server-side from the address just
  // filled in above (spec section 9) — never a frontend calculation.
  await page.getByRole('button', { name: 'Check shipping options' }).click()
  const standardRate = page.locator('.rates li', { hasText: 'Standard Shipping' })
  await expect(standardRate).toBeVisible()
  await standardRate.getByRole('radio').check()

  // Selecting the rate updates the checkout total server-side — assert
  // the shipping line before placing the order, so this is genuinely
  // checking "the price came from the backend", not just "a request
  // happened" (spec section 11).
  await expect(page.getByText(/Shipping \(Standard Shipping\):/)).toBeVisible()

  await page.getByRole('button', { name: 'Place order' }).click()
  await page.waitForURL(/\/order-confirmation\//)

  const orderText = await page.getByText(/Order #\d+ has been placed/).textContent()
  const orderNumber = orderText?.match(/#(\d+)/)?.[1]
  expect(orderNumber).toBeTruthy()

  // The order confirmation shows the shipping snapshot (OrderShippingLine),
  // not a live ShippingMethod lookup (spec section 14).
  await expect(page.getByText(/Shipping \(Standard Shipping\):/)).toBeVisible()
  await expect(page.getByText(/estimated 3.5 days/)).toBeVisible()

  await page.getByRole('button', { name: 'Pay with Fake Payment' }).click()
  await page.waitForURL(/\/fake-payments\//)
  await page.getByRole('button', { name: 'Pay successfully' }).click()
  await expect(page.getByText(/resolved:\s*paid/)).toBeVisible()

  await page.getByRole('link', { name: '← Back' }).click()
  await expect(page.getByText(`Payment successful — Order #${orderNumber}`)).toBeVisible()

  // Admin: same browser context, different origin — a real cross-app
  // assertion (see checkout.spec.ts/payment.spec.ts for the same pattern).
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
  await expect(page.getByText('paid')).toBeVisible()

  // Order snapshot shows the shipping line the customer selected.
  await expect(page.getByText('— Standard Shipping')).toBeVisible()

  // Create a shipment for the (now-paid) order.
  await page.getByRole('heading', { name: 'Shipments' }).scrollIntoViewIfNeeded()
  await page.locator('.ship-form input[type="checkbox"]').first().check()
  await page.getByRole('button', { name: 'Create shipment' }).click()
  await expect(page.getByText('No shipments yet.')).not.toBeVisible()

  await page.getByRole('link', { name: 'View' }).last().click()
  await page.waitForURL(/\/shipments\/.+/)
  await expect(page.getByRole('row', { name: 'Status created' })).toBeVisible()

  // Drive the fake provider through its webhook-simulated lifecycle
  // (spec section 19) and confirm the append-only tracking timeline
  // grows with each transition.
  await page.getByRole('button', { name: 'Mark in transit' }).click()
  await expect(page.getByRole('row', { name: 'Status in_transit' })).toBeVisible()

  await page.getByRole('button', { name: 'Mark delivered' }).click()
  await expect(page.getByRole('row', { name: 'Status delivered' })).toBeVisible()

  const timelineRows = page.locator('table', { hasText: 'Description' }).locator('tbody tr')
  await expect(timelineRows).toHaveCount(3)
  await expect(timelineRows.nth(0)).toContainText('created')
  await expect(timelineRows.nth(1)).toContainText('in_transit')
  await expect(timelineRows.nth(2)).toContainText('delivered')
})

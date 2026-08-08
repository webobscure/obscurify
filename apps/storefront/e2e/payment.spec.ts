import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) to have been run
 * — see playwright.config.ts. Extends checkout.spec.ts's flow through a
 * real fake-payment round trip: order created → choose Fake Payment →
 * fake provider page → Pay successfully → signed webhook processed →
 * storefront confirmation shows paid → admin (Orders and Payments) shows
 * it too. No payment step here ever updates Order state directly from
 * the browser — every assertion below is checking state that only a
 * verified backend webhook could have produced.
 */
async function placeOrder(page: import('@playwright/test').Page) {
  await page.goto(`${BASE}/products/e2e-shirt`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('button', { name: 'M', exact: true })).toHaveClass(/selected/)
  await page.getByRole('button', { name: 'Add to cart' }).click()
  await expect(page.getByText('Cart(1)')).toBeVisible()

  await page.getByRole('link', { name: /Cart/ }).click()
  await page.getByRole('link', { name: 'Proceed to checkout' }).click()
  await expect(page).toHaveURL(`${BASE}/checkout`)

  await expect(page.getByLabel('Email')).toBeVisible()
  await page.getByLabel('Email').fill('payer@example.com')
  await page.getByLabel('First name').fill('Grace')
  await page.getByLabel('Last name').fill('Hopper')
  await page.getByLabel('Country code').fill('US')
  await page.getByLabel('City').fill('Testville')
  await page.getByLabel('Address line 1').fill('1 Payment Street')

  await page.getByRole('button', { name: 'Place order' }).click()
  await page.waitForURL(/\/order-confirmation\//)

  const orderText = await page.getByText(/Order #\d+ has been placed/).textContent()
  const orderNumber = orderText?.match(/#(\d+)/)?.[1]
  const orderId = page.url().split('/order-confirmation/')[1]

  return { orderNumber, orderId }
}

test('pays successfully through the fake provider and shows paid everywhere', async ({ page }) => {
  const { orderNumber } = await placeOrder(page)

  await expect(page.getByRole('button', { name: 'Pay with Fake Payment' })).toBeVisible()
  await page.getByRole('button', { name: 'Pay with Fake Payment' }).click()
  await page.waitForURL(/\/fake-payments\//)

  await expect(page.getByRole('heading', { name: 'Fake Payment' })).toBeVisible()
  await expect(page.getByText(`#${orderNumber}`)).toBeVisible()
  await expect(page.getByText('processing')).toBeVisible()

  await page.getByRole('button', { name: 'Pay successfully' }).click()
  await expect(page.getByText(/resolved:\s*paid/)).toBeVisible()

  await page.getByRole('link', { name: '← Back' }).click()
  await expect(page).toHaveURL(/\/order-confirmation\//)
  // The success banner already includes the order number, so this one
  // assertion covers both "payment succeeded" and "for the right order".
  await expect(page.getByText(`Payment successful — Order #${orderNumber}`)).toBeVisible()

  // Admin: same browser context, different origin — a real cross-app
  // assertion (see checkout.spec.ts for the same pattern).
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
  await page.waitForLoadState('networkidle')
  await page.getByRole('row', { name: new RegExp(`#${orderNumber}`) }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/orders\/.+/)
  await expect(page.getByText('paid')).toBeVisible()

  await page.getByRole('link', { name: 'Payments' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/payments`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('row', { name: new RegExp(`#${orderNumber}`) })).toContainText('paid')
})

test('a failed fake payment leaves the order pending, and the storefront offers to retry', async ({ page }) => {
  const { orderNumber } = await placeOrder(page)

  await page.getByRole('button', { name: 'Pay with Fake Payment' }).click()
  await page.waitForURL(/\/fake-payments\//)

  await page.getByRole('button', { name: 'Fail payment' }).click()
  await expect(page.getByText(/resolved:\s*failed/)).toBeVisible()

  await page.getByRole('link', { name: '← Back' }).click()
  await expect(page).toHaveURL(/\/order-confirmation\//)

  // Still pending — the storefront never marks it paid itself, and the
  // same "Pay with Fake Payment" affordance doubles as retry (a new
  // Payment is allowed once the old one is failed/cancelled).
  await expect(page.getByText(`Order #${orderNumber} has been placed and is awaiting payment.`)).toBeVisible()
  await expect(page.getByRole('button', { name: 'Pay with Fake Payment' })).toBeVisible()
})

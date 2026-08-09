import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — the fixture's
 * "E2E RU Zone" + "Pickup Point" method exist specifically for this spec,
 * since the fake provider's pickup-point network is RU-only (spec
 * section 5). Covers the Pickup service end to end: storefront checkout
 * -> RU shipping address -> select Pickup Point service -> select a
 * specific pickup point -> place order -> fake payment -> paid; admin ->
 * fulfill -> create fake shipment -> walk the async lifecycle through
 * accepted/in_transit/out_for_delivery/delivered (spec section 29) ->
 * confirm the pickup point snapshot is visible on both the order and the
 * shipment detail page, and the tracking timeline records every step.
 * One test, deliberately: shares the platform's 5/minute/IP login rate
 * limit with checkout.spec.ts/payment.spec.ts/shipping.spec.ts/
 * admin-*.spec.ts (see those files' comments on the same constraint).
 */
test('storefront pickup checkout, through fake payment, to a delivered shipment with a pickup point snapshot in admin', async ({ page }) => {
  await page.goto(`${BASE}/products/e2e-shirt`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('button', { name: 'M', exact: true })).toHaveClass(/selected/)
  await page.getByRole('button', { name: 'Add to cart' }).click()
  await expect(page.getByText('Cart(1)')).toBeVisible()

  await page.getByRole('link', { name: /Cart/ }).click()
  await page.getByRole('link', { name: 'Proceed to checkout' }).click()
  await expect(page).toHaveURL(`${BASE}/checkout`)

  await expect(page.getByLabel('Email')).toBeVisible()
  await page.getByLabel('Email').fill('pickup-e2e@example.com')
  await page.getByLabel('First name').fill('Ada')
  await page.getByLabel('Last name').fill('Lovelace')
  await page.getByLabel('Country code').fill('RU')
  await page.getByLabel('City').fill('Moscow')
  await page.getByLabel('Address line 1').fill('1 Tverskaya St')

  await page.getByRole('button', { name: 'Check shipping options' }).click()
  const pickupRate = page.locator('.rates li', { hasText: 'Pickup Point' })
  await expect(pickupRate).toBeVisible()

  // Selecting a Pickup-service rate must not submit anything to the
  // backend on its own — it only expands the pickup-point picker (spec
  // section 6). Confirm the checkout total hasn't been claimed yet.
  await pickupRate.getByRole('radio').first().check()
  const tverskayaPoint = page.locator('.pickup-points li', { hasText: 'Tverskaya' })
  await expect(tverskayaPoint).toBeVisible()

  await tverskayaPoint.getByRole('radio').check()

  // Selection is only confirmed server-side once a point is chosen —
  // assert the shipping line and the pickup snapshot before placing the
  // order, proving both came from the backend (spec sections 6/11).
  await expect(page.getByText(/Shipping \(Pickup Point\):/)).toBeVisible()
  await expect(page.getByText(/Pickup at: Fake Pickup — Tverskaya/)).toBeVisible()

  await page.getByRole('button', { name: 'Place order' }).click()
  await page.waitForURL(/\/order-confirmation\//)

  const orderText = await page.getByText(/Order #\d+ has been placed/).textContent()
  const orderNumber = orderText?.match(/#(\d+)/)?.[1]
  expect(orderNumber).toBeTruthy()

  // The confirmation page renders the immutable OrderShippingLine
  // snapshot, not a live provider lookup (spec section 18) — the pickup
  // point must still be there.
  await expect(page.getByText(/Pickup at: Fake Pickup — Tverskaya/)).toBeVisible()

  await page.getByRole('button', { name: 'Pay with Fake Payment' }).click()
  await page.waitForURL(/\/fake-payments\//)
  await page.getByRole('button', { name: 'Pay successfully' }).click()
  await expect(page.getByText(/resolved:\s*paid/)).toBeVisible()

  await page.getByRole('link', { name: '← Back' }).click()
  await expect(page.getByText(`Payment successful — Order #${orderNumber}`)).toBeVisible()

  // Admin: same browser context, different origin.
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

  // Order snapshot shows the shipping line the customer selected and the
  // pickup point snapshot (spec section 17).
  await expect(page.getByText('— Pickup Point')).toBeVisible()
  await expect(page.getByText(/Pickup at: Fake Pickup — Tverskaya/)).toBeVisible()

  await page.getByRole('heading', { name: 'Fulfillments' }).scrollIntoViewIfNeeded()
  await page.locator('.ship-form input[type="checkbox"]').first().check()
  await page.getByRole('button', { name: 'Create fulfillment' }).click()
  await expect(page.getByText('No fulfillments yet.')).not.toBeVisible()

  await page.getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/fulfillments\/.+/)

  await page.getByRole('button', { name: 'Allocate' }).click()
  await expect(page.getByRole('heading', { name: 'Picking' })).toBeVisible()

  await page.getByRole('button', { name: 'Save picked quantities' }).click()
  await expect(page.getByRole('heading', { name: 'Packing' })).toBeVisible()

  await page.getByRole('button', { name: 'Save packed quantities' }).click()
  await expect(page.getByRole('heading', { name: 'Create shipment' })).toBeVisible()

  await page.getByRole('button', { name: 'Create shipment (fake provider)' }).click()
  await expect(page.locator('.status-completed')).toBeVisible()

  await page.getByRole('link', { name: 'View', exact: true }).click()
  await page.waitForURL(/\/shipments\/.+/)
  await expect(page.getByRole('row', { name: 'Status created' })).toBeVisible()

  // Destination-or-pickup-point (spec section 22): the shipment detail
  // page must show the pickup point, not a delivery address.
  await expect(page.getByRole('row', { name: /Pickup point.*Fake Pickup — Tverskaya/ })).toBeVisible()

  // Full async lifecycle (spec section 29): created -> accepted ->
  // in_transit -> out_for_delivery -> delivered, one dev-control click
  // per hop, verifying the append-only tracking timeline grows each time.
  await page.getByRole('button', { name: 'Mark accepted' }).click()
  await expect(page.getByRole('row', { name: 'Status accepted' })).toBeVisible()

  await page.getByRole('button', { name: 'Mark in transit' }).click()
  await expect(page.getByRole('row', { name: 'Status in_transit' })).toBeVisible()

  await page.getByRole('button', { name: 'Mark out for delivery' }).click()
  await expect(page.getByRole('row', { name: 'Status out_for_delivery' })).toBeVisible()

  await page.getByRole('button', { name: 'Mark delivered' }).click()
  await expect(page.getByRole('row', { name: 'Status delivered' })).toBeVisible()

  const timelineRows = page.locator('table', { hasText: 'Description' }).locator('tbody tr')
  await expect(timelineRows).toHaveCount(5)
  await expect(timelineRows.nth(0)).toContainText('created')
  await expect(timelineRows.nth(1)).toContainText('accepted')
  await expect(timelineRows.nth(2)).toContainText('in_transit')
  await expect(timelineRows.nth(3)).toContainText('out_for_delivery')
  await expect(timelineRows.nth(4)).toContainText('delivered')
})

import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`
const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — see
 * playwright.config.ts and SeedE2EStorefrontCommand's shipping zone/
 * method fixture. Full order lifecycle through Fulfillment Core
 * (Milestone 7's mandatory flow, spec section 21): storefront cart ->
 * checkout -> shipping address -> choose fake shipping rate -> complete
 * order -> fake payment -> paid; admin -> open order -> create Fulfillment
 * -> allocate -> pick -> pack (auto-ready) -> create shipment (completes
 * the Fulfillment) -> fake provider in_transit -> delivered; verify the
 * tracking timeline throughout. One test, deliberately: this shares the
 * platform's 5/minute/IP login rate limit with checkout.spec.ts/
 * payment.spec.ts/admin-*.spec.ts (see those files' comments on the same
 * constraint).
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
  // Scoped to the Financial status row, not just any "paid" text — the
  // order page's own Payments section (Milestone 9) also renders a
  // payment's status as plain "paid" text elsewhere on the same page.
  await expect(page.getByRole('row', { name: 'Financial paid' })).toBeVisible()

  // Order snapshot shows the shipping line the customer selected.
  await expect(page.getByText('— Standard Shipping')).toBeVisible()

  // Create a Fulfillment for the (now-paid) order — Milestone 7:
  // Shipment creation moved off this page, now happens on the
  // Fulfillment's own page once it's fully picked and packed.
  await page.getByRole('heading', { name: 'Fulfillments' }).scrollIntoViewIfNeeded()
  await page.locator('.ship-form input[type="checkbox"]').first().check()
  await page.getByRole('button', { name: 'Create fulfillment' }).click()
  await expect(page.getByText('No fulfillments yet.')).not.toBeVisible()

  // Scoped to the Fulfillments section — the order page's own Payments
  // section (Milestone 9) also has its own "View" link by this point.
  await page.locator('section', { hasText: 'Fulfillments' }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/fulfillments\/.+/)

  await page.getByRole('button', { name: 'Allocate' }).click()
  await expect(page.getByRole('heading', { name: 'Picking' })).toBeVisible()

  // Picked-quantity inputs default to each item's full ordered quantity.
  await page.getByRole('button', { name: 'Save picked quantities' }).click()
  await expect(page.getByRole('heading', { name: 'Packing' })).toBeVisible()

  // Packed-quantity inputs default to each item's full picked quantity —
  // fully packed in one call auto-advances the Fulfillment to `ready`.
  await page.getByRole('button', { name: 'Save packed quantities' }).click()
  await expect(page.getByRole('heading', { name: 'Create shipment' })).toBeVisible()

  await page.getByRole('button', { name: 'Create shipment (fake provider)' }).click()
  await expect(page.locator('.status-completed')).toBeVisible()

  await page.getByRole('link', { name: 'View', exact: true }).click()
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

  // Returns & Reverse Logistics Core (Milestone 8) — request a return
  // against the now-delivered order and walk it through the full
  // lifecycle to a restock. Reuses this test's existing admin login
  // rather than opening a new one, since the platform's 5/minute/IP
  // login throttle is already shared tightly across this whole suite
  // (see this file's top-of-file comment and admin-navigation.spec.ts's
  // identical constraint).
  await page.getByRole('link', { name: 'View order' }).click()
  await page.waitForURL(/\/orders\/.+/)

  await page.getByRole('heading', { name: 'Request a return' }).scrollIntoViewIfNeeded()
  await page.locator('section', { hasText: 'Request a return' }).locator('input[type="checkbox"]').first().check()
  await page.getByRole('button', { name: 'Request return' }).click()
  await expect(page.getByText('No returns yet.')).not.toBeVisible()

  await page.locator('section', { hasText: 'Returns' }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/returns\/.+/)

  await page.getByRole('button', { name: 'Approve' }).click()
  await expect(page.locator('.status').getByText('awaiting_return')).toBeVisible()

  await page.getByRole('button', { name: 'Mark received' }).click()
  await expect(page.locator('.status').getByText('received')).toBeVisible()

  await page.getByRole('button', { name: 'Complete inspection' }).click()
  await expect(page.locator('.status').getByText('inspection')).toBeVisible()

  await page.getByRole('button', { name: 'Complete return' }).click()
  await expect(page.locator('.status').getByText('completed')).toBeVisible()

  // The restock disposition was actually applied to Inventory, not just
  // recorded as a decision (spec section 8: only after inspection).
  await expect(page.getByText(/Applied/)).toBeVisible()

  // Refunds & Financial Ledger (Milestone 9) — request a manual refund
  // (no provider call, completes synchronously) against the now-restocked
  // return and confirm the ledger/financial timeline reflect it. Still the
  // same admin login/order as above — see the comment above the return
  // section for why.
  await page.getByRole('link', { name: 'View order' }).click()
  await page.waitForURL(/\/orders\/.+/)

  await page.getByRole('heading', { name: 'Request a refund' }).scrollIntoViewIfNeeded()
  await page.locator('section', { hasText: 'Request a refund' }).locator('input[type="checkbox"]').first().check()
  await page.getByRole('button', { name: 'Request refund' }).click()
  await expect(page.getByText('No refunds yet.')).not.toBeVisible()

  await page.locator('section', { hasText: 'Refunds' }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/refunds\/.+/)
  await expect(page.locator('.status').getByText('completed')).toBeVisible()
  await expect(page.getByText('manual')).toBeVisible()

  // Back on the order page, the ledger/financial timeline both reflect
  // the refund — proving Milestone 9's Payment -> ... -> Ledger -> Order
  // Financial Status pipeline actually ran, not just the Refund row.
  await page.getByRole('link', { name: 'View order' }).click()
  await page.waitForURL(/\/orders\/.+/)

  // Scoped by which section actually *contains* the heading, not by
  // substring — the Financial timeline's own event description text
  // ("Ledger entries posted for refund #...") would otherwise also match
  // a plain hasText: 'Ledger' filter.
  const ledgerSection = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Ledger', exact: true }) })
  await expect(ledgerSection.getByText('Refund', { exact: true })).toBeVisible()

  const timelineSection = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Financial timeline', exact: true }) })
  await expect(timelineSection.getByText('refund_completed')).toBeVisible()
})

import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares (see playwright.config.ts). Covers the Milestone 21 admin
 * surface: the seeded default fake provider/channels, creating a
 * template and an event routing rule, composing an ad-hoc notification
 * from the Notification Center and seeing it land, and the Delivery
 * Log's manual retry action against the fake provider's deterministic
 * failure sentinel (`@fail.test`). Deliberately does not assert on a
 * live Platform-Event-triggered notification (that requires firing a
 * real commerce event plus `outbox:process`, already covered thoroughly
 * at the API/pipeline level in tests/Feature/Notifications/ and
 * tests/Concurrency/NotificationDeliveryConcurrencyTest.php) — this
 * spec only exercises the admin pages and the CRUD/compose/retry flows.
 * Shares the platform's 5/minute/IP login rate limit with every other
 * admin-*.spec.ts.
 */
test('manages notification templates, channels, providers, and the delivery log end to end from the admin', async ({ page }) => {
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

  await page.getByRole('button', { name: 'Switch store' }).click()
  await page.getByRole('option', { name: /E2E Store/ }).click()
  await expect(page.getByRole('button', { name: 'Switch store' })).toContainText('E2E Store')

  // Channels: the default fake provider + 5 channels auto-seed on first read.
  await page.goto(`${ADMIN_BASE}/notifications/channels`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('row', { name: /email/ })).toBeVisible()

  // Providers: the seeded default.
  await page.goto(`${ADMIN_BASE}/notifications/providers`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('cell', { name: 'fake', exact: true })).toBeVisible()

  // Templates: create one.
  await page.goto(`${ADMIN_BASE}/notifications/templates`)
  await page.waitForLoadState('networkidle')
  const templateName = `E2E Order Confirmation ${Date.now()}`
  const templateBuilder = page.locator('.builder').first()
  await templateBuilder.getByLabel('Name', { exact: true }).fill(templateName)
  await templateBuilder.getByLabel('Subject', { exact: true }).fill('Your order is confirmed')
  await templateBuilder.locator('textarea').fill('Thanks for your order, {{customer.first_name}}!')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/notification-templates') && response.request().method() === 'POST'),
    templateBuilder.getByRole('button', { name: 'Create template' }).click(),
  ])
  await expect(page.getByRole('cell', { name: templateName })).toBeVisible()

  // Event routing: route CustomerCreated -> the new template on email.
  const routingForm = page.locator('.routing-form')
  await routingForm.getByLabel('Event type').fill('CustomerCreated')
  await routingForm.getByLabel('Template').selectOption({ label: `${templateName} (email)` })
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/notification-events') && response.request().method() === 'POST'),
    routingForm.getByRole('button', { name: 'Add routing rule' }).click(),
  ])
  await expect(page.getByRole('cell', { name: 'CustomerCreated' })).toBeVisible()

  // Notification Center: compose an ad-hoc notification that will fail
  // deterministically against the fake provider's reserved sentinel,
  // then retry it from the Delivery Log.
  await page.goto(`${ADMIN_BASE}/notifications`)
  await page.waitForLoadState('networkidle')
  const composer = page.locator('.composer')
  await composer.getByPlaceholder('Optional override').fill('always-fails@fail.test')
  await composer.locator('textarea').fill('This will fail on purpose.')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/notifications') && response.request().method() === 'POST'),
    composer.getByRole('button', { name: 'Send' }).click(),
  ])
  await page.waitForLoadState('networkidle')

  // The send itself queues a real job (QUEUE_CONNECTION=redis in this
  // dev environment, processed by Horizon) — it can complete a moment
  // after the create request returns, so poll rather than asserting
  // on the very next render.
  await expect(async () => {
    await page.reload()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('tbody tr').first().locator('.badge')).toHaveText('failed')
  }).toPass({ timeout: 15_000 })

  await page.goto(`${ADMIN_BASE}/notifications/deliveries`)
  await page.waitForLoadState('networkidle')
  await page.locator('.filters select').selectOption('failed')
  await page.waitForLoadState('networkidle')
  const failedRow = page.locator('tbody tr').first()
  await expect(failedRow.locator('.badge')).toHaveText('failed')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/retry') && response.request().method() === 'POST'),
    failedRow.getByRole('button', { name: 'Retry now' }).click(),
  ])
})

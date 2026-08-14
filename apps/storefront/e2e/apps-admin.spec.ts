import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares. Covers the Apps SDK admin surface end to end (Milestone 12):
 * register a private app (client secret shown once), view its detail
 * page, install it on the active store, and confirm the installation
 * state (no tokens/webhooks yet — those only exist once an app
 * completes the OAuth flow, covered at the API level by
 * tests/Feature/Apps/OAuthFlowTest.php on the backend). One test,
 * deliberately: shares the platform's 5/minute/IP login rate limit with
 * every other admin-*.spec.ts (see those files' comments on the same
 * constraint).
 */
test('registers a private app, shows the client secret once, and installs it on the active store', async ({ page }) => {
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

  await page.getByRole('link', { name: 'Apps' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/apps`)

  const appName = `E2E Test App ${Date.now()}`
  await page.getByPlaceholder('Name').fill(appName)
  await page.getByPlaceholder('Slug (my-app)').fill(`e2e-test-app-${Date.now()}`)
  await page.getByPlaceholder('https://app.example.com/oauth/callback').fill('https://app.example.test/callback')
  await page.getByRole('checkbox', { name: 'orders.read' }).check()
  await page.getByRole('button', { name: 'Register app' }).click()

  await expect(page.getByText('Client secret (shown once')).toBeVisible()

  await page.getByRole('row', { name: appName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/apps\/.+/)

  await expect(page.getByText('Not installed on this store.')).toBeVisible()
  await page.getByRole('button', { name: 'Install' }).click()

  await expect(page.getByRole('row', { name: /Status\s+active/i })).toBeVisible()
  await expect(page.getByText('No tokens issued yet')).toBeVisible()
})

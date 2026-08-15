import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares (see playwright.config.ts). Covers the Milestone 20 admin
 * surface: the auto-created default dashboard, the Widget Builder
 * (create/edit/remove a widget, drill down), the Report builder (run a
 * report, view its result, trigger an export from the Export Center),
 * and Saved Reports (create, run, delete). Deliberately does not assert
 * on populated metric values (that requires real order/payment/refund
 * lifecycle events plus `outbox:process`, already covered thoroughly at
 * the API/pipeline level in tests/Feature/Analytics/ and
 * tests/Concurrency/AnalyticsSnapshotConcurrencyTest.php) — this spec
 * only exercises the pages rendering and the CRUD/run/export flows
 * correctly against a store with no analytics data yet. Shares the
 * platform's 5/minute/IP login rate limit with every other
 * admin-*.spec.ts.
 */
test('manages the analytics dashboard, a report, and a saved report end to end from the admin', async ({ page }) => {
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

  // Dashboard auto-creates on first visit.
  await page.goto(`${ADMIN_BASE}/analytics`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('heading', { name: 'Analytics' })).toBeVisible()

  // Add a metric-card widget.
  const widgetTitle = `E2E Revenue ${Date.now()}`
  const builder = page.locator('.builder').first()
  await builder.getByLabel('Title').fill(widgetTitle)
  await builder.locator('select').nth(0).selectOption('metric_card')
  await builder.locator('select').nth(1).selectOption('gross_revenue')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/analytics/dashboards/') && response.url().includes('/widgets') && response.request().method() === 'POST'),
    builder.getByRole('button', { name: 'Add widget' }).click(),
  ])

  const widgetCard = page.locator('.widget', { hasText: widgetTitle })
  await expect(widgetCard).toBeVisible()

  // Edit it.
  await widgetCard.getByRole('button', { name: 'Edit' }).click()
  const editedTitle = `${widgetTitle} (edited)`
  await builder.getByLabel('Title').fill(editedTitle)
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/analytics/widgets/') && response.request().method() === 'PATCH'),
    builder.getByRole('button', { name: 'Save widget' }).click(),
  ])
  await expect(page.locator('.widget', { hasText: editedTitle })).toBeVisible()

  // Drill down — renders the section even with zero matching events.
  await page.locator('.widget', { hasText: editedTitle }).getByRole('button', { name: 'Details' }).click()
  await expect(page.locator('.drill-down')).toBeVisible()
  await page.locator('.drill-down .link', { hasText: 'Close' }).click()

  // Remove it.
  page.once('dialog', dialog => dialog.accept())
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/analytics/widgets/') && response.request().method() === 'DELETE'),
    page.locator('.widget', { hasText: editedTitle }).getByRole('button', { name: 'Remove' }).click(),
  ])
  await expect(page.locator('.widget', { hasText: editedTitle })).toHaveCount(0)

  // Reports: run an orders report.
  await page.goto(`${ADMIN_BASE}/analytics/reports`)
  await page.waitForLoadState('networkidle')
  await page.locator('.builder select').first().selectOption('orders')
  await Promise.all([
    page.waitForURL(/\/analytics\/reports\/.+/),
    page.getByRole('button', { name: 'Run report' }).click(),
  ])
  await page.waitForLoadState('networkidle')
  await expect(page.locator('.meta-row .badge')).toHaveText('completed')

  // Export Center: trigger a CSV export and expect a download link.
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/exports') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Export CSV' }).click(),
  ])
  await expect(page.locator('.exports-table')).toContainText('CSV')
  await expect(page.locator('.exports-table a', { hasText: 'Download' })).toBeVisible()

  // Saved Reports: create, run, delete.
  await page.goto(`${ADMIN_BASE}/analytics/saved-reports`)
  await page.waitForLoadState('networkidle')
  const savedReportName = `E2E Orders Report ${Date.now()}`
  await page.getByLabel('Name').fill(savedReportName)
  await page.locator('.builder select').selectOption('orders')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/analytics/saved-reports') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Save report' }).click(),
  ])
  const savedRow = page.getByRole('row', { name: new RegExp(savedReportName) })
  await expect(savedRow).toBeVisible()

  await Promise.all([
    page.waitForURL(/\/analytics\/reports\/.+/),
    savedRow.getByRole('button', { name: 'Run' }).click(),
  ])
  await page.waitForLoadState('networkidle')
  await expect(page.locator('.meta-row .badge')).toHaveText('completed')

  await page.goto(`${ADMIN_BASE}/analytics/saved-reports`)
  await page.waitForLoadState('networkidle')
  page.once('dialog', dialog => dialog.accept())
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/analytics/saved-reports/') && response.request().method() === 'DELETE'),
    page.getByRole('row', { name: new RegExp(savedReportName) }).getByRole('button', { name: 'Delete' }).click(),
  ])
  await expect(page.getByRole('row', { name: new RegExp(savedReportName) })).toHaveCount(0)
})

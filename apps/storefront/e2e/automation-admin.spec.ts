import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares (see playwright.config.ts). Covers the Milestone 19 admin
 * surface: create a workflow, set its trigger, build a condition and an
 * action, save, reload to confirm persistence, publish, disable/
 * re-enable, and the templates gallery's "use this template" flow.
 * Deliberately does not assert on a live execution actually running
 * (that requires a real trigger event plus a queue worker or manual
 * `outbox:process` + job dispatch, already covered thoroughly at the
 * API/engine level in tests/Feature/Automation/ and tests/Concurrency/
 * WorkflowExecutionConcurrencyTest.php) — this spec only exercises the
 * Execution History/Logs *pages* rendering correctly, not a specific
 * execution's presence. Shares the platform's 5/minute/IP login rate
 * limit with every other admin-*.spec.ts.
 */
test('manages a workflow end to end from the admin, and uses a starter template', async ({ page }) => {
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

  // Activating a store (its own backend status) is separate from
  // selecting it as this admin session's active working store — done
  // through StoreSwitcher, in the sidebar on every page.
  await page.getByRole('button', { name: 'Switch store' }).click()
  await page.getByRole('option', { name: /E2E Store/ }).click()
  await expect(page.getByRole('button', { name: 'Switch store' })).toContainText('E2E Store')

  // Create a workflow, then open its editor.
  const workflowName = `E2E High Value Order ${Date.now()}`
  await page.goto(`${ADMIN_BASE}/automation`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Name').fill(workflowName)
  await page.getByRole('button', { name: 'Create workflow' }).click()
  await page.waitForURL(/\/automation\/.+/)
  await page.waitForLoadState('networkidle')

  await expect(page.locator('.status-row .badge')).toHaveText('draft')

  // Trigger.
  // Scoped to `main`: the topbar's own language-switcher <select>
  // (Milestone 26) is also a select, sitting outside `main`.
  await page.getByRole('main').locator('select').first().selectOption('CustomerCreated')

  // Condition: order.total_amount greater_than 50000.
  await page.getByRole('button', { name: '+ Condition' }).click()
  const conditionNode = page.locator('.node').first()
  await conditionNode.locator('input[type="text"]').first().fill('order.total_amount')
  await conditionNode.locator('select').nth(1).selectOption('greater_than')
  await conditionNode.locator('input[type="text"]').nth(1).fill('50000')

  // Action: send an in-app notification.
  await page.getByRole('button', { name: '+ Action' }).click()
  const actionNode = page.locator('.action').first()
  await actionNode.locator('select').selectOption('send_in_app_notification')
  await actionNode.getByLabel('Body', { exact: true }).fill('High value order came in')

  // Save, then wait for the actual PATCH to resolve before reloading —
  // otherwise the reload aborts the in-flight request client-side (the
  // exact race diagnosed and fixed in customer-intelligence-admin.spec.ts).
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/automation/workflows/') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save' }).click(),
  ])
  await page.reload()
  await page.waitForLoadState('networkidle')

  await expect(page.getByRole('main').locator('select').first()).toHaveValue('CustomerCreated')
  await expect(page.locator('.node').first().locator('input[type="text"]').first()).toHaveValue('order.total_amount')
  await expect(page.locator('.action').first().getByLabel('Body', { exact: true })).toHaveValue('High value order came in')

  // Publish.
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/publish') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Publish' }).click(),
  ])
  await expect(page.locator('.status-row .badge')).toHaveText('published')

  // Disable, then re-enable.
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/disable') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Disable' }).click(),
  ])
  await expect(page.locator('.status-row .badge')).toHaveText('disabled')

  await Promise.all([
    page.waitForResponse(response => response.url().includes('/enable') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Enable' }).click(),
  ])
  await expect(page.locator('.status-row .badge')).toHaveText('published')

  // Version history shows exactly one published version (no edits after publish yet).
  await expect(page.getByRole('cell', { name: '1', exact: true })).toBeVisible()

  // Templates gallery: use a starter template and confirm it lands on a
  // brand-new draft workflow's editor.
  await page.goto(`${ADMIN_BASE}/automation/templates`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('heading', { name: 'Welcome customer' })).toBeVisible()
  await page.getByRole('heading', { name: 'Welcome customer' }).locator('..').getByRole('button', { name: 'Use this template' }).click()
  await page.waitForURL(/\/automation\/.+/)
  await page.waitForLoadState('networkidle')
  await expect(page.locator('.status-row .badge')).toHaveText('draft')
  await expect(page.getByRole('main').locator('select').first()).toHaveValue('CustomerCreated')

  // Execution history page renders without error.
  await page.goto(`${ADMIN_BASE}/automation/executions`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('heading', { name: 'Execution History' })).toBeVisible()
})

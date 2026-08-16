import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares (see playwright.config.ts). Covers the Milestone 24 admin
 * surface: Russian Legal Details (including a real INN-checksum
 * validation error before a valid save), Tax/VAT Settings,
 * Fiscalization Settings (creating and activating the fake provider),
 * Payment Methods, and the read-only Fiscal Receipts list. Shares the
 * platform's 5/minute/IP login rate limit with every other
 * admin-*.spec.ts.
 */
test('manages Russian legal details, VAT settings, fiscalization settings, and payment methods end to end from the admin', async ({ page }) => {
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

  // Legal Details — an invalid INN checksum is rejected before a valid one saves.
  await page.goto(`${ADMIN_BASE}/russian-commerce/legal-profile`)
  await page.waitForLoadState('networkidle')
  await page.getByLabel('Legal entity type').selectOption('legal_entity')
  await page.getByLabel('Legal name').fill('OOO E2E Test Store')
  await page.getByLabel('INN', { exact: true }).fill('1234567890')
  await page.getByLabel('KPP').fill('770701001')
  await page.getByRole('button', { name: /Save legal details/ }).click()
  await expect(page.locator('.error')).toContainText('The INN is not valid for the given legal entity type.')

  await page.getByLabel('INN', { exact: true }).fill('7707083893')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/russian-commerce/legal-profile') && response.request().method() === 'PUT'),
    page.getByRole('button', { name: /Save legal details/ }).click(),
  ])
  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(page.getByLabel('Legal name')).toHaveValue('OOO E2E Test Store')
  await expect(page.getByLabel('INN', { exact: true })).toHaveValue('7707083893')

  // Tax / VAT Settings.
  await page.goto(`${ADMIN_BASE}/russian-commerce/tax-settings`)
  await page.waitForLoadState('networkidle')
  await page.getByLabel('Default VAT rate').selectOption('vat_20')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/russian-commerce/fiscalization-settings') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save' }).click(),
  ])
  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(page.getByLabel('Default VAT rate')).toHaveValue('vat_20')

  // Fiscalization Settings — create a fake provider, then activate it and
  // require receipts.
  await page.goto(`${ADMIN_BASE}/russian-commerce/fiscalization-settings`)
  await page.waitForLoadState('networkidle')
  const providerCode = `e2efake${Date.now()}`
  await page.getByLabel('Code').fill(providerCode)
  await page.getByLabel('Name').fill('E2E Fake Provider')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/russian-commerce/fiscalization-providers') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create provider' }).click(),
  ])
  await expect(page.getByRole('cell', { name: providerCode })).toBeVisible()

  await page.getByLabel('Active provider').selectOption({ label: `E2E Fake Provider (${providerCode})` })
  await page.getByLabel('Receipts required').check()
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/russian-commerce/fiscalization-settings') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save settings' }).click(),
  ])
  await expect(page.getByText(/no active provider is selected/)).not.toBeVisible()

  // Payment Methods.
  await page.goto(`${ADMIN_BASE}/russian-commerce/payment-methods`)
  await page.waitForLoadState('networkidle')
  await page.getByLabel('Bank card').check()
  await page.getByLabel('SBP (Fast Payments System)').check()
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/russian-commerce/payment-method-settings') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save' }).click(),
  ])
  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(page.getByLabel('Bank card')).toBeChecked()
  await expect(page.getByLabel('SBP (Fast Payments System)')).toBeChecked()
  await expect(page.getByLabel('Cash')).not.toBeChecked()

  // Fiscal Receipts — read-only list loads without error (no receipts
  // exist yet for this store; the empty state renders cleanly).
  await page.goto(`${ADMIN_BASE}/russian-commerce/fiscal-receipts`)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('heading', { name: 'Fiscal Receipts' })).toBeVisible()
  await expect(page.locator('.error')).not.toBeVisible()
})

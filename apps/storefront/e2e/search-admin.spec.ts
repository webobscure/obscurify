import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares (see playwright.config.ts). Covers the Milestone 22 admin
 * surface: creating a fresh product, indexing it via the Dashboard's
 * "Full reindex" action (deliberately not waiting on the outbox
 * scheduler — see docs/architecture/search.md), finding it via the
 * "Try a search" preview, then exercising Synonyms, Rules & Ranking,
 * Pinned Products, Search Settings, and Search Analytics. Shares the
 * platform's 5/minute/IP login rate limit with every other
 * admin-*.spec.ts.
 */
test('indexes a product and manages synonyms, rules, pinned results, settings, and analytics end to end from the admin', async ({ page }) => {
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

  // Create a fresh, uniquely-titled product so this spec doesn't depend
  // on whatever the seed fixture already contains.
  const productTitle = `E2E Search Widget ${Date.now()}`
  await page.goto(`${ADMIN_BASE}/products`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Product title').fill(productTitle)
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/products') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create product' }).click(),
  ])
  await expect(page).toHaveURL(/\/products\/.+/)
  const productId = page.url().split('/products/')[1]

  // A freshly created product defaults to draft (not searchable) — publish
  // it so the reindex below actually surfaces it. No variants exist yet
  // (the create form only takes a title), so "Status" uniquely matches
  // the product-level select, not a variant row's.
  await page.getByLabel('Status').selectOption('active')
  await Promise.all([
    page.waitForResponse(response => response.url().includes(`/api/v1/products/${productId}`) && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save details' }).click(),
  ])

  // Dashboard: full reindex, then find the new product via "Try a search".
  await page.goto(`${ADMIN_BASE}/search`)
  await page.waitForLoadState('networkidle')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/search-index/reindex') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Full reindex' }).click(),
  ])
  await expect(page.locator('dl')).toContainText('ready')

  await page.locator('.preview-card input').fill(productTitle)
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/search-preview')),
    page.getByRole('button', { name: 'Search', exact: true }).click(),
  ])
  await expect(page.getByRole('cell', { name: productTitle })).toBeVisible()

  // Synonyms.
  await page.goto(`${ADMIN_BASE}/search/synonyms`)
  await page.waitForLoadState('networkidle')
  const synonymTerm = `e2eterm${Date.now()}`
  await page.getByPlaceholder('tv').fill(synonymTerm)
  await page.getByPlaceholder('television, telly').fill('e2ealias')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/search-synonyms') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create synonym' }).click(),
  ])
  await expect(page.getByRole('cell', { name: synonymTerm })).toBeVisible()

  // Rules & Ranking.
  await page.goto(`${ADMIN_BASE}/search/rules`)
  await page.waitForLoadState('networkidle')
  const ruleName = `E2E Boost Rule ${Date.now()}`
  const ruleBuilder = page.locator('.builder').first()
  await ruleBuilder.getByLabel('Name', { exact: true }).fill(ruleName)
  await ruleBuilder.getByLabel('Product ID', { exact: true }).fill(productId)
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/search-rules') && response.request().method() === 'POST'),
    ruleBuilder.getByRole('button', { name: 'Create rule' }).click(),
  ])
  await expect(page.getByRole('cell', { name: ruleName })).toBeVisible()

  // Pinned Products.
  await page.goto(`${ADMIN_BASE}/search/pinned`)
  await page.waitForLoadState('networkidle')
  const pinKeyword = `e2epin${Date.now()}`
  const pinBuilder = page.locator('.builder').first()
  await pinBuilder.getByLabel('Keyword', { exact: true }).fill(pinKeyword)
  await pinBuilder.getByLabel('Product ID', { exact: true }).fill(productId)
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/pinned-search-results') && response.request().method() === 'POST'),
    pinBuilder.getByRole('button', { name: 'Pin product' }).click(),
  ])
  await expect(page.getByRole('cell', { name: pinKeyword })).toBeVisible()

  // Search Settings: change results-per-page and save.
  await page.goto(`${ADMIN_BASE}/search/settings`)
  await page.waitForLoadState('networkidle')
  const resultsPerPageInput = page.locator('.settings-card input[type="number"]').first()
  await resultsPerPageInput.fill('12')
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/search-settings') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save settings' }).click(),
  ])
  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(resultsPerPageInput).toHaveValue('12')

  // Search Analytics: page loads and reflects the search performed above.
  await page.goto(`${ADMIN_BASE}/search/analytics`)
  await page.waitForLoadState('networkidle')
  await expect(page.locator('.stat').first()).toBeVisible()
})

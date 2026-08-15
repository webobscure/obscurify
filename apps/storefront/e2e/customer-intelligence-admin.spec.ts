import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'
const STOREFRONT_BASE = 'http://e2e-storefront.localhost:3100'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares (see playwright.config.ts). Covers the Milestone 18 admin
 * surface: manual customer groups (create, add/remove a member),
 * customer tags (create, manually assign/remove on a customer), and the
 * segment builder (create a segment, add a nested AND/OR rule tree,
 * reload, confirm it persisted). Deliberately does not assert on live
 * segment *membership* appearing on the customer detail page — that only
 * updates on the next RecomputeCustomerMetrics-triggering event (an
 * order, registration, or profile update), none of which the admin UI
 * itself can trigger for an existing customer (profile edits are the
 * customer's own, via the storefront portal) — that computation is
 * already covered thoroughly at the API level in
 * tests/Feature/CustomerIntelligence/SegmentRecalculationTest.php.
 * Shares the platform's 5/minute/IP login rate limit with every other
 * admin-*.spec.ts.
 */
test('manages a customer group, a customer tag, and a nested-rule segment from the admin', async ({ page }) => {
  const email = `e2e-ci-customer-${Date.now()}@example.test`

  // A real customer to exercise group membership and tag assignment
  // against, registered through the storefront the same way every other
  // customer-portal spec does.
  await page.goto(`${STOREFRONT_BASE}/account/register`)
  await page.waitForLoadState('networkidle')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill('super-secret-1')
  await page.getByLabel('Confirm password').fill('super-secret-1')
  await page.getByRole('button', { name: 'Create account' }).click()
  await expect(page).toHaveURL(`${STOREFRONT_BASE}/account`)

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

  // Find the freshly-registered customer's id via the admin search box.
  await page.goto(`${ADMIN_BASE}/customers`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Search email or name…').fill(email)
  await page.getByRole('button', { name: 'Search', exact: true }).click()
  await expect(page.getByRole('row', { name: new RegExp(email) })).toBeVisible()
  await page.getByRole('row', { name: new RegExp(email) }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/customers\/.+/)
  const customerId = page.url().split('/').pop()!

  // Customer Group: create manual, add the member, verify count.
  const groupName = `E2E VIP Wholesale ${Date.now()}`
  await page.goto(`${ADMIN_BASE}/customer-groups`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Name').fill(groupName)
  await page.getByRole('button', { name: 'Create group' }).click()
  await page.getByRole('row', { name: groupName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/customer-groups\/.+/)

  await page.getByPlaceholder('Customer ID').fill(customerId)
  await page.getByRole('button', { name: 'Add' }).click()
  await expect(page.getByText(email)).toBeVisible()

  await page.reload()
  await expect(page.getByText(email)).toBeVisible()

  // Customer Tag: create, then manually assign it from the customer's
  // own detail page.
  const tagName = `E2E Fraud Risk ${Date.now()}`
  await page.goto(`${ADMIN_BASE}/customer-tags`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder(/Name \(e\.g\./).fill(tagName)
  await page.getByRole('button', { name: 'Create tag' }).click()
  await expect(page.getByRole('cell', { name: tagName })).toBeVisible()

  await page.goto(`${ADMIN_BASE}/customers/${customerId}`)
  await page.waitForLoadState('networkidle')
  await page.locator('.add-tag select').selectOption({ label: tagName })
  await page.getByRole('button', { name: 'Assign' }).click()
  await expect(page.getByText(tagName)).toBeVisible()

  // Customer Segment: create, build a nested AND/OR rule tree, save,
  // reload, and confirm the tree survived the round trip.
  const segmentName = `E2E Verified Nordics ${Date.now()}`
  await page.goto(`${ADMIN_BASE}/customer-segments`)
  await page.waitForLoadState('networkidle')
  await page.getByPlaceholder('Name').fill(segmentName)
  await page.getByRole('button', { name: 'Create segment' }).click()
  await page.getByRole('row', { name: segmentName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/customer-segments\/.+/)

  await page.getByRole('button', { name: '+ Condition' }).click()
  const fieldSelects = page.locator('.node select').first()
  await fieldSelects.selectOption('condition')

  // First top-level row: email_verified is_true.
  const firstRow = page.locator('.node').first()
  await firstRow.locator('select').nth(1).selectOption('email_verified')
  await firstRow.locator('select').nth(2).selectOption('is_true')

  // Second top-level row: a nested OR group with two country conditions.
  await page.getByRole('button', { name: '+ Group' }).click()
  const groupNode = page.locator('.node').nth(1)
  await groupNode.locator('select').nth(1).selectOption('or')
  await groupNode.getByRole('button', { name: '+ Condition' }).click()
  await groupNode.getByRole('button', { name: '+ Condition' }).click()

  const childRows = groupNode.locator('.children .node')
  await childRows.nth(0).locator('select').nth(1).selectOption('country_code')
  await childRows.nth(0).locator('select').nth(2).selectOption('equals')
  await childRows.nth(0).locator('input').fill('SE')
  await childRows.nth(1).locator('select').nth(1).selectOption('country_code')
  await childRows.nth(1).locator('select').nth(2).selectOption('equals')
  await childRows.nth(1).locator('input').fill('NO')

  // Save triggers an async PATCH; wait for it to actually resolve before
  // reloading, otherwise the reload aborts the in-flight request client-side
  // and nothing gets persisted.
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/v1/customer-segments/') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save' }).click(),
  ])
  await page.reload()
  await page.waitForLoadState('networkidle')

  // The persisted shape survives the round trip: 2 top-level nodes, the
  // second (group) node has 2 children. Scoped to the outermost
  // .builder specifically — .builder > .node alone would also match the
  // nested builder's own direct children. The nested SegmentRuleBuilder
  // renders its own .builder wrapper inside .children, so the children
  // count is scoped to :scope > .children > .builder > .node.
  const topLevelBuilder = page.locator('.builder').first()
  await expect(topLevelBuilder.locator(':scope > .node')).toHaveCount(2)
  await expect(topLevelBuilder.locator(':scope > .node').nth(1).locator(':scope > .children > .builder > .node')).toHaveCount(2)
})

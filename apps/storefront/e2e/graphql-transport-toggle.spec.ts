import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — same fixture
 * golden-path.spec.ts uses. Proves Milestone 23's spec section 9 claim
 * concretely: the products listing page renders the identical product
 * list whether it's fetched over REST (default) or GraphQL
 * (?transport=graphql), because StorefrontGraphQLClient reshapes its
 * response into the exact same contract StorefrontApiClient returns —
 * see products/index.vue's own docblock on the `transport` toggle.
 */
test('renders the same product listing over REST and GraphQL transports', async ({ page }) => {
  await page.goto(`${BASE}/products`)
  await expect(page.getByText('E2E Shirt')).toBeVisible()
  await expect(page.locator('.transport-toggle a.active')).toHaveText('REST')

  await page.getByRole('link', { name: 'GraphQL' }).click()
  await expect(page).toHaveURL(/transport=graphql/)
  await expect(page.getByText('E2E Shirt')).toBeVisible()
  await expect(page.locator('.transport-toggle a.active')).toHaveText('GraphQL')

  await page.getByRole('link', { name: 'REST' }).click()
  await expect(page).toHaveURL(/transport=rest/)
  await expect(page.getByText('E2E Shirt')).toBeVisible()
})

test('the GraphQL playground page loads and can run a query in a real browser', async ({ page }) => {
  // Same API server every other spec in this suite requires already
  // running (see playwright.config.ts's own docblock: `php artisan
  // serve` on :8000, separate from the Nuxt dev servers this config
  // manages). Hostname must be one with a seeded Domain row —
  // GraphQLAuthenticator resolves tenant from the Host header exactly
  // like the storefront REST API does, and bare "localhost" resolves
  // no store (see EnsureStorefrontTenantContext).
  await page.goto('http://e2e-storefront.localhost:8000/api/graphql/playground')
  await expect(page.getByRole('heading', { name: 'GraphQL Playground' })).toBeVisible()

  await page.getByRole('button', { name: /Run/ }).click()
  await expect(page.locator('#result')).toContainText('"data"', { timeout: 10_000 })
})

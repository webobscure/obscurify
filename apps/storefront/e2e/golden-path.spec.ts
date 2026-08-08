import { expect, test } from '@playwright/test'

const HOST = 'e2e-storefront.localhost:3100'
const BASE = `http://${HOST}`

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) to have been run
 * against the same database this app's API server is pointed at — see
 * playwright.config.ts. Store/domain/product/variant/stock are all
 * deterministic and idempotent, so this spec is safe to re-run as-is.
 */
test('browse, select a variant, add to cart, change quantity, and persist after reload', async ({ page }) => {
  await page.goto(`${BASE}/products`)
  await expect(page.getByText('E2E Shirt')).toBeVisible()

  await page.getByRole('link', { name: /E2E Shirt/ }).first().click()
  await expect(page).toHaveURL(`${BASE}/products/e2e-shirt`)
  await expect(page.getByRole('heading', { name: 'E2E Shirt' })).toBeVisible()

  // The fixture has a single "M" variant, pre-selected by default.
  await expect(page.getByRole('button', { name: 'M', exact: true })).toHaveClass(/selected/)

  await page.getByRole('button', { name: 'Add to cart' }).click()
  await expect(page.getByText('Cart(1)')).toBeVisible()

  await page.getByRole('link', { name: /Cart/ }).click()
  await expect(page).toHaveURL(`${BASE}/cart`)
  // The cart line shows the variant title/SKU, not the product title.
  await expect(page.getByText('E2E-SHIRT-M')).toBeVisible()

  const quantityInput = page.locator('input[type="number"]')
  await expect(quantityInput).toHaveValue('1')

  await quantityInput.fill('3')
  await quantityInput.dispatchEvent('change')
  await expect(page.getByText('Cart(3)')).toBeVisible()
  await expect(quantityInput).toHaveValue('3')

  await page.reload()
  await expect(page.locator('input[type="number"]')).toHaveValue('3')
  await expect(page.getByText('Cart(3)')).toBeVisible()
})

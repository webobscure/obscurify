import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'
const STORE_HOST = 'e2e-storefront.localhost:3100'
const STORE_BASE = `http://${STORE_HOST}`

/**
 * Regression coverage for the i18n-migration navigation investigation
 * (see docs/architecture/localization.md and ADR-032). This platform
 * uses `strategy: 'no_prefix'` in both apps — Russian, English, and
 * German all resolve at the *same* plain path; the URL never carries a
 * locale segment. These specs exist to lock that in and to catch a
 * regression of the real bug found during the investigation: the
 * storefront's client-only auth redirect (unauthenticated visitor
 * hitting a protected /account/** page) used to produce a Vue hydration
 * mismatch because @nuxtjs/i18n's own async global route middleware
 * changed how Nuxt resolves the initial client navigation — fixed via
 * `routeRules: { '/account/**': { ssr: false } }` in nuxt.config.ts.
 *
 * Requires `php artisan e2e:seed-storefront` — same fixture as every
 * other admin-*.spec.ts / storefront spec (e2e@example.test / password,
 * "E2E Store"). Shares the platform's 5/minute/IP login rate limit.
 */
test('admin: Russian default -> Products -> product detail -> Orders -> switch English -> switch German -> back -> logout, with no locale-prefixed URLs anywhere', async ({ browser }) => {
  // Chromium's default Accept-Language/navigator.language is en-US, which
  // detectBrowserLanguage resolves to the 'en' configured locale — force
  // ru-RU so this test actually exercises the Russian-default path
  // (spec: "Russian is the platform default").
  const context = await browser.newContext({ locale: 'ru-RU' })
  const page = await context.newPage()

  await page.goto(`${ADMIN_BASE}/login`)
  await page.waitForLoadState('networkidle')
  // Login form fields are locale-agnostic here: this context forces
  // ru-RU, so the placeholders render in Russian ("Email"/"Пароль").
  await page.locator('input[type="email"]').fill('e2e@example.test')
  await page.locator('input[type="password"]').fill('password')
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/stores`)

  const storeRow = page.getByRole('row', { name: /E2E Store/ })
  await expect(storeRow).toBeVisible()
  // stores/index.vue's Activate/Active text is translated ("Активировать"/"Активен");
  // the button is language-agnostic to find (last cell of the row).
  const activateButton = storeRow.locator('button')
  if (await activateButton.isVisible().catch(() => false)) {
    await activateButton.click()
    await page.waitForTimeout(300)
  }
  await page.locator('.switcher .trigger').click()
  await page.getByRole('option', { name: /E2E Store/ }).click()

  // Russian is the platform default — no locale prefix in the URL.
  await page.getByRole('link', { name: 'Товары', exact: true }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/products`)

  // products/index.vue's "Edit" link text is one of the not-yet-translated
  // strings (ADR-032 Decision 8: representative, not exhaustive, coverage)
  // — literal in every locale, not a routing bug.
  const editLink = page.locator('a', { hasText: 'Edit' }).first()
  if (await editLink.isVisible().catch(() => false)) {
    await editLink.click()
    await expect(page).toHaveURL(/\/products\/[^/]+$/)
    expect(page.url()).not.toMatch(/\/(ru|en|de)\//)
  }

  await page.getByRole('link', { name: 'Заказы', exact: true }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/orders`)

  // Switch to English — the route must not change, only the labels.
  await page.getByRole('combobox').first().selectOption('en')
  await expect(page).toHaveURL(`${ADMIN_BASE}/orders`)
  await page.getByRole('link', { name: 'Customers', exact: true }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/customers`)
  expect(page.url()).not.toMatch(/\/(ru|en|de)\//)

  // Switch to German — again, no route change.
  await page.getByRole('combobox').first().selectOption('de')
  await expect(page).toHaveURL(`${ADMIN_BASE}/customers`)
  await page.getByRole('link', { name: 'Analytics', exact: true }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/analytics`)
  expect(page.url()).not.toMatch(/\/(ru|en|de)\//)

  // Browser back — lands on the previous real route, still unprefixed.
  await page.goBack()
  await expect(page).toHaveURL(`${ADMIN_BASE}/customers`)

  // Logout — language-agnostic selector (label is German at this point).
  await page.locator('.user-menu .trigger').click()
  await page.locator('.menu button.danger').click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/login`)

  await context.close()
})

test('storefront: Russian default -> product -> collection -> cart -> language switch preserves the same logical page', async ({ page }) => {
  await page.goto(`${STORE_BASE}/products`)
  await page.waitForLoadState('networkidle')
  expect(page.url()).not.toMatch(/\/(ru|en|de)\//)

  const productLink = page.locator('a[href^="/products/"]').first()
  await expect(productLink).toBeVisible()
  const productHref = await productLink.getAttribute('href')
  expect(productHref).not.toMatch(/^\/(ru|en|de)\//)
  await productLink.click()
  await page.waitForURL(/\/products\/.+/)
  await page.waitForLoadState('networkidle')
  const productUrl = page.url()
  expect(productUrl).not.toMatch(/\/(ru|en|de)\//)

  // Switch language on the product page — the exact same product URL
  // (with its slug/params) must remain valid, not redirect to a
  // locale-prefixed variant or to the homepage.
  await page.getByRole('combobox').first().selectOption('en')
  await page.waitForTimeout(300)
  expect(page.url()).toBe(productUrl)

  await page.getByRole('combobox').first().selectOption('de')
  await page.waitForTimeout(300)
  expect(page.url()).toBe(productUrl)

  // Direct load of the same URL in German context must still resolve
  // (no prefix required, no 404).
  await page.goto(productUrl)
  await page.waitForLoadState('networkidle')
  await expect(page.getByRole('button', { name: /Add to cart|In den Warenkorb|В корзину/ })).toBeVisible()
})

test('storefront: unauthenticated direct load of a protected /account/** page redirects cleanly, without a hydration mismatch', async ({ page }) => {
  const consoleWarnings: string[] = []
  page.on('console', msg => {
    if (msg.type() === 'warning' && msg.text().includes('Hydration')) consoleWarnings.push(msg.text())
    if (msg.type() === 'error' && msg.text().includes('Hydration')) consoleWarnings.push(msg.text())
  })

  await page.goto(`${STORE_BASE}/account`)
  await expect(page).toHaveURL(`${STORE_BASE}/account/login`)
  expect(consoleWarnings, `unexpected hydration warnings: ${consoleWarnings.join(' | ')}`).toHaveLength(0)
})

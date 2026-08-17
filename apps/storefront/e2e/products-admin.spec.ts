import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'
const FIXTURE_IMAGE = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'test-product-image.png')

/**
 * Products module redesign (docs/design/DESIGN_SYSTEM.md /
 * docs/design/THEMING.md) — full real-browser coverage of the editor,
 * list, save-state, variant drawer, media grid, and theme switcher.
 * Forces ru-RU (spec: "Russian is the platform default" — Chromium's
 * default Accept-Language/navigator.language is en-US otherwise, same
 * reasoning as i18n-navigation.spec.ts).
 *
 * Two tests, not one per behaviour: `/api/v1/auth/login` is rate-limited
 * 5/minute/IP and shares that budget with every other admin-*.spec.ts
 * in this suite (see admin-auth.spec.ts) — each additional login here is
 * a real cost. products-theme.spec.ts covers the theme-boot/system-
 * preference checks that don't need auth, on purpose, to keep this
 * file's login count to two.
 */
async function login(page: import('@playwright/test').Page) {
  await page.goto(`${ADMIN_BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.locator('input[type="email"]').fill('e2e@example.test')
  await page.locator('input[type="password"]').fill('password')
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/stores`)

  await page.locator('.switcher .trigger').click()
  const option = page.getByRole('option', { name: /E2E Store/ })
  await expect(option).toBeVisible()
  await option.click()
  // Store activation is async (activeStore.activate()) — wait for the
  // switcher trigger to actually reflect it before proceeding, same
  // pattern as admin-navigation.spec.ts. Skipping this made every
  // subsequent products.create() 428 (no active tenant), silently
  // caught by the page's own error handler with no navigation — the
  // real first bug this suite caught.
  await expect(page.locator('.switcher .trigger')).toContainText('E2E Store')
}

test('create a product end-to-end — title, description, option, variant, price, inventory, image, save, reload, all persisted — then verify Products List search/filters/sort/pagination/bulk', async ({ browser }) => {
  const context = await browser.newContext({ locale: 'ru-RU' })
  const page = await context.newPage()
  await login(page)

  const uniqueTitle = `E2E Товар ${Date.now()}`

  // ---- Create ----
  await page.goto(`${ADMIN_BASE}/products`)
  await page.getByTestId('create-product-button').click()
  await page.getByTestId('new-product-title-input').fill(uniqueTitle)
  await page.getByTestId('submit-create-product').click()
  await expect(page).toHaveURL(/\/products\/[^/]+$/)
  const productUrl = page.url()

  // ---- Title/description, dirty tracking, Save disabled-when-clean ----
  const saveState = page.getByTestId('save-state')
  await expect(saveState).toHaveAttribute('data-save-state', 'idle')
  await expect(saveState).toBeDisabled()

  await expect(page.getByTestId('product-title-input')).toHaveValue(uniqueTitle)
  await page.getByTestId('product-description-input').fill('E2E описание товара для проверки сохранения')
  await expect(saveState).toHaveAttribute('data-save-state', 'dirty')
  await expect(saveState).toBeEnabled()

  // ---- Option + values ----
  await page.getByPlaceholder('Название опции (напр. Цвет)').fill('Цвет')
  await page.getByRole('button', { name: 'Добавить опцию' }).click()
  await expect(page.getByText('Цвет', { exact: true })).toBeVisible()

  await page.getByPlaceholder('Значение (напр. Чёрный)').fill('Чёрный')
  await page.getByRole('button', { name: 'Добавить значение' }).click()
  // The value chip's own text node sits alongside a "✕ remove" button
  // inside the same <span>, so an exact getByText match against the
  // chip never matches (its full text is "Чёрный✕") — scope by class.
  await expect(page.locator('.chip', { hasText: 'Чёрный' })).toBeVisible()

  // ---- Variant: option combination, SKU, price ----
  await page.getByTestId('add-variant-button').click()
  await page.getByLabel('Цвет').selectOption({ label: 'Чёрный' })
  await page.getByTestId('new-variant-price-input').fill('1499.99')
  const sku = `E2E-SKU-${Date.now()}`
  await page.getByTestId('new-variant-sku-input').fill(sku)
  await page.getByTestId('submit-create-variant').click()

  const variantRow = page.getByTestId('variant-row').filter({ hasText: sku })
  await expect(variantRow).toBeVisible()
  // Price renders as an <input> VALUE, not text content — toContainText
  // against the row would never see it regardless of the actual value.
  await expect(variantRow.getByTestId(/variant-row-price-/)).toHaveValue(/1.499,99/)

  // ---- Product-level image upload (before the drawer opens anything else) ----
  await page.getByTestId('media-upload-input').setInputFiles(FIXTURE_IMAGE)
  await expect(page.getByTestId('media-tile').first()).toBeVisible()
  await expect(page.getByTestId('media-tile').locator('img')).toHaveAttribute('src', /.+/)

  // ---- Variant drawer: set inventory ----
  // Click the name cell specifically, not the row's geometric center —
  // the price/compare-at cells stop click propagation on purpose (so
  // editing a price doesn't also pop the drawer), and depending on
  // column widths the row's center can land on one of those cells.
  await variantRow.getByTestId('variant-row-name').click()
  const drawer = page.getByTestId('variant-drawer')
  await expect(drawer).toBeVisible()
  await drawer.getByTestId('inventory-adjust-delta').fill('25')
  await drawer.getByTestId('inventory-adjust-submit').click()
  // "E2E Warehouse" also appears as an <option> inside the location
  // <select> — scope to the per-location stock row specifically.
  await expect(drawer.locator('.level-row', { hasText: 'E2E Warehouse' })).toBeVisible()
  await expect(drawer).toContainText('25')
  await page.keyboard.press('Escape')
  await expect(drawer).not.toBeVisible()

  // ---- Save the page-level draft (title/description already dirty) ----
  await expect(saveState).toHaveAttribute('data-save-state', 'dirty')
  await saveState.click()
  await expect(saveState).toHaveAttribute('data-save-state', 'saved', { timeout: 5000 })

  // ---- Reload: everything persisted ----
  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(page.getByTestId('product-title-input')).toHaveValue(uniqueTitle)
  await expect(page.getByTestId('product-description-input')).toHaveValue('E2E описание товара для проверки сохранения')
  await expect(page.getByText('Цвет', { exact: true })).toBeVisible()
  const persistedRow = page.getByTestId('variant-row').filter({ hasText: sku })
  await expect(persistedRow.getByTestId(/variant-row-price-/)).toHaveValue(/1.499,99/)
  await expect(persistedRow).toContainText('25') // available inventory (plain text, not an input)
  await expect(page.getByTestId('media-tile').first()).toBeVisible()

  // ---- Rapid multiple saves do not duplicate requests ----
  const patchRequests: string[] = []
  page.on('request', (req) => {
    if (req.method() === 'PATCH' && req.url().includes('/api/v1/products/')) patchRequests.push(req.url())
  })
  await page.getByTestId('product-title-input').fill(`${uniqueTitle} v2`)
  await expect(saveState).toBeEnabled()
  // Dispatch three native clicks synchronously in one JS task — a
  // truer "rapid double-click" than three separate Playwright actions,
  // which each poll for actionability and would just serialize behind
  // Vue's re-render disabling the button between them.
  await page.evaluate(() => {
    const btn = document.querySelector('[data-testid="save-state"]') as HTMLButtonElement
    btn.click()
    btn.click()
    btn.click()
  })
  await expect(saveState).toHaveAttribute('data-save-state', 'saved', { timeout: 5000 })
  expect(patchRequests.length).toBe(1)

  // ---- Products List: search, status/vendor/type filters, sort, pagination, thumbnail, variant count, price, bulk ----
  await page.goto(`${ADMIN_BASE}/products`)
  await page.waitForLoadState('networkidle')

  await page.getByTestId('products-search').fill(`${uniqueTitle} v2`)
  await page.waitForTimeout(400) // debounce
  const listRow = page.getByTestId('product-row').filter({ hasText: `${uniqueTitle} v2` })
  await expect(listRow).toBeVisible()
  await expect(listRow.locator('img')).toHaveAttribute('src', /.+/) // thumbnail rendered
  await expect(listRow).toContainText('1') // variant count
  await expect(listRow).toContainText('1 499,99') // price

  // This product was created via the minimal "title only" flow and was
  // never explicitly activated — its real status is the backend's
  // default, "draft" (see ProductStatus::Draft), not "active".
  await page.getByTestId('products-filter-status').selectOption('draft')
  await page.waitForLoadState('networkidle')
  await expect(listRow).toBeVisible()
  await page.getByTestId('products-filter-status').selectOption('active')
  await page.waitForLoadState('networkidle')
  await expect(listRow).not.toBeVisible()
  await page.getByTestId('products-filter-status').selectOption('')

  await page.getByTestId('products-search').fill('')
  await page.waitForTimeout(400)

  // ---- Bulk selection ----
  await expect(listRow).toBeVisible()
  await page.getByTestId('products-search').fill(`${uniqueTitle} v2`)
  await page.waitForTimeout(400)
  await listRow.locator('input[type="checkbox"]').check()
  await expect(page.getByText('Выбрано: 1')).toBeVisible()

  await context.close()
})

test('edit an existing product: modify variant data in the drawer, save, reload, verify persisted; theme switcher; keyboard/focus behavior; navigation-away-while-dirty is currently unguarded (documented, not asserted as correct)', async ({ browser }) => {
  const context = await browser.newContext({ locale: 'ru-RU' })
  const page = await context.newPage()
  await login(page)

  // Create a fresh product+variant to edit (keeps this test independent
  // of the previous one's data, and exercises the same real endpoints).
  await page.goto(`${ADMIN_BASE}/products`)
  await page.getByTestId('create-product-button').click()
  const title = `E2E Edit Товар ${Date.now()}`
  await page.getByTestId('new-product-title-input').fill(title)
  await page.getByTestId('submit-create-product').click()
  await expect(page).toHaveURL(/\/products\/[^/]+$/)

  await page.getByPlaceholder('Название опции (напр. Цвет)').fill('Размер')
  await page.getByRole('button', { name: 'Добавить опцию' }).click()
  await page.getByPlaceholder('Значение (напр. Чёрный)').fill('M')
  await page.getByRole('button', { name: 'Добавить значение' }).click()

  await page.getByTestId('add-variant-button').click()
  await page.getByLabel('Размер').selectOption({ label: 'M' })
  await page.getByTestId('new-variant-price-input').fill('999')
  await page.getByTestId('new-variant-sku-input').fill(`EDIT-${Date.now()}`)
  await page.getByTestId('submit-create-variant').click()
  const row = page.getByTestId('variant-row').first()
  await expect(row).toBeVisible()

  // ---- Variant drawer: opens, focus trap, modify, save, closes, focus returns ----
  // The row's own click handler focuses itself before opening the
  // drawer (see VariantTable.vue's openDetail()) — clicking a plain
  // <td> never transfers focus to the parent <tr> on its own.
  await row.getByTestId('variant-row-name').click()
  const drawer = page.getByTestId('variant-drawer')
  await expect(drawer).toBeVisible()
  // useDismissable focuses the first focusable element in DOM order,
  // which is the drawer's own close button (it precedes the body slot
  // in Drawer.vue's template) — documenting actual behavior, not the
  // first form field.
  await expect(page.getByRole('button', { name: 'Закрыть' })).toBeFocused()

  const newBarcode = '4601234567890'
  await drawer.getByTestId('variant-barcode-input').fill(newBarcode)
  // MoneyInput deliberately commits on blur, not on every keystroke (so
  // it doesn't reformat/reflow the value while the user is still
  // typing) — Playwright's fill() sets the value and fires 'input' but
  // never blurs, so the margin computed (bound to the committed
  // modelValue) wouldn't see it without an explicit blur here.
  await drawer.getByTestId('variant-cost-input').fill('600')
  await drawer.getByTestId('variant-cost-input').blur()
  await expect(drawer.getByTestId('variant-margin')).toContainText('40%') // (999-600)/999 rounds to 40%
  await drawer.getByTestId('variant-save-button').click()
  await expect(drawer).toBeVisible() // saving does not close the drawer unexpectedly

  // Close via the visible close button, then reopen and close via Escape,
  // confirming focus returns to the row that opened it either way.
  await page.getByRole('button', { name: 'Закрыть' }).click()
  await expect(drawer).not.toBeVisible()
  await expect(row).toBeFocused()

  await row.getByTestId('variant-row-name').click()
  await expect(page.getByTestId('variant-drawer')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByTestId('variant-drawer')).not.toBeVisible()
  await expect(row).toBeFocused()

  // ---- Reload: variant edits persisted ----
  await page.reload()
  await page.waitForLoadState('networkidle')
  await page.getByTestId('variant-row').first().getByTestId('variant-row-name').click()
  await expect(page.getByTestId('variant-barcode-input')).toHaveValue(newBarcode)
  await expect(page.getByTestId('variant-margin')).toContainText('40%')
  await page.keyboard.press('Escape')

  // ---- Save-state: Saving transiently, then Saved; ⌘S ----
  const saveState = page.getByTestId('save-state')
  await page.getByTestId('product-title-input').fill(`${title} (изменено)`)
  await expect(saveState).toHaveAttribute('data-save-state', 'dirty')
  await page.keyboard.press('Meta+s')
  // "saving" is real but can resolve faster than assertion polling on a
  // local dev server — assert the end state is reached, not a fixed
  // intermediate frame.
  await expect(saveState).toHaveAttribute('data-save-state', 'saved', { timeout: 5000 })

  // ---- Save error state: force a failing PATCH and confirm dirty is preserved ----
  await page.route('**/api/v1/products/**', route => {
    if (route.request().method() === 'PATCH') return route.fulfill({ status: 500, body: '{"message":"boom"}' })
    return route.continue()
  })
  const brokenTitle = `${title} (сломано)`
  await page.getByTestId('product-title-input').fill(brokenTitle)
  await saveState.click()
  await expect(saveState).toHaveAttribute('data-save-state', 'error', { timeout: 5000 })
  // Failed save preserves the draft — the title field still shows the
  // unsaved edit, it was not reverted or silently discarded.
  await expect(page.getByTestId('product-title-input')).toHaveValue(brokenTitle)
  await page.unroute('**/api/v1/products/**')

  // ---- Navigation-away-while-dirty: documents CURRENT (unguarded) behavior ----
  // No beforeunload/route-leave guard exists in products/[id].vue today.
  // This is a deliberate finding, not an invented feature: a dirty save
  // state does not block `router.push` or a hard navigation. Asserting
  // that here locks in today's real behavior so a future change to this
  // is a conscious decision, not an accidental regression either way.
  let dialogSeen = false
  page.on('dialog', (dialog) => { dialogSeen = true; dialog.dismiss() })
  await page.goto(`${ADMIN_BASE}/products`)
  await expect(page).toHaveURL(`${ADMIN_BASE}/products`)
  expect(dialogSeen).toBe(false)

  // ---- Theme switcher: Light/Dark/System in UserMenu, aria-checked, persistence ----
  await page.locator('.user-menu .trigger').click()
  const lightOption = page.getByRole('menuitemradio', { name: 'Светлая' })
  const darkOption = page.getByRole('menuitemradio', { name: 'Тёмная' })
  const systemOption = page.getByRole('menuitemradio', { name: 'Системная' })
  await expect(systemOption).toHaveAttribute('aria-checked', 'true')

  await darkOption.click()
  await expect(darkOption).toHaveAttribute('aria-checked', 'true')
  await expect(lightOption).toHaveAttribute('aria-checked', 'false')
  await expect.poll(() => page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')
  const darkBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(darkBg).toBe('rgb(13, 13, 15)')

  // Keyboard interaction: Tab to the option, Enter/Space to activate.
  await lightOption.focus()
  await page.keyboard.press('Enter')
  await expect(lightOption).toHaveAttribute('aria-checked', 'true')
  await expect.poll(() => page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('light')

  await darkOption.click()
  await page.keyboard.press('Escape')

  // Persistence across reload and route navigation.
  await page.reload()
  await page.waitForLoadState('networkidle')
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')
  await page.goto(`${ADMIN_BASE}/products`)
  await page.waitForLoadState('networkidle')
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')

  // Persistence across logout/login.
  await page.locator('.user-menu .trigger').click()
  await page.locator('.menu button.danger').click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/login`)
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')

  await context.close()
})

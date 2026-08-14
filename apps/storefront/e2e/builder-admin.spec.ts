import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares. Covers the visual Builder + Theme Customizer end to end
 * (Milestone 15): create a theme (so the built-in Section/Block Library
 * has something to seed against) and a page, open the Builder, add a
 * section and a nested block from the preset pickers, edit settings via
 * the raw-JSON panel, save, undo, redo, publish, and confirm the page
 * renders live on the storefront with what was built — then set a
 * Theme Customizer field and confirm it persists. Deliberately does not
 * exercise the drag-and-drop reorder gesture itself — native HTML5
 * drag-and-drop is unreliable to simulate through Playwright's synthetic
 * event dispatch, and the reorder logic it drives is a plain array
 * splice already covered at the unit level by the code itself being
 * straightforward, not something worth a flaky e2e test. One test,
 * deliberately: shares the platform's 5/minute/IP login rate limit with
 * every other admin-*.spec.ts.
 */
test('builds a page visually with a section and a nested block, publishes it, and customizes the theme', async ({ page }) => {
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

  // A theme must exist and be active so the Section/Block Library has a
  // theme version to seed against, and so publishing the page has
  // somewhere to render.
  await page.getByRole('link', { name: 'Themes' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/themes`)
  const themeName = `E2E Builder Theme ${Date.now()}`
  await page.getByPlaceholder('Name').fill(themeName)
  await page.getByPlaceholder('Slug (my-theme)').fill(`e2e-builder-theme-${Date.now()}`)
  await page.getByRole('button', { name: 'Create theme' }).click()
  await page.getByRole('row', { name: themeName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/themes\/.+/)
  const publishThemeButton = page.getByRole('button', { name: 'Publish draft' })
  await publishThemeButton.click()
  await expect(publishThemeButton).toHaveText('Publish draft')
  await expect(page.getByRole('row', { name: 'Serving the storefront' })).toContainText('Yes')

  // Create the page, then open its visual Builder.
  await page.getByRole('link', { name: 'Pages' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/pages`)
  const pageTitle = `E2E Builder Page ${Date.now()}`
  const pageSlug = `e2e-builder-page-${Date.now()}`
  await page.getByPlaceholder('Title').fill(pageTitle)
  await page.getByPlaceholder('Slug (about-us)').fill(pageSlug)
  await page.getByRole('button', { name: 'Create page' }).click()
  await page.getByRole('row', { name: pageTitle }).getByRole('link', { name: 'Builder' }).click()
  await page.waitForURL(/\/builder\/pages\/.+/)

  // Add a Hero section from the Section Library picker.
  await page.getByRole('button', { name: 'Add section' }).click()
  await page.getByRole('button', { name: /Hero/ }).click()
  await expect(page.getByRole('button', { name: 'Hero' })).toBeVisible()

  // Add a Button block into it from the Block Library picker.
  await page.getByRole('button', { name: 'Add block' }).click()
  await page.getByRole('button', { name: /Button/ }).click()
  await expect(page.locator('code', { hasText: 'button' })).toBeVisible()

  // Select the section and edit its settings as raw JSON.
  await page.getByRole('button', { name: 'Hero' }).click()
  const settingsTextarea = page.locator('.details textarea')
  await settingsTextarea.fill(JSON.stringify({ heading: 'E2E Builder Heading' }))
  await page.getByRole('button', { name: 'Apply' }).click()

  // Explicit Save rather than waiting out the autosave debounce.
  await page.getByRole('button', { name: 'Save' }).click()
  await expect(page.locator('.save-state')).toContainText('Saved')

  // Undo removes the settings edit's revision; redo brings it back.
  const undoButton = page.getByRole('button', { name: 'Undo' })
  await expect(undoButton).toBeEnabled()
  await undoButton.click()
  await expect(page.getByRole('button', { name: 'Redo' })).toBeEnabled()
  await page.getByRole('button', { name: 'Redo' }).click()

  // Redo's hydrate() is async — wait for the block to actually reappear
  // before publishing, or a fast Publish click can race ahead of it.
  await expect(page.locator('code', { hasText: 'button' })).toBeVisible()
  await expect(page.getByRole('row', { name: /^#\d+/ }).first()).toBeVisible() // revisions table populated

  // The theme was published *before* the Builder ever opened, which is
  // what seeds the full Section/Block Library onto the theme's current
  // draft — so the still-active version only has the single default
  // "hero" section `CreateTheme` seeds up front, never "button". Publish
  // the theme again now that the library has been seeded, so the
  // version actually serving the storefront has the block type this
  // page references.
  const builderUrl = page.url()
  await page.getByRole('link', { name: 'Themes' }).click()
  await page.getByRole('row', { name: themeName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/themes\/.+/)
  await publishThemeButton.click()
  await expect(publishThemeButton).toHaveText('Publish draft')
  await page.goto(builderUrl)

  // Publish and confirm the page is live with what was built.
  await page.getByRole('button', { name: 'Publish' }).click()
  await expect(page.getByRole('button', { name: 'Publish' })).toBeEnabled()

  const response = await page.request.get(`http://localhost:8000/api/v1/storefront/pages/${pageSlug}`, {
    headers: { Host: 'e2e-storefront.localhost' },
  })
  expect(response.ok()).toBeTruthy()
  const body = await response.json()
  expect(body.data.rendered.sections[0].handle).toBe('hero')
  expect(body.data.rendered.sections[0].blocks[0].handle).toBe('button')

  // Theme Customizer: set a field and confirm it saves.
  await page.getByRole('link', { name: 'Theme Customizer' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/theme-customizer`)
  await page.locator('#color_primary').fill('#112233')
  await page.getByRole('button', { name: 'Save' }).click()
  await page.reload()
  await expect(page.locator('#color_primary')).toHaveValue('#112233')
})

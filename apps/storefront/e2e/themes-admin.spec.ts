import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares. Covers the Theme Engine admin surface end to end (Milestone
 * 13): create a theme, edit its draft's home template sections as raw
 * JSON, publish it twice (so there are two published versions), roll
 * back specifically to the older one, and render a preview. One test,
 * deliberately: shares the platform's 5/minute/IP login rate limit with
 * every other admin-*.spec.ts (see those files' comments on the same
 * constraint).
 */
test('creates a theme, edits its draft template, publishes it, rolls back, and previews', async ({ page }) => {
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

  await page.getByRole('link', { name: 'Themes' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/themes`)

  const themeName = `E2E Theme ${Date.now()}`
  await page.getByPlaceholder('Name').fill(themeName)
  await page.getByPlaceholder('Slug (my-theme)').fill(`e2e-theme-${Date.now()}`)
  await page.getByRole('button', { name: 'Create theme' }).click()

  await page.getByRole('row', { name: themeName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/themes\/.+/)

  await expect(page.getByRole('rowheader', { name: 'Draft version' })).toBeVisible()

  const homeTemplate = page.locator('.template').filter({ hasText: 'home' })
  await homeTemplate.getByRole('textbox').fill(JSON.stringify([
    { id: 'hero-1', section_handle: 'hero', settings: { heading: 'E2E Sale' }, blocks: [] },
  ]))
  await homeTemplate.getByRole('button', { name: 'Save sections' }).click()
  // v-model reflects the saved value via the textarea's `.value` property,
  // not a text node — assert on the value, not text content.
  await expect(homeTemplate.getByRole('textbox')).toHaveValue(/E2E Sale/)

  const versionsTable = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Versions' }) }).locator('table')
  const publishButton = page.getByRole('button', { name: 'Publish draft' })

  await publishButton.click()
  await expect(publishButton).toHaveText('Publish draft')
  await expect(versionsTable.getByRole('row', { name: '#1' })).toContainText('published')

  // Publish the fresh draft again so there are two published versions —
  // otherwise "roll back" would just re-activate the version already
  // live and prove nothing.
  await publishButton.click()
  await expect(publishButton).toHaveText('Publish draft')
  await expect(versionsTable.getByRole('row', { name: '#2' })).toContainText('published')

  const v1Row = versionsTable.getByRole('row', { name: '#1' })
  await v1Row.getByRole('button', { name: 'Roll back to this' }).click()
  await expect(page.getByRole('row', { name: 'Serving the storefront' })).toContainText('Yes')

  // Scoped to `main`: the topbar's own language-switcher <select>
  // (Milestone 26) is also a combobox, sitting outside `main`.
  await page.getByRole('main').getByRole('combobox').selectOption('home')
  await page.getByRole('button', { name: 'Render preview' }).click()
  await expect(page.getByText('hero')).toBeVisible()
  await expect(page.getByText('E2E Sale')).toBeVisible()
})

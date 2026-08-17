import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Theme boot/system-preference coverage for docs/design/THEMING.md.
 * Deliberately runs unauthenticated (against /login) rather than a
 * Products page: apply-theme.client.ts and tokens.css apply at the app
 * root regardless of route/layout (layouts/auth.vue included), so this
 * exercises the exact same boot-time code path a Product page would
 * without spending a login against the shared 5/minute/IP rate limit
 * (see admin-auth.spec.ts) — that budget is reserved for
 * products-admin.spec.ts's two authenticated flows.
 */
test('explicit dark preference applies before the page is visible — no light-theme flash on a hard load', async ({ browser }) => {
  const context = await browser.newContext()
  await context.addCookies([{ name: 'admin_theme', value: 'dark', domain: 'localhost', path: '/' }])
  const page = await context.newPage()

  await page.goto(`${ADMIN_BASE}/login`, { waitUntil: 'commit' })
  // Read data-theme as early as the DOM allows — before networkidle,
  // before any settle — to prove the plugin ran during client boot, not
  // after a visible delay.
  await expect.poll(() => page.evaluate(() => document.documentElement.getAttribute('data-theme')), {
    timeout: 2000,
  }).toBe('dark')

  const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  // --color-bg in dark theme is #0d0d0f = rgb(13, 13, 15) — assert the
  // actual painted background is dark, not just the attribute.
  expect(bg).toBe('rgb(13, 13, 15)')

  await context.close()
})

test('explicit light preference renders light even when the OS reports dark', async ({ browser }) => {
  const context = await browser.newContext({ colorScheme: 'dark' })
  await context.addCookies([{ name: 'admin_theme', value: 'light', domain: 'localhost', path: '/' }])
  const page = await context.newPage()

  await page.goto(`${ADMIN_BASE}/login`, { waitUntil: 'commit' })
  await expect.poll(() => page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('light')

  const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  // --color-bg in light theme is #f6f6f8 = rgb(246, 246, 248) — proves
  // the explicit choice wins over prefers-color-scheme, not just that
  // the attribute is set (tokens.css's :not([data-theme="light"]) guard
  // is what this actually exercises).
  expect(bg).toBe('rgb(246, 246, 248)')

  await context.close()
})

test('system preference (no cookie) follows the OS colorScheme live, without a reload', async ({ browser }) => {
  const context = await browser.newContext({ colorScheme: 'light' })
  const page = await context.newPage()

  await page.goto(`${ADMIN_BASE}/login`)
  await page.waitForLoadState('networkidle')

  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBeNull()
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(246, 246, 248)')

  // Flip the OS preference with no navigation at all — tokens.css's
  // `@media (prefers-color-scheme: dark)` block must re-resolve on its
  // own; useColorMode never re-reads the OS itself (see THEMING.md
  // section 4), so this is purely a CSS re-resolution, not app logic.
  await page.emulateMedia({ colorScheme: 'dark' })
  await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(13, 13, 15)')
  // Still no explicit attribute — "system" never stamps one.
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBeNull()

  await page.emulateMedia({ colorScheme: 'light' })
  await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(246, 246, 248)')

  await context.close()
})

test('theme preference persists across reload and a fresh context reusing the same cookie (simulates a new browser session)', async ({ browser }) => {
  const context = await browser.newContext()
  await context.addCookies([{ name: 'admin_theme', value: 'dark', domain: 'localhost', path: '/' }])
  const page = await context.newPage()
  await page.goto(`${ADMIN_BASE}/login`)
  await page.waitForLoadState('networkidle')
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')

  await page.reload()
  await page.waitForLoadState('networkidle')
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')

  const cookies = await context.cookies()
  await context.close()

  // A brand new context with no shared browser state, cookie injected
  // manually — the closest Playwright gets to "restart the browser."
  const freshContext = await browser.newContext()
  await freshContext.addCookies(cookies.filter(c => c.name === 'admin_theme'))
  const freshPage = await freshContext.newPage()
  await freshPage.goto(`${ADMIN_BASE}/login`)
  await freshPage.waitForLoadState('networkidle')
  expect(await freshPage.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe('dark')

  await freshContext.close()
})

import { expect, test } from '@playwright/test'

const ADMIN_BASE = 'http://localhost:3000'

/**
 * Requires `php artisan e2e:seed-storefront` (apps/api) — reuses the
 * "E2E Store" / e2e@example.test fixture every other admin-facing spec
 * shares. Covers the CMS admin surface end to end (Milestone 14): create
 * a page, edit its draft sections, publish it, set its SEO; create a
 * menu and add a nested item to it; create a blog, write a post,
 * publish it; create a redirect. Deliberately skips exercising the page
 * preview panel — that requires an active theme to exist first (a
 * separate concern already covered by themes-admin.spec.ts and the
 * backend's own PageRenderingTest), and this spec should stay
 * runnable in isolation rather than depending on test run order across
 * files. One test, deliberately: shares the platform's 5/minute/IP
 * login rate limit with every other admin-*.spec.ts.
 */
test('creates a page, publishes it, builds a menu, writes a blog post, and adds a redirect', async ({ page }) => {
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

  // --- Pages ---
  await page.getByRole('link', { name: 'Pages' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/pages`)

  const pageSlug = `e2e-about-${Date.now()}`
  const pageTitle = `E2E About Page ${Date.now()}`
  await page.getByPlaceholder('Title').fill(pageTitle)
  await page.getByPlaceholder('Slug (about-us)').fill(pageSlug)
  await page.getByRole('button', { name: 'Create page' }).click()

  await page.getByRole('row', { name: pageTitle }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/pages\/.+/)

  await page.getByRole('textbox').first().fill(JSON.stringify([
    { id: 'hero-1', section_handle: 'hero', settings: { heading: 'E2E Page Heading' }, blocks: [] },
  ]))
  await page.getByRole('button', { name: 'Save sections' }).click()
  await expect(page.getByRole('textbox').first()).toHaveValue(/E2E Page Heading/)

  const publishPageButton = page.getByRole('button', { name: 'Publish draft' })
  await publishPageButton.click()
  // Publishing triggers an async `load()` that repopulates the SEO form
  // from the (now different) current draft's own SEO — wait for that
  // full reload to settle before typing, or the reload can overwrite
  // what was just typed.
  await expect(publishPageButton).toHaveText('Publish draft')
  await expect(page.getByRole('row', { name: 'Live on the storefront' })).toContainText('Yes')

  await page.getByPlaceholder('Meta title').fill('E2E About | Meta Title')
  await page.getByRole('button', { name: 'Save SEO' }).click()
  await expect(page.getByPlaceholder('Meta title')).toHaveValue('E2E About | Meta Title')

  // --- Menus ---
  await page.getByRole('link', { name: 'Menus' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/menus`)

  const menuHandle = `e2e-menu-${Date.now()}`
  const menuName = `E2E Menu ${Date.now()}`
  await page.getByPlaceholder('Name (Main navigation)').fill(menuName)
  await page.getByPlaceholder('Handle (main-menu)').fill(menuHandle)
  await page.getByRole('button', { name: 'Create menu' }).click()

  await page.getByRole('row', { name: menuName }).getByRole('link', { name: 'View' }).click()
  await page.waitForURL(/\/menus\/.+/)

  await page.getByPlaceholder('Label').fill('Shop')
  await page.getByPlaceholder('URL (https://… or /path)').fill('/shop')
  await page.getByRole('button', { name: 'Add item' }).click()

  await expect(page.locator('.label').filter({ hasText: 'Shop' })).toBeVisible()
  await expect(page.getByRole('row', { name: 'Items' })).toContainText('1')

  // --- Blog ---
  await page.getByRole('link', { name: 'Blogs' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/blogs`)

  const blogSlug = `e2e-news-${Date.now()}`
  const blogTitle = `E2E News ${Date.now()}`
  await page.getByPlaceholder('Title (News)').fill(blogTitle)
  await page.getByPlaceholder('Slug (news)').fill(blogSlug)
  await page.getByRole('button', { name: 'Create blog' }).click()

  await page.getByRole('row', { name: blogTitle }).getByRole('link', { name: 'Posts' }).click()
  await page.waitForURL(/\/blogs\/.+/)

  const postTitle = `E2E Hello World ${Date.now()}`
  await page.getByPlaceholder('Title').fill(postTitle)
  await page.getByPlaceholder('Slug (hello-world)').fill(`e2e-hello-world-${Date.now()}`)
  await page.getByPlaceholder('Body').fill('Our first E2E post.')
  await page.getByRole('button', { name: 'Create post' }).click()

  await page.getByRole('row', { name: postTitle }).getByRole('link', { name: 'Edit' }).click()
  await page.waitForURL(/\/blog-posts\/.+/)

  await page.getByRole('button', { name: 'Publish now' }).click()
  await expect(page.getByRole('row', { name: 'Status' })).toContainText('published')

  // --- Redirects ---
  await page.getByRole('link', { name: 'Redirects' }).click()
  await expect(page).toHaveURL(`${ADMIN_BASE}/redirects`)

  await page.getByPlaceholder('From (/old-page)').fill(`/e2e-old-${Date.now()}`)
  await page.getByPlaceholder('To (/new-page)').fill(`/pages/${pageSlug}`)
  await page.getByRole('button', { name: 'Create redirect' }).click()

  await expect(page.getByRole('row', { name: new RegExp(`/pages/${pageSlug}`) })).toBeVisible()
})

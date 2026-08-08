import { defineConfig, devices } from '@playwright/test'

/**
 * Requires the API to already be running on :8000 (`php artisan serve`
 * from apps/api, or `make api`) with Postgres/Redis up — same
 * prerequisite as every other local dev flow in this repo. This config
 * manages both the storefront Nuxt dev server (checkout.spec.ts,
 * golden-path.spec.ts) and, for checkout.spec.ts's merchant-sees-the-
 * order assertion, the admin Nuxt dev server too.
 *
 * Run `php artisan e2e:seed-storefront` (apps/api) once before `pnpm e2e`
 * to provision the deterministic fixture the specs depend on
 * (e2e-storefront.localhost / e2e-shirt, owned by e2e@example.test /
 * password on the "E2E Store" store) — idempotent, safe to re-run.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  // `fullyParallel: false` only serializes tests *within* one spec file —
  // separate files still run across parallel workers by default, and
  // both specs here share one dev server + one seeded fixture (same
  // product/store), so concurrent runs raced each other. Force one
  // worker so specs never overlap.
  workers: 1,
  retries: 0,
  reporter: 'line',
  // Nuxt dev mode compiles each route on demand the first time it's
  // requested in a given dev-server lifetime — a route neither spec has
  // hit yet (e.g. /checkout, or anything in the admin app) can take
  // noticeably longer than the 5s default on that first request. Padding
  // both timeouts avoids flaking on that one-time cost; it doesn't mask
  // real product slowness since production builds have no such lag.
  expect: {
    timeout: 10_000,
  },
  use: {
    trace: 'retain-on-failure',
    navigationTimeout: 15_000,
    actionTimeout: 10_000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: [
    {
      command: 'nuxt dev --port 3100',
      // Plain 'localhost' has no seeded Domain row and 404s (correctly —
      // see EnsureStorefrontTenantContext) — probe the fixture's own host
      // instead so readiness reflects what the spec actually needs.
      url: 'http://e2e-storefront.localhost:3100/products',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
    {
      command: 'nuxt dev --port 3000',
      cwd: '../admin',
      url: 'http://localhost:3000/login',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
  ],
})

import { defineConfig, devices } from '@playwright/test'

/**
 * Requires the API to already be running on :8000 (`php artisan serve`
 * from apps/api, or `make api`) with Postgres/Redis up — same
 * prerequisite as every other local dev flow in this repo. This config
 * only manages the storefront Nuxt dev server itself.
 *
 * Run `php artisan e2e:seed-storefront` (apps/api) once before `pnpm e2e`
 * to provision the deterministic fixture the spec depends on
 * (e2e-storefront.localhost / e2e-shirt) — idempotent, safe to re-run.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: 0,
  reporter: 'line',
  use: {
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: 'nuxt dev --port 3100',
    // Plain 'localhost' has no seeded Domain row and 404s (correctly —
    // see EnsureStorefrontTenantContext) — probe the fixture's own host
    // instead so readiness reflects what the spec actually needs.
    url: 'http://e2e-storefront.localhost:3100/products',
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
  },
})

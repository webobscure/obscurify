// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint'],

  // SSR stays on: the storefront is a public, SEO-relevant surface.
  // The active Store is resolved backend-side from the Host header (see
  // ARCHITECTURE.md section 9) — this app never selects a store itself,
  // it just calls the API on whatever host the visitor is already using
  // (see app/composables/useStorefrontApiBaseUrl.ts).
  runtimeConfig: {
    public: {
      // The API is conventionally reachable on the same hostname as the
      // storefront, just on this port — see README's local dev flow
      // (store-a.localhost:3001 -> store-a.localhost:8000).
      apiPort: '8000',
    },
  },
})

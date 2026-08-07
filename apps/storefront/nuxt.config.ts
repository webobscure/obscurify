// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint'],

  // SSR stays on (default): the storefront is a public, SEO-relevant
  // surface. Future milestone: resolve the active Store from the
  // incoming Host header (see ARCHITECTURE.md section 9) before catalog
  // pages are added — no store resolution exists yet in this shell.
})

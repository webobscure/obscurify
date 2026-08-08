// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint'],

  css: ['~/assets/css/tokens.css'],

  // Merchant admin is an authenticated dashboard, not a public/SEO
  // surface — SPA rendering keeps token/tenant state (localStorage)
  // simple instead of threading it through SSR.
  ssr: false,

  runtimeConfig: {
    public: {
      apiBaseUrl: 'http://localhost:8000',
    },
  },
})

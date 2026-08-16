// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', '@nuxtjs/i18n'],

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

  // Internationalization & Localization (Milestone 26) — Russian is the
  // platform default (spec: "Russian must become the default language
  // for the entire platform"); no `no_prefix` URL locale segments (the
  // admin isn't a public/SEO surface — see docs/architecture/localization.md).
  // Each locale below declares its own `file`, which this module
  // fetches lazily per-active-locale by design (spec section 4:
  // "Lazy-load translation bundles") — there is no separate `lazy`
  // toggle in this module version; per-locale `file` entries are
  // always lazy-loaded.
  i18n: {
    baseUrl: '/',
    defaultLocale: 'ru',
    strategy: 'no_prefix',
    locales: [
      { code: 'ru', name: 'Русский', file: 'ru.json' },
      { code: 'en', name: 'English', file: 'en.json' },
      { code: 'de', name: 'Deutsch', file: 'de.json' },
    ],
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'admin_locale',
      redirectOn: 'no prefix',
    },
  },
})

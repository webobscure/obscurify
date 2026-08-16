// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', '@nuxtjs/i18n'],

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

  // Internationalization & Localization (Milestone 26) — Russian is the
  // platform default. No URL locale prefixing (`no_prefix`): the
  // backend already resolves the storefront's language from the
  // `storefront_locale` cookie / Accept-Language / store default (see
  // docs/architecture/localization.md) — a URL-segment scheme would be
  // a second, conflicting source of truth for the same decision.
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
      // Matches the backend's own cookie name (StorefrontLocaleController)
      // so both sides read/write the exact same preference — a customer
      // switching language never gets a different answer from the API
      // than from the page chrome around it.
      cookieKey: 'storefront_locale',
      redirectOn: 'no prefix',
    },
  },
})

import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import de from '../i18n/locales/de.json'
import en from '../i18n/locales/en.json'
import ru from '../i18n/locales/ru.json'

const nuxtConfigSource = readFileSync(fileURLToPath(new URL('../nuxt.config.ts', import.meta.url)), 'utf-8')

/**
 * Regression guards for the i18n navigation investigation (see
 * docs/architecture/localization.md and the Milestone 26 follow-up
 * fixing the /account/** hydration mismatch). This app uses
 * `strategy: 'no_prefix'` — every locale resolves at the same plain
 * path, never `/ru/products` or `/en/products` — a deliberate choice
 * (locale is resolved server-side from the `storefront_locale` cookie /
 * Accept-Language / store default, not from the URL). These tests read
 * the actual config source rather than importing `defineNuxtConfig`
 * output, since this is a plain Vitest environment without the full
 * Nuxt test runtime.
 */
describe('i18n routing configuration', () => {
  it('uses the no_prefix URL strategy', () => {
    expect(nuxtConfigSource).toMatch(/strategy:\s*'no_prefix'/)
  })

  it('defaults to Russian', () => {
    expect(nuxtConfigSource).toMatch(/defaultLocale:\s*'ru'/)
  })

  it('declares exactly ru/en/de locales', () => {
    const codes = [...nuxtConfigSource.matchAll(/code:\s*'(\w+)'/g)].map(m => m[1])
    expect(codes).toEqual(['ru', 'en', 'de'])
  })

  it('opts /account/** out of SSR, so the client-only auth redirect never races hydration against server-rendered protected content', () => {
    // Root cause: @nuxtjs/i18n registers a global async route middleware,
    // which changed how Nuxt resolves the *initial* client navigation —
    // the storefront's client-only `auth` middleware (app/middleware/
    // auth.ts) now actually fires on a hard page load (previously it
    // silently never did — a separate, pre-existing gap). Because SSR
    // has no way to know the visitor is unauthenticated (the customer
    // token lives in localStorage), it always rendered the real page;
    // if the client then redirects before hydration finishes, Vue
    // hydrates the login page against the account page's server DOM —
    // a genuine hydration mismatch, not cosmetic. ssr:false for this
    // route family removes the server DOM entirely, so there is nothing
    // to mismatch against.
    expect(nuxtConfigSource).toMatch(/'\/account\/\*\*':\s*\{\s*ssr:\s*false\s*\}/)
  })
})

/**
 * Reads a dot-path key ('chrome.cart') out of a locale JSON object —
 * mirrors how Vue I18n itself resolves the same key at runtime.
 */
function resolveKey(bundle: Record<string, unknown>, key: string): unknown {
  return key.split('.').reduce<unknown>((node, segment) => {
    return node && typeof node === 'object' ? (node as Record<string, unknown>)[segment] : undefined
  }, bundle)
}

describe('locale bundles', () => {
  const chromeKeys = ['products_nav', 'cart', 'store_fallback', 'seller_inn_label', 'language']

  it('has every chrome key, non-empty, in all three locales', () => {
    for (const key of chromeKeys) {
      for (const [name, bundle] of [['ru', ru], ['en', en], ['de', de]] as const) {
        const value = resolveKey(bundle, `chrome.${key}`)
        expect(value, `chrome.${key} missing in ${name}.json`).toEqual(expect.any(String))
        expect(value, `chrome.${key} empty in ${name}.json`).not.toBe('')
      }
    }
  })
})

import { describe, expect, it } from 'vitest'
import de from '../i18n/locales/de.json'
import en from '../i18n/locales/en.json'
import ru from '../i18n/locales/ru.json'

/**
 * Products module redesign — every new string lives under the
 * productsList, productEditor, and productStatus namespaces (see
 * docs/design/DESIGN_SYSTEM.md "Products redesign"). Mirrors
 * navigation.test.ts's locale-completeness check: a key present in one
 * locale but missing/empty in another would silently show English (or a
 * raw key) to a Russian merchant.
 */
function collectKeys(node: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(node).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'object' && value !== null
      ? collectKeys(value as Record<string, unknown>, path)
      : [path]
  })
}

function resolveKey(bundle: Record<string, unknown>, key: string): unknown {
  return key.split('.').reduce<unknown>((node, segment) => {
    return node && typeof node === 'object' ? (node as Record<string, unknown>)[segment] : undefined
  }, bundle)
}

describe('Products module i18n completeness', () => {
  const namespaces = ['productsList', 'productEditor', 'productStatus'] as const
  const bundles = { ru, en, de } as const

  for (const namespace of namespaces) {
    it(`has an identical key set across ru/en/de for "${namespace}"`, () => {
      const ruKeys = collectKeys((ru as Record<string, unknown>)[namespace] as Record<string, unknown>).sort()
      const enKeys = collectKeys((en as Record<string, unknown>)[namespace] as Record<string, unknown>).sort()
      const deKeys = collectKeys((de as Record<string, unknown>)[namespace] as Record<string, unknown>).sort()

      expect(enKeys).toEqual(ruKeys)
      expect(deKeys).toEqual(ruKeys)
    })

    it(`has a non-empty string value for every "${namespace}" key in all three locales`, () => {
      const keys = collectKeys((ru as Record<string, unknown>)[namespace] as Record<string, unknown>)

      for (const key of keys) {
        for (const [localeName, bundle] of Object.entries(bundles)) {
          const value = resolveKey(bundle, `${namespace}.${key}`)
          expect(value, `${namespace}.${key} missing in ${localeName}.json`).toEqual(expect.any(String))
          expect(value, `${namespace}.${key} empty in ${localeName}.json`).not.toBe('')
        }
      }
    })
  }

  it('chrome.theme_* keys (theme switcher) exist and are non-empty in all three locales', () => {
    for (const key of ['theme', 'theme_light', 'theme_dark', 'theme_system']) {
      for (const [localeName, bundle] of Object.entries(bundles)) {
        const value = resolveKey(bundle, `chrome.${key}`)
        expect(value, `chrome.${key} missing in ${localeName}.json`).toEqual(expect.any(String))
        expect(value, `chrome.${key} empty in ${localeName}.json`).not.toBe('')
      }
    }
  })
})

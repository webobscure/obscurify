export type ColorModePreference = 'light' | 'dark' | 'system'

function isValidPreference(value: unknown): value is ColorModePreference {
  return value === 'light' || value === 'dark' || value === 'system'
}

/**
 * Theme preference (docs/design/THEMING.md) — persisted the same way
 * `admin_locale` already is (a plain cookie, one year), and applied by
 * stamping `data-theme` on <html>. "system" stamps nothing at all: the
 * `@media (prefers-color-scheme)` block in tokens.css is what resolves
 * it, so this composable never needs to read the OS preference itself.
 */
export function useColorMode() {
  const cookie = useCookie<ColorModePreference>('admin_theme', {
    default: () => 'system',
    maxAge: 60 * 60 * 24 * 365,
    sameSite: 'lax',
  })

  function apply(pref: ColorModePreference) {
    if (!import.meta.client) return

    if (pref === 'system') {
      document.documentElement.removeAttribute('data-theme')
    } else {
      document.documentElement.setAttribute('data-theme', pref)
    }
  }

  const preference = computed<ColorModePreference>({
    get: () => (isValidPreference(cookie.value) ? cookie.value : 'system'),
    set: (value) => {
      cookie.value = value
      apply(value)
    },
  })

  return { preference, apply }
}

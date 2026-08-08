import type { StorefrontStore } from '@obscurify/types'

/**
 * SSR-safe (no cookie dependency, unlike useCart) — fetched once per
 * request/app instance and shared via useState so header/footer/SEO all
 * read the same value without re-fetching.
 */
export async function useStorefrontStore() {
  const store = useState<StorefrontStore | null>('store', () => null)

  if (store.value === null) {
    // An unrecognized host (no matching Domain row) 404s here — the app
    // shell degrades to a generic "Store" header rather than crashing,
    // so pages can still render their own not-found state.
    try {
      const response = await useStorefrontApi().store.get()
      store.value = response.data
    } catch {
      store.value = null
    }
  }

  return store
}

import type { Cart } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

/**
 * Client-only by design: the cart depends on the HttpOnly guest-cart
 * cookie, which only the browser holds. Forwarding it through SSR would
 * need relaying the incoming Cookie header out and any Set-Cookie back in
 * — real, but non-trivial, plumbing that buys little here since cart
 * content isn't SEO-relevant. Product/collection pages (the content that
 * matters for SEO) are fully SSR'd; the cart hydrates just after mount
 * (see plugins/hydrate-cart.client.ts), same as admin's auth/store state.
 */
export function useCart() {
  const cart = useState<Cart | null>('cart', () => null)
  const loading = useState<boolean>('cart-loading', () => false)
  const error = useState<string | null>('cart-error', () => null)

  const itemCount = computed(() => computeItemCount(cart.value?.items ?? []))

  async function withCart(fn: () => Promise<{ data: Cart }>) {
    error.value = null
    try {
      const response = await fn()
      cart.value = response.data
    } catch (e) {
      error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
      throw e
    }
  }

  async function fetch() {
    loading.value = true
    try {
      await withCart(() => useStorefrontApi().cart.get())
    } finally {
      loading.value = false
    }
  }

  function addItem(variantId: string, quantity: number) {
    return withCart(() => useStorefrontApi().cart.addItem({ variant_id: variantId, quantity }))
  }

  function updateItem(itemId: string, quantity: number) {
    return withCart(() => useStorefrontApi().cart.updateItem(itemId, { quantity }))
  }

  async function removeItem(itemId: string) {
    error.value = null
    try {
      await useStorefrontApi().cart.removeItem(itemId)
      await fetch()
    } catch (e) {
      error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
      throw e
    }
  }

  return { cart, loading, error, itemCount, fetch, addItem, updateItem, removeItem }
}

import type { StorefrontCheckout } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

export interface CheckoutAddressInput {
  first_name?: string | null
  last_name?: string | null
  phone?: string | null
  country_code?: string | null
  region?: string | null
  city?: string | null
  postal_code?: string | null
  address_line1?: string | null
  address_line2?: string | null
}

export interface UpdateCheckoutInput {
  email?: string | null
  phone?: string | null
  shipping_address?: CheckoutAddressInput
  billing_same_as_shipping?: boolean
  billing_address?: CheckoutAddressInput
}

/**
 * Client-only, same reasoning as useCart(): checkout ownership rides on
 * the HttpOnly guest-cart cookie, so this state can't be meaningfully
 * derived during SSR.
 *
 * `idempotencyKey` is generated once per browser session's checkout
 * attempt and reused across retries of the *same* "Place order" click
 * (a dropped response, a double-click) — see StorefrontApiClient's
 * checkout.complete() and the backend's IdempotencyKeyStore. It is
 * deliberately not regenerated on every call; only a fresh page
 * load/module state reset starts a new attempt.
 */
export function useCheckout() {
  const checkout = useState<StorefrontCheckout | null>('checkout', () => null)
  const loading = useState<boolean>('checkout-loading', () => false)
  const error = useState<string | null>('checkout-error', () => null)
  const idempotencyKey = useState<string>('checkout-idempotency-key', () => crypto.randomUUID())

  async function open() {
    loading.value = true
    error.value = null
    try {
      const response = await useStorefrontApi().checkout.open()
      checkout.value = response.data
    } catch (e) {
      error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function update(data: UpdateCheckoutInput) {
    error.value = null
    try {
      const response = await useStorefrontApi().checkout.update(data)
      checkout.value = response.data
    } catch (e) {
      error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
      throw e
    }
  }

  async function complete() {
    loading.value = true
    error.value = null
    try {
      return (await useStorefrontApi().checkout.complete(idempotencyKey.value)).data
    } catch (e) {
      error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
      throw e
    } finally {
      loading.value = false
    }
  }

  return { checkout, loading, error, open, update, complete }
}

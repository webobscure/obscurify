import type { StorefrontPayment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

/**
 * Client-only, same reasoning as useCheckout(): nothing here needs SSR.
 * `idempotencyKey` follows the same "generated once per attempt, reused
 * across retries of that attempt" rule as useCheckout's.
 */
export function usePayment() {
  const payment = useState<StorefrontPayment | null>('payment', () => null)
  const loading = useState<boolean>('payment-loading', () => false)
  const error = useState<string | null>('payment-error', () => null)

  async function create(orderId: string, provider: string) {
    loading.value = true
    error.value = null
    try {
      const idempotencyKey = crypto.randomUUID()
      const response = await useStorefrontApi().payments.create(orderId, provider, idempotencyKey)
      payment.value = response.data
      return response.data
    } catch (e) {
      error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
      throw e
    } finally {
      loading.value = false
    }
  }

  return { payment, loading, error, create }
}

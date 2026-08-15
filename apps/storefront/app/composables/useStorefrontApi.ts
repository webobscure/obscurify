import { StorefrontApiClient } from '@obscurify/api-client'

/**
 * Deliberately NOT a cached module-level singleton (unlike admin's
 * useApi()): this server process handles requests for every store's
 * hostname, so a client cached at module scope would leak the first
 * request's base URL into every later request. A fresh, cheap client per
 * call keeps each request's tenant resolution correct.
 */
export function useStorefrontApi(): StorefrontApiClient {
  return new StorefrontApiClient({
    baseUrl: useStorefrontApiBaseUrl(),
    getCustomerToken: () => useCustomerAuth().accessToken.value,
    onCustomerUnauthorized: () => useCustomerAuth().clearSession(),
  })
}

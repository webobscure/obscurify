import type { Customer } from '@obscurify/types'

const ACCESS_TOKEN_KEY = 'obscurify_customer_access_token'
const REFRESH_TOKEN_KEY = 'obscurify_customer_refresh_token'

/**
 * Customer-portal auth state (Milestone 16). Client-only by nature: the
 * CustomerAccessToken lives in localStorage, so SSR always renders as
 * signed-out and the account pages hydrate after mount — same shape as
 * admin's useAuth(), but with a rotating refresh token alongside the
 * short-lived access token.
 */
export function useCustomerAuth() {
  const accessToken = useState<string | null>('customer_access_token', () => null)
  const refreshToken = useState<string | null>('customer_refresh_token', () => null)
  const customer = useState<Customer | null>('customer', () => null)

  const isAuthenticated = computed(() => accessToken.value !== null)

  function persist(key: string, value: string | null) {
    if (!import.meta.client) return

    if (value) {
      localStorage.setItem(key, value)
    } else {
      localStorage.removeItem(key)
    }
  }

  function setTokens(access: string | null, refresh: string | null) {
    accessToken.value = access
    refreshToken.value = refresh
    persist(ACCESS_TOKEN_KEY, access)
    persist(REFRESH_TOKEN_KEY, refresh)
  }

  function hydrate() {
    if (import.meta.client && accessToken.value === null) {
      accessToken.value = localStorage.getItem(ACCESS_TOKEN_KEY)
      refreshToken.value = localStorage.getItem(REFRESH_TOKEN_KEY)
    }
  }

  async function register(data: { email: string; password: string; first_name?: string; last_name?: string; phone?: string }) {
    const response = await useStorefrontApi().account.register(data)
    setTokens(response.access_token, response.refresh_token)
    customer.value = response.data
  }

  async function login(data: { email: string; password: string }) {
    const response = await useStorefrontApi().account.login(data)
    setTokens(response.access_token, response.refresh_token)
    customer.value = response.data
  }

  function clearSession() {
    setTokens(null, null)
    customer.value = null
  }

  async function logout() {
    try {
      await useStorefrontApi().account.logout()
    } catch {
      // The access token may already be expired or revoked server-side —
      // the local sign-out below has to happen either way.
    } finally {
      clearSession()
    }
  }

  async function fetchCustomer() {
    const response = await useStorefrontApi().account.show()
    customer.value = response.data
    return response.data
  }

  return { accessToken, refreshToken, customer, isAuthenticated, hydrate, setTokens, register, login, logout, clearSession, fetchCustomer }
}

import type { User } from '@obscurify/types'

const TOKEN_KEY = 'obscurify_admin_token'

export function useAuth() {
  const token = useState<string | null>('auth_token', () => null)
  const user = useState<User | null>('auth_user', () => null)

  const isAuthenticated = computed(() => token.value !== null)

  function setToken(value: string | null) {
    token.value = value
    if (import.meta.client) {
      if (value) {
        localStorage.setItem(TOKEN_KEY, value)
      } else {
        localStorage.removeItem(TOKEN_KEY)
      }
    }
  }

  function hydrate() {
    if (import.meta.client && token.value === null) {
      token.value = localStorage.getItem(TOKEN_KEY)
    }
  }

  async function register(data: { name: string; email: string; password: string; password_confirmation: string }) {
    const response = await useApi().auth.register(data)
    setToken(response.token)
    user.value = response.data
  }

  async function login(data: { email: string; password: string }) {
    const response = await useApi().auth.login(data)
    setToken(response.token)
    user.value = response.data
  }

  function clearSession() {
    setToken(null)
    user.value = null
  }

  async function logout() {
    try {
      await useApi().auth.logout()
    } catch {
      // Token may already be stale/revoked server-side — the local
      // sign-out below must happen regardless of whether the backend
      // call succeeded.
    } finally {
      clearSession()
    }
  }

  async function fetchMe() {
    const response = await useApi().auth.me()
    user.value = response.data
    return response.data
  }

  return { token, user, isAuthenticated, hydrate, register, login, logout, fetchMe, clearSession }
}

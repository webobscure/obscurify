import { ApiClient } from '@obscurify/api-client'

let client: ApiClient | null = null

/**
 * Single ApiClient instance for the whole app. All components/composables
 * go through this — no raw `$fetch` calls to the API elsewhere.
 */
export function useApi(): ApiClient {
  if (!client) {
    const config = useRuntimeConfig()
    const router = useRouter()

    client = new ApiClient({
      baseUrl: config.public.apiBaseUrl,
      getToken: () => useAuth().token.value,
      getStoreId: () => useActiveStore().storeId.value,
      // A 401 from any authenticated endpoint means the backend no longer
      // recognizes this token (revoked, expired, or the DB was reset under
      // it) — authentication and tenant selection must never be left in a
      // state the backend disagrees with, so both clear together.
      onUnauthorized: () => {
        useAuth().clearSession()
        useActiveStore().clear()

        if (router.currentRoute.value.path !== '/login') {
          router.push('/login')
        }
      },
    })
  }

  return client
}

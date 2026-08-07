import { ApiClient } from '@obscurify/api-client'

let client: ApiClient | null = null

/**
 * Single ApiClient instance for the whole app. All components/composables
 * go through this — no raw `$fetch` calls to the API elsewhere.
 */
export function useApi(): ApiClient {
  if (!client) {
    const config = useRuntimeConfig()

    client = new ApiClient({
      baseUrl: config.public.apiBaseUrl,
      getToken: () => useAuth().token.value,
      getStoreId: () => useActiveStore().storeId.value,
    })
  }

  return client
}

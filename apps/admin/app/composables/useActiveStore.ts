import type { Store } from '@obscurify/types'

const STORE_ID_KEY = 'obscurify_admin_active_store_id'

export function useActiveStore() {
  const storeId = useState<string | null>('active_store_id', () => null)
  const store = useState<Store | null>('active_store', () => null)

  function setStoreId(value: string | null) {
    storeId.value = value
    if (import.meta.client) {
      if (value) {
        localStorage.setItem(STORE_ID_KEY, value)
      } else {
        localStorage.removeItem(STORE_ID_KEY)
      }
    }
  }

  function hydrate() {
    if (import.meta.client && storeId.value === null) {
      storeId.value = localStorage.getItem(STORE_ID_KEY)
    }
  }

  async function activate(id: string) {
    const response = await useApi().stores.activate(id)
    store.value = response.data
    setStoreId(response.data.id)
    return response.data
  }

  function clear() {
    setStoreId(null)
    store.value = null
  }

  return { storeId, store, hydrate, activate, clear }
}

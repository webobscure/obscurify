import type {
  ApiCollection,
  ApiErrorBody,
  ApiResource,
  AuthResponse,
  Category,
  Collection,
  InventoryItem,
  InventoryLevel,
  Location,
  Media,
  Order,
  Payment,
  Product,
  ProductOption,
  ProductOptionValue,
  ProductVariant,
  Store,
  User,
} from '@obscurify/types'

export class ApiClientError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: ApiErrorBody,
  ) {
    super(body.message ?? `Request failed with status ${status}`)
    this.name = 'ApiClientError'
  }
}

export interface ApiClientOptions {
  baseUrl: string
  getToken?: () => string | null | undefined
  getStoreId?: () => string | null | undefined
}

/**
 * Thin, typed HTTP boundary over the Laravel API. Nothing in apps/admin or
 * apps/storefront should call `$fetch`/`fetch` against the API directly —
 * everything goes through here so auth/tenant headers and error shapes
 * stay in one place.
 */
export class ApiClient {
  constructor(private readonly options: ApiClientOptions) {}

  private async request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers)
    headers.set('Accept', 'application/json')

    if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    const token = this.options.getToken?.()
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }

    const storeId = this.options.getStoreId?.()
    if (storeId) {
      headers.set('X-Store-Id', storeId)
    }

    const response = await fetch(`${this.options.baseUrl}${path}`, {
      ...init,
      headers,
    })

    if (response.status === 204) {
      return undefined as T
    }

    const body = await response.json().catch(() => ({ message: response.statusText }))

    if (!response.ok) {
      throw new ApiClientError(response.status, body as ApiErrorBody)
    }

    return body as T
  }

  readonly auth = {
    register: (data: { name: string; email: string; password: string; password_confirmation: string }) =>
      this.request<AuthResponse>('/api/v1/auth/register', { method: 'POST', body: JSON.stringify(data) }),

    login: (data: { email: string; password: string }) =>
      this.request<AuthResponse>('/api/v1/auth/login', { method: 'POST', body: JSON.stringify(data) }),

    logout: () => this.request<void>('/api/v1/auth/logout', { method: 'POST' }),

    me: () => this.request<ApiResource<User>>('/api/v1/me'),
  }

  readonly stores = {
    list: () => this.request<ApiCollection<Store>>('/api/v1/stores'),

    create: (data: { name: string; slug: string; default_currency?: string; default_locale?: string; timezone?: string }) =>
      this.request<ApiResource<Store>>('/api/v1/stores', { method: 'POST', body: JSON.stringify(data) }),

    get: (storeId: string) => this.request<ApiResource<Store>>(`/api/v1/stores/${storeId}`),

    activate: (storeId: string) =>
      this.request<ApiResource<Store>>(`/api/v1/stores/${storeId}/activate`, { method: 'POST' }),
  }

  readonly products = {
    list: () => this.request<ApiCollection<Product>>('/api/v1/products'),

    create: (data: { title: string; slug?: string; description?: string; vendor?: string; product_type?: string; status?: string; seo_title?: string; seo_description?: string }) =>
      this.request<ApiResource<Product>>('/api/v1/products', { method: 'POST', body: JSON.stringify(data) }),

    get: (productId: string) => this.request<ApiResource<Product>>(`/api/v1/products/${productId}`),

    update: (productId: string, data: Partial<{ title: string; slug: string; description: string; vendor: string; product_type: string; status: string; seo_title: string; seo_description: string }>) =>
      this.request<ApiResource<Product>>(`/api/v1/products/${productId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (productId: string) =>
      this.request<void>(`/api/v1/products/${productId}`, { method: 'DELETE' }),
  }

  readonly productOptions = {
    list: (productId: string) => this.request<ApiCollection<ProductOption>>(`/api/v1/products/${productId}/options`),

    create: (productId: string, data: { name: string; position?: number }) =>
      this.request<ApiResource<ProductOption>>(`/api/v1/products/${productId}/options`, { method: 'POST', body: JSON.stringify(data) }),

    update: (productId: string, optionId: string, data: Partial<{ name: string; position: number }>) =>
      this.request<ApiResource<ProductOption>>(`/api/v1/products/${productId}/options/${optionId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (productId: string, optionId: string) =>
      this.request<void>(`/api/v1/products/${productId}/options/${optionId}`, { method: 'DELETE' }),

    values: {
      create: (productId: string, optionId: string, data: { value: string; position?: number }) =>
        this.request<ApiResource<ProductOptionValue>>(`/api/v1/products/${productId}/options/${optionId}/values`, { method: 'POST', body: JSON.stringify(data) }),

      update: (productId: string, optionId: string, valueId: string, data: Partial<{ value: string; position: number }>) =>
        this.request<ApiResource<ProductOptionValue>>(`/api/v1/products/${productId}/options/${optionId}/values/${valueId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (productId: string, optionId: string, valueId: string) =>
        this.request<void>(`/api/v1/products/${productId}/options/${optionId}/values/${valueId}`, { method: 'DELETE' }),
    },
  }

  readonly productVariants = {
    list: (productId: string) => this.request<ApiCollection<ProductVariant>>(`/api/v1/products/${productId}/variants`),

    create: (productId: string, data: {
      title?: string
      sku?: string | null
      barcode?: string | null
      price_amount: number
      compare_at_price_amount?: number | null
      cost_amount?: number | null
      currency?: string
      weight?: number | null
      length?: number | null
      width?: number | null
      height?: number | null
      status?: string
      option_value_ids?: string[]
    }) => this.request<ApiResource<ProductVariant>>(`/api/v1/products/${productId}/variants`, { method: 'POST', body: JSON.stringify(data) }),

    update: (productId: string, variantId: string, data: Partial<{
      title: string
      sku: string | null
      barcode: string | null
      price_amount: number
      compare_at_price_amount: number | null
      cost_amount: number | null
      currency: string
      weight: number | null
      length: number | null
      width: number | null
      height: number | null
      status: string
      option_value_ids: string[]
    }>) => this.request<ApiResource<ProductVariant>>(`/api/v1/products/${productId}/variants/${variantId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (productId: string, variantId: string) =>
      this.request<void>(`/api/v1/products/${productId}/variants/${variantId}`, { method: 'DELETE' }),
  }

  readonly media = {
    attachToProduct: (productId: string, file: File, extra: { alt?: string; position?: number } = {}) => {
      const form = new FormData()
      form.set('file', file)
      if (extra.alt !== undefined) form.set('alt', extra.alt)
      if (extra.position !== undefined) form.set('position', String(extra.position))

      return this.request<ApiResource<Media>>(`/api/v1/products/${productId}/media`, { method: 'POST', body: form })
    },

    attachToVariant: (productId: string, variantId: string, file: File, extra: { alt?: string; position?: number } = {}) => {
      const form = new FormData()
      form.set('file', file)
      if (extra.alt !== undefined) form.set('alt', extra.alt)
      if (extra.position !== undefined) form.set('position', String(extra.position))

      return this.request<ApiResource<Media>>(`/api/v1/products/${productId}/variants/${variantId}/media`, { method: 'POST', body: form })
    },

    update: (mediaId: string, data: Partial<{ alt: string; position: number }>) =>
      this.request<ApiResource<Media>>(`/api/v1/media/${mediaId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (mediaId: string) => this.request<void>(`/api/v1/media/${mediaId}`, { method: 'DELETE' }),
  }

  readonly collections = {
    list: () => this.request<ApiCollection<Collection>>('/api/v1/collections'),

    create: (data: { title: string; slug?: string; description?: string; status?: string }) =>
      this.request<ApiResource<Collection>>('/api/v1/collections', { method: 'POST', body: JSON.stringify(data) }),

    get: (collectionId: string) => this.request<ApiResource<Collection>>(`/api/v1/collections/${collectionId}`),

    update: (collectionId: string, data: Partial<{ title: string; slug: string; description: string; status: string }>) =>
      this.request<ApiResource<Collection>>(`/api/v1/collections/${collectionId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (collectionId: string) =>
      this.request<void>(`/api/v1/collections/${collectionId}`, { method: 'DELETE' }),

    addProduct: (collectionId: string, productId: string) =>
      this.request<ApiResource<Collection>>(`/api/v1/collections/${collectionId}/products/${productId}`, { method: 'POST' }),

    removeProduct: (collectionId: string, productId: string) =>
      this.request<void>(`/api/v1/collections/${collectionId}/products/${productId}`, { method: 'DELETE' }),
  }

  readonly categories = {
    list: () => this.request<ApiCollection<Category>>('/api/v1/categories'),

    create: (data: { title: string; slug?: string; parent_id?: string | null; position?: number }) =>
      this.request<ApiResource<Category>>('/api/v1/categories', { method: 'POST', body: JSON.stringify(data) }),

    get: (categoryId: string) => this.request<ApiResource<Category>>(`/api/v1/categories/${categoryId}`),

    update: (categoryId: string, data: Partial<{ title: string; slug: string; parent_id: string | null; position: number }>) =>
      this.request<ApiResource<Category>>(`/api/v1/categories/${categoryId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (categoryId: string) =>
      this.request<void>(`/api/v1/categories/${categoryId}`, { method: 'DELETE' }),
  }

  readonly locations = {
    list: () => this.request<ApiCollection<Location>>('/api/v1/locations'),

    create: (data: { name: string; status?: string; country?: string; region?: string; city?: string; address?: string }) =>
      this.request<ApiResource<Location>>('/api/v1/locations', { method: 'POST', body: JSON.stringify(data) }),

    update: (locationId: string, data: Partial<{ name: string; status: string; country: string; region: string; city: string; address: string }>) =>
      this.request<ApiResource<Location>>(`/api/v1/locations/${locationId}`, { method: 'PATCH', body: JSON.stringify(data) }),
  }

  readonly inventory = {
    list: () => this.request<ApiCollection<InventoryItem>>('/api/v1/inventory'),

    adjust: (itemId: string, data: { location_id: string; quantity_delta: number; reason: string; reference_type?: string; reference_id?: string }) =>
      this.request<ApiResource<InventoryLevel>>(`/api/v1/inventory/${itemId}/adjust`, { method: 'POST', body: JSON.stringify(data) }),
  }

  /**
   * Read-only this milestone — no shipping/fulfillment endpoints exist
   * yet. Payment state is now visible (see `payments` below), but only
   * ever changes through a verified provider webhook, never an admin
   * action here.
   */
  readonly orders = {
    list: (page?: number) => this.request<ApiCollection<Order>>(`/api/v1/orders${page ? `?page=${page}` : ''}`),

    get: (orderId: string) => this.request<ApiResource<Order>>(`/api/v1/orders/${orderId}`),
  }

  /**
   * Read-only this milestone — no cancel/refund endpoints exist yet.
   */
  readonly payments = {
    list: (page?: number) => this.request<ApiCollection<Payment>>(`/api/v1/payments${page ? `?page=${page}` : ''}`),

    get: (paymentId: string) => this.request<ApiResource<Payment>>(`/api/v1/payments/${paymentId}`),
  }

  health = () => this.request<{ status: string }>('/api/v1/health')
}

export { StorefrontApiClient } from './storefront'
export type { StorefrontApiClientOptions, StorefrontCollectionShowResponse } from './storefront'

import type {
  ApiCollection,
  ApiErrorBody,
  ApiResource,
  Cart,
  StorefrontCategory,
  StorefrontCheckout,
  StorefrontCollection,
  StorefrontOrderConfirmation,
  StorefrontProduct,
  StorefrontStore,
} from '@obscurify/types'
import { ApiClientError } from './index'

export interface StorefrontCollectionShowResponse {
  data: StorefrontCollection
  products: ApiCollection<StorefrontProduct>
}

export interface StorefrontApiClientOptions {
  /**
   * Must already carry the same host the browser is using — the backend
   * resolves the active Store from this request's Host header, never
   * from a header/body parameter. See apps/storefront's useStorefrontApi
   * composable for how this is derived per-request (SSR and client).
   */
  baseUrl: string
}

/**
 * Thin, typed HTTP boundary over the public storefront API
 * (/api/v1/storefront/*). Deliberately separate from ApiClient (merchant
 * admin): no bearer token, no X-Store-Id — tenant comes from the
 * hostname — and cart requests carry the HttpOnly cart cookie via
 * `credentials: 'include'`, which the admin client never needs.
 */
export class StorefrontApiClient {
  constructor(private readonly options: StorefrontApiClientOptions) {}

  private async request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers)
    headers.set('Accept', 'application/json')

    if (init.body && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    const response = await fetch(`${this.options.baseUrl}${path}`, {
      ...init,
      headers,
      credentials: 'include',
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

  readonly store = {
    get: () => this.request<ApiResource<StorefrontStore>>('/api/v1/storefront/store'),
  }

  readonly products = {
    list: (params: { collection?: string; category?: string; sort?: 'newest' | 'price_asc' | 'price_desc'; page?: number } = {}) => {
      const query = new URLSearchParams()
      if (params.collection) query.set('collection', params.collection)
      if (params.category) query.set('category', params.category)
      if (params.sort) query.set('sort', params.sort)
      if (params.page) query.set('page', String(params.page))
      const qs = query.toString()

      return this.request<ApiCollection<StorefrontProduct>>(`/api/v1/storefront/products${qs ? `?${qs}` : ''}`)
    },

    get: (slug: string) => this.request<ApiResource<StorefrontProduct>>(`/api/v1/storefront/products/${slug}`),
  }

  readonly collections = {
    list: () => this.request<ApiCollection<StorefrontCollection>>('/api/v1/storefront/collections'),

    get: (slug: string, page?: number) =>
      this.request<StorefrontCollectionShowResponse>(`/api/v1/storefront/collections/${slug}${page ? `?page=${page}` : ''}`),
  }

  readonly categories = {
    list: () => this.request<ApiCollection<StorefrontCategory>>('/api/v1/storefront/categories'),

    get: (slug: string) => this.request<ApiResource<StorefrontCategory>>(`/api/v1/storefront/categories/${slug}`),
  }

  readonly cart = {
    get: () => this.request<ApiResource<Cart>>('/api/v1/storefront/cart'),

    addItem: (data: { variant_id: string; quantity: number }) =>
      this.request<ApiResource<Cart>>('/api/v1/storefront/cart/items', { method: 'POST', body: JSON.stringify(data) }),

    updateItem: (itemId: string, data: { quantity: number }) =>
      this.request<ApiResource<Cart>>(`/api/v1/storefront/cart/items/${itemId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    removeItem: (itemId: string) =>
      this.request<void>(`/api/v1/storefront/cart/items/${itemId}`, { method: 'DELETE' }),
  }

  readonly checkout = {
    open: () => this.request<ApiResource<StorefrontCheckout>>('/api/v1/storefront/checkout', { method: 'POST' }),

    update: (data: {
      email?: string | null
      phone?: string | null
      shipping_address?: Partial<{
        first_name: string | null
        last_name: string | null
        phone: string | null
        country_code: string | null
        region: string | null
        city: string | null
        postal_code: string | null
        address_line1: string | null
        address_line2: string | null
      }>
      billing_same_as_shipping?: boolean
      billing_address?: Partial<{
        first_name: string | null
        last_name: string | null
        phone: string | null
        country_code: string | null
        region: string | null
        city: string | null
        postal_code: string | null
        address_line1: string | null
        address_line2: string | null
      }>
    }) => this.request<ApiResource<StorefrontCheckout>>('/api/v1/storefront/checkout', { method: 'PATCH', body: JSON.stringify(data) }),

    /**
     * `idempotencyKey` should be generated once per checkout attempt on
     * the client (e.g. a UUID kept in component state) and reused across
     * retries of the *same* attempt — a fresh key means a genuinely new
     * order. See CompleteCheckout/IdempotencyKeyStore on the backend.
     */
    complete: (idempotencyKey: string) =>
      this.request<ApiResource<StorefrontOrderConfirmation>>('/api/v1/storefront/checkout/complete', {
        method: 'POST',
        headers: { 'Idempotency-Key': idempotencyKey },
      }),
  }

  readonly orders = {
    get: (orderId: string) =>
      this.request<ApiResource<StorefrontOrderConfirmation>>(`/api/v1/storefront/orders/${orderId}`),
  }
}

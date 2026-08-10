import type {
  ApiCollection,
  ApiErrorBody,
  ApiResource,
  AuthResponse,
  Category,
  Collection,
  Fulfillment,
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
  Refund,
  ReturnRequest,
  Shipment,
  ShippingMethod,
  ShippingZone,
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
  /**
   * Called whenever any request comes back 401. The bearer token is stale
   * (revoked, expired, or from a session the backend no longer knows about)
   * — the caller should clear local auth/tenant state in response, since
   * the frontend must never keep showing a user as signed in once the
   * backend has stopped agreeing.
   */
  onUnauthorized?: () => void
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
      if (response.status === 401) {
        this.options.onUnauthorized?.()
      }

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
   * Order state is otherwise read-only — no cancel/refund/edit endpoints
   * exist yet. Payment state only ever changes through a verified
   * provider webhook, never an admin action here; shipment creation is
   * the one write action, since it's a genuine merchant workflow step
   * (spec section 15/18).
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

  readonly refunds = {
    list: (page?: number) => this.request<ApiCollection<Refund>>(`/api/v1/refunds${page ? `?page=${page}` : ''}`),

    get: (refundId: string) => this.request<ApiResource<Refund>>(`/api/v1/refunds/${refundId}`),

    /**
     * `idempotencyKey` should be generated once per refund-request attempt
     * and reused across retries of the *same* attempt (spec section 13) —
     * see StorefrontApiClient.checkout.complete() for the identical
     * pattern on the storefront side.
     */
    create: (
      orderId: string,
      data: {
        items?: { return_item_id: string; quantity: number; amount: number }[]
        shipping_amount?: number
        adjustment_amount?: number
        reason?: string | null
        provider?: string | null
      },
      idempotencyKey: string,
    ) =>
      this.request<ApiResource<Refund>>(`/api/v1/orders/${orderId}/refunds`, {
        method: 'POST',
        headers: { 'Idempotency-Key': idempotencyKey },
        body: JSON.stringify(data),
      }),

    cancel: (refundId: string) =>
      this.request<ApiResource<Refund>>(`/api/v1/refunds/${refundId}/cancel`, { method: 'POST' }),
  }

  /**
   * Dev/test-only — backs the fake refund control page. Never available
   * in a production API; calling these against one 404s.
   */
  readonly fakeRefunds = {
    outcome: (externalRefundId: string, outcome: 'success' | 'failure') =>
      this.request<ApiResource<{ processed?: boolean }>>(`/api/v1/fake-refunds/${externalRefundId}/outcome`, {
        method: 'POST',
        body: JSON.stringify({ outcome }),
      }),
  }

  readonly shippingMethods = {
    list: () => this.request<ApiCollection<ShippingMethod>>('/api/v1/shipping-methods'),

    create: (data: {
      name: string
      code: string
      provider: string
      service_code?: string | null
      status?: string
      price_amount: number
      currency: string
      estimated_days_min?: number | null
      estimated_days_max?: number | null
      settings?: Record<string, unknown> | null
      zone_ids?: string[]
    }) => this.request<ApiResource<ShippingMethod>>('/api/v1/shipping-methods', { method: 'POST', body: JSON.stringify(data) }),

    update: (methodId: string, data: Partial<{
      name: string
      code: string
      service_code: string | null
      status: string
      price_amount: number
      currency: string
      estimated_days_min: number | null
      estimated_days_max: number | null
      settings: Record<string, unknown> | null
      zone_ids: string[]
    }>) => this.request<ApiResource<ShippingMethod>>(`/api/v1/shipping-methods/${methodId}`, { method: 'PATCH', body: JSON.stringify(data) }),
  }

  readonly shippingZones = {
    list: () => this.request<ApiCollection<ShippingZone>>('/api/v1/shipping-zones'),

    create: (data: { name: string; status?: string; regions?: { country_code: string; region?: string | null; postal_code_pattern?: string | null }[] }) =>
      this.request<ApiResource<ShippingZone>>('/api/v1/shipping-zones', { method: 'POST', body: JSON.stringify(data) }),

    update: (zoneId: string, data: Partial<{ name: string; status: string; regions: { country_code: string; region?: string | null; postal_code_pattern?: string | null }[] }>) =>
      this.request<ApiResource<ShippingZone>>(`/api/v1/shipping-zones/${zoneId}`, { method: 'PATCH', body: JSON.stringify(data) }),
  }

  readonly shipments = {
    list: (page?: number) => this.request<ApiCollection<Shipment>>(`/api/v1/shipments${page ? `?page=${page}` : ''}`),

    get: (shipmentId: string) => this.request<ApiResource<Shipment>>(`/api/v1/shipments/${shipmentId}`),

    cancel: (shipmentId: string) => this.request<ApiResource<Shipment>>(`/api/v1/shipments/${shipmentId}/cancel`, { method: 'POST' }),
  }

  /**
   * Milestone 7 (Fulfillment Core). A Shipment is now created via
   * `complete()` against a `ready` Fulfillment, not directly from an
   * Order — see `shipments` above, which stays read/cancel-only.
   */
  readonly fulfillments = {
    list: (page?: number) => this.request<ApiCollection<Fulfillment>>(`/api/v1/fulfillments${page ? `?page=${page}` : ''}`),

    get: (fulfillmentId: string) => this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}`),

    create: (orderId: string, data: { items: { order_item_id: string; quantity: number }[]; notes?: string | null }) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/orders/${orderId}/fulfillments`, { method: 'POST', body: JSON.stringify(data) }),

    update: (fulfillmentId: string, data: { notes?: string | null }) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    allocate: (fulfillmentId: string) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}/allocate`, { method: 'POST' }),

    pick: (fulfillmentId: string, items: { fulfillment_item_id: string; picked_quantity: number }[]) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}/pick`, { method: 'POST', body: JSON.stringify({ items }) }),

    pack: (fulfillmentId: string, items: { fulfillment_item_id: string; packed_quantity: number }[]) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}/pack`, { method: 'POST', body: JSON.stringify({ items }) }),

    complete: (fulfillmentId: string, provider: string) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}/complete`, { method: 'POST', body: JSON.stringify({ provider }) }),

    cancel: (fulfillmentId: string) =>
      this.request<ApiResource<Fulfillment>>(`/api/v1/fulfillments/${fulfillmentId}/cancel`, { method: 'POST' }),
  }

  readonly returns = {
    list: (page?: number) => this.request<ApiCollection<ReturnRequest>>(`/api/v1/returns${page ? `?page=${page}` : ''}`),

    get: (returnId: string) => this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}`),

    create: (
      orderId: string,
      data: {
        items: { order_item_id: string; quantity: number; reason: string; condition?: string | null; notes?: string | null }[]
        notes?: string | null
        customer_id?: string | null
      },
    ) => this.request<ApiResource<ReturnRequest>>(`/api/v1/orders/${orderId}/returns`, { method: 'POST', body: JSON.stringify(data) }),

    update: (returnId: string, data: { notes?: string | null }) =>
      this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    approve: (returnId: string) =>
      this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}/approve`, { method: 'POST' }),

    reject: (returnId: string, reason?: string | null) =>
      this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}/reject`, { method: 'POST', body: JSON.stringify({ reason }) }),

    receive: (returnId: string) =>
      this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}/receive`, { method: 'POST' }),

    inspect: (
      returnId: string,
      items: { return_item_id: string; condition: string; photos?: unknown[] | null; notes?: string | null; disposition: string; disposition_notes?: string | null }[],
    ) => this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}/inspect`, { method: 'POST', body: JSON.stringify({ items }) }),

    complete: (returnId: string) =>
      this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}/complete`, { method: 'POST' }),

    cancel: (returnId: string) =>
      this.request<ApiResource<ReturnRequest>>(`/api/v1/returns/${returnId}/cancel`, { method: 'POST' }),
  }

  health = () => this.request<{ status: string }>('/api/v1/health')
}

export { StorefrontApiClient } from './storefront'
export type { StorefrontApiClientOptions, StorefrontCollectionShowResponse } from './storefront'

import type {
  ApiCollection,
  ApiErrorBody,
  ApiResource,
  AuthResponse,
  Product,
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

    if (init.body && !headers.has('Content-Type')) {
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

    create: (data: { title: string; slug?: string; description?: string; status?: string }) =>
      this.request<ApiResource<Product>>('/api/v1/products', { method: 'POST', body: JSON.stringify(data) }),

    get: (productId: string) => this.request<ApiResource<Product>>(`/api/v1/products/${productId}`),

    update: (productId: string, data: Partial<{ title: string; slug: string; description: string; status: string }>) =>
      this.request<ApiResource<Product>>(`/api/v1/products/${productId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (productId: string) =>
      this.request<void>(`/api/v1/products/${productId}`, { method: 'DELETE' }),
  }

  health = () => this.request<{ status: string }>('/api/v1/health')
}

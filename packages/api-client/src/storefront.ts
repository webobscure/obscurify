import type {
  ApiCollection,
  ApiErrorBody,
  ApiResource,
  Cart,
  Customer,
  CustomerAddress,
  CustomerAuthResponse,
  CustomerOrder,
  CustomerOrderSummary,
  CustomerSession,
  CustomerTokenPair,
  FakePaymentInfo,
  NotificationPreference,
  NotificationRecipient,
  ReorderResult,
  ReturnReason,
  ReturnRequest,
  StorefrontCategory,
  StorefrontCheckout,
  StorefrontCollection,
  StorefrontOrderConfirmation,
  StorefrontPayment,
  StorefrontProduct,
  StorefrontShippingRate,
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
  /**
   * A CustomerAccessToken bearer token (Milestone 16) — entirely separate
   * from the merchant admin's Sanctum token (ApiClientOptions.getToken).
   * Only the `account` namespace below ever sends this; every other
   * storefront request stays anonymous/cookie-based exactly as before.
   */
  getCustomerToken?: () => string | null | undefined
  /**
   * Called whenever an `account`-namespace request comes back 401 — the
   * access token is stale (expired, revoked, or its session was logged
   * out elsewhere). The caller should clear local customer auth state.
   */
  onCustomerUnauthorized?: () => void
}

/**
 * Thin, typed HTTP boundary over the public storefront API
 * (/api/v1/storefront/*). Deliberately separate from ApiClient (merchant
 * admin): no merchant bearer token, no X-Store-Id — tenant comes from the
 * hostname — and cart requests carry the HttpOnly cart cookie via
 * `credentials: 'include'`, which the admin client never needs. The
 * `account` namespace (Milestone 16) is the one exception to "no bearer
 * token": customer accounts authenticate via a CustomerAccessToken bearer
 * token, not a cookie — see docs/architecture/customer-accounts.md for
 * why (SPA-style bearer auth, matching the Apps OAuth token pattern,
 * rather than a stateful PHP session).
 */
export class StorefrontApiClient {
  constructor(private readonly options: StorefrontApiClientOptions) {}

  private async request<T>(path: string, init: RequestInit = {}, opts: { customerAuth?: boolean } = {}): Promise<T> {
    const headers = new Headers(init.headers)
    headers.set('Accept', 'application/json')

    if (init.body && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    if (opts.customerAuth) {
      const token = this.options.getCustomerToken?.()
      if (token) {
        headers.set('Authorization', `Bearer ${token}`)
      }
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
      if (response.status === 401 && opts.customerAuth) {
        this.options.onCustomerUnauthorized?.()
      }

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

    /**
     * Requires a shipping address to already be saved on the checkout
     * (via update() above) — the backend resolves rates from that address,
     * never from anything passed here (spec section 9: "do not calculate
     * from frontend").
     */
    shippingRates: () =>
      this.request<ApiCollection<StorefrontShippingRate>>('/api/v1/storefront/checkout/shipping-rates'),

    /**
     * Identifies *which* previously-quoted rate to select — the price is
     * always re-derived server-side, never trusted from here (spec
     * section 11).
     */
    selectShipping: (data: { provider: string; service_code?: string | null; shipping_method_id?: string | null; pickup_point_id?: string | null }) =>
      this.request<ApiResource<StorefrontCheckout>>('/api/v1/storefront/checkout/shipping', {
        method: 'PATCH',
        body: JSON.stringify(data),
      }),

    /**
     * Case-insensitive lookup — rejected with a 422 if the code is
     * unknown/inactive/expired/exhausted or this cart simply doesn't earn
     * it yet (see ApplyDiscountCode on the backend).
     */
    applyDiscountCode: (code: string) =>
      this.request<ApiResource<StorefrontCheckout>>('/api/v1/storefront/checkout/discount-code', {
        method: 'POST',
        body: JSON.stringify({ code }),
      }),

    removeDiscountCode: () =>
      this.request<ApiResource<StorefrontCheckout>>('/api/v1/storefront/checkout/discount-code', { method: 'DELETE' }),
  }

  readonly orders = {
    get: (orderId: string) =>
      this.request<ApiResource<StorefrontOrderConfirmation>>(`/api/v1/storefront/orders/${orderId}`),
  }

  readonly payments = {
    /**
     * `idempotencyKey` should be generated once per payment attempt and
     * reused across retries of the *same* attempt — see
     * StorefrontApiClient.checkout.complete() for the identical pattern.
     */
    create: (orderId: string, provider: string, idempotencyKey: string) =>
      this.request<ApiResource<StorefrontPayment>>(`/api/v1/storefront/orders/${orderId}/payments`, {
        method: 'POST',
        headers: { 'Idempotency-Key': idempotencyKey },
        body: JSON.stringify({ provider }),
      }),
  }

  /**
   * Customer accounts + customer portal (Milestone 16). Register/login/
   * password/verify-email are anonymous and rate-limited
   * (throttle:customer-auth on the backend); everything else requires a
   * valid CustomerAccessToken, sent automatically via
   * StorefrontApiClientOptions.getCustomerToken.
   */
  readonly account = {
    register: (data: { email: string; password: string; first_name?: string; last_name?: string; phone?: string }) =>
      this.request<CustomerAuthResponse>('/api/v1/storefront/account/register', { method: 'POST', body: JSON.stringify(data) }),

    login: (data: { email: string; password: string }) =>
      this.request<CustomerAuthResponse>('/api/v1/storefront/account/login', { method: 'POST', body: JSON.stringify(data) }),

    logout: () =>
      this.request<void>('/api/v1/storefront/account/logout', { method: 'POST' }, { customerAuth: true }),

    /** Rotates the presented refresh token — the old one becomes unusable the instant this succeeds (single-use rotation). */
    refresh: (refreshToken: string) =>
      this.request<ApiResource<CustomerTokenPair>>('/api/v1/storefront/account/refresh', {
        method: 'POST',
        body: JSON.stringify({ refresh_token: refreshToken }),
      }),

    forgotPassword: (email: string) =>
      this.request<{ message: string }>('/api/v1/storefront/account/password/forgot', { method: 'POST', body: JSON.stringify({ email }) }),

    resetPassword: (token: string, password: string) =>
      this.request<{ message: string }>('/api/v1/storefront/account/password/reset', {
        method: 'POST',
        body: JSON.stringify({ token, password }),
      }),

    verifyEmail: (token: string) =>
      this.request<ApiResource<Customer>>('/api/v1/storefront/account/verify-email', { method: 'POST', body: JSON.stringify({ token }) }),

    resendVerification: () =>
      this.request<{ message: string }>('/api/v1/storefront/account/verify-email/resend', { method: 'POST' }, { customerAuth: true }),

    show: () =>
      this.request<ApiResource<Customer>>('/api/v1/storefront/account', {}, { customerAuth: true }),

    update: (data: Partial<{ first_name: string | null; last_name: string | null; phone: string | null }>) =>
      this.request<ApiResource<Customer>>('/api/v1/storefront/account', { method: 'PATCH', body: JSON.stringify(data) }, { customerAuth: true }),

    orders: {
      list: (page?: number) =>
        this.request<ApiCollection<CustomerOrderSummary>>(`/api/v1/storefront/account/orders${page ? `?page=${page}` : ''}`, {}, { customerAuth: true }),

      get: (orderId: string) =>
        this.request<ApiResource<CustomerOrder>>(`/api/v1/storefront/account/orders/${orderId}`, {}, { customerAuth: true }),

      /** "Buy again" — always re-prices live; see ReorderResult.skipped for lines that could not be carried over. */
      reorder: (orderId: string, cartToken?: string | null) =>
        this.request<ApiResource<ReorderResult>>(`/api/v1/storefront/account/orders/${orderId}/reorder`, {
          method: 'POST',
          body: JSON.stringify({ cart_token: cartToken ?? undefined }),
        }, { customerAuth: true }),

      requestReturn: (orderId: string, data: { notes?: string; items: { order_item_id: string; quantity: number; reason: ReturnReason; condition?: string; notes?: string }[] }) =>
        this.request<ApiResource<ReturnRequest>>(`/api/v1/storefront/account/orders/${orderId}/returns`, {
          method: 'POST',
          body: JSON.stringify(data),
        }, { customerAuth: true }),
    },

    addresses: {
      list: () =>
        this.request<ApiCollection<CustomerAddress>>('/api/v1/storefront/account/addresses', {}, { customerAuth: true }),

      create: (data: Partial<CustomerAddress> & { country_code: string; city: string; address_line1: string }) =>
        this.request<ApiResource<CustomerAddress>>('/api/v1/storefront/account/addresses', {
          method: 'POST',
          body: JSON.stringify(data),
        }, { customerAuth: true }),

      update: (addressId: string, data: Partial<CustomerAddress>) =>
        this.request<ApiResource<CustomerAddress>>(`/api/v1/storefront/account/addresses/${addressId}`, {
          method: 'PATCH',
          body: JSON.stringify(data),
        }, { customerAuth: true }),

      remove: (addressId: string) =>
        this.request<void>(`/api/v1/storefront/account/addresses/${addressId}`, { method: 'DELETE' }, { customerAuth: true }),
    },

    sessions: {
      list: () =>
        this.request<ApiCollection<CustomerSession>>('/api/v1/storefront/account/sessions', {}, { customerAuth: true }),

      revoke: (sessionId: string) =>
        this.request<void>(`/api/v1/storefront/account/sessions/${sessionId}`, { method: 'DELETE' }, { customerAuth: true }),
    },

    /**
     * Notification Center + Omnichannel Messaging (Milestone 21) —
     * `/account/notifications` (spec section 10). Read/unread state
     * lives on the recipient row, not the notification itself — see
     * NotificationRecipient.
     */
    notifications: {
      list: (page?: number) =>
        this.request<ApiCollection<NotificationRecipient>>(`/api/v1/storefront/account/notifications${page ? `?page=${page}` : ''}`, {}, { customerAuth: true }),

      markRead: (notificationRecipientId: string) =>
        this.request<ApiResource<NotificationRecipient>>(`/api/v1/storefront/account/notifications/${notificationRecipientId}/read`, { method: 'PATCH' }, { customerAuth: true }),
    },

    notificationPreferences: {
      show: () =>
        this.request<ApiResource<NotificationPreference>>('/api/v1/storefront/account/notification-preferences', {}, { customerAuth: true }),

      update: (data: Partial<Omit<NotificationPreference, 'id' | 'customer_id'>>) =>
        this.request<ApiResource<NotificationPreference>>('/api/v1/storefront/account/notification-preferences', {
          method: 'PATCH',
          body: JSON.stringify(data),
        }, { customerAuth: true }),
    },
  }

  /**
   * Dev/test-only — backs the fake payment page. Never available in a
   * production API (see config/payments.php on the backend); calling
   * these against a production API 404s.
   */
  readonly fakePayments = {
    get: (externalPaymentId: string) =>
      this.request<ApiResource<FakePaymentInfo>>(`/api/v1/fake-payments/${externalPaymentId}`),

    outcome: (externalPaymentId: string, outcome: 'success' | 'failure' | 'cancelled' | 'pending' | 'delayed_success') =>
      this.request<ApiResource<{ processed?: boolean; dispatched?: boolean; delay_seconds?: number }>>(
        `/api/v1/fake-payments/${externalPaymentId}/outcome`,
        { method: 'POST', body: JSON.stringify({ outcome }) },
      ),
  }
}

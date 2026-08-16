import type {
  ApiCollection,
  ApiErrorBody,
  ApiResource,
  App,
  AppExtension,
  AppliedDiscount,
  AppToken,
  AuthResponse,
  Author,
  Blog,
  BlogPost,
  BlogPostStatus,
  BuilderPageState,
  BuilderPreset,
  BuilderPresetType,
  BuilderRevisionRestoreResult,
  BuilderRevisionSummary,
  Category,
  Collection,
  Customer,
  CustomerActivityEvent,
  CustomerAddress,
  CustomerGroup,
  CustomerGroupType,
  CustomerMetric,
  CustomerSegment,
  CustomerSnapshot,
  CustomerTag,
  Dashboard,
  DashboardWidget,
  DashboardWidgetType,
  DiscountCode,
  ExportFormat,
  ExportRecurrence,
  Fulfillment,
  InstalledApp,
  InventoryItem,
  InventoryLevel,
  Location,
  Media,
  Menu,
  MenuItem,
  MenuItemTargetType,
  MetricDefinition,
  Notification,
  NotificationChannel,
  NotificationChannelType,
  NotificationDelivery,
  NotificationDeliveryStatus,
  NotificationEvent,
  NotificationPreference,
  NotificationProvider,
  NotificationStatus,
  NotificationSummary,
  NotificationTemplate,
  Order,
  Page,
  PageStatus,
  PageTemplate,
  PageVersion,
  Payment,
  Product,
  ProductOption,
  ProductOptionValue,
  ProductVariant,
  Promotion,
  PromotionUsage,
  Redirect,
  Refund,
  RenderedPage,
  Report,
  ReportExport,
  ReportType,
  ReturnRequest,
  PinnedSearchResult,
  SavedReport,
  SearchAnalyticsSummary,
  SearchIndex,
  SearchProvider,
  SearchResponse,
  SearchRule,
  SearchSettings,
  SearchSuggestionsResponse,
  SearchSynonym,
  SectionInstance,
  SegmentRuleInput,
  SeoMetadata,
  Shipment,
  ShippingMethod,
  ShippingZone,
  Store,
  Theme,
  ThemeAsset,
  ThemeAssetType,
  ThemeCustomizerState,
  ThemeStatus,
  ThemeTemplate,
  ThemeTemplateType,
  ThemeVersion,
  TimeDimension,
  User,
  WidgetData,
  Workflow,
  WorkflowActionInput,
  WorkflowConditionInput,
  WorkflowExecution,
  WorkflowTemplate,
  WorkflowTriggerCatalogEntry,
  WorkflowVariableCatalogEntry,
  WorkflowVersion,
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
   * Merchant-admin customer management (Milestone 16 spec section 12) —
   * read-only, see AdminCustomerController's docblock on the backend for
   * why there is no update action here (profile edits belong to the
   * customer themselves, via StorefrontApiClient.account).
   */
  readonly customers = {
    /** Search (Milestone 18 spec section 10): tags, groups, segments, metric thresholds — every filter is optional and ANDed together. */
    list: (params: { page?: number; search?: string; status?: string; tag?: string; group_id?: string; segment_id?: string; min_total_spent?: number; max_total_spent?: number; min_order_count?: number; min_lifetime_value?: number } = {}) => {
      const query = new URLSearchParams()
      if (params.page) query.set('page', String(params.page))
      if (params.search) query.set('search', params.search)
      if (params.status) query.set('status', params.status)
      if (params.tag) query.set('tag', params.tag)
      if (params.group_id) query.set('group_id', params.group_id)
      if (params.segment_id) query.set('segment_id', params.segment_id)
      if (params.min_total_spent !== undefined) query.set('min_total_spent', String(params.min_total_spent))
      if (params.max_total_spent !== undefined) query.set('max_total_spent', String(params.max_total_spent))
      if (params.min_order_count !== undefined) query.set('min_order_count', String(params.min_order_count))
      if (params.min_lifetime_value !== undefined) query.set('min_lifetime_value', String(params.min_lifetime_value))
      const qs = query.toString()

      return this.request<ApiCollection<Customer>>(`/api/v1/customers${qs ? `?${qs}` : ''}`)
    },

    get: (customerId: string) => this.request<ApiResource<Customer>>(`/api/v1/customers/${customerId}`),

    orders: (customerId: string, page?: number) =>
      this.request<ApiCollection<Order>>(`/api/v1/customers/${customerId}/orders${page ? `?page=${page}` : ''}`),

    returns: (customerId: string, page?: number) =>
      this.request<ApiCollection<ReturnRequest>>(`/api/v1/customers/${customerId}/returns${page ? `?page=${page}` : ''}`),

    addresses: (customerId: string) =>
      this.request<ApiCollection<CustomerAddress>>(`/api/v1/customers/${customerId}/addresses`),

    activity: (customerId: string, page?: number) =>
      this.request<ApiCollection<CustomerActivityEvent>>(`/api/v1/customers/${customerId}/activity${page ? `?page=${page}` : ''}`),

    /** Milestone 18 — null when RecomputeCustomerMetrics has never run for this customer. */
    metrics: (customerId: string) => this.request<ApiResource<CustomerMetric | null>>(`/api/v1/customers/${customerId}/metrics`),

    metricsHistory: (customerId: string) =>
      this.request<ApiCollection<CustomerSnapshot>>(`/api/v1/customers/${customerId}/metrics/history`),

    groups: (customerId: string) => this.request<ApiCollection<CustomerGroup>>(`/api/v1/customers/${customerId}/groups`),

    segments: (customerId: string) => this.request<ApiCollection<CustomerSegment>>(`/api/v1/customers/${customerId}/segments`),

    tags: (customerId: string) => this.request<ApiCollection<CustomerTag>>(`/api/v1/customers/${customerId}/tags`),

    assignTag: (customerId: string, tagId: string) =>
      this.request<ApiResource<CustomerTag>>(`/api/v1/customers/${customerId}/tags`, { method: 'POST', body: JSON.stringify({ tag_id: tagId }) }),

    removeTag: (customerId: string, tagId: string) =>
      this.request<void>(`/api/v1/customers/${customerId}/tags/${tagId}`, { method: 'DELETE' }),
  }

  /**
   * Customer Groups + Segments + Tags (Milestone 18) — see
   * docs/architecture/customer-intelligence.md. `rules` on
   * create/update is the whole tree, always replaced atomically
   * server-side, never patched incrementally.
   */
  readonly customerGroups = {
    list: () => this.request<ApiCollection<CustomerGroup>>('/api/v1/customer-groups'),

    get: (groupId: string) => this.request<ApiResource<CustomerGroup>>(`/api/v1/customer-groups/${groupId}`),

    create: (data: { name: string; description?: string | null; type: CustomerGroupType; rules?: SegmentRuleInput[] }) =>
      this.request<ApiResource<CustomerGroup>>('/api/v1/customer-groups', { method: 'POST', body: JSON.stringify(data) }),

    update: (groupId: string, data: Partial<{ name: string; description: string | null; status: string; rules: SegmentRuleInput[] }>) =>
      this.request<ApiResource<CustomerGroup>>(`/api/v1/customer-groups/${groupId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (groupId: string) => this.request<void>(`/api/v1/customer-groups/${groupId}`, { method: 'DELETE' }),

    addMember: (groupId: string, customerId: string) =>
      this.request<void>(`/api/v1/customer-groups/${groupId}/members`, { method: 'POST', body: JSON.stringify({ customer_id: customerId }) }),

    removeMember: (groupId: string, customerId: string) =>
      this.request<void>(`/api/v1/customer-groups/${groupId}/members/${customerId}`, { method: 'DELETE' }),
  }

  readonly customerSegments = {
    list: () => this.request<ApiCollection<CustomerSegment>>('/api/v1/customer-segments'),

    get: (segmentId: string) => this.request<ApiResource<CustomerSegment>>(`/api/v1/customer-segments/${segmentId}`),

    create: (data: { name: string; description?: string | null; rules?: SegmentRuleInput[] }) =>
      this.request<ApiResource<CustomerSegment>>('/api/v1/customer-segments', { method: 'POST', body: JSON.stringify(data) }),

    update: (segmentId: string, data: Partial<{ name: string; description: string | null; status: string; rules: SegmentRuleInput[] }>) =>
      this.request<ApiResource<CustomerSegment>>(`/api/v1/customer-segments/${segmentId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (segmentId: string) => this.request<void>(`/api/v1/customer-segments/${segmentId}`, { method: 'DELETE' }),
  }

  readonly customerTags = {
    list: () => this.request<ApiCollection<CustomerTag>>('/api/v1/customer-tags'),

    create: (name: string) => this.request<ApiResource<CustomerTag>>('/api/v1/customer-tags', { method: 'POST', body: JSON.stringify({ name }) }),

    remove: (tagId: string) => this.request<void>(`/api/v1/customer-tags/${tagId}`, { method: 'DELETE' }),
  }

  /**
   * Automation Engine (Milestone 19) — see docs/architecture/automation.md.
   */
  readonly automation = {
    workflows: {
      list: () => this.request<ApiCollection<Workflow>>('/api/v1/automation/workflows'),

      get: (workflowId: string) => this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}`),

      create: (data: { name: string; description?: string | null; trigger?: { event_type: string } | null; conditions?: WorkflowConditionInput[]; actions?: WorkflowActionInput[] }) =>
        this.request<ApiResource<Workflow>>('/api/v1/automation/workflows', { method: 'POST', body: JSON.stringify(data) }),

      update: (workflowId: string, data: Partial<{ name: string; description: string | null; trigger: { event_type: string } | null; conditions: WorkflowConditionInput[]; actions: WorkflowActionInput[] }>) =>
        this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      versions: (workflowId: string) => this.request<ApiCollection<WorkflowVersion>>(`/api/v1/automation/workflows/${workflowId}/versions`),

      publish: (workflowId: string) => this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}/publish`, { method: 'POST' }),

      disable: (workflowId: string) => this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}/disable`, { method: 'POST' }),

      enable: (workflowId: string) => this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}/enable`, { method: 'POST' }),

      archive: (workflowId: string) => this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}/archive`, { method: 'POST' }),

      rollback: (workflowId: string, versionId: string) =>
        this.request<ApiResource<Workflow>>(`/api/v1/automation/workflows/${workflowId}/rollback`, { method: 'POST', body: JSON.stringify({ version_id: versionId }) }),
    },

    executions: {
      list: (params?: { workflow_id?: string; status?: string; page?: number }) => {
        const query = new URLSearchParams()
        if (params?.workflow_id) query.set('workflow_id', params.workflow_id)
        if (params?.status) query.set('status', params.status)
        if (params?.page) query.set('page', String(params.page))
        const suffix = query.toString() ? `?${query.toString()}` : ''

        return this.request<ApiCollection<WorkflowExecution>>(`/api/v1/automation/executions${suffix}`)
      },

      get: (executionId: string) => this.request<ApiResource<WorkflowExecution>>(`/api/v1/automation/executions/${executionId}`),
    },

    templates: {
      list: () => this.request<ApiCollection<WorkflowTemplate>>('/api/v1/automation/templates'),

      instantiate: (templateId: string, name?: string) =>
        this.request<ApiResource<Workflow>>(`/api/v1/automation/templates/${templateId}/instantiate`, { method: 'POST', body: JSON.stringify({ name }) }),
    },

    variables: () => this.request<{ data: WorkflowVariableCatalogEntry[] }>('/api/v1/automation/variables'),

    triggers: () => this.request<{ data: WorkflowTriggerCatalogEntry[] }>('/api/v1/automation/triggers'),
  }

  /**
   * Analytics Platform (Milestone 20) — see docs/architecture/analytics.md.
   */
  readonly analytics = {
    dashboards: {
      default: () => this.request<ApiResource<Dashboard>>('/api/v1/analytics/dashboard'),

      list: () => this.request<ApiCollection<Dashboard>>('/api/v1/analytics/dashboards'),

      get: (dashboardId: string) => this.request<ApiResource<Dashboard>>(`/api/v1/analytics/dashboards/${dashboardId}`),

      create: (data: { name: string; is_default?: boolean }) =>
        this.request<ApiResource<Dashboard>>('/api/v1/analytics/dashboards', { method: 'POST', body: JSON.stringify(data) }),

      update: (dashboardId: string, data: Partial<{ name: string; is_default: boolean }>) =>
        this.request<ApiResource<Dashboard>>(`/api/v1/analytics/dashboards/${dashboardId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (dashboardId: string) => this.request<void>(`/api/v1/analytics/dashboards/${dashboardId}`, { method: 'DELETE' }),
    },

    widgets: {
      all: (dashboardId?: string) =>
        this.request<ApiCollection<DashboardWidget>>(`/api/v1/analytics/widgets${dashboardId ? `?dashboard_id=${dashboardId}` : ''}`),

      list: (dashboardId: string) => this.request<ApiCollection<DashboardWidget>>(`/api/v1/analytics/dashboards/${dashboardId}/widgets`),

      create: (dashboardId: string, data: { type: DashboardWidgetType; title: string; config?: Record<string, unknown>; position?: number }) =>
        this.request<ApiResource<DashboardWidget>>(`/api/v1/analytics/dashboards/${dashboardId}/widgets`, { method: 'POST', body: JSON.stringify(data) }),

      update: (widgetId: string, data: Partial<{ type: DashboardWidgetType; title: string; config: Record<string, unknown>; position: number }>) =>
        this.request<ApiResource<DashboardWidget>>(`/api/v1/analytics/widgets/${widgetId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (widgetId: string) => this.request<void>(`/api/v1/analytics/widgets/${widgetId}`, { method: 'DELETE' }),

      data: (widgetId: string, params?: { time_dimension?: TimeDimension; from?: string; to?: string }) => {
        const query = new URLSearchParams()
        if (params?.time_dimension) query.set('time_dimension', params.time_dimension)
        if (params?.from) query.set('from', params.from)
        if (params?.to) query.set('to', params.to)
        const suffix = query.toString() ? `?${query.toString()}` : ''

        return this.request<{ data: WidgetData | null }>(`/api/v1/analytics/widgets/${widgetId}/data${suffix}`)
      },

      drillDown: (widgetId: string, params?: { time_dimension?: TimeDimension; from?: string; to?: string; page?: number }) => {
        const query = new URLSearchParams()
        if (params?.time_dimension) query.set('time_dimension', params.time_dimension)
        if (params?.from) query.set('from', params.from)
        if (params?.to) query.set('to', params.to)
        if (params?.page) query.set('page', String(params.page))
        const suffix = query.toString() ? `?${query.toString()}` : ''

        return this.request<ApiCollection<Record<string, unknown>>>(`/api/v1/analytics/widgets/${widgetId}/drill-down${suffix}`)
      },
    },

    reports: {
      list: () => this.request<ApiCollection<Report>>('/api/v1/analytics/reports'),

      get: (reportId: string) => this.request<ApiResource<Report>>(`/api/v1/analytics/reports/${reportId}`),

      create: (data: { report_type: ReportType; filters?: Record<string, unknown>; columns?: string[]; saved_report_id?: string }) =>
        this.request<ApiResource<Report>>('/api/v1/analytics/reports', { method: 'POST', body: JSON.stringify(data) }),
    },

    savedReports: {
      list: () => this.request<ApiCollection<SavedReport>>('/api/v1/analytics/saved-reports'),

      create: (data: { name: string; report_type: ReportType; filters?: Record<string, unknown>; columns?: string[] }) =>
        this.request<ApiResource<SavedReport>>('/api/v1/analytics/saved-reports', { method: 'POST', body: JSON.stringify(data) }),

      update: (savedReportId: string, data: Partial<{ name: string; filters: Record<string, unknown>; columns: string[] }>) =>
        this.request<ApiResource<SavedReport>>(`/api/v1/analytics/saved-reports/${savedReportId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (savedReportId: string) => this.request<void>(`/api/v1/analytics/saved-reports/${savedReportId}`, { method: 'DELETE' }),
    },

    exports: {
      create: (reportId: string, data: { format: ExportFormat; scheduled_at?: string | null; recurrence?: ExportRecurrence | null }) =>
        this.request<ApiResource<ReportExport>>(`/api/v1/analytics/reports/${reportId}/exports`, { method: 'POST', body: JSON.stringify(data) }),

      downloadUrl: (exportId: string) => `/api/v1/analytics/exports/${exportId}/download`,
    },

    metrics: () => this.request<ApiCollection<MetricDefinition>>('/api/v1/analytics/metrics'),
  }

  /**
   * Notification Center + Omnichannel Messaging (Milestone 21) — see
   * docs/architecture/notifications.md. Customer-facing preferences and
   * history live under StorefrontApiClient.account instead — see
   * storefront.ts.
   */
  readonly notifications = {
    list: (params?: { status?: NotificationDeliveryStatus | NotificationStatus; channel?: NotificationChannelType; page?: number }) => {
      const query = new URLSearchParams()
      if (params?.status) query.set('status', params.status)
      if (params?.channel) query.set('channel', params.channel)
      if (params?.page) query.set('page', String(params.page))
      const suffix = query.toString() ? `?${query.toString()}` : ''

      return this.request<ApiCollection<NotificationSummary>>(`/api/v1/notifications${suffix}`)
    },

    get: (notificationId: string) => this.request<ApiResource<Notification>>(`/api/v1/notifications/${notificationId}`),

    create: (data: { channel: NotificationChannelType; customer_id?: string; address?: string; template_id?: string; subject?: string; body_text?: string; body_html?: string }) =>
      this.request<ApiResource<Notification>>('/api/v1/notifications', { method: 'POST', body: JSON.stringify(data) }),

    templates: {
      list: () => this.request<ApiCollection<NotificationTemplate>>('/api/v1/notification-templates'),

      get: (templateId: string) => this.request<ApiResource<NotificationTemplate>>(`/api/v1/notification-templates/${templateId}`),

      create: (data: { key?: string | null; name: string; channel: NotificationChannelType; locale?: string; subject?: string | null; body_text: string; body_html?: string | null; is_active?: boolean }) =>
        this.request<ApiResource<NotificationTemplate>>('/api/v1/notification-templates', { method: 'POST', body: JSON.stringify(data) }),

      update: (templateId: string, data: Partial<{ key: string | null; name: string; channel: NotificationChannelType; locale: string; subject: string | null; body_text: string; body_html: string | null; is_active: boolean }>) =>
        this.request<ApiResource<NotificationTemplate>>(`/api/v1/notification-templates/${templateId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (templateId: string) => this.request<void>(`/api/v1/notification-templates/${templateId}`, { method: 'DELETE' }),
    },

    channels: {
      list: () => this.request<ApiCollection<NotificationChannel>>('/api/v1/notification-channels'),

      update: (channelId: string, data: Partial<{ provider_id: string | null; is_enabled: boolean }>) =>
        this.request<ApiResource<NotificationChannel>>(`/api/v1/notification-channels/${channelId}`, { method: 'PATCH', body: JSON.stringify(data) }),
    },

    providers: {
      list: () => this.request<ApiCollection<NotificationProvider>>('/api/v1/notification-providers'),

      create: (data: { code: string; name: string; is_enabled?: boolean; config?: Record<string, unknown> }) =>
        this.request<ApiResource<NotificationProvider>>('/api/v1/notification-providers', { method: 'POST', body: JSON.stringify(data) }),

      update: (providerId: string, data: Partial<{ name: string; is_enabled: boolean; config: Record<string, unknown> }>) =>
        this.request<ApiResource<NotificationProvider>>(`/api/v1/notification-providers/${providerId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (providerId: string) => this.request<void>(`/api/v1/notification-providers/${providerId}`, { method: 'DELETE' }),
    },

    events: {
      list: () => this.request<ApiCollection<NotificationEvent>>('/api/v1/notification-events'),

      create: (data: { event_type: string; channel: NotificationChannelType; template_id: string; is_enabled?: boolean }) =>
        this.request<ApiResource<NotificationEvent>>('/api/v1/notification-events', { method: 'POST', body: JSON.stringify(data) }),

      update: (eventId: string, data: Partial<{ event_type: string; channel: NotificationChannelType; template_id: string; is_enabled: boolean }>) =>
        this.request<ApiResource<NotificationEvent>>(`/api/v1/notification-events/${eventId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (eventId: string) => this.request<void>(`/api/v1/notification-events/${eventId}`, { method: 'DELETE' }),
    },

    /** Backs the "Delivery Log" / "Failed Deliveries" / "Retry Queue" admin views — all three are this same list, filtered by ?status=. */
    deliveries: {
      list: (params?: { status?: NotificationDeliveryStatus; page?: number }) => {
        const query = new URLSearchParams()
        if (params?.status) query.set('status', params.status)
        if (params?.page) query.set('page', String(params.page))
        const suffix = query.toString() ? `?${query.toString()}` : ''

        return this.request<ApiCollection<NotificationDelivery>>(`/api/v1/notification-deliveries${suffix}`)
      },

      retry: (deliveryId: string) =>
        this.request<ApiResource<NotificationDelivery>>(`/api/v1/notification-deliveries/${deliveryId}/retry`, { method: 'POST' }),
    },

    /** Admin-on-behalf-of-a-customer preferences — the customer-self route lives under StorefrontApiClient.account. */
    preferences: {
      get: (customerId: string) => this.request<ApiResource<NotificationPreference>>(`/api/v1/customers/${customerId}/notification-preferences`),

      update: (customerId: string, data: Partial<Omit<NotificationPreference, 'id' | 'customer_id'>>) =>
        this.request<ApiResource<NotificationPreference>>(`/api/v1/customers/${customerId}/notification-preferences`, { method: 'PATCH', body: JSON.stringify(data) }),
    },
  }

  /**
   * Search & Discovery Platform (Milestone 22) — see
   * docs/architecture/search.md. Customer-facing search lives under
   * StorefrontApiClient.search instead — see storefront.ts.
   */
  readonly search = {
    /** Admin "try a search" preview — reuses the exact same ExecuteSearch pipeline the storefront hits. */
    preview: (params: { q?: string; sort?: string; page?: number; per_page?: number } = {}) => {
      const query = new URLSearchParams()
      if (params.q) query.set('q', params.q)
      if (params.sort) query.set('sort', params.sort)
      if (params.page) query.set('page', String(params.page))
      if (params.per_page) query.set('per_page', String(params.per_page))
      const suffix = query.toString() ? `?${query.toString()}` : ''

      return this.request<SearchResponse>(`/api/v1/search-preview${suffix}`)
    },

    index: {
      get: () => this.request<ApiResource<SearchIndex>>('/api/v1/search-index'),

      reindex: (productIds?: string[]) =>
        this.request<ApiResource<SearchIndex>>('/api/v1/search-index/reindex', {
          method: 'POST',
          body: JSON.stringify(productIds ? { product_ids: productIds } : {}),
        }),
    },

    settings: {
      get: () => this.request<ApiResource<SearchSettings>>('/api/v1/search-settings'),

      update: (data: Partial<{ active_provider_id: string | null; results_per_page: number; autocomplete_limit: number; typo_tolerance_enabled: boolean; synonyms_enabled: boolean; facets_enabled: boolean }>) =>
        this.request<ApiResource<SearchSettings>>('/api/v1/search-settings', { method: 'PATCH', body: JSON.stringify(data) }),
    },

    providers: {
      list: () => this.request<ApiCollection<SearchProvider>>('/api/v1/search-providers'),

      create: (data: { code: string; name: string; is_enabled?: boolean; config?: Record<string, unknown> }) =>
        this.request<ApiResource<SearchProvider>>('/api/v1/search-providers', { method: 'POST', body: JSON.stringify(data) }),

      update: (providerId: string, data: Partial<{ name: string; is_enabled: boolean; config: Record<string, unknown> }>) =>
        this.request<ApiResource<SearchProvider>>(`/api/v1/search-providers/${providerId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (providerId: string) => this.request<void>(`/api/v1/search-providers/${providerId}`, { method: 'DELETE' }),
    },

    synonyms: {
      list: () => this.request<ApiCollection<SearchSynonym>>('/api/v1/search-synonyms'),

      create: (data: { term: string; synonyms: string[]; is_bidirectional?: boolean; locale?: string | null; is_active?: boolean }) =>
        this.request<ApiResource<SearchSynonym>>('/api/v1/search-synonyms', { method: 'POST', body: JSON.stringify(data) }),

      update: (synonymId: string, data: Partial<{ term: string; synonyms: string[]; is_bidirectional: boolean; locale: string | null; is_active: boolean }>) =>
        this.request<ApiResource<SearchSynonym>>(`/api/v1/search-synonyms/${synonymId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (synonymId: string) => this.request<void>(`/api/v1/search-synonyms/${synonymId}`, { method: 'DELETE' }),
    },

    /** Backs both the "Search Rules" (boost/hide) and "Ranking" admin views — the same resource, sorted by position. */
    rules: {
      list: () => this.request<ApiCollection<SearchRule>>('/api/v1/search-rules'),

      create: (data: { name: string; keyword?: string | null; action: 'boost' | 'hide'; product_id: string; boost_amount?: number | null; is_active?: boolean; position?: number }) =>
        this.request<ApiResource<SearchRule>>('/api/v1/search-rules', { method: 'POST', body: JSON.stringify(data) }),

      update: (ruleId: string, data: Partial<{ name: string; keyword: string | null; action: 'boost' | 'hide'; product_id: string; boost_amount: number | null; is_active: boolean; position: number }>) =>
        this.request<ApiResource<SearchRule>>(`/api/v1/search-rules/${ruleId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (ruleId: string) => this.request<void>(`/api/v1/search-rules/${ruleId}`, { method: 'DELETE' }),
    },

    pinnedResults: {
      list: () => this.request<ApiCollection<PinnedSearchResult>>('/api/v1/pinned-search-results'),

      create: (data: { keyword: string; product_id: string; position?: number; is_active?: boolean }) =>
        this.request<ApiResource<PinnedSearchResult>>('/api/v1/pinned-search-results', { method: 'POST', body: JSON.stringify(data) }),

      update: (pinnedId: string, data: Partial<{ keyword: string; product_id: string; position: number; is_active: boolean }>) =>
        this.request<ApiResource<PinnedSearchResult>>(`/api/v1/pinned-search-results/${pinnedId}`, { method: 'PATCH', body: JSON.stringify(data) }),

      remove: (pinnedId: string) => this.request<void>(`/api/v1/pinned-search-results/${pinnedId}`, { method: 'DELETE' }),
    },

    analytics: (params?: { from?: string; to?: string }) => {
      const query = new URLSearchParams()
      if (params?.from) query.set('from', params.from)
      if (params?.to) query.set('to', params.to)
      const suffix = query.toString() ? `?${query.toString()}` : ''

      return this.request<ApiResource<SearchAnalyticsSummary>>(`/api/v1/search-analytics${suffix}`)
    },
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

  readonly promotions = {
    list: () => this.request<ApiCollection<Promotion>>('/api/v1/promotions'),

    get: (promotionId: string) => this.request<ApiResource<Promotion>>(`/api/v1/promotions/${promotionId}`),

    create: (data: {
      name: string
      description?: string | null
      trigger_type?: string
      stacking_mode?: string
      priority?: number
      status?: string
      starts_at?: string | null
      ends_at?: string | null
      rules?: { type: string; parameters?: Record<string, unknown> }[]
      actions?: { type: string; parameters?: Record<string, unknown> }[]
    }) => this.request<ApiResource<Promotion>>('/api/v1/promotions', { method: 'POST', body: JSON.stringify(data) }),

    update: (promotionId: string, data: Partial<{
      name: string
      description: string | null
      trigger_type: string
      stacking_mode: string
      priority: number
      status: string
      starts_at: string | null
      ends_at: string | null
      rules: { type: string; parameters?: Record<string, unknown> }[]
      actions: { type: string; parameters?: Record<string, unknown> }[]
    }>) => this.request<ApiResource<Promotion>>(`/api/v1/promotions/${promotionId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    usage: (promotionId: string) => this.request<ApiCollection<PromotionUsage>>(`/api/v1/promotions/${promotionId}/usage`),

    /** "What would apply" for a hypothetical cart — never persists anything (see PreviewPromotions on the backend). */
    preview: (data: {
      items: { product_variant_id: string; quantity: number }[]
      shipping_amount?: number
      country_code?: string | null
      customer_id?: string | null
      discount_code?: string | null
    }) => this.request<{ discount_amount: number; applied: AppliedDiscount[] }>('/api/v1/promotions/preview', { method: 'POST', body: JSON.stringify(data) }),
  }

  readonly discountCodes = {
    list: (promotionId?: string) =>
      this.request<ApiCollection<DiscountCode>>(`/api/v1/discount-codes${promotionId ? `?promotion_id=${promotionId}` : ''}`),

    create: (data: { promotion_id: string; code: string; usage_limit?: number | null; per_customer_limit?: number | null; expires_at?: string | null; status?: string }) =>
      this.request<ApiResource<DiscountCode>>('/api/v1/discount-codes', { method: 'POST', body: JSON.stringify(data) }),

    update: (discountCodeId: string, data: Partial<{ code: string; usage_limit: number | null; per_customer_limit: number | null; expires_at: string | null; status: string }>) =>
      this.request<ApiResource<DiscountCode>>(`/api/v1/discount-codes/${discountCodeId}`, { method: 'PATCH', body: JSON.stringify(data) }),
  }

  /**
   * Apps SDK + OAuth + Extension Platform (Milestone 12) — registration
   * and installation only; the OAuth authorization-code/token exchange
   * itself is the third-party app's own server's job, not something the
   * admin SPA drives beyond the one-time consent redirect (not wrapped
   * here — see docs/architecture/apps.md).
   */
  readonly apps = {
    list: () => this.request<ApiCollection<App>>('/api/v1/apps'),

    get: (appId: string) => this.request<ApiResource<App>>(`/api/v1/apps/${appId}`),

    create: (data: { type?: string; name: string; slug: string; developer?: string | null; description?: string | null; redirect_urls: string[]; requested_scopes?: string[] }) =>
      this.request<ApiResource<App>>('/api/v1/apps', { method: 'POST', body: JSON.stringify(data) }),

    install: (appId: string) =>
      this.request<ApiResource<InstalledApp>>(`/api/v1/apps/${appId}/install`, { method: 'POST' }),
  }

  readonly installedApps = {
    list: () => this.request<ApiCollection<InstalledApp>>('/api/v1/installed-apps'),

    get: (installedAppId: string) => this.request<ApiResource<InstalledApp>>(`/api/v1/installed-apps/${installedAppId}`),

    uninstall: (installedAppId: string) =>
      this.request<ApiResource<InstalledApp>>(`/api/v1/installed-apps/${installedAppId}/uninstall`, { method: 'POST' }),

    tokens: (installedAppId: string) =>
      this.request<ApiCollection<AppToken>>(`/api/v1/installed-apps/${installedAppId}/tokens`),

    webhooks: (installedAppId: string) =>
      this.request<ApiCollection<{ id: string; name: string; target_url: string; event_types: string[]; status: string }>>(`/api/v1/installed-apps/${installedAppId}/webhooks`),
  }

  readonly adminExtensions = {
    list: (point?: string) =>
      this.request<ApiCollection<AppExtension>>(`/api/v1/admin-extensions${point ? `?point=${point}` : ''}`),
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

  /**
   * Theme Engine (Milestone 13). No `destroy`: an unwanted theme is
   * archived via `update({ status: 'archived' })`, never deleted, so
   * ActiveTheme/duplicate lineage never dangles — see routes/api.php.
   */
  readonly themes = {
    list: () => this.request<ApiCollection<Theme>>('/api/v1/themes'),

    get: (themeId: string) => this.request<ApiResource<Theme>>(`/api/v1/themes/${themeId}`),

    create: (data: { name: string; slug: string }) =>
      this.request<ApiResource<Theme>>('/api/v1/themes', { method: 'POST', body: JSON.stringify(data) }),

    update: (themeId: string, data: Partial<{ name: string; status: ThemeStatus }>) =>
      this.request<ApiResource<Theme>>(`/api/v1/themes/${themeId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    /** Publishes the theme's current draft version. */
    publish: (themeId: string) =>
      this.request<ApiResource<Theme>>(`/api/v1/themes/${themeId}/publish`, { method: 'POST' }),

    duplicate: (themeId: string) =>
      this.request<ApiResource<Theme>>(`/api/v1/themes/${themeId}/duplicate`, { method: 'POST' }),

    /** Renders the theme's current *draft* version — never the live one. */
    preview: (themeId: string, template: ThemeTemplateType) =>
      this.request<ApiResource<RenderedPage>>(`/api/v1/themes/${themeId}/preview?template=${encodeURIComponent(template)}`),

    versions: (themeId: string) => this.request<ApiCollection<ThemeVersion>>(`/api/v1/themes/${themeId}/versions`),
  }

  /**
   * Version-scoped editing. Both `settings.update` and `templates.update`
   * are rejected server-side for a published version — published versions
   * are immutable, so only the current draft is editable.
   */
  readonly themeVersions = {
    rollback: (themeVersionId: string) =>
      this.request<ApiResource<Theme>>(`/api/v1/theme-versions/${themeVersionId}/rollback`, { method: 'POST' }),

    settings: {
      /** Flat key -> value map, not a resource collection. */
      list: (themeVersionId: string) =>
        this.request<ApiResource<Record<string, unknown>>>(`/api/v1/theme-versions/${themeVersionId}/settings`),

      update: (themeVersionId: string, settings: Record<string, unknown>) =>
        this.request<ApiResource<Record<string, unknown>>>(`/api/v1/theme-versions/${themeVersionId}/settings`, { method: 'PATCH', body: JSON.stringify({ settings }) }),
    },

    templates: {
      /** Always nine rows, one per ThemeTemplateType. */
      list: (themeVersionId: string) =>
        this.request<ApiCollection<ThemeTemplate>>(`/api/v1/theme-versions/${themeVersionId}/templates`),

      /** Replaces the template's ordered section-instance list wholesale. */
      update: (themeVersionId: string, type: ThemeTemplateType, sections: unknown[]) =>
        this.request<ApiResource<ThemeTemplate>>(`/api/v1/theme-versions/${themeVersionId}/templates/${encodeURIComponent(type)}`, { method: 'PATCH', body: JSON.stringify({ sections }) }),
    },
  }

  /**
   * CMS (Milestone 14). A Page carries the same draft/publish/rollback
   * lifecycle as a Theme, one version at a time — see `themes` above. No
   * `destroy`: an unwanted page is archived via `update({ status: 'archived' })`.
   */
  readonly pages = {
    list: () => this.request<ApiCollection<Page>>('/api/v1/pages'),

    get: (pageId: string) => this.request<ApiResource<Page>>(`/api/v1/pages/${pageId}`),

    create: (data: { title: string; slug: string; sections?: unknown[]; page_template_id?: string | null }) =>
      this.request<ApiResource<Page>>('/api/v1/pages', { method: 'POST', body: JSON.stringify(data) }),

    update: (pageId: string, data: Partial<{ title: string; status: PageStatus }>) =>
      this.request<ApiResource<Page>>(`/api/v1/pages/${pageId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    /** Publishes the page's current draft version. */
    publish: (pageId: string) =>
      this.request<ApiResource<Page>>(`/api/v1/pages/${pageId}/publish`, { method: 'POST' }),

    duplicate: (pageId: string) =>
      this.request<ApiResource<Page>>(`/api/v1/pages/${pageId}/duplicate`, { method: 'POST' }),

    /** Renders the page's current *draft* sections against the store's active theme. */
    preview: (pageId: string) =>
      this.request<ApiResource<RenderedPage>>(`/api/v1/pages/${pageId}/preview`),

    versions: (pageId: string) => this.request<ApiCollection<PageVersion>>(`/api/v1/pages/${pageId}/versions`),
  }

  /**
   * Version-scoped editing. Both `sections` and `seo` are rejected
   * server-side for a published version — published versions are
   * immutable, so only the current draft is editable.
   */
  readonly pageVersions = {
    rollback: (pageVersionId: string) =>
      this.request<ApiResource<Page>>(`/api/v1/page-versions/${pageVersionId}/rollback`, { method: 'POST' }),

    /** Replaces the version's ordered section-instance list wholesale. */
    updateSections: (pageVersionId: string, sections: unknown[]) =>
      this.request<ApiResource<PageVersion>>(`/api/v1/page-versions/${pageVersionId}/sections`, { method: 'PATCH', body: JSON.stringify({ sections }) }),

    seo: {
      /** Returns an all-null record when no SEO row exists yet, never 404. */
      get: (pageVersionId: string) =>
        this.request<ApiResource<SeoMetadata>>(`/api/v1/page-versions/${pageVersionId}/seo`),

      update: (pageVersionId: string, data: Partial<SeoMetadata>) =>
        this.request<ApiResource<SeoMetadata>>(`/api/v1/page-versions/${pageVersionId}/seo`, { method: 'PATCH', body: JSON.stringify(data) }),
    },
  }

  readonly pageTemplates = {
    list: () => this.request<ApiCollection<PageTemplate>>('/api/v1/page-templates'),

    create: (data: { name: string; sections: unknown[] }) =>
      this.request<ApiResource<PageTemplate>>('/api/v1/page-templates', { method: 'POST', body: JSON.stringify(data) }),

    update: (pageTemplateId: string, data: Partial<{ name: string; sections: unknown[] }>) =>
      this.request<ApiResource<PageTemplate>>(`/api/v1/page-templates/${pageTemplateId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (pageTemplateId: string) =>
      this.request<void>(`/api/v1/page-templates/${pageTemplateId}`, { method: 'DELETE' }),
  }

  /**
   * Visual Builder (Milestone 15). There is deliberately no per-operation
   * endpoint (no "move section", no "insert block"): every drag-and-drop
   * mutation happens against a local reactive array and `pages.update`
   * saves the resulting document wholesale — see
   * `App\Domain\Builder\Application\SaveBuilderLayout`.
   *
   * `publish`/`duplicate`/`rollback` return the plain CMS `Page` resource
   * (they are passthroughs to the Milestone 14 actions), *not* the builder
   * state — reload via `pages.get` afterwards to pick the new draft up.
   */
  readonly builder = {
    pages: {
      get: (pageId: string) =>
        this.request<ApiResource<BuilderPageState>>(`/api/v1/builder/pages/${pageId}`),

      /** Replaces the draft's whole section tree, nested blocks included. */
      update: (pageId: string, sections: SectionInstance[]) =>
        this.request<ApiResource<BuilderPageState>>(`/api/v1/builder/pages/${pageId}`, { method: 'PATCH', body: JSON.stringify({ sections }) }),

      publish: (pageId: string) =>
        this.request<ApiResource<Page>>(`/api/v1/builder/pages/${pageId}/publish`, { method: 'POST' }),

      duplicate: (pageId: string) =>
        this.request<ApiResource<Page>>(`/api/v1/builder/pages/${pageId}/duplicate`, { method: 'POST' }),

      /**
       * Takes the page id plus the target version in the body, unlike CMS's
       * own `pageVersions.rollback`, which the version id alone scopes.
       */
      rollback: (pageId: string, pageVersionId: string) =>
        this.request<ApiResource<Page>>(`/api/v1/builder/pages/${pageId}/rollback`, { method: 'POST', body: JSON.stringify({ page_version_id: pageVersionId }) }),

      undo: (pageId: string) =>
        this.request<ApiResource<BuilderPageState>>(`/api/v1/builder/pages/${pageId}/undo`, { method: 'POST' }),

      redo: (pageId: string) =>
        this.request<ApiResource<BuilderPageState>>(`/api/v1/builder/pages/${pageId}/redo`, { method: 'POST' }),
    },

    revisions: {
      /** Newest first. Every save — autosave included — appends one. */
      list: (pageId: string) =>
        this.request<ApiCollection<BuilderRevisionSummary>>(`/api/v1/builder/pages/${pageId}/revisions`),

      /** Returns only `{draft_version_id, sections}` — no undo/redo flags. */
      restore: (pageId: string, revisionId: string) =>
        this.request<ApiResource<BuilderRevisionRestoreResult>>(`/api/v1/builder/pages/${pageId}/revisions/${revisionId}/restore`, { method: 'POST' }),
    },

    presets: {
      list: (type?: BuilderPresetType) =>
        this.request<ApiCollection<BuilderPreset>>(`/api/v1/builder/presets${type ? `?type=${encodeURIComponent(type)}` : ''}`),
    },

    /**
     * Read-only field metadata plus current values. Saving goes through the
     * Milestone 13 endpoint `themeVersions.settings.update` with the
     * `theme_version_id` this returns — there is no builder-side write path.
     */
    themeCustomizer: () =>
      this.request<ApiResource<ThemeCustomizerState>>('/api/v1/builder/theme-customizer'),
  }

  /**
   * Scoped to a theme *version* — a merchant's asset library belongs to the
   * draft they are currently editing, which is the same
   * `theme_version_id` `builder.themeCustomizer()` returns.
   */
  readonly themeAssets = {
    list: (themeVersionId: string) =>
      this.request<ApiCollection<ThemeAsset>>(`/api/v1/theme-versions/${themeVersionId}/assets`),

    upload: (themeVersionId: string, file: File, type: ThemeAssetType) => {
      const form = new FormData()
      form.set('file', file)
      form.set('type', type)

      return this.request<ApiResource<ThemeAsset>>(`/api/v1/theme-versions/${themeVersionId}/assets`, { method: 'POST', body: form })
    },

    remove: (themeAssetId: string) =>
      this.request<void>(`/api/v1/theme-assets/${themeAssetId}`, { method: 'DELETE' }),
  }

  readonly menus = {
    /** Listed menus carry no `items` — only `get` builds the nested tree. */
    list: () => this.request<ApiCollection<Menu>>('/api/v1/menus'),

    get: (menuId: string) => this.request<ApiResource<Menu>>(`/api/v1/menus/${menuId}`),

    create: (data: { name: string; handle: string }) =>
      this.request<ApiResource<Menu>>('/api/v1/menus', { method: 'POST', body: JSON.stringify(data) }),

    update: (menuId: string, data: Partial<{ name: string; handle: string }>) =>
      this.request<ApiResource<Menu>>(`/api/v1/menus/${menuId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (menuId: string) => this.request<void>(`/api/v1/menus/${menuId}`, { method: 'DELETE' }),

    addItem: (menuId: string, data: { label: string; target_type: MenuItemTargetType; target_id?: string | null; url?: string | null; parent_id?: string | null; position?: number }) =>
      this.request<ApiResource<MenuItem>>(`/api/v1/menus/${menuId}/items`, { method: 'POST', body: JSON.stringify(data) }),
  }

  readonly menuItems = {
    update: (menuItemId: string, data: Partial<{ label: string; target_type: MenuItemTargetType; target_id: string | null; url: string | null; parent_id: string | null; position: number }>) =>
      this.request<ApiResource<MenuItem>>(`/api/v1/menu-items/${menuItemId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (menuItemId: string) => this.request<void>(`/api/v1/menu-items/${menuItemId}`, { method: 'DELETE' }),
  }

  readonly authors = {
    list: () => this.request<ApiCollection<Author>>('/api/v1/authors'),

    create: (data: { name: string; bio?: string | null; avatar_path?: string | null }) =>
      this.request<ApiResource<Author>>('/api/v1/authors', { method: 'POST', body: JSON.stringify(data) }),

    update: (authorId: string, data: Partial<{ name: string; bio: string | null; avatar_path: string | null }>) =>
      this.request<ApiResource<Author>>(`/api/v1/authors/${authorId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (authorId: string) => this.request<void>(`/api/v1/authors/${authorId}`, { method: 'DELETE' }),
  }

  readonly blogs = {
    list: () => this.request<ApiCollection<Blog>>('/api/v1/blogs'),

    get: (blogId: string) => this.request<ApiResource<Blog>>(`/api/v1/blogs/${blogId}`),

    create: (data: { title: string; slug: string }) =>
      this.request<ApiResource<Blog>>('/api/v1/blogs', { method: 'POST', body: JSON.stringify(data) }),

    update: (blogId: string, data: Partial<{ title: string; slug: string }>) =>
      this.request<ApiResource<Blog>>(`/api/v1/blogs/${blogId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (blogId: string) => this.request<void>(`/api/v1/blogs/${blogId}`, { method: 'DELETE' }),

    posts: (blogId: string) => this.request<ApiCollection<BlogPost>>(`/api/v1/blogs/${blogId}/posts`),
  }

  /**
   * `status` is never sent on create — the backend derives `scheduled` vs
   * `draft` from whether `scheduled_at` is set.
   */
  readonly blogPosts = {
    get: (blogPostId: string) => this.request<ApiResource<BlogPost>>(`/api/v1/blog-posts/${blogPostId}`),

    create: (blogId: string, data: { title: string; slug: string; author_id?: string | null; excerpt?: string | null; body: string; featured_image_path?: string | null; scheduled_at?: string | null }) =>
      this.request<ApiResource<BlogPost>>(`/api/v1/blogs/${blogId}/posts`, { method: 'POST', body: JSON.stringify(data) }),

    update: (blogPostId: string, data: Partial<{ title: string; slug: string; author_id: string | null; excerpt: string | null; body: string; status: BlogPostStatus; featured_image_path: string | null; scheduled_at: string | null }>) =>
      this.request<ApiResource<BlogPost>>(`/api/v1/blog-posts/${blogPostId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    publish: (blogPostId: string) =>
      this.request<ApiResource<BlogPost>>(`/api/v1/blog-posts/${blogPostId}/publish`, { method: 'POST' }),

    remove: (blogPostId: string) => this.request<void>(`/api/v1/blog-posts/${blogPostId}`, { method: 'DELETE' }),

    seo: {
      get: (blogPostId: string) => this.request<ApiResource<SeoMetadata>>(`/api/v1/blog-posts/${blogPostId}/seo`),

      update: (blogPostId: string, data: Partial<SeoMetadata>) =>
        this.request<ApiResource<SeoMetadata>>(`/api/v1/blog-posts/${blogPostId}/seo`, { method: 'PATCH', body: JSON.stringify(data) }),
    },
  }

  readonly redirects = {
    list: () => this.request<ApiCollection<Redirect>>('/api/v1/redirects'),

    create: (data: { from_path: string; to_path: string; status_code?: number }) =>
      this.request<ApiResource<Redirect>>('/api/v1/redirects', { method: 'POST', body: JSON.stringify(data) }),

    update: (redirectId: string, data: Partial<{ from_path: string; to_path: string; status_code: number }>) =>
      this.request<ApiResource<Redirect>>(`/api/v1/redirects/${redirectId}`, { method: 'PATCH', body: JSON.stringify(data) }),

    remove: (redirectId: string) => this.request<void>(`/api/v1/redirects/${redirectId}`, { method: 'DELETE' }),
  }

  health = () => this.request<{ status: string }>('/api/v1/health')
}

export { StorefrontApiClient } from './storefront'
export type { StorefrontApiClientOptions, StorefrontCollectionShowResponse } from './storefront'

export { StorefrontGraphQLClient } from './storefront-graphql'
export type { StorefrontGraphQLClientOptions } from './storefront-graphql'

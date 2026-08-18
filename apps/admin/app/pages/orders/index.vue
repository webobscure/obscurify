<template>
  <div class="page">
    <PageHeader :title="t('ordersList.title')" />

    <p v-if="!activeStore.storeId.value" class="hint">{{ t('chrome.select_a_store') }}</p>

    <template v-else>
      <div class="toolbar">
        <FilterBar
          :search="query"
          :search-placeholder="t('ordersList.search_placeholder')"
          :has-active-filters="hasActiveFilters"
          testid="orders-search"
          @update:search="query = $event"
          @reset="resetFilters"
        >
          <select v-model="statusFilter" data-testid="orders-filter-status">
            <option value="">{{ t('ordersList.filter_status') }}: {{ t('ordersList.filter_all') }}</option>
            <option v-for="s in ORDER_STATUSES" :key="s" :value="s">{{ t(`orderStatuses.order.${s}`) }}</option>
          </select>
          <select v-model="financialFilter" data-testid="orders-filter-financial">
            <option value="">{{ t('ordersList.filter_financial_status') }}: {{ t('ordersList.filter_all') }}</option>
            <option v-for="s in FINANCIAL_STATUSES" :key="s" :value="s">{{ t(`orderStatuses.financial.${s}`) }}</option>
          </select>
          <select v-model="fulfillmentFilter" data-testid="orders-filter-fulfillment">
            <option value="">{{ t('ordersList.filter_fulfillment_status') }}: {{ t('ordersList.filter_all') }}</option>
            <option v-for="s in FULFILLMENT_STATUSES" :key="s" :value="s">{{ t(`orderStatuses.fulfillment.${s}`) }}</option>
          </select>
          <select v-model="sortKey" data-testid="orders-sort">
            <option value="created_desc">{{ t('ordersList.sort_newest') }}</option>
            <option value="created_asc">{{ t('ordersList.sort_oldest') }}</option>
            <option value="total_desc">{{ t('ordersList.sort_total_desc') }}</option>
            <option value="total_asc">{{ t('ordersList.sort_total_asc') }}</option>
          </select>
        </FilterBar>

        <Dropdown variant="menu" class="views">
          <template #trigger>
            <AppIcon name="collections" size="sm" /> {{ activeViewLabel }} <AppIcon name="chevron" size="sm" />
          </template>
          <button role="menuitem" type="button" @click="applyView(null)">{{ t('ordersList.saved_views_all') }}</button>
          <div v-if="savedViews.length" class="group">
            <button v-for="view in savedViews" :key="view.id" role="menuitem" type="button" @click="applyView(view)">
              <span>{{ view.name }}</span>
              <IconButton icon="close" size="sm" :ariaLabel="t('ordersList.saved_view_delete')" @click.stop="removeView(view.id)" />
            </button>
          </div>
          <div class="group">
            <button role="menuitem" type="button" @click="saveViewOpen = true">+ {{ t('ordersList.saved_view_save') }}</button>
          </div>
        </Dropdown>
      </div>

      <p class="scope-note">{{ t('ordersList.results_scope_note') }}</p>

      <div v-if="selected.length" class="bulk-bar">
        <span>{{ t('ordersList.bulk_selected', { count: selected.length }) }}</span>
        <Button size="sm" variant="secondary" icon="upload" @click="exportSelected">{{ t('ordersList.bulk_export') }}</Button>
      </div>

      <DataTable
        :columns="columns"
        :rows="visibleOrders"
        :row-key="(r) => (r as Order).id"
        :row-label="(r) => `#${(r as Order).number}`"
        selectable
        :selected="selected"
        :sort-key="tableSortKey"
        :sort-dir="tableSortDir"
        :loading="loading"
        :error="error"
        :empty-title="hasActiveFilters ? t('ordersList.empty_no_results_title') : t('ordersList.empty_title')"
        :empty-description="hasActiveFilters ? t('ordersList.empty_no_results_description') : t('ordersList.empty_description')"
        @update:selected="selected = $event"
        @sort="onSort"
        @retry="reload"
      >
        <template #cell-order="{ row }">
          <span class="order-number" @click.stop="router.push(`/orders/${(row as Order).id}`)">#{{ (row as Order).number }}</span>
        </template>
        <template #cell-customer="{ row }">
          <div class="customer-cell">
            <Avatar :name="customerName(row as Order)" size="sm" />
            <span>{{ customerName(row as Order) }}</span>
          </div>
        </template>
        <template #cell-status="{ row }"><OrderStatusBadge :status="(row as Order).order_status" domain="order" /></template>
        <template #cell-financial="{ row }"><OrderStatusBadge :status="(row as Order).financial_status" domain="financial" /></template>
        <template #cell-fulfillment="{ row }"><OrderStatusBadge :status="(row as Order).fulfillment_status" domain="fulfillment" /></template>
        <template #cell-total="{ row }">{{ formatMoney({ amount: (row as Order).total_amount, currency: (row as Order).currency }, intlLocale) }}</template>
        <template #cell-created="{ row }">{{ formatDate((row as Order).created_at) }}</template>
      </DataTable>

      <Pagination
        v-if="meta"
        :page="meta.current_page"
        :last-page="meta.last_page"
        :per-page="meta.per_page"
        :total="meta.total"
        @update:page="onPageChange"
      />
    </template>

    <Modal v-model:open="saveViewOpen" :title="t('ordersList.saved_view_save')" size="sm">
      <form class="stack" @submit.prevent="submitSaveView">
        <Input v-model="newViewName" :label="t('ordersList.saved_view_name_placeholder')" required />
        <Button type="submit" variant="primary">{{ t('common.save') }}</Button>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'
import type { ApiCollection, FinancialStatus, FulfillmentStatus, Order, OrderStatus } from '@obscurify/types'

const ORDER_STATUSES: OrderStatus[] = ['open', 'cancelled', 'closed']
const FINANCIAL_STATUSES: FinancialStatus[] = ['pending', 'authorized', 'paid', 'partially_refunded', 'refunded', 'voided']
const FULFILLMENT_STATUSES: FulfillmentStatus[] = ['unfulfilled', 'partial', 'fulfilled']

interface SavedView {
  id: string
  name: string
  query: string
  statusFilter: string
  financialFilter: string
  fulfillmentFilter: string
  sortKey: string
}

const { t, locale } = useI18n()
const api = useApi()
const activeStore = useActiveStore()
const router = useRouter()
const toast = useToast()

const orders = ref<Order[]>([])
const meta = ref<ApiCollection<Order>['meta'] | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const selected = ref<string[]>([])
const page = ref(1)

const query = ref('')
const statusFilter = ref('')
const financialFilter = ref('')
const fulfillmentFilter = ref('')
const sortKey = ref<'created_desc' | 'created_asc' | 'total_desc' | 'total_asc'>('created_desc')

const activeViewId = ref<string | null>(null)
const saveViewOpen = ref(false)
const newViewName = ref('')
const savedViews = ref<SavedView[]>([])

const intlLocale = computed(() => (locale.value === 'ru' ? 'ru-RU' : locale.value))

const hasActiveFilters = computed(() => !!(query.value || statusFilter.value || financialFilter.value || fulfillmentFilter.value))

const activeViewLabel = computed(() => {
  const view = savedViews.value.find(v => v.id === activeViewId.value)
  return view ? view.name : t('ordersList.saved_views')
})

const columns = [
  { key: 'order', label: t('ordersList.col_order') },
  { key: 'customer', label: t('ordersList.col_customer') },
  { key: 'status', label: t('ordersList.col_status') },
  { key: 'financial', label: t('ordersList.col_payment') },
  { key: 'fulfillment', label: t('ordersList.col_fulfillment') },
  { key: 'total', label: t('ordersList.col_total'), align: 'right' as const, sortable: true },
  { key: 'created', label: t('ordersList.col_created'), sortable: true },
]

/**
 * Every filter/search/sort below runs against `orders.value` — the
 * currently loaded page only. `OrderController::index()` is hardcoded
 * (`orderByDesc('number')->paginate()`, no query params at all — unlike
 * `customers.list()`, which already supports server-side search/filter);
 * adding real server-side search here would mean changing backend
 * business logic, out of scope for this milestone. `results_scope_note`
 * says so plainly rather than presenting a "global search" that silently
 * only searches 15-50 rows.
 */
const visibleOrders = computed(() => {
  let result = orders.value

  const q = query.value.trim().toLowerCase()
  if (q) {
    result = result.filter((order) => {
      const name = customerName(order).toLowerCase()
      return String(order.number).includes(q) || name.includes(q) || (order.email ?? '').toLowerCase().includes(q)
    })
  }

  if (statusFilter.value) result = result.filter(o => o.order_status === statusFilter.value)
  if (financialFilter.value) result = result.filter(o => o.financial_status === financialFilter.value)
  if (fulfillmentFilter.value) result = result.filter(o => o.fulfillment_status === fulfillmentFilter.value)

  const sorted = [...result]
  switch (sortKey.value) {
    case 'created_asc': sorted.sort((a, b) => a.created_at.localeCompare(b.created_at)); break
    case 'total_desc': sorted.sort((a, b) => b.total_amount - a.total_amount); break
    case 'total_asc': sorted.sort((a, b) => a.total_amount - b.total_amount); break
    default: sorted.sort((a, b) => b.created_at.localeCompare(a.created_at))
  }

  return sorted
})

const tableSortKey = computed(() => (sortKey.value.startsWith('total') ? 'total' : 'created'))
const tableSortDir = computed(() => (sortKey.value.endsWith('asc') ? 'asc' : 'desc'))

function onSort({ key, dir }: { key: string; dir: 'asc' | 'desc' | null }) {
  if (!dir) { sortKey.value = 'created_desc'; return }
  sortKey.value = `${key}_${dir}` as typeof sortKey.value
}

function customerName(order: Order): string {
  if (order.customer) {
    const name = `${order.customer.first_name ?? ''} ${order.customer.last_name ?? ''}`.trim()
    if (name) return name
  }
  return order.email ?? t('orderEditor.no_customer')
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(intlLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function resetFilters() {
  query.value = ''
  statusFilter.value = ''
  financialFilter.value = ''
  fulfillmentFilter.value = ''
  activeViewId.value = null
}

function onPageChange(newPage: number) {
  page.value = newPage
  reload()
}

async function reload() {
  if (!activeStore.storeId.value) return
  loading.value = true
  error.value = null
  try {
    const response = await api.orders.list(page.value)
    orders.value = response.data
    meta.value = response.meta ?? null
    selected.value = []
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : t('common.something_went_wrong')
  } finally {
    loading.value = false
  }
}

/** Client-side CSV export of the current selection — the only bulk action with anything real behind it (Orders has no bulk-write endpoint at all; fulfill/return/refund are single-order operations). */
function exportSelected() {
  const rows = orders.value.filter(o => selected.value.includes(o.id))
  const header = ['Number', 'Customer', 'Email', 'Order status', 'Payment status', 'Fulfillment status', 'Total', 'Currency', 'Created at']
  const lines = rows.map(o => [
    o.number,
    customerName(o),
    o.email ?? '',
    o.order_status,
    o.financial_status,
    o.fulfillment_status,
    (o.total_amount / 100).toFixed(2),
    o.currency,
    o.created_at,
  ].map(field => `"${String(field).replaceAll('"', '""')}"`).join(','))

  const csv = [header.join(','), ...lines].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `orders-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
  toast.success(t('ordersList.bulk_export'))
}

const SAVED_VIEWS_KEY = 'obscurify_admin_order_saved_views'

function loadSavedViews() {
  if (!import.meta.client) return
  try {
    const raw = localStorage.getItem(SAVED_VIEWS_KEY)
    savedViews.value = raw ? JSON.parse(raw) : []
  } catch {
    savedViews.value = []
  }
}

function persistSavedViews() {
  if (!import.meta.client) return
  localStorage.setItem(SAVED_VIEWS_KEY, JSON.stringify(savedViews.value))
}

function applyView(view: SavedView | null) {
  if (!view) {
    resetFilters()
    return
  }
  query.value = view.query
  statusFilter.value = view.statusFilter
  financialFilter.value = view.financialFilter
  fulfillmentFilter.value = view.fulfillmentFilter
  sortKey.value = view.sortKey as typeof sortKey.value
  activeViewId.value = view.id
}

function submitSaveView() {
  if (!newViewName.value.trim()) return
  const view: SavedView = {
    id: crypto.randomUUID(),
    name: newViewName.value.trim(),
    query: query.value,
    statusFilter: statusFilter.value,
    financialFilter: financialFilter.value,
    fulfillmentFilter: fulfillmentFilter.value,
    sortKey: sortKey.value,
  }
  savedViews.value = [...savedViews.value, view]
  persistSavedViews()
  activeViewId.value = view.id
  newViewName.value = ''
  saveViewOpen.value = false
}

function removeView(id: string) {
  savedViews.value = savedViews.value.filter(v => v.id !== id)
  persistSavedViews()
  if (activeViewId.value === id) activeViewId.value = null
  persistSavedViews()
}

loadSavedViews()
watch(() => activeStore.storeId.value, (id) => { if (id) reload() }, { immediate: true })
</script>

<style scoped>
.page { max-width: var(--content-max-width); }

.hint { color: var(--color-text-muted); }

.toolbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.toolbar :deep(.filter-bar) { flex: 1; margin-bottom: 0; }

.views {
  flex-shrink: 0;
}
.views :deep(.dropdown-trigger) {
  height: 36px;
  padding: 0 var(--space-3);
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}
.views :deep(.dropdown-menu) { min-width: 220px; }
.views :deep([role='menuitem']) { justify-content: space-between; }
.views .group + .group,
.views :deep(.group) {
  border-top: var(--border-width) solid var(--color-border);
  margin-top: var(--space-1);
  padding-top: var(--space-1);
}

.scope-note {
  margin: var(--space-2) 0 0;
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
}

.bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-accent-bg);
  border-radius: var(--radius-md);
  padding: var(--space-2) var(--space-3);
  margin: var(--space-3) 0;
  font-size: var(--text-sm);
  color: var(--color-text);
}

.order-number {
  font-weight: var(--font-weight-medium);
  color: var(--color-accent);
  cursor: pointer;
}
.order-number:hover { text-decoration: underline; }

.customer-cell {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.stack { display: flex; flex-direction: column; gap: var(--space-3); }
</style>

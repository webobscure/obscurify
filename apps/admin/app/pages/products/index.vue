<template>
  <div>
    <PageHeader :title="t('productsList.title')">
      <template #actions>
        <button type="button" class="btn primary" data-testid="create-product-button" @click="createOpen = true">+ {{ t('productsList.create') }}</button>
      </template>
    </PageHeader>

    <p v-if="!activeStore.storeId.value" class="hint">{{ t('chrome.select_a_store') }}</p>

    <template v-else>
      <FilterBar
        :search="filters.search"
        :search-placeholder="t('productsList.search_placeholder')"
        :has-active-filters="hasActiveFilters"
        testid="products-search"
        @update:search="onSearchInput"
        @reset="resetFilters"
      >
        <select v-model="filters.status" data-testid="products-filter-status" @change="reload">
          <option value="">{{ t('productsList.filter_all_statuses') }}</option>
          <option value="draft">{{ t('productStatus.draft') }}</option>
          <option value="active">{{ t('productStatus.active') }}</option>
          <option value="archived">{{ t('productStatus.archived') }}</option>
        </select>
        <input v-model="filters.vendor" type="text" data-testid="products-filter-vendor" :placeholder="t('productsList.filter_vendor_placeholder')" @change="reload">
        <input v-model="filters.productType" type="text" data-testid="products-filter-type" :placeholder="t('productsList.filter_product_type_placeholder')" @change="reload">
        <select v-model="filters.collectionId" data-testid="products-filter-collection" @change="reload">
          <option value="">{{ t('productsList.filter_all_collections') }}</option>
          <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.title }}</option>
        </select>
      </FilterBar>

      <p v-if="error" class="error-banner">{{ error }}</p>

      <div v-if="selected.length" class="bulk-bar">
        <span>{{ t('productsList.bulk_selected', { count: selected.length }) }}</span>
        <div class="bulk-actions">
          <button type="button" class="btn sm" @click="bulkUpdateStatus('active')">{{ t('productsList.bulk_activate') }}</button>
          <button type="button" class="btn sm" @click="bulkUpdateStatus('archived')">{{ t('productsList.bulk_archive') }}</button>
          <button type="button" class="btn sm danger" @click="bulkDeleteOpen = true">{{ t('productsList.bulk_delete') }}</button>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="check-col">
                <input type="checkbox" :aria-label="t('common.select_all')" :checked="allSelected" @change="toggleAll(($event.target as HTMLInputElement).checked)">
              </th>
              <th>{{ t('productsList.col_product') }}</th>
              <th>{{ t('productsList.col_status') }}</th>
              <th class="num">{{ t('productsList.col_price') }}</th>
              <th class="num">{{ t('productsList.col_variants') }}</th>
              <th>{{ t('productsList.col_vendor') }}</th>
              <th>{{ t('productsList.col_updated') }}</th>
            </tr>
          </thead>
          <tbody v-if="loading">
            <tr v-for="n in 6" :key="n">
              <td><Skeleton variant="block" width="16px" height="16px" /></td>
              <td><Skeleton width="60%" /></td>
              <td><Skeleton width="50px" /></td>
              <td class="num"><Skeleton width="60px" /></td>
              <td class="num"><Skeleton width="20px" /></td>
              <td><Skeleton width="70%" /></td>
              <td><Skeleton width="70%" /></td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr v-for="product in products" :key="product.id" class="row" data-testid="product-row" :data-product-id="product.id" :data-product-title="product.title" @click="router.push(`/products/${product.id}`)">
              <td class="check-col" @click.stop>
                <input type="checkbox" :aria-label="t('common.select_row', { row: product.title })" :checked="selected.includes(product.id)" @change="toggleOne(product.id, ($event.target as HTMLInputElement).checked)">
              </td>
              <td>
                <div class="product-cell">
                  <span class="thumb">
                    <img v-if="product.media?.[0]" :src="product.media[0].url" :alt="product.media[0].alt ?? ''">
                  </span>
                  <span>{{ product.title }}</span>
                </div>
              </td>
              <td><ProductStatusBadge :status="product.status" /></td>
              <td class="num">{{ priceRange(product) }}</td>
              <td class="num">{{ product.variants?.length ?? 0 }}</td>
              <td>{{ product.vendor || '—' }}</td>
              <td>{{ formatDate(product.updated_at) }}</td>
            </tr>
          </tbody>
        </table>

        <EmptyState
          v-if="!loading && !products.length"
          :title="hasActiveFilters ? t('productsList.empty_no_results_title') : t('productsList.empty_title')"
          :description="hasActiveFilters ? t('productsList.empty_no_results_description') : t('productsList.empty_description')"
        >
          <template v-if="!hasActiveFilters" #action>
            <button type="button" class="btn primary" @click="createOpen = true">+ {{ t('productsList.create') }}</button>
          </template>
        </EmptyState>
      </div>

      <Pagination
        v-if="meta"
        :page="meta.current_page"
        :last-page="meta.last_page"
        :per-page="meta.per_page"
        :total="meta.total"
        @update:page="onPageChange"
      />
    </template>

    <Modal v-model:open="createOpen" :title="t('productsList.create_modal_title')" size="sm">
      <form class="stack" @submit.prevent="submitCreate">
        <label class="field">
          <span>{{ t('productsList.create_modal_field_title') }}</span>
          <input v-model="newTitle" type="text" required autofocus data-testid="new-product-title-input">
        </label>
        <button type="submit" class="btn primary" data-testid="submit-create-product">{{ t('productsList.create_modal_submit') }}</button>
      </form>
    </Modal>

    <Modal v-model:open="bulkDeleteOpen" :title="t('productsList.delete_confirm_title')" size="sm">
      <p>{{ t('productsList.delete_confirm_body') }}</p>
      <template #actions>
        <button type="button" class="btn" @click="bulkDeleteOpen = false">{{ t('common.cancel') }}</button>
        <button type="button" class="btn danger" @click="bulkDelete">{{ t('common.delete') }}</button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'
import type { ApiCollection, Product } from '@obscurify/types'

const { t, locale } = useI18n()
const api = useApi()
const activeStore = useActiveStore()
const router = useRouter()

const products = ref<Product[]>([])
const meta = ref<ApiCollection<Product>['meta'] | null>(null)
const collections = ref<Array<{ id: string; title: string }>>([])
const loading = ref(true)
const error = ref<string | null>(null)
const selected = ref<string[]>([])
const createOpen = ref(false)
const bulkDeleteOpen = ref(false)
const newTitle = ref('')

const filters = reactive({
  search: '',
  status: '',
  vendor: '',
  productType: '',
  collectionId: '',
  page: 1,
})

const hasActiveFilters = computed(() =>
  !!(filters.search || filters.status || filters.vendor || filters.productType || filters.collectionId),
)

const allSelected = computed(() => products.value.length > 0 && selected.value.length === products.value.length)

let searchDebounce: ReturnType<typeof setTimeout> | undefined

function onSearchInput(value: string) {
  filters.search = value
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => reload(), 300)
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  filters.vendor = ''
  filters.productType = ''
  filters.collectionId = ''
  reload()
}

function onPageChange(page: number) {
  filters.page = page
  reload()
}

async function reload() {
  if (!activeStore.storeId.value) return
  loading.value = true
  filters.page = filters.page ?? 1

  try {
    error.value = null
    const response = await api.products.list({
      page: filters.page,
      search: filters.search || undefined,
      status: filters.status || undefined,
      vendor: filters.vendor || undefined,
      productType: filters.productType || undefined,
      collectionId: filters.collectionId || undefined,
    })
    products.value = response.data
    meta.value = response.meta ?? null
    selected.value = []
  } catch (err) {
    error.value = err instanceof ApiClientError ? err.message : t('common.something_went_wrong')
  } finally {
    loading.value = false
  }
}

function priceRange(product: Product) {
  const amounts = (product.variants ?? []).map(v => v.price_amount)
  if (!amounts.length) return '—'
  const currency = product.variants![0]!.currency
  const min = Math.min(...amounts)
  const max = Math.max(...amounts)
  if (min === max) return formatMoney({ amount: min, currency })
  return `${formatMoney({ amount: min, currency })} – ${formatMoney({ amount: max, currency })}`
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(locale.value === 'ru' ? 'ru-RU' : locale.value, { dateStyle: 'medium' }).format(new Date(value))
}

function toggleOne(id: string, checked: boolean) {
  selected.value = checked ? [...selected.value, id] : selected.value.filter(v => v !== id)
}

function toggleAll(checked: boolean) {
  selected.value = checked ? products.value.map(p => p.id) : []
}

async function bulkUpdateStatus(status: string) {
  await Promise.all(selected.value.map(id => api.products.update(id, { status }).catch(() => null)))
  await reload()
}

async function bulkDelete() {
  await Promise.all(selected.value.map(id => api.products.remove(id).catch(() => null)))
  bulkDeleteOpen.value = false
  await reload()
}

async function submitCreate() {
  if (!newTitle.value.trim()) return
  try {
    const result = await api.products.create({ title: newTitle.value.trim() })
    createOpen.value = false
    newTitle.value = ''
    router.push(`/products/${result.data.id}`)
  } catch (err) {
    error.value = err instanceof ApiClientError ? err.message : t('common.something_went_wrong')
  }
}

watch(() => activeStore.storeId.value, (id) => {
  if (id) reload()
}, { immediate: true })

const collectionsResponse = await api.collections.list().catch(() => null)
if (collectionsResponse) collections.value = collectionsResponse.data.map(c => ({ id: c.id, title: c.title }))
</script>

<style scoped>
.hint {
  color: var(--color-text-muted);
}

.error-banner {
  color: var(--color-danger);
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  font-size: var(--text-sm);
  margin-bottom: var(--space-4);
}

.bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-accent-bg);
  border: 1px solid var(--color-accent);
  border-radius: var(--radius-md);
  padding: var(--space-2) var(--space-3);
  margin-bottom: var(--space-3);
  font-size: var(--text-sm);
  color: var(--color-text);
}

.bulk-actions { display: flex; gap: var(--space-2); }

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.table thead th {
  position: sticky;
  top: 0;
  text-align: left;
  font-weight: var(--font-weight-medium);
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-subtle);
  padding: var(--space-2) var(--space-3);
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface-muted);
}

.table td {
  padding: var(--space-2) var(--space-3);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text);
}
.table tbody tr:last-child td { border-bottom: none; }

.table td.num, .table th.num { text-align: right; font-variant-numeric: tabular-nums; }

.check-col { width: 36px; }

.row { cursor: pointer; }
.row:hover { background: var(--color-surface-muted); }

.product-cell {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.thumb {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  overflow: hidden;
  flex-shrink: 0;
}
.thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

.stack { display: flex; flex-direction: column; gap: var(--space-3); }
.field { display: flex; flex-direction: column; gap: 4px; font-size: var(--text-sm); }
.field span { color: var(--color-text-muted); font-size: var(--text-xs); }
.field input { padding: var(--space-2); border: 1px solid var(--color-border-strong); border-radius: var(--radius-sm); background: var(--color-surface); color: var(--color-text); }

.btn {
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  color: var(--color-text);
}
.btn.sm { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); }
.btn.primary { background: var(--color-accent); border-color: var(--color-accent); color: var(--color-text-on-accent); }
.btn.danger { color: var(--color-danger); border-color: var(--color-danger); }
</style>

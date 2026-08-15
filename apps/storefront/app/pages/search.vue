<template>
  <div>
    <h1>Search</h1>

    <form class="search-form" @submit.prevent="applyQuery">
      <div class="search-input">
        <input v-model="queryInput" type="search" placeholder="Search products…" @input="onInput">
        <ul v-if="suggestions.length && queryInput" class="suggestions">
          <li v-for="suggestion in suggestions" :key="suggestion.productId">
            <button type="button" @click="selectSuggestion(suggestion)">{{ suggestion.title }}</button>
          </li>
        </ul>
      </div>
      <button type="submit">Search</button>
    </form>

    <form class="filters" @submit.prevent>
      <label>
        Sort
        <select v-model="sort">
          <option value="relevance">Relevance</option>
          <option value="newest">Newest</option>
          <option value="oldest">Oldest</option>
          <option value="price_asc">Price: low to high</option>
          <option value="price_desc">Price: high to low</option>
          <option value="best_selling">Best selling</option>
        </select>
      </label>

      <label v-if="facets && Object.keys(facets.vendor).length">
        Vendor
        <select v-model="vendorFilter">
          <option value="">All vendors</option>
          <option v-for="(count, vendor) in facets.vendor" :key="vendor" :value="vendor">{{ vendor }} ({{ count }})</option>
        </select>
      </label>

      <label v-if="facets && facets.category.length">
        Category
        <select v-model="categoryFilter">
          <option value="">All categories</option>
          <option v-for="cat in facets.category" :key="cat.id" :value="cat.id">{{ cat.label }} ({{ cat.count }})</option>
        </select>
      </label>
    </form>

    <p v-if="pending">Loading…</p>
    <p v-else-if="result && result.meta.total === 0">No results for "{{ activeQuery }}".</p>
    <ul v-else-if="result" class="result-grid">
      <li v-for="item in result.data" :key="item.product_id">
        <NuxtLink :to="`/products/${item.slug}`" @click="recordClick(item)">
          <img v-if="item.thumbnail_url" :src="item.thumbnail_url" :alt="item.title" width="200">
          <div>{{ item.title }} <span v-if="item.is_pinned" class="pinned-badge">Featured</span></div>
          <div v-if="item.price.min !== null && item.price.currency">{{ formatMoney({ amount: item.price.min, currency: item.price.currency }) }}</div>
        </NuxtLink>
      </li>
    </ul>

    <nav v-if="result && result.meta.total > result.meta.per_page" class="pagination">
      <button type="button" :disabled="page <= 1" @click="page--">Previous</button>
      <span>Page {{ result.meta.page }}</span>
      <button type="button" :disabled="result.data.length < result.meta.per_page" @click="page++">Next</button>
    </nav>
  </div>
</template>

<script setup lang="ts">
import type { SearchResponse, SearchResultItem, SearchSuggestionProduct } from '@obscurify/types'
import { formatMoney } from '~/utils/money'

const route = useRoute()
const router = useRouter()

const activeQuery = computed(() => (route.query.q as string) ?? '')
const queryInput = ref(activeQuery.value)
const sort = ref((route.query.sort as string) ?? 'relevance')
const page = ref(Number(route.query.page ?? 1))
const vendorFilter = ref((route.query.vendor as string) ?? '')
const categoryFilter = ref((route.query.category as string) ?? '')

const suggestions = ref<SearchSuggestionProduct[]>([])
let suggestTimer: ReturnType<typeof setTimeout> | undefined

function onInput() {
  if (suggestTimer) clearTimeout(suggestTimer)
  suggestTimer = setTimeout(async () => {
    if (!queryInput.value) {
      suggestions.value = []
      return
    }
    const response = await useStorefrontApi().search.suggestions(queryInput.value)
    suggestions.value = response.data.products
  }, 200)
}

function selectSuggestion(suggestion: SearchSuggestionProduct) {
  suggestions.value = []
  queryInput.value = suggestion.title
  applyQuery()
}

function applyQuery() {
  suggestions.value = []
  page.value = 1
  router.replace({ query: { ...route.query, q: queryInput.value || undefined, page: undefined } })
}

watch([sort, page, vendorFilter, categoryFilter], ([newSort, newPage, newVendor, newCategory]) => {
  router.replace({
    query: {
      ...route.query,
      sort: newSort === 'relevance' ? undefined : newSort,
      page: newPage === 1 ? undefined : newPage,
      vendor: newVendor || undefined,
      category: newCategory || undefined,
    },
  })
})

const filters = computed(() => {
  const f: Record<string, unknown> = {}
  if (vendorFilter.value) f.vendors = [vendorFilter.value]
  if (categoryFilter.value) f.category_ids = [categoryFilter.value]
  return f
})

const { data, pending } = await useAsyncData<SearchResponse>(
  'search-results',
  () => useStorefrontApi().search.index({
    q: activeQuery.value,
    sort: sort.value,
    page: page.value,
    filters: filters.value,
  }),
  { watch: [activeQuery, sort, page, filters] },
)

const result = computed(() => data.value)
const facets = computed(() => data.value?.facets)

function recordClick(item: SearchResultItem) {
  if (!result.value) return
  useStorefrontApi().search.click({ search_query_id: result.value.meta.search_query_id, product_id: item.product_id }).catch(() => {})
}

useSeoMeta({
  title: 'Search',
  description: 'Search products.',
})
</script>

<style scoped>
.search-form {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.search-input {
  position: relative;
  flex: 1;
}

.search-input input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ccc;
  border-radius: 6px;
}

.suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 6px;
  margin-top: 0.25rem;
  list-style: none;
  padding: 0.25rem 0;
  z-index: 10;
}

.suggestions button {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.4rem 0.75rem;
  background: none;
  border: none;
  cursor: pointer;
}

.suggestions button:hover {
  background: #f5f5f5;
}

.filters {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.result-grid {
  list-style: none;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1.5rem;
}

.result-grid a {
  color: inherit;
  text-decoration: none;
}

.result-grid img {
  max-width: 100%;
  border-radius: 6px;
}

.pinned-badge {
  font-size: 0.75rem;
  color: #a06400;
}

.pagination {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-top: 2rem;
}
</style>

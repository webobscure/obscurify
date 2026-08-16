<template>
  <div>
    <h1>Products</h1>

    <form class="filters" @submit.prevent>
      <label>
        Sort
        <select v-model="sort">
          <option value="newest">Newest</option>
          <option value="price_asc">Price: low to high</option>
          <option value="price_desc">Price: high to low</option>
        </select>
      </label>
      <span class="transport-toggle" data-testid="transport-toggle">
        <NuxtLink :to="{ query: { ...route.query, transport: 'rest' } }" :class="{ active: transport === 'rest' }">REST</NuxtLink>
        <NuxtLink :to="{ query: { ...route.query, transport: 'graphql' } }" :class="{ active: transport === 'graphql' }">GraphQL</NuxtLink>
      </span>
    </form>

    <p v-if="pending">Loading…</p>
    <ul v-else-if="products.length" class="product-grid">
      <li v-for="product in products" :key="product.id">
        <NuxtLink :to="`/products/${product.slug}`">
          <img v-if="product.media[0]" :src="product.media[0].url" :alt="product.media[0].alt ?? product.title" width="200">
          <div>{{ product.title }}</div>
          <div v-if="product.price">{{ formatMoney(product.price) }}</div>
        </NuxtLink>
      </li>
    </ul>
    <p v-else>No products found.</p>

    <nav v-if="meta && meta.last_page > 1" class="pagination">
      <button type="button" :disabled="page <= 1" @click="page--">Previous</button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button type="button" :disabled="page >= meta.last_page" @click="page++">Next</button>
    </nav>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const router = useRouter()

const sort = ref((route.query.sort as string) ?? 'newest')
const page = ref(Number(route.query.page ?? 1))
const collection = computed(() => route.query.collection as string | undefined)
const category = computed(() => route.query.category as string | undefined)

/**
 * Milestone 23 (spec section 9): proof that the storefront can switch
 * from REST to GraphQL without touching business logic — the only
 * thing this toggle changes is which client resolves `.products.list()`,
 * because StorefrontGraphQLClient returns the identical
 * ApiCollection<StorefrontProduct> shape StorefrontApiClient does (see
 * that class's own docblock). Every line below this — sort, pagination,
 * the template — is unaware which transport served the data.
 */
const transport = computed(() => (route.query.transport === 'graphql' ? 'graphql' : 'rest'))

watch([sort, page], ([newSort, newPage]) => {
  router.replace({ query: { ...route.query, sort: newSort, page: newPage === 1 ? undefined : newPage } })
})

const { data, pending } = await useAsyncData(
  'products-listing',
  () => {
    const client = transport.value === 'graphql' ? useStorefrontGraphQL() : useStorefrontApi()

    return client.products.list({
      sort: sort.value as 'newest' | 'price_asc' | 'price_desc',
      page: page.value,
      collection: collection.value,
      category: category.value,
    })
  },
  { watch: [sort, page, collection, category, transport] },
)

const products = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

useSeoMeta({
  title: 'Products',
  description: 'Browse all products.',
})
</script>

<style scoped>
.filters {
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.transport-toggle {
  display: inline-flex;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.transport-toggle a {
  color: #888;
  text-decoration: none;
}

.transport-toggle a.active {
  color: inherit;
  font-weight: 600;
  text-decoration: underline;
}

.product-grid {
  list-style: none;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1.5rem;
}

.product-grid a {
  color: inherit;
  text-decoration: none;
}

.product-grid img {
  max-width: 100%;
  border-radius: 6px;
}

.pagination {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-top: 2rem;
}
</style>

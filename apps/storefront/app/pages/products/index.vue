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

watch([sort, page], ([newSort, newPage]) => {
  router.replace({ query: { ...route.query, sort: newSort, page: newPage === 1 ? undefined : newPage } })
})

const { data, pending } = await useAsyncData(
  'products-listing',
  () => useStorefrontApi().products.list({
    sort: sort.value as 'newest' | 'price_asc' | 'price_desc',
    page: page.value,
    collection: collection.value,
    category: category.value,
  }),
  { watch: [sort, page, collection, category] },
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

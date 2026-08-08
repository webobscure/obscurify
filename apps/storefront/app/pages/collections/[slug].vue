<template>
  <div v-if="collection">
    <h1>{{ collection.title }}</h1>
    <p>{{ collection.description }}</p>

    <ul v-if="products.length" class="product-grid">
      <li v-for="product in products" :key="product.id">
        <NuxtLink :to="`/products/${product.slug}`">
          <img v-if="product.media[0]" :src="product.media[0].url" :alt="product.media[0].alt ?? product.title" width="200">
          <div>{{ product.title }}</div>
          <div v-if="product.price">{{ formatMoney(product.price) }}</div>
        </NuxtLink>
      </li>
    </ul>
    <p v-else>No products in this collection yet.</p>

    <nav v-if="meta && meta.last_page > 1" class="pagination">
      <button type="button" :disabled="page <= 1" @click="page--">Previous</button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button type="button" :disabled="page >= meta.last_page" @click="page++">Next</button>
    </nav>
  </div>
  <p v-else-if="pending">Loading…</p>
  <p v-else>Collection not found.</p>
</template>

<script setup lang="ts">
const route = useRoute()
const slug = route.params.slug as string
const page = ref(1)

const { data, pending } = await useAsyncData(
  `collection-${slug}`,
  () => useStorefrontApi().collections.get(slug, page.value),
  { watch: [page] },
)

const collection = computed(() => data.value?.data ?? null)
const products = computed(() => data.value?.products.data ?? [])
const meta = computed(() => data.value?.products.meta)

useSeoMeta({
  title: () => collection.value?.title,
  description: () => collection.value?.description ?? undefined,
  ogTitle: () => collection.value?.title,
  ogDescription: () => collection.value?.description ?? undefined,
})

const canonicalUrl = useRequestURL().origin + `/collections/${slug}`

useHead(() => ({
  link: [{ rel: 'canonical', href: canonicalUrl }],
}))
</script>

<style scoped>
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

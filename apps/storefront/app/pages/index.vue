<template>
  <div>
    <h1>{{ store?.name }}</h1>

    <h2>Latest products</h2>
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
    <p v-else>No products yet.</p>
  </div>
</template>

<script setup lang="ts">
const store = await useStorefrontStore()

useSeoMeta({
  description: `Shop at ${store.value?.name ?? 'our store'}.`,
})

const { data, pending } = await useAsyncData('home-products', () => useStorefrontApi().products.list({ sort: 'newest' }))
const products = computed(() => data.value?.data ?? [])
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
</style>

<template>
  <div>
    <h1>Products</h1>

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="products.length">
        <thead>
          <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Vendor</th>
            <th>Status</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id">
            <td>{{ product.title }}</td>
            <td>{{ product.slug }}</td>
            <td>{{ product.vendor }}</td>
            <td>{{ product.status }}</td>
            <td><NuxtLink :to="`/products/${product.id}`">Edit</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No products yet.</p>

      <h2>Create a product</h2>
      <form @submit.prevent="handleCreate">
        <input v-model="title" type="text" placeholder="Product title" required >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create product' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Product } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const products = ref<Product[]>([])
const title = ref('')
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()
const router = useRouter()

async function loadProducts() {
  if (!activeStore.storeId.value) return
  const response = await useApi().products.list()
  products.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    const response = await useApi().products.create({ title: title.value })
    router.push(`/products/${response.data.id}`)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

onMounted(loadProducts)
watch(() => activeStore.storeId.value, loadProducts)
</script>

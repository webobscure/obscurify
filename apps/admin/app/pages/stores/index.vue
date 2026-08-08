<template>
  <div>
    <PageHeader title="Stores" />

    <table v-if="stores.length">
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th>Status</th>
          <th/>
        </tr>
      </thead>
      <tbody>
        <tr v-for="store in stores" :key="store.id">
          <td>{{ store.name }}</td>
          <td>{{ store.slug }}</td>
          <td>{{ store.status }}</td>
          <td>
            <button v-if="activeStore.storeId.value !== store.id" @click="handleActivate(store.id)">
              Activate
            </button>
            <strong v-else>Active</strong>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else>No stores yet.</p>

    <h2>Create a store</h2>
    <form @submit.prevent="handleCreate">
      <input v-model="name" type="text" placeholder="Store name" required >
      <input v-model="slug" type="text" placeholder="store-slug" required pattern="[a-z0-9\-_]+" >
      <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create store' }}</button>
    </form>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import type { Store } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const stores = ref<Store[]>([])
const name = ref('')
const slug = ref('')
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function loadStores() {
  const response = await useApi().stores.list()
  stores.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().stores.create({ name: name.value, slug: slug.value })
    name.value = ''
    slug.value = ''
    await loadStores()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function handleActivate(storeId: string) {
  error.value = null
  try {
    await activeStore.activate(storeId)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(loadStores)
</script>

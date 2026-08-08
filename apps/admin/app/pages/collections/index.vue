<template>
  <div>
    <PageHeader title="Collections" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="collections.length">
        <thead>
          <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Status</th>
            <th>Products</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="collection in collections" :key="collection.id">
            <td>{{ collection.title }}</td>
            <td>{{ collection.slug }}</td>
            <td>{{ collection.status }}</td>
            <td>{{ collection.products?.length ?? 0 }}</td>
            <td><button type="button" @click="handleDelete(collection.id)">Delete</button></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No collections yet.</p>

      <h2>Create a collection</h2>
      <form @submit.prevent="handleCreate">
        <input v-model="title" type="text" placeholder="Collection title" required >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create collection' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Collection } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const collections = ref<Collection[]>([])
const title = ref('')
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().collections.list()
  collections.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().collections.create({ title: title.value })
    title.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function handleDelete(id: string) {
  error.value = null
  try {
    await useApi().collections.remove(id)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

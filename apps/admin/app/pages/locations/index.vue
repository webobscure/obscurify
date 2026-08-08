<template>
  <div>
    <h1>Locations</h1>

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="locations.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>City</th>
            <th>Country</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="location in locations" :key="location.id">
            <td>{{ location.name }}</td>
            <td>{{ location.status }}</td>
            <td>{{ location.city }}</td>
            <td>{{ location.country }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No locations yet.</p>

      <h2>Create a location</h2>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name (e.g. Main Warehouse)" required >
        <input v-model="form.country" type="text" placeholder="Country" >
        <input v-model="form.region" type="text" placeholder="Region" >
        <input v-model="form.city" type="text" placeholder="City" >
        <input v-model="form.address" type="text" placeholder="Address" >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create location' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Location } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const locations = ref<Location[]>([])
const form = reactive({ name: '', country: '', region: '', city: '', address: '' })
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().locations.list()
  locations.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().locations.create({ ...form })
    form.name = ''
    form.country = ''
    form.region = ''
    form.city = ''
    form.address = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 360px;
}
</style>

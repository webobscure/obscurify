<template>
  <div>
    <h1>Inventory</h1>

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="items.length">
        <thead>
          <tr>
            <th>Variant</th>
            <th>Location</th>
            <th>On hand</th>
            <th>Reserved</th>
            <th>Available</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="item in items" :key="item.id">
            <tr v-for="level in item.levels" :key="level.id">
              <td>{{ item.product_variant_id }}</td>
              <td>{{ locationName(level.location_id) }}</td>
              <td>{{ level.on_hand }}</td>
              <td>{{ level.reserved }}</td>
              <td>{{ level.available }}</td>
            </tr>
            <tr v-if="!item.levels?.length">
              <td>{{ item.product_variant_id }}</td>
              <td colspan="4">No stock recorded yet.</td>
            </tr>
          </template>
        </tbody>
      </table>
      <p v-else>No inventory-tracked variants yet — create a product variant first.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { InventoryItem, Location } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const items = ref<InventoryItem[]>([])
const locations = ref<Location[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

function locationName(id: string): string {
  return locations.value.find(l => l.id === id)?.name ?? id
}

async function load() {
  if (!activeStore.storeId.value) return
  try {
    const [itemsResponse, locationsResponse] = await Promise.all([
      useApi().inventory.list(),
      useApi().locations.list(),
    ])
    items.value = itemsResponse.data
    locations.value = locationsResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

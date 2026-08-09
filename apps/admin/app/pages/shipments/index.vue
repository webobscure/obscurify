<template>
  <div>
    <PageHeader title="Shipments" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="shipments.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>Provider</th>
            <th>Tracking</th>
            <th>Created</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="shipment in shipments" :key="shipment.id">
            <td>{{ shipment.status }}</td>
            <td>{{ shipment.provider }}</td>
            <td>{{ shipment.tracking_number ?? '—' }}</td>
            <td>{{ shipment.created_at }}</td>
            <td><NuxtLink :to="`/shipments/${shipment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No shipments yet.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Shipment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const shipments = ref<Shipment[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().shipments.list()
    shipments.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

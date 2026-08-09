<template>
  <div>
    <PageHeader title="Fulfillments" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="fulfillments.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>Order</th>
            <th>Items</th>
            <th>Created</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fulfillment in fulfillments" :key="fulfillment.id">
            <td><span class="status" :class="`status-${fulfillment.status}`">{{ fulfillment.status }}</span></td>
            <td><NuxtLink :to="`/orders/${fulfillment.order_id}`">View order</NuxtLink></td>
            <td>{{ fulfillment.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0 }}</td>
            <td>{{ fulfillment.created_at }}</td>
            <td><NuxtLink :to="`/fulfillments/${fulfillment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No fulfillments yet.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Fulfillment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const fulfillments = ref<Fulfillment[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().fulfillments.list()
    fulfillments.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.status {
  text-transform: capitalize;
}
</style>

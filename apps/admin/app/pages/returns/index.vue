<template>
  <div>
    <PageHeader title="Returns" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="returns.length">
        <thead>
          <tr>
            <th>Number</th>
            <th>Status</th>
            <th>Order</th>
            <th>Items</th>
            <th>Requested</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="returnRequest in returns" :key="returnRequest.id">
            <td>#{{ returnRequest.number }}</td>
            <td><span class="status" :class="`status-${returnRequest.status}`">{{ returnRequest.status }}</span></td>
            <td><NuxtLink :to="`/orders/${returnRequest.order_id}`">View order</NuxtLink></td>
            <td>{{ returnRequest.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0 }}</td>
            <td>{{ returnRequest.requested_at }}</td>
            <td><NuxtLink :to="`/returns/${returnRequest.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No returns yet.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ReturnRequest } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const returns = ref<ReturnRequest[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().returns.list()
    returns.value = response.data
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

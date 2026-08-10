<template>
  <div>
    <PageHeader title="Refunds" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="refunds.length">
        <thead>
          <tr>
            <th>Number</th>
            <th>Status</th>
            <th>Order</th>
            <th>Provider</th>
            <th>Amount</th>
            <th>Requested</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="refund in refunds" :key="refund.id">
            <td>#{{ refund.number }}</td>
            <td><span class="status" :class="`status-${refund.status}`">{{ refund.status }}</span></td>
            <td><NuxtLink :to="`/orders/${refund.order_id}`">View order</NuxtLink></td>
            <td>{{ refund.provider ?? 'manual' }}</td>
            <td>{{ formatMoney({ amount: refund.amount, currency: refund.currency }) }}</td>
            <td>{{ refund.requested_at }}</td>
            <td><NuxtLink :to="`/refunds/${refund.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No refunds yet.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Refund } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const refunds = ref<Refund[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().refunds.list()
    refunds.value = response.data
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

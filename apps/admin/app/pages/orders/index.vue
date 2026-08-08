<template>
  <div>
    <PageHeader title="Orders" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="orders.length">
        <thead>
          <tr>
            <th>Number</th>
            <th>Customer</th>
            <th>Order</th>
            <th>Financial</th>
            <th>Fulfillment</th>
            <th>Total</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>#{{ order.number }}</td>
            <td>{{ order.customer?.first_name }} {{ order.customer?.last_name }} <span v-if="!order.customer">{{ order.email ?? '—' }}</span></td>
            <td>{{ order.order_status }}</td>
            <td>{{ order.financial_status }}</td>
            <td>{{ order.fulfillment_status }}</td>
            <td>{{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</td>
            <td><NuxtLink :to="`/orders/${order.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No orders yet.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Order } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const orders = ref<Order[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().orders.list()
    orders.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<template>
  <div>
    <h1>Order history</h1>
    <p class="links"><NuxtLink to="/account">Back to my account</NuxtLink></p>

    <ClientOnly>
      <p v-if="loading">Loading…</p>
      <template v-else-if="orders.length">
        <table>
          <thead>
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Fulfillment</th>
              <th>Total</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td><NuxtLink :to="`/account/orders/${order.id}`">#{{ order.number }}</NuxtLink></td>
              <td>{{ formatDate(order.created_at) }}</td>
              <td>{{ order.order_status }}</td>
              <td>{{ order.financial_status }}</td>
              <td>{{ order.fulfillment_status }}</td>
              <td>{{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</td>
              <td><NuxtLink :to="`/account/orders/${order.id}`">View</NuxtLink></td>
            </tr>
          </tbody>
        </table>
      </template>
      <p v-else class="muted">You haven't placed any orders yet.</p>

      <p v-if="error" class="error">{{ error }}</p>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { CustomerOrderSummary } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ middleware: 'auth' })

const orders = ref<CustomerOrderSummary[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('ru-RU')
}

onMounted(async () => {
  try {
    orders.value = (await useStorefrontApi().account.orders.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
})

useSeoMeta({
  title: 'Order history',
  robots: 'noindex',
})
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

th,
td {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid #e0e0e0;
}

.muted {
  color: #777;
  margin-top: 1rem;
}

.links a {
  color: #1a1a1a;
}
</style>

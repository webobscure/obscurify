<template>
  <div>
    <PageHeader title="Payments" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="payments.length">
        <thead>
          <tr>
            <th>Order</th>
            <th>Provider</th>
            <th>Status</th>
            <th>Amount</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in payments" :key="payment.id">
            <td>#{{ payment.order_number ?? payment.order_id }}</td>
            <td>{{ payment.provider }}</td>
            <td>{{ payment.status }}</td>
            <td>{{ formatMoney({ amount: payment.amount, currency: payment.currency }) }}</td>
            <td><NuxtLink :to="`/payments/${payment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No payments yet.</p>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Payment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const payments = ref<Payment[]>([])
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().payments.list()
    payments.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

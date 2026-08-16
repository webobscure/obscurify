<template>
  <div v-if="receipt">
    <PageHeader
      title="Fiscal Receipt"
      :breadcrumbs="[{ label: 'Russian Commerce', to: '/russian-commerce/legal-profile' }, { label: 'Fiscal Receipts', to: '/russian-commerce/fiscal-receipts' }, { label: 'Receipt' }]"
    />
    <p v-if="error" class="error">{{ error }}</p>

    <section>
      <h2>Overview</h2>
      <table class="kv">
        <tbody>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${receipt.order_id}`">View order</NuxtLink></td></tr>
          <tr><th>Status</th><td>{{ receipt.status }}</td></tr>
          <tr><th>Operation</th><td>{{ receipt.operation }}</td></tr>
          <tr><th>Provider</th><td>{{ receipt.provider }}</td></tr>
          <tr><th>External receipt ID</th><td>{{ receipt.external_receipt_id ?? '—' }}</td></tr>
          <tr><th>Seller</th><td>INN {{ receipt.seller_inn }}<template v-if="receipt.seller_kpp">, KPP {{ receipt.seller_kpp }}</template></td></tr>
          <tr><th>Total</th><td>{{ formatMoney({ amount: receipt.total_amount, currency: receipt.currency }) }}</td></tr>
          <tr><th>Fiscalized at</th><td>{{ receipt.fiscalized_at ?? '—' }}</td></tr>
          <tr v-if="receipt.error_message"><th>Error</th><td>{{ receipt.error_message }}</td></tr>
          <tr><th>Attempts</th><td>{{ receipt.attempt_count }}</td></tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Items</h2>
      <table v-if="receipt.items?.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Amount</th>
            <th>VAT rate</th>
            <th>Payment method</th>
            <th>Payment subject</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in receipt.items" :key="item.id">
            <td>{{ item.name }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ formatMoney({ amount: item.price_amount, currency: receipt.currency }) }}</td>
            <td>{{ formatMoney({ amount: item.amount, currency: receipt.currency }) }}</td>
            <td>{{ item.vat_rate }}</td>
            <td>{{ item.payment_method }}</td>
            <td>{{ item.payment_subject }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No items.</p>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Fiscal receipt not found.</p>
</template>

<script setup lang="ts">
import type { FiscalReceipt } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const receiptId = route.params.id as string

const receipt = ref<FiscalReceipt | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().russianCommerce.fiscalReceipts.get(receiptId)
    receipt.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
}
</style>

<template>
  <div>
    <PageHeader title="Fiscal Receipts" :breadcrumbs="[{ label: 'Russian Commerce', to: '/russian-commerce/legal-profile' }, { label: 'Fiscal Receipts' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="receipts.length">
        <thead>
          <tr>
            <th>Order</th>
            <th>Status</th>
            <th>Provider</th>
            <th>Total</th>
            <th>Fiscalized at</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="receipt in receipts" :key="receipt.id">
            <td><NuxtLink :to="`/orders/${receipt.order_id}`">View order</NuxtLink></td>
            <td><span class="badge" :class="receipt.status">{{ receipt.status }}</span></td>
            <td>{{ receipt.provider }}</td>
            <td>{{ formatMoney({ amount: receipt.total_amount, currency: receipt.currency }) }}</td>
            <td>{{ receipt.fiscalized_at ?? '—' }}</td>
            <td><NuxtLink :to="`/russian-commerce/fiscal-receipts/${receipt.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No fiscal receipts yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { FiscalReceipt } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const receipts = ref<FiscalReceipt[]>([])
const pending = ref(true)
const error = ref<string | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const response = await useApi().russianCommerce.fiscalReceipts.list()
    receipts.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>

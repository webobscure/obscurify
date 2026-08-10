<template>
  <div v-if="refund">
    <PageHeader
      :title="`Refund #${refund.number}`"
      :breadcrumbs="[{ label: 'Refunds', to: '/refunds' }, { label: `#${refund.number}` }]"
    >
      <template #actions>
        <button
          v-if="refund.status === 'requested'"
          type="button"
          class="danger"
          :disabled="busy"
          @click="handleCancel"
        >
          Cancel refund
        </button>
      </template>
    </PageHeader>
    <p v-if="error" class="error">{{ error }}</p>

    <section>
      <h2>Overview</h2>
      <table class="kv">
        <tbody>
          <tr><th>Status</th><td><span class="status" :class="`status-${refund.status}`">{{ refund.status }}</span></td></tr>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${refund.order_id}`">View order</NuxtLink></td></tr>
          <tr><th>Payment</th><td><NuxtLink :to="`/payments/${refund.payment_id}`">View payment</NuxtLink></td></tr>
          <tr><th>Provider</th><td>{{ refund.provider ?? 'manual' }}</td></tr>
          <tr v-if="refund.provider_reference"><th>Provider reference</th><td>{{ refund.provider_reference }}</td></tr>
          <tr><th>Amount</th><td>{{ formatMoney({ amount: refund.amount, currency: refund.currency }) }}</td></tr>
          <tr v-if="refund.shipping_amount > 0"><th>Shipping portion</th><td>{{ formatMoney({ amount: refund.shipping_amount, currency: refund.currency }) }}</td></tr>
          <tr v-if="refund.adjustment_amount > 0"><th>Manual adjustment portion</th><td>{{ formatMoney({ amount: refund.adjustment_amount, currency: refund.currency }) }}</td></tr>
          <tr><th>Reason</th><td>{{ refund.reason ?? '—' }}</td></tr>
          <tr><th>Requested</th><td>{{ refund.requested_at }}</td></tr>
          <tr><th>Processed</th><td>{{ refund.processed_at ?? '—' }}</td></tr>
        </tbody>
      </table>
    </section>

    <section v-if="refund.items?.length">
      <h2>Items</h2>
      <table>
        <thead>
          <tr><th>Return item</th><th>Quantity</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in refund.items" :key="item.id">
            <td>{{ item.return_item_id }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ formatMoney({ amount: item.amount, currency: refund.currency }) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <!--
      Dev/test-only fake provider controls — never rendered in a
      production build; the backend independently 404s these calls
      unless payments.fake.enabled too. Only shown while a provider
      refund is genuinely awaiting its webhook (`processing`) — a
      manual refund never has anything to simulate.
    -->
    <section v-if="isDev && refund.provider === 'fake' && refund.status === 'processing'">
      <h2>Fake provider controls (dev only)</h2>
      <p class="muted">Delivers a real signed webhook through the same endpoint a real provider would use.</p>
      <div class="fake-actions">
        <button type="button" :disabled="busy" @click="simulate('success')">Mark succeeded</button>
        <button type="button" :disabled="busy" @click="simulate('failure')">Mark failed</button>
      </div>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Refund not found.</p>
</template>

<script setup lang="ts">
import type { Refund } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const refundId = route.params.id as string
const isDev = import.meta.dev

const refund = ref<Refund | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().refunds.get(refundId)
    refund.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

async function handleCancel() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().refunds.cancel(refundId)
    refund.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

/**
 * Reached directly with $fetch (not useApi(), which is bearer-token-
 * authenticated admin traffic): the fake control endpoint is
 * deliberately unauthenticated, the same way a real provider's webhook
 * sender would be — see FakeRefundOutcomeController.
 */
async function simulate(outcome: 'success' | 'failure') {
  if (!refund.value?.provider_reference) return

  busy.value = true
  error.value = null
  try {
    await useApi().fakeRefunds.outcome(refund.value.provider_reference, outcome)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Failed to simulate outcome.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.status {
  text-transform: capitalize;
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.fake-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

button.danger {
  background: transparent;
  border: 1px solid var(--color-danger);
  color: var(--color-danger);
}
</style>

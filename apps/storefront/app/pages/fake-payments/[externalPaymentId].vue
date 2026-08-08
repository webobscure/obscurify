<template>
  <div>
    <h1>Fake Payment</h1>

    <template v-if="info">
      <table class="kv">
        <tbody>
          <tr><th>Order</th><td>#{{ info.order_number }}</td></tr>
          <tr><th>Payment ID</th><td>{{ info.payment_id }}</td></tr>
          <tr><th>Amount</th><td>{{ formatMoney({ amount: info.amount, currency: info.currency }) }}</td></tr>
          <tr><th>Status</th><td>{{ info.status }}</td></tr>
        </tbody>
      </table>

      <template v-if="info.status === 'processing'">
        <p class="hint">This is a development-only simulated payment page — no real money moves. Choose an outcome:</p>
        <div class="actions">
          <button type="button" :disabled="submitting" @click="trigger('success')">Pay successfully</button>
          <button type="button" :disabled="submitting" @click="trigger('failure')">Fail payment</button>
          <button type="button" :disabled="submitting" @click="trigger('cancelled')">Cancel payment</button>
          <button type="button" :disabled="submitting" @click="trigger('pending')">Leave pending</button>
          <button type="button" :disabled="submitting" @click="trigger('delayed_success')">Send delayed success</button>
        </div>
        <p v-if="delayed" class="hint">Delayed success dispatched — it will arrive asynchronously; reload this page in a few seconds to see it applied.</p>
      </template>
      <template v-else>
        <p class="resolved">This payment has been resolved: <strong>{{ info.status }}</strong>.</p>
      </template>

      <p v-if="error" class="error">{{ error }}</p>

      <p><a href="#" @click.prevent="router.back()">&larr; Back</a> · <NuxtLink to="/products">Continue shopping</NuxtLink></p>
    </template>
    <p v-else-if="pending">Loading…</p>
    <p v-else>Fake payment not found — either the id is wrong, or fake payments are not enabled in this environment.</p>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const router = useRouter()
const externalPaymentId = route.params.externalPaymentId as string

const { data, pending, refresh } = await useAsyncData(`fake-payment-${externalPaymentId}`, () => useStorefrontApi().fakePayments.get(externalPaymentId))

const info = computed(() => data.value?.data ?? null)
const submitting = ref(false)
const error = ref<string | null>(null)
const delayed = ref(false)

async function trigger(outcome: 'success' | 'failure' | 'cancelled' | 'pending' | 'delayed_success') {
  submitting.value = true
  error.value = null
  delayed.value = false
  try {
    const result = await useStorefrontApi().fakePayments.outcome(externalPaymentId, outcome)
    delayed.value = Boolean(result.data.dispatched)
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    submitting.value = false
  }
}

useSeoMeta({
  title: 'Fake Payment',
  robots: 'noindex',
})
</script>

<style scoped>
.kv th {
  text-align: left;
  padding: 0.35rem 1rem 0.35rem 0;
  color: #666;
  font-weight: 400;
  width: 1%;
  white-space: nowrap;
}

.kv td {
  padding: 0.35rem 0;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 1rem;
}

button {
  padding: 0.6rem 1rem;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: default;
}

.hint {
  color: #777;
  font-size: 0.85rem;
}

.resolved {
  padding: 0.75rem 1rem;
  background: #f0f0f0;
  border-radius: 4px;
}
</style>

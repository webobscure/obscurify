<template>
  <div v-if="payment">
    <PageHeader
      title="Payment"
      :breadcrumbs="[{ label: 'Payments', to: '/payments' }, { label: 'Payment' }]"
    />
    <p v-if="error" class="error">{{ error }}</p>

    <!--
      Read-only this milestone — no cancel/refund actions (spec section
      35): a Payment's own status only ever changes through a verified
      provider webhook, never an admin action here.
    -->
    <section>
      <h2>Overview</h2>
      <table class="kv">
        <tbody>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${payment.order_id}`">#{{ payment.order_number ?? payment.order_id }}</NuxtLink></td></tr>
          <tr><th>Provider</th><td>{{ payment.provider }}</td></tr>
          <tr><th>Status</th><td>{{ payment.status }}</td></tr>
          <tr><th>Amount</th><td>{{ formatMoney({ amount: payment.amount, currency: payment.currency }) }}</td></tr>
          <tr><th>Authorized</th><td>{{ formatMoney({ amount: payment.authorized_amount, currency: payment.currency }) }}</td></tr>
          <tr><th>Captured</th><td>{{ formatMoney({ amount: payment.captured_amount, currency: payment.currency }) }}</td></tr>
          <tr><th>Refunded</th><td>{{ formatMoney({ amount: payment.refunded_amount, currency: payment.currency }) }}</td></tr>
          <tr><th>External ID</th><td>{{ payment.external_payment_id ?? '—' }}</td></tr>
          <tr v-if="payment.payment_method"><th>Payment method</th><td>{{ payment.payment_method }}</td></tr>
          <tr><th>Created</th><td>{{ payment.created_at }}</td></tr>
        </tbody>
      </table>
    </section>

    <!-- Russian Commerce Foundation (Milestone 24) — null unless this
         payment's order required a fiscal receipt. -->
    <section v-if="payment.fiscal_receipt">
      <h2>Fiscalization</h2>
      <table class="kv">
        <tbody>
          <tr><th>Status</th><td>{{ payment.fiscal_receipt.status }}</td></tr>
          <tr><th>Provider</th><td>{{ payment.fiscal_receipt.provider }}</td></tr>
          <tr><th>Total</th><td>{{ formatMoney({ amount: payment.fiscal_receipt.total_amount, currency: payment.fiscal_receipt.currency }) }}</td></tr>
          <tr><th>Fiscalized at</th><td>{{ payment.fiscal_receipt.fiscalized_at ?? '—' }}</td></tr>
          <tr v-if="payment.fiscal_receipt.error_message"><th>Error</th><td>{{ payment.fiscal_receipt.error_message }}</td></tr>
        </tbody>
      </table>
      <p><NuxtLink :to="`/russian-commerce/fiscal-receipts/${payment.fiscal_receipt.id}`">View receipt detail</NuxtLink></p>
    </section>

    <section>
      <h2>Attempts</h2>
      <table v-if="payment.attempts?.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>External attempt ID</th>
            <th>Error</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="attempt in payment.attempts" :key="attempt.id">
            <td>{{ attempt.status }}</td>
            <td>{{ attempt.external_attempt_id ?? '—' }}</td>
            <td>{{ attempt.error_message ?? '—' }}</td>
            <td>{{ attempt.created_at }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No attempts recorded.</p>
    </section>

    <section>
      <h2>Transaction timeline</h2>
      <table v-if="payment.transactions?.length">
        <thead>
          <tr>
            <th>Type</th>
            <th>Status</th>
            <th>Amount</th>
            <th>External transaction ID</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="transaction in payment.transactions" :key="transaction.id">
            <td>{{ transaction.type }}</td>
            <td>{{ transaction.status }}</td>
            <td>{{ formatMoney({ amount: transaction.amount, currency: transaction.currency }) }}</td>
            <td>{{ transaction.external_transaction_id ?? '—' }}</td>
            <td>{{ transaction.created_at }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No transactions recorded.</p>

      <!--
        A lightweight summary derived from the transaction ledger above
        (each transaction row corresponds to one processed provider
        event) rather than a separate raw webhook-event listing — see
        spec section 25: no reason to expose payload hashes/raw signed
        bodies to the admin UI.
      -->
      <p class="hint">{{ payment.transactions?.length ?? 0 }} provider event(s) recorded for this payment.</p>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Payment not found.</p>
</template>

<script setup lang="ts">
import type { Payment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const paymentId = route.params.id as string

const payment = ref<Payment | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().payments.get(paymentId)
    payment.value = response.data
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
  color: var(--color-text-subtle);
  font-size: var(--text-sm);
}
</style>

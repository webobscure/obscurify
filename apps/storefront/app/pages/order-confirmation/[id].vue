<template>
  <div>
    <template v-if="order">
      <h1>Thank you for your order!</h1>

      <p v-if="order.financial_status === 'paid'" class="payment-success">
        Payment successful — Order #{{ order.number }}, {{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}
      </p>
      <template v-else>
        <p>Order #{{ order.number }} has been placed and is awaiting payment.</p>
        <button type="button" :disabled="payment.loading.value" @click="handlePay">
          {{ payment.loading.value ? 'Starting payment…' : 'Pay with Fake Payment' }}
        </button>
        <p v-if="payment.error.value" class="error">{{ payment.error.value }}</p>
      </template>

      <table>
        <tbody>
          <tr v-for="(item, index) in order.items" :key="index">
            <td>{{ item.product_title }} <span v-if="item.variant_title">({{ item.variant_title }})</span> &times; {{ item.quantity }}</td>
            <td>{{ formatMoney({ amount: item.line_total_amount, currency: item.currency }) }}</td>
          </tr>
        </tbody>
      </table>

      <p v-if="order.shipping_line" class="totals-line">
        Shipping ({{ order.shipping_line.name }}): {{ formatMoney({ amount: order.shipping_amount, currency: order.currency }) }}
      </p>
      <p class="totals">
        <strong>Total: {{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</strong>
      </p>

      <section v-if="order.shipping_line">
        <h2>Shipping method</h2>
        <p>
          {{ order.shipping_line.name }}
          <template v-if="order.shipping_line.estimated_days_min || order.shipping_line.estimated_days_max">
            — estimated {{ order.shipping_line.estimated_days_min }}–{{ order.shipping_line.estimated_days_max }} days
          </template>
        </p>
        <p v-if="order.shipping_line.pickup_point" class="pickup-point">
          Pickup at: {{ order.shipping_line.pickup_point.name }} — {{ order.shipping_line.pickup_point.address }}, {{ order.shipping_line.pickup_point.city }}
          <template v-if="order.shipping_line.pickup_point.opening_hours">({{ order.shipping_line.pickup_point.opening_hours }})</template>
        </p>
      </section>

      <section v-if="order.shipping_address">
        <h2>Shipping address</h2>
        <p>
          {{ order.shipping_address.first_name }} {{ order.shipping_address.last_name }}<br>
          {{ order.shipping_address.address_line1 }}<br>
          <template v-if="order.shipping_address.address_line2">{{ order.shipping_address.address_line2 }}<br></template>
          {{ order.shipping_address.city }}<span v-if="order.shipping_address.region">, {{ order.shipping_address.region }}</span> {{ order.shipping_address.postal_code }}<br>
          {{ order.shipping_address.country_code }}
        </p>
      </section>

      <p><NuxtLink to="/products">Continue shopping</NuxtLink></p>
    </template>
    <p v-else-if="pending">Loading…</p>
    <p v-else>Order not found.</p>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const orderId = route.params.id as string

// A 404 here means "not found" (wrong id, or another store's order under
// this same hostname is impossible anyway since Order is tenant-scoped) —
// useAsyncData captures it into `error` rather than throwing.
const { data, pending } = await useAsyncData(`order-confirmation-${orderId}`, () => useStorefrontApi().orders.get(orderId))

const order = computed(() => data.value?.data ?? null)
const payment = usePayment()

// Only "fake" exists this milestone (spec section 27) — a real provider
// choice would replace this hardcoded call, not add a second code path
// alongside it.
async function handlePay() {
  try {
    const created = await payment.create(orderId, 'fake')
    if (created.redirect_url) await navigateTo(created.redirect_url)
  } catch (e) {
    if (!(e instanceof ApiClientError)) throw e
  }
}

useSeoMeta({
  title: () => order.value ? `Order #${order.value.number}` : 'Order confirmation',
  robots: 'noindex',
})
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

td {
  padding: 0.35rem 0;
  border-bottom: 1px solid #e0e0e0;
}

.totals-line {
  margin: 0.5rem 0 0;
  text-align: right;
  color: #555;
}

.totals {
  margin-top: 0.75rem;
  text-align: right;
}

section {
  margin-top: 1.5rem;
}

.payment-success {
  padding: 0.75rem 1rem;
  background: #e6f4ea;
  color: #1e4620;
  border-radius: 4px;
}

button {
  margin-top: 0.75rem;
  padding: 0.6rem 1.25rem;
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
</style>

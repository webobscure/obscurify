<template>
  <div v-if="order">
    <p><NuxtLink to="/orders">&larr; Orders</NuxtLink></p>
    <h1>Order #{{ order.number }}</h1>
    <p v-if="error" class="error">{{ error }}</p>

    <!--
      Read-only this milestone — no pay/refund/fulfill/cancel actions:
      no PaymentGateway or shipping provider exists yet (see spec section
      34). This page is deliberately just a viewer.
    -->
    <section>
      <h2>Status</h2>
      <table class="kv">
        <tbody>
          <tr><th>Order</th><td>{{ order.order_status }}</td></tr>
          <tr><th>Financial</th><td>{{ order.financial_status }}</td></tr>
          <tr><th>Fulfillment</th><td>{{ order.fulfillment_status }}</td></tr>
          <tr><th>Created</th><td>{{ order.created_at }}</td></tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Customer</h2>
      <table class="kv">
        <tbody>
          <tr><th>Name</th><td>{{ order.customer?.first_name }} {{ order.customer?.last_name }}</td></tr>
          <tr><th>Email</th><td>{{ order.customer?.email ?? order.email ?? '—' }}</td></tr>
          <tr><th>Phone</th><td>{{ order.customer?.phone ?? order.phone ?? '—' }}</td></tr>
        </tbody>
      </table>
    </section>

    <section class="addresses">
      <div v-if="order.shipping_address">
        <h2>Shipping address</h2>
        <p>
          {{ order.shipping_address.first_name }} {{ order.shipping_address.last_name }}<br>
          {{ order.shipping_address.address_line1 }}<br>
          <template v-if="order.shipping_address.address_line2">{{ order.shipping_address.address_line2 }}<br></template>
          {{ order.shipping_address.city }}<span v-if="order.shipping_address.region">, {{ order.shipping_address.region }}</span> {{ order.shipping_address.postal_code }}<br>
          {{ order.shipping_address.country_code }}
        </p>
      </div>
      <div v-if="order.billing_address">
        <h2>Billing address</h2>
        <p>
          {{ order.billing_address.first_name }} {{ order.billing_address.last_name }}<br>
          {{ order.billing_address.address_line1 }}<br>
          <template v-if="order.billing_address.address_line2">{{ order.billing_address.address_line2 }}<br></template>
          {{ order.billing_address.city }}<span v-if="order.billing_address.region">, {{ order.billing_address.region }}</span> {{ order.billing_address.postal_code }}<br>
          {{ order.billing_address.country_code }}
        </p>
      </div>
    </section>

    <section>
      <h2>Items</h2>
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Unit price</th>
            <th>Quantity</th>
            <th>Line total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in order.items" :key="item.id">
            <td>{{ item.product_title }} <span v-if="item.variant_title">({{ item.variant_title }})</span></td>
            <td>{{ item.sku ?? '—' }}</td>
            <td>{{ formatMoney({ amount: item.unit_price_amount, currency: item.currency }) }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ formatMoney({ amount: item.line_total_amount, currency: item.currency }) }}</td>
          </tr>
        </tbody>
      </table>

      <table class="kv totals">
        <tbody>
          <tr><th>Subtotal</th><td>{{ formatMoney({ amount: order.items_subtotal_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Shipping</th><td>{{ formatMoney({ amount: order.shipping_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Discount</th><td>{{ formatMoney({ amount: order.discount_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Tax</th><td>{{ formatMoney({ amount: order.tax_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Total</th><td><strong>{{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</strong></td></tr>
        </tbody>
      </table>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Order not found.</p>
</template>

<script setup lang="ts">
import type { Order } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const orderId = route.params.id as string

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().orders.get(orderId)
    order.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
section {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

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

.addresses {
  display: flex;
  gap: 3rem;
}

.totals {
  margin-top: 1rem;
  max-width: 320px;
  margin-left: auto;
}
</style>

<template>
  <div>
    <template v-if="order">
      <h1>Thank you for your order!</h1>
      <p>Order #{{ order.number }} has been placed and is awaiting payment.</p>

      <table>
        <tbody>
          <tr v-for="(item, index) in order.items" :key="index">
            <td>{{ item.product_title }} <span v-if="item.variant_title">({{ item.variant_title }})</span> &times; {{ item.quantity }}</td>
            <td>{{ formatMoney({ amount: item.line_total_amount, currency: item.currency }) }}</td>
          </tr>
        </tbody>
      </table>

      <p class="totals">
        <strong>Total: {{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</strong>
      </p>

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
const route = useRoute()
const orderId = route.params.id as string

// A 404 here means "not found" (wrong id, or another store's order under
// this same hostname is impossible anyway since Order is tenant-scoped) —
// useAsyncData captures it into `error` rather than throwing.
const { data, pending } = await useAsyncData(`order-confirmation-${orderId}`, () => useStorefrontApi().orders.get(orderId))

const order = computed(() => data.value?.data ?? null)

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

.totals {
  margin-top: 0.75rem;
  text-align: right;
}

section {
  margin-top: 1.5rem;
}
</style>

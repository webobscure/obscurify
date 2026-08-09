<template>
  <div v-if="order">
    <PageHeader
      :title="`Order #${order.number}`"
      :breadcrumbs="[{ label: 'Orders', to: '/orders' }, { label: `#${order.number}` }]"
    />
    <p v-if="error" class="error">{{ error }}</p>

    <!--
      Read-only otherwise — no pay/refund/fulfill/cancel actions: no
      PaymentGateway exists yet (see spec section 34). Shipment creation
      below is the one write action this page supports.
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
          <tr>
            <th>Shipping</th>
            <td>
              {{ formatMoney({ amount: order.shipping_amount, currency: order.currency }) }}
              <span v-if="order.shipping_line" class="muted"> — {{ order.shipping_line.name }}</span>
            </td>
          </tr>
          <tr><th>Discount</th><td>{{ formatMoney({ amount: order.discount_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Tax</th><td>{{ formatMoney({ amount: order.tax_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Total</th><td><strong>{{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</strong></td></tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Shipments</h2>

      <table v-if="order.shipments?.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>Tracking</th>
            <th>Items</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="shipment in order.shipments" :key="shipment.id">
            <td>{{ shipment.status }}</td>
            <td>{{ shipment.tracking_number ?? '—' }}</td>
            <td>{{ shipment.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0 }}</td>
            <td><NuxtLink :to="`/shipments/${shipment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No shipments yet.</p>

      <template v-if="order.financial_status === 'paid' && unshippedItems.length">
        <h3>Create a shipment</h3>
        <form class="ship-form" @submit.prevent="handleCreateShipment">
          <table>
            <thead>
              <tr>
                <th/>
                <th>Product</th>
                <th>Remaining</th>
                <th>Ship quantity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in unshippedItems" :key="line.item.id">
                <td><input v-model="line.selected" type="checkbox"></td>
                <td>{{ line.item.product_title }} <span v-if="line.item.variant_title">({{ line.item.variant_title }})</span></td>
                <td>{{ line.remaining }}</td>
                <td>
                  <input
                    v-model.number="line.quantity"
                    type="number"
                    min="1"
                    :max="line.remaining"
                    :disabled="!line.selected"
                  >
                </td>
              </tr>
            </tbody>
          </table>
          <button type="submit" :disabled="creatingShipment">{{ creatingShipment ? 'Creating…' : 'Create shipment' }}</button>
        </form>
      </template>
      <p v-if="shipmentError" class="error">{{ shipmentError }}</p>
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
const creatingShipment = ref(false)
const shipmentError = ref<string | null>(null)

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

/**
 * Remaining-to-ship quantity per OrderItem — ordered quantity minus what's
 * already on any (non-cancelled) shipment for this order. The backend is
 * still the real guard against overshipping (CreateShipment locks the
 * OrderItem row); this is purely a UI convenience so the merchant isn't
 * offered a quantity the request would reject anyway.
 */
const unshippedItems = computed(() => {
  if (!order.value) return []

  const shippedByItem = new Map<string, number>()
  for (const shipment of order.value.shipments ?? []) {
    if (shipment.status === 'cancelled') continue
    for (const shipmentItem of shipment.items ?? []) {
      shippedByItem.set(shipmentItem.order_item_id, (shippedByItem.get(shipmentItem.order_item_id) ?? 0) + shipmentItem.quantity)
    }
  }

  return (order.value.items ?? [])
    .map(item => ({
      item,
      remaining: item.quantity - (shippedByItem.get(item.id) ?? 0),
      selected: false,
      quantity: item.quantity - (shippedByItem.get(item.id) ?? 0),
    }))
    .filter(line => line.remaining > 0)
})

async function handleCreateShipment() {
  const lines = unshippedItems.value
    .filter(line => line.selected && line.quantity > 0)
    .map(line => ({ order_item_id: line.item.id, quantity: line.quantity }))

  if (lines.length === 0) {
    shipmentError.value = 'Select at least one item to ship.'
    return
  }

  creatingShipment.value = true
  shipmentError.value = null
  try {
    await useApi().orders.createShipment(orderId, { provider: 'fake', lines })
    await load()
  } catch (e) {
    shipmentError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creatingShipment.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.addresses {
  display: flex;
  gap: 3rem;
}

.totals {
  margin-top: 1rem;
  max-width: 320px;
  margin-left: auto;
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.ship-form input[type='number'] {
  width: 5rem;
}
</style>

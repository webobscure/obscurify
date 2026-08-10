<template>
  <div v-if="order">
    <PageHeader
      :title="`Order #${order.number}`"
      :breadcrumbs="[{ label: 'Orders', to: '/orders' }, { label: `#${order.number}` }]"
    />
    <p v-if="error" class="error">{{ error }}</p>

    <!--
      Read-only otherwise — no pay/refund/cancel actions: no PaymentGateway
      exists yet. Creating a Fulfillment below is the one write action
      this page supports; picking/packing/completing/cancelling happen on
      the Fulfillment's own page (Milestone 7 — Shipment creation moved
      there too, since a Shipment now requires a ready Fulfillment).
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
              <template v-if="order.shipping_line?.pickup_point">
                <br>
                <span class="muted">
                  Pickup at: {{ order.shipping_line.pickup_point.name }} — {{ order.shipping_line.pickup_point.address }}, {{ order.shipping_line.pickup_point.city }}
                </span>
              </template>
            </td>
          </tr>
          <tr><th>Discount</th><td>{{ formatMoney({ amount: order.discount_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Tax</th><td>{{ formatMoney({ amount: order.tax_amount, currency: order.currency }) }}</td></tr>
          <tr><th>Total</th><td><strong>{{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</strong></td></tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Reservations</h2>
      <table v-if="order.reservations?.length">
        <thead>
          <tr><th>Status</th><th>Quantity</th><th>Location</th><th>Expires</th></tr>
        </thead>
        <tbody>
          <tr v-for="reservation in order.reservations" :key="reservation.id">
            <td>{{ reservation.status }}</td>
            <td>{{ reservation.quantity }}</td>
            <td>{{ reservation.location_id }}</td>
            <td>{{ reservation.expires_at ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No reservations.</p>
    </section>

    <section>
      <h2>Fulfillments</h2>

      <table v-if="order.fulfillments?.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>Items</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fulfillment in order.fulfillments" :key="fulfillment.id">
            <td>{{ fulfillment.status }}</td>
            <td>{{ fulfillment.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0 }}</td>
            <td><NuxtLink :to="`/fulfillments/${fulfillment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No fulfillments yet.</p>

      <!--
        Picking/packing/completing (which creates the Shipment) and
        cancelling all happen on the Fulfillment's own page — this form
        only covers step one of the lifecycle, registering which items
        (and how much of each) this fulfillment attempt covers.
      -->
      <template v-if="order.financial_status === 'paid' && unfulfilledItems.length">
        <h3>Create a fulfillment</h3>
        <form class="ship-form" @submit.prevent="handleCreateFulfillment">
          <table>
            <thead>
              <tr>
                <th/>
                <th>Product</th>
                <th>Remaining</th>
                <th>Fulfill quantity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in unfulfilledItems" :key="line.item.id">
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
          <button type="submit" :disabled="creatingFulfillment">{{ creatingFulfillment ? 'Creating…' : 'Create fulfillment' }}</button>
        </form>
      </template>
      <p v-if="fulfillmentError" class="error">{{ fulfillmentError }}</p>
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
    </section>

    <section>
      <h2>Returns</h2>
      <table v-if="order.returns?.length">
        <thead>
          <tr>
            <th>Number</th>
            <th>Status</th>
            <th>Items</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="returnRequest in order.returns" :key="returnRequest.id">
            <td>#{{ returnRequest.number }}</td>
            <td>{{ returnRequest.status }}</td>
            <td>{{ returnRequest.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0 }}</td>
            <td><NuxtLink :to="`/returns/${returnRequest.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No returns yet.</p>

      <!--
        Approving/receiving/inspecting/completing all happen on the
        Return's own page — this form only covers step one, registering
        which items (and how much of each) are being returned.
      -->
      <template v-if="returnableItems.length">
        <h3>Request a return</h3>
        <form class="ship-form" @submit.prevent="handleCreateReturn">
          <table>
            <thead>
              <tr>
                <th/>
                <th>Product</th>
                <th>Returnable</th>
                <th>Return quantity</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in returnableItems" :key="line.item.id">
                <td><input v-model="line.selected" type="checkbox"></td>
                <td>{{ line.item.product_title }} <span v-if="line.item.variant_title">({{ line.item.variant_title }})</span></td>
                <td>{{ line.returnable }}</td>
                <td>
                  <input
                    v-model.number="line.quantity"
                    type="number"
                    min="1"
                    :max="line.returnable"
                    :disabled="!line.selected"
                  >
                </td>
                <td>
                  <select v-model="line.reason" :disabled="!line.selected">
                    <option v-for="r in RETURN_REASONS" :key="r" :value="r">{{ r }}</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          <button type="submit" :disabled="creatingReturn">{{ creatingReturn ? 'Requesting…' : 'Request return' }}</button>
        </form>
      </template>
      <p v-if="returnError" class="error">{{ returnError }}</p>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Order not found.</p>
</template>

<script setup lang="ts">
import type { Order, ReturnReason } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const RETURN_REASONS: ReturnReason[] = ['wrong_size', 'damaged', 'not_as_described', 'ordered_by_mistake', 'defective', 'other']

const route = useRoute()
const orderId = route.params.id as string

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const creatingFulfillment = ref(false)
const fulfillmentError = ref<string | null>(null)
const creatingReturn = ref(false)
const returnError = ref<string | null>(null)

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
 * Remaining-to-fulfill quantity per OrderItem — ordered quantity minus
 * what's already on any (non-cancelled) fulfillment for this order. The
 * backend is still the real guard against over-fulfilling
 * (CreateFulfillment locks the OrderItem row); this is purely a UI
 * convenience so the merchant isn't offered a quantity the request would
 * reject anyway.
 */
const unfulfilledItems = computed(() => {
  if (!order.value) return []

  const fulfilledByItem = new Map<string, number>()
  for (const fulfillment of order.value.fulfillments ?? []) {
    if (fulfillment.status === 'cancelled') continue
    for (const fulfillmentItem of fulfillment.items ?? []) {
      fulfilledByItem.set(fulfillmentItem.order_item_id, (fulfilledByItem.get(fulfillmentItem.order_item_id) ?? 0) + fulfillmentItem.quantity)
    }
  }

  return (order.value.items ?? [])
    .map(item => ({
      item,
      remaining: item.quantity - (fulfilledByItem.get(item.id) ?? 0),
      selected: false,
      quantity: item.quantity - (fulfilledByItem.get(item.id) ?? 0),
    }))
    .filter(line => line.remaining > 0)
})

/**
 * Returnable quantity per OrderItem — shipped quantity (across non-
 * cancelled Shipments) minus what's already claimed by any non-rejected,
 * non-cancelled ReturnRequest. The backend is still the real guard
 * (RequestReturn locks the OrderItem row and re-derives this exact
 * number) — this is purely a UI convenience, same reasoning as
 * unfulfilledItems above.
 */
const returnableItems = computed(() => {
  if (!order.value) return []

  const shippedByItem = new Map<string, number>()
  for (const shipment of order.value.shipments ?? []) {
    if (shipment.status === 'cancelled') continue
    for (const shipmentItem of shipment.items ?? []) {
      shippedByItem.set(shipmentItem.order_item_id, (shippedByItem.get(shipmentItem.order_item_id) ?? 0) + shipmentItem.quantity)
    }
  }

  const returnedByItem = new Map<string, number>()
  for (const returnRequest of order.value.returns ?? []) {
    if (returnRequest.status === 'rejected' || returnRequest.status === 'cancelled') continue
    for (const returnItem of returnRequest.items ?? []) {
      returnedByItem.set(returnItem.order_item_id, (returnedByItem.get(returnItem.order_item_id) ?? 0) + returnItem.quantity)
    }
  }

  return (order.value.items ?? [])
    .map(item => ({
      item,
      returnable: (shippedByItem.get(item.id) ?? 0) - (returnedByItem.get(item.id) ?? 0),
      selected: false,
      quantity: (shippedByItem.get(item.id) ?? 0) - (returnedByItem.get(item.id) ?? 0),
      reason: 'other' as ReturnReason,
    }))
    .filter(line => line.returnable > 0)
})

async function handleCreateReturn() {
  const items = returnableItems.value
    .filter(line => line.selected && line.quantity > 0)
    .map(line => ({ order_item_id: line.item.id, quantity: line.quantity, reason: line.reason }))

  if (items.length === 0) {
    returnError.value = 'Select at least one item to return.'
    return
  }

  creatingReturn.value = true
  returnError.value = null
  try {
    await useApi().returns.create(orderId, { items })
    await load()
  } catch (e) {
    returnError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creatingReturn.value = false
  }
}

async function handleCreateFulfillment() {
  const items = unfulfilledItems.value
    .filter(line => line.selected && line.quantity > 0)
    .map(line => ({ order_item_id: line.item.id, quantity: line.quantity }))

  if (items.length === 0) {
    fulfillmentError.value = 'Select at least one item to fulfill.'
    return
  }

  creatingFulfillment.value = true
  fulfillmentError.value = null
  try {
    await useApi().fulfillments.create(orderId, { items })
    await load()
  } catch (e) {
    fulfillmentError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creatingFulfillment.value = false
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

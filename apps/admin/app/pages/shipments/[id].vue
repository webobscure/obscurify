<template>
  <div v-if="shipment">
    <PageHeader
      :title="`Shipment ${shipment.tracking_number ?? shipment.id}`"
      :breadcrumbs="[{ label: 'Shipments', to: '/shipments' }, { label: shipment.tracking_number ?? shipment.id }]"
    >
      <template #actions>
        <button
          v-if="!TERMINAL_STATUSES.includes(shipment.status)"
          type="button"
          :disabled="cancelling"
          @click="handleCancel"
        >
          {{ cancelling ? 'Cancelling…' : 'Cancel shipment' }}
        </button>
      </template>
    </PageHeader>
    <p v-if="error" class="error">{{ error }}</p>

    <section>
      <h2>Status</h2>
      <table class="kv">
        <tbody>
          <tr><th>Status</th><td><span class="status">{{ shipment.status }}</span></td></tr>
          <tr><th>Provider</th><td>{{ shipment.provider }}</td></tr>
          <tr><th>Tracking number</th><td>{{ shipment.tracking_number ?? '—' }}</td></tr>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${shipment.order_id}`">View order</NuxtLink></td></tr>
          <tr><th>Fulfillment</th><td><NuxtLink :to="`/fulfillments/${shipment.fulfillment_id}`">View fulfillment</NuxtLink></td></tr>
          <tr v-if="shipment.pickup_point">
            <th>Pickup point</th>
            <td>{{ shipment.pickup_point.name }} — {{ shipment.pickup_point.address }}, {{ shipment.pickup_point.city }}</td>
          </tr>
          <tr v-else-if="shipment.destination">
            <th>Destination</th>
            <td>{{ shipment.destination.city }}, {{ shipment.destination.country_code }} {{ shipment.destination.postal_code }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Items</h2>
      <table v-if="shipment.items?.length">
        <thead>
          <tr><th>Order item</th><th>Quantity</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in shipment.items" :key="item.id">
            <td>{{ item.order_item_id }}</td>
            <td>{{ item.quantity }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Tracking timeline</h2>
      <table v-if="shipment.tracking_events?.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>Description</th>
            <th>Location</th>
            <th>Occurred</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="event in shipment.tracking_events" :key="event.id">
            <td>{{ event.status }}</td>
            <td>{{ event.description ?? '—' }}</td>
            <td>{{ event.location ?? '—' }}</td>
            <td>{{ event.occurred_at }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No tracking events yet.</p>
    </section>

    <!--
      Dev/test-only fake provider controls — never rendered in a
      production build; the backend independently 404s these calls
      unless commerce.shipping.fake.enabled too (spec section 32:
      "guard via environment/config, not only UI hiding"). Every action
      here goes through the real signed webhook endpoint (spec section
      11), never a direct model write.
    -->
    <section v-if="isDev && shipment.provider === 'fake'">
      <h2>Fake provider controls (dev only)</h2>

      <h3>Lifecycle</h3>
      <div class="fake-actions">
        <button type="button" :disabled="simulating" @click="simulate('accepted')">Mark accepted</button>
        <button type="button" :disabled="simulating" @click="simulate('in_transit')">Mark in transit</button>
        <button type="button" :disabled="simulating" @click="simulate('out_for_delivery')">Mark out for delivery</button>
        <button type="button" :disabled="simulating" @click="simulate('delivered')">Mark delivered</button>
        <button type="button" :disabled="simulating" @click="simulate('delivery_exception')">Delivery exception</button>
        <button type="button" :disabled="simulating" @click="simulate('failed')">Fail shipment</button>
      </div>

      <h3>Delayed (queued)</h3>
      <p class="muted">Dispatches through the queue with a configured delay instead of processing immediately (spec section 14).</p>
      <div class="fake-actions">
        <button type="button" :disabled="simulating" @click="simulate('accepted', { delayed: true })">Queue accepted</button>
        <button type="button" :disabled="simulating" @click="simulate('in_transit', { delayed: true })">Queue in transit</button>
      </div>

      <h3>Developer actions</h3>
      <p class="muted">Exercise the webhook endpoint's own hardening — never something a real carrier would trigger deliberately.</p>
      <div class="fake-actions">
        <button type="button" :disabled="simulating" @click="sendDuplicate">Send duplicate webhook</button>
        <button type="button" :disabled="simulating" @click="sendInvalidSignature">Send invalid signature</button>
        <button type="button" :disabled="simulating" @click="simulate('in_transit')">Send out-of-order webhook</button>
      </div>
      <p v-if="devActionResult" class="dev-result">{{ devActionResult }}</p>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Shipment not found.</p>
</template>

<script setup lang="ts">
import type { Shipment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const TERMINAL_STATUSES = ['delivered', 'failed', 'cancelled']

type FakeOutcome = 'accepted' | 'in_transit' | 'out_for_delivery' | 'delivered' | 'delivery_exception' | 'failed' | 'cancelled'

const route = useRoute()
const shipmentId = route.params.id as string
const isDev = import.meta.dev

const shipment = ref<Shipment | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const cancelling = ref(false)
const simulating = ref(false)
const devActionResult = ref<string | null>(null)
let lastEventId: string | null = null

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().shipments.get(shipmentId)
    shipment.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

async function handleCancel() {
  cancelling.value = true
  error.value = null
  try {
    await useApi().shipments.cancel(shipmentId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    cancelling.value = false
  }
}

/**
 * Reached directly with $fetch (not useApi(), which is bearer-token-
 * authenticated admin traffic): the fake control endpoints are
 * deliberately unauthenticated, the same way a real carrier's webhook
 * sender would be.
 */
async function simulate(outcome: FakeOutcome, options: { delayed?: boolean; eventId?: string } = {}) {
  if (!shipment.value?.tracking_url) return

  simulating.value = true
  error.value = null
  devActionResult.value = null
  try {
    const externalShipmentId = shipment.value.tracking_url.split('/fake-shipments/')[1]
    const config = useRuntimeConfig()
    const response = await $fetch<{ data: { queued?: boolean, delay_seconds?: number, event_id?: string } }>(
      `${config.public.apiBaseUrl}/api/v1/fake-shipments/${externalShipmentId}/outcome`,
      { method: 'POST', body: { outcome, ...options } },
    )

    if (response.data.event_id) lastEventId = response.data.event_id

    if (response.data.queued) {
      devActionResult.value = `Queued — will process in ${response.data.delay_seconds}s.`
    } else {
      await load()
    }
  } catch {
    error.value = 'Failed to simulate outcome.'
  } finally {
    simulating.value = false
  }
}

/**
 * Resends the last delivered event_id — proves the webhook endpoint's
 * idempotency (spec section 12): the tracking timeline must not grow.
 */
async function sendDuplicate() {
  if (!lastEventId) {
    devActionResult.value = 'Mark a status first, then duplicate it.'
    return
  }
  await simulate('in_transit', { eventId: lastEventId })
  devActionResult.value = `Resent event ${lastEventId} — timeline should be unchanged.`
}

async function sendInvalidSignature() {
  if (!shipment.value?.tracking_url) return

  simulating.value = true
  error.value = null
  try {
    const externalShipmentId = shipment.value.tracking_url.split('/fake-shipments/')[1]
    const config = useRuntimeConfig()
    const response = await $fetch<{ data: { rejected: boolean, reason?: string } }>(
      `${config.public.apiBaseUrl}/api/v1/fake-shipments/${externalShipmentId}/invalid-signature`,
      { method: 'POST', body: { outcome: 'delivered' } },
    )
    devActionResult.value = response.data.rejected
      ? `Correctly rejected: ${response.data.reason}`
      : 'Unexpectedly accepted — this would be a real bug.'
  } catch {
    error.value = 'Failed to send invalid-signature test.'
  } finally {
    simulating.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.status {
  text-transform: capitalize;
}

.fake-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.dev-result {
  font-size: var(--text-sm);
  font-family: monospace;
}
</style>

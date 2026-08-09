<template>
  <div v-if="shipment">
    <PageHeader
      :title="`Shipment ${shipment.tracking_number ?? shipment.id}`"
      :breadcrumbs="[{ label: 'Shipments', to: '/shipments' }, { label: shipment.tracking_number ?? shipment.id }]"
    >
      <template #actions>
        <button
          v-if="!['delivered', 'failed', 'cancelled'].includes(shipment.status)"
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
          <tr><th>Status</th><td>{{ shipment.status }}</td></tr>
          <tr><th>Provider</th><td>{{ shipment.provider }}</td></tr>
          <tr><th>Tracking number</th><td>{{ shipment.tracking_number ?? '—' }}</td></tr>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${shipment.order_id}`">View order</NuxtLink></td></tr>
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
      Dev/test-only fake provider controls (spec section 20) — never
      rendered in a production build; the backend independently 404s
      these calls unless commerce.shipping.fake.enabled too (spec section
      40: "guard via environment/config, not only UI hiding").
    -->
    <section v-if="isDev && shipment.provider === 'fake'">
      <h2>Fake provider controls (dev only)</h2>
      <div class="fake-actions">
        <button type="button" :disabled="simulating" @click="simulate('in_transit')">Mark in transit</button>
        <button type="button" :disabled="simulating" @click="simulate('delivered')">Mark delivered</button>
        <button type="button" :disabled="simulating" @click="simulate('failed')">Fail shipment</button>
      </div>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Shipment not found.</p>
</template>

<script setup lang="ts">
import type { Shipment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const shipmentId = route.params.id as string
const isDev = import.meta.dev

const shipment = ref<Shipment | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const cancelling = ref(false)
const simulating = ref(false)

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
 * Simulates a carrier webhook via the dev-only fake shipment outcome
 * endpoint — reached directly with $fetch (not useApi(), which is bearer-
 * token-authenticated admin traffic): this endpoint is deliberately
 * unauthenticated, the same way the real carrier's webhook would be.
 */
async function simulate(outcome: 'in_transit' | 'delivered' | 'failed') {
  if (!shipment.value?.tracking_url) return

  simulating.value = true
  error.value = null
  try {
    const externalShipmentId = shipment.value.tracking_url.split('/fake-shipments/')[1]
    const config = useRuntimeConfig()
    await $fetch(`${config.public.apiBaseUrl}/api/v1/fake-shipments/${externalShipmentId}/outcome`, {
      method: 'POST',
      body: { outcome },
    })
    await load()
  } catch {
    error.value = 'Failed to simulate outcome.'
  } finally {
    simulating.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.fake-actions {
  display: flex;
  gap: 0.5rem;
}
</style>

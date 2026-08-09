<template>
  <div v-if="fulfillment">
    <PageHeader
      :title="`Fulfillment ${fulfillment.id}`"
      :breadcrumbs="[{ label: 'Fulfillments', to: '/fulfillments' }, { label: fulfillment.id }]"
    >
      <template #actions>
        <button
          v-if="!TERMINAL_STATUSES.includes(fulfillment.status)"
          type="button"
          class="danger"
          :disabled="busy"
          @click="handleCancel"
        >
          Cancel fulfillment
        </button>
      </template>
    </PageHeader>
    <p v-if="error" class="error">{{ error }}</p>

    <section>
      <h2>Status</h2>
      <table class="kv">
        <tbody>
          <tr><th>Status</th><td><span class="status" :class="`status-${fulfillment.status}`">{{ fulfillment.status }}</span></td></tr>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${fulfillment.order_id}`">View order</NuxtLink></td></tr>
          <tr><th>Notes</th><td>{{ fulfillment.notes ?? '—' }}</td></tr>
          <tr><th>Completed</th><td>{{ fulfillment.completed_at ?? '—' }}</td></tr>
        </tbody>
      </table>
    </section>

    <!--
      Status transitions are always explicit merchant actions — this page
      never auto-advances anything the backend didn't already report as
      the new status in its response (spec section 9: Admin UI must
      expose these transitions, not infer them).
    -->
    <section v-if="fulfillment.status === 'pending'">
      <h2>Allocate inventory</h2>
      <p>Claims reservation capacity for every item below before picking can start.</p>
      <button type="button" :disabled="busy" @click="handleAllocate">{{ busy ? 'Allocating…' : 'Allocate' }}</button>
    </section>

    <section v-if="['allocated', 'picking'].includes(fulfillment.status)">
      <h2>Picking</h2>
      <table>
        <thead>
          <tr><th>Order item</th><th>Ordered</th><th>Picked</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in fulfillment.items" :key="item.id">
            <td>{{ item.order_item_id }}</td>
            <td>{{ item.quantity }}</td>
            <td><input v-model.number="pickQuantities[item.id]" type="number" min="0" :max="item.quantity"></td>
          </tr>
        </tbody>
      </table>
      <button type="button" :disabled="busy" @click="handlePick">{{ busy ? 'Saving…' : 'Save picked quantities' }}</button>
    </section>

    <section v-if="['picking', 'packing'].includes(fulfillment.status)">
      <h2>Packing</h2>
      <table>
        <thead>
          <tr><th>Order item</th><th>Picked</th><th>Packed</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in fulfillment.items" :key="item.id">
            <td>{{ item.order_item_id }}</td>
            <td>{{ item.picked_quantity }}</td>
            <td><input v-model.number="packQuantities[item.id]" type="number" min="0" :max="item.picked_quantity"></td>
          </tr>
        </tbody>
      </table>
      <button type="button" :disabled="busy" @click="handlePack">{{ busy ? 'Saving…' : 'Save packed quantities' }}</button>
    </section>

    <section v-if="fulfillment.status === 'ready'">
      <h2>Create shipment</h2>
      <p>Fully packed — creates the Shipment and completes this fulfillment (consumes reserved stock).</p>
      <button type="button" :disabled="busy" @click="handleComplete">{{ busy ? 'Creating…' : 'Create shipment (fake provider)' }}</button>
    </section>

    <section>
      <h2>Items &amp; allocations</h2>
      <table>
        <thead>
          <tr>
            <th>Order item</th>
            <th>Quantity</th>
            <th>Picked</th>
            <th>Packed</th>
            <th>Allocations</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in fulfillment.items" :key="item.id">
            <td>{{ item.order_item_id }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ item.picked_quantity }}</td>
            <td>{{ item.packed_quantity }}</td>
            <td>
              <ul v-if="item.allocations?.length" class="allocations">
                <li v-for="allocation in item.allocations" :key="allocation.id">
                  {{ allocation.quantity }} @ location {{ allocation.location_id }}
                  <span v-if="allocation.cancelled_at" class="muted">(released)</span>
                  <span v-else-if="allocation.consumed_at" class="muted">(consumed)</span>
                </li>
              </ul>
              <span v-else class="muted">Not allocated yet</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="fulfillment.shipments?.length">
      <h2>Shipments</h2>
      <table>
        <thead>
          <tr><th>Status</th><th>Tracking</th><th/></tr>
        </thead>
        <tbody>
          <tr v-for="shipment in fulfillment.shipments" :key="shipment.id">
            <td>{{ shipment.status }}</td>
            <td>{{ shipment.tracking_number ?? '—' }}</td>
            <td><NuxtLink :to="`/shipments/${shipment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Timeline</h2>
      <table v-if="fulfillment.events?.length">
        <thead>
          <tr><th>Event</th><th>Description</th><th>Occurred</th></tr>
        </thead>
        <tbody>
          <tr v-for="event in fulfillment.events" :key="event.id">
            <td>{{ event.type }}</td>
            <td>{{ event.description ?? '—' }}</td>
            <td>{{ event.occurred_at }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No events yet.</p>
    </section>
  </div>
  <p v-else-if="loading">Loading…</p>
  <p v-else>Fulfillment not found.</p>
</template>

<script setup lang="ts">
import type { Fulfillment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const TERMINAL_STATUSES = ['completed', 'cancelled']

const route = useRoute()
const fulfillmentId = route.params.id as string

const fulfillment = ref<Fulfillment | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

const pickQuantities = ref<Record<string, number>>({})
const packQuantities = ref<Record<string, number>>({})

/**
 * Always overwrites (not just fills in when unset): packed_quantity's
 * sensible default depends on picked_quantity, which changes as picking
 * progresses, so a one-time default captured before picking happened
 * would stay stuck at 0 forever. Since this runs right after every server
 * response, "latest server state" is exactly what a fresh default should
 * reflect.
 */
function syncFormDefaults() {
  if (!fulfillment.value) return
  for (const item of fulfillment.value.items ?? []) {
    pickQuantities.value[item.id] = item.quantity
    packQuantities.value[item.id] = item.picked_quantity
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().fulfillments.get(fulfillmentId)
    fulfillment.value = response.data
    syncFormDefaults()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

async function handleAllocate() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().fulfillments.allocate(fulfillmentId)
    fulfillment.value = response.data
    syncFormDefaults()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handlePick() {
  if (!fulfillment.value) return
  busy.value = true
  error.value = null
  try {
    const items = (fulfillment.value.items ?? []).map(item => ({
      fulfillment_item_id: item.id,
      picked_quantity: pickQuantities.value[item.id] ?? item.picked_quantity,
    }))
    const response = await useApi().fulfillments.pick(fulfillmentId, items)
    fulfillment.value = response.data
    syncFormDefaults()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handlePack() {
  if (!fulfillment.value) return
  busy.value = true
  error.value = null
  try {
    const items = (fulfillment.value.items ?? []).map(item => ({
      fulfillment_item_id: item.id,
      packed_quantity: packQuantities.value[item.id] ?? item.packed_quantity,
    }))
    const response = await useApi().fulfillments.pack(fulfillmentId, items)
    fulfillment.value = response.data
    syncFormDefaults()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handleComplete() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().fulfillments.complete(fulfillmentId, 'fake')
    fulfillment.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handleCancel() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().fulfillments.cancel(fulfillmentId)
    fulfillment.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
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

.allocations {
  margin: 0;
  padding-left: 1.1rem;
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

input[type='number'] {
  width: 5rem;
}

button.danger {
  background: transparent;
  border: 1px solid var(--color-danger);
  color: var(--color-danger);
}
</style>

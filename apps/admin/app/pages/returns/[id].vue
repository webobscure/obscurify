<template>
  <div v-if="returnRequest">
    <PageHeader
      :title="`Return #${returnRequest.number}`"
      :breadcrumbs="[{ label: 'Returns', to: '/returns' }, { label: `#${returnRequest.number}` }]"
    >
      <template #actions>
        <button
          v-if="!TERMINAL_STATUSES.includes(returnRequest.status)"
          type="button"
          class="danger"
          :disabled="busy"
          @click="handleCancel"
        >
          Cancel return
        </button>
      </template>
    </PageHeader>
    <p v-if="error" class="error">{{ error }}</p>

    <section>
      <h2>Status</h2>
      <table class="kv">
        <tbody>
          <tr><th>Status</th><td><span class="status" :class="`status-${returnRequest.status}`">{{ returnRequest.status }}</span></td></tr>
          <tr><th>Order</th><td><NuxtLink :to="`/orders/${returnRequest.order_id}`">View order</NuxtLink></td></tr>
          <tr><th>Requested</th><td>{{ returnRequest.requested_at }}</td></tr>
          <tr><th>Approved</th><td>{{ returnRequest.approved_at ?? '—' }}</td></tr>
          <tr><th>Received</th><td>{{ returnRequest.received_at ?? '—' }}</td></tr>
          <tr><th>Closed</th><td>{{ returnRequest.closed_at ?? '—' }}</td></tr>
          <tr><th>Notes</th><td>{{ returnRequest.notes ?? '—' }}</td></tr>
        </tbody>
      </table>
    </section>

    <!--
      Status transitions are always explicit merchant actions — this page
      never auto-advances anything the backend didn't already report as
      the new status in its response (spec section 11: the Admin Workflow
      must expose these transitions, not infer them).
    -->
    <section v-if="returnRequest.status === 'requested'">
      <h2>Approve or reject</h2>
      <div class="actions">
        <button type="button" :disabled="busy" @click="handleApprove">{{ busy ? 'Approving…' : 'Approve' }}</button>
        <label class="reject-form">
          Rejection reason (optional)
          <input v-model="rejectReason" type="text" placeholder="Outside the return window…">
        </label>
        <button type="button" class="danger" :disabled="busy" @click="handleReject">{{ busy ? 'Rejecting…' : 'Reject' }}</button>
      </div>
    </section>

    <section v-if="returnRequest.status === 'awaiting_return'">
      <h2>Receive the package</h2>
      <p>Marks the physical package as arrived at the warehouse — no inventory changes yet (only after inspection).</p>
      <button type="button" :disabled="busy" @click="handleReceive">{{ busy ? 'Receiving…' : 'Mark received' }}</button>
    </section>

    <section v-if="returnRequest.status === 'received'">
      <h2>Inspection &amp; disposition</h2>
      <p>Examine each item, record its condition, and choose what happens to it — inventory only changes once the return is completed.</p>
      <table>
        <thead>
          <tr><th>Order item</th><th>Quantity</th><th>Claimed reason</th><th>Verified condition</th><th>Disposition</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in returnRequest.items" :key="item.id">
            <td>{{ item.order_item_id }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ item.reason }}</td>
            <td>
              <select v-model="inspectionForm[item.id]!.condition">
                <option v-for="c in CONDITIONS" :key="c" :value="c">{{ c }}</option>
              </select>
            </td>
            <td>
              <select v-model="inspectionForm[item.id]!.disposition">
                <option v-for="d in DISPOSITIONS" :key="d" :value="d">{{ d }}</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <button type="button" :disabled="busy" @click="handleInspect">{{ busy ? 'Saving…' : 'Complete inspection' }}</button>
    </section>

    <section v-if="returnRequest.status === 'inspection'">
      <h2>Ready to complete</h2>
      <p>Applies every item's disposition to Inventory — restocked items increase on-hand stock; damaged/discarded items are logged but never become sellable again.</p>
      <button type="button" :disabled="busy" @click="handleComplete">{{ busy ? 'Completing…' : 'Complete return' }}</button>
    </section>

    <section>
      <h2>Items</h2>
      <table>
        <thead>
          <tr>
            <th>Order item</th>
            <th>Quantity</th>
            <th>Reason</th>
            <th>Inspection</th>
            <th>Disposition</th>
            <th>Inventory impact</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in returnRequest.items" :key="item.id">
            <td>{{ item.order_item_id }}</td>
            <td>{{ item.quantity }}</td>
            <td>{{ item.reason }} <span v-if="item.condition" class="muted">({{ item.condition }} claimed)</span></td>
            <td>
              <span v-if="item.inspection">{{ item.inspection.condition }}</span>
              <span v-else class="muted">Not inspected yet</span>
            </td>
            <td>
              <span v-if="item.disposition">{{ item.disposition.disposition }}</span>
              <span v-else class="muted">—</span>
            </td>
            <td>
              <span v-if="item.disposition?.applied_at" class="muted">Applied {{ item.disposition.applied_at }}</span>
              <span v-else-if="item.disposition" class="muted">Pending completion</span>
              <span v-else class="muted">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section>
      <h2>Timeline</h2>
      <table v-if="returnRequest.events?.length">
        <thead>
          <tr><th>Event</th><th>Description</th><th>Occurred</th></tr>
        </thead>
        <tbody>
          <tr v-for="event in returnRequest.events" :key="event.id">
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
  <p v-else>Return not found.</p>
</template>

<script setup lang="ts">
import type { ReturnCondition, ReturnDispositionValue, ReturnRequest } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const TERMINAL_STATUSES = ['completed', 'rejected', 'cancelled']
const CONDITIONS: ReturnCondition[] = ['new', 'like_new', 'damaged', 'defective', 'missing_parts', 'other']
const DISPOSITIONS: ReturnDispositionValue[] = ['restock', 'damaged', 'repair', 'discard', 'manual_review']

const route = useRoute()
const returnId = route.params.id as string

const returnRequest = ref<ReturnRequest | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)
const rejectReason = ref('')

const inspectionForm = ref<Record<string, { condition: ReturnCondition; disposition: ReturnDispositionValue }>>({})

/**
 * Always overwrites (not just fills in when unset) — same reasoning as
 * FulfillmentDetail's pick/pack form defaults: the item list itself can
 * change shape between loads (e.g. after inspection), so a stale default
 * captured once would drift from what's actually on screen.
 */
function syncFormDefaults() {
  if (!returnRequest.value) return
  for (const item of returnRequest.value.items ?? []) {
    if (!inspectionForm.value[item.id]) {
      inspectionForm.value[item.id] = { condition: item.condition ?? 'new', disposition: 'restock' }
    }
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useApi().returns.get(returnId)
    returnRequest.value = response.data
    syncFormDefaults()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

async function handleApprove() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().returns.approve(returnId)
    returnRequest.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handleReject() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().returns.reject(returnId, rejectReason.value || null)
    returnRequest.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handleReceive() {
  busy.value = true
  error.value = null
  try {
    const response = await useApi().returns.receive(returnId)
    returnRequest.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    busy.value = false
  }
}

async function handleInspect() {
  if (!returnRequest.value) return
  busy.value = true
  error.value = null
  try {
    const items = (returnRequest.value.items ?? []).map(item => ({
      return_item_id: item.id,
      condition: inspectionForm.value[item.id]?.condition ?? 'new',
      disposition: inspectionForm.value[item.id]?.disposition ?? 'restock',
    }))
    const response = await useApi().returns.inspect(returnId, items)
    returnRequest.value = response.data
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
    const response = await useApi().returns.complete(returnId)
    returnRequest.value = response.data
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
    const response = await useApi().returns.cancel(returnId)
    returnRequest.value = response.data
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

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.reject-form {
  display: flex;
  flex-direction: column;
  font-size: var(--text-sm);
  gap: 0.25rem;
}

button.danger {
  background: transparent;
  border: 1px solid var(--color-danger);
  color: var(--color-danger);
}
</style>

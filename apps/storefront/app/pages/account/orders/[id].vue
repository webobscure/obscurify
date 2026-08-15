<template>
  <div>
    <ClientOnly>
      <p v-if="loading">Loading…</p>
      <template v-else-if="order">
        <h1>Order #{{ order.number }}</h1>
        <p class="links"><NuxtLink to="/account/orders">Back to order history</NuxtLink></p>

        <p class="statuses">
          <span class="badge">{{ order.order_status }}</span>
          <span class="badge">{{ order.financial_status }}</span>
          <span class="badge">{{ order.fulfillment_status }}</span>
        </p>

        <table>
          <tbody>
            <tr v-for="item in order.items" :key="item.id">
              <td>{{ item.product_title }} <span v-if="item.variant_title">({{ item.variant_title }})</span> &times; {{ item.quantity }}</td>
              <td>{{ formatMoney({ amount: item.line_total_amount, currency: item.currency }) }}</td>
            </tr>
          </tbody>
        </table>
        <p class="totals"><strong>Total: {{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</strong></p>

        <p>
          <button type="button" :disabled="reordering" @click="handleReorder">
            {{ reordering ? 'Adding to cart…' : 'Buy again' }}
          </button>
        </p>
        <p v-if="reorderSkipped.length" class="muted">
          Not carried over: {{ reorderSkipped.map(s => s.product_title).join(', ') }} (no longer available).
        </p>
        <p v-if="reorderError" class="error">{{ reorderError }}</p>

        <section v-if="order.payments.length">
          <h2>Payments</h2>
          <ul>
            <li v-for="payment in order.payments" :key="payment.id">
              {{ payment.provider }} — {{ payment.status }} — {{ formatMoney({ amount: payment.amount, currency: payment.currency }) }}
            </li>
          </ul>
        </section>

        <section v-if="order.shipments.length">
          <h2>Shipments</h2>
          <ul>
            <li v-for="shipment in order.shipments" :key="shipment.id">
              {{ shipment.provider }} — {{ shipment.status }}
              <template v-if="shipment.tracking_number">
                — <a v-if="shipment.tracking_url" :href="shipment.tracking_url" target="_blank" rel="noopener">{{ shipment.tracking_number }}</a>
                <template v-else>{{ shipment.tracking_number }}</template>
              </template>
            </li>
          </ul>
        </section>

        <section v-if="order.returns.length">
          <h2>Returns</h2>
          <ul>
            <li v-for="ret in order.returns" :key="ret.id">
              Return #{{ ret.number }} — {{ ret.status }}
            </li>
          </ul>
        </section>

        <section v-if="order.refunds.length">
          <h2>Refunds</h2>
          <ul>
            <li v-for="refund in order.refunds" :key="refund.id">
              Refund #{{ refund.number }} — {{ refund.status }} — {{ formatMoney({ amount: refund.amount, currency: refund.currency }) }}
            </li>
          </ul>
        </section>

        <section>
          <h2>Request a return</h2>
          <form @submit.prevent="handleRequestReturn">
            <div v-for="item in order.items" :key="item.id" class="return-line">
              <label class="checkbox">
                <input v-model="line(item.id).selected" type="checkbox">
                {{ item.product_title }} <span v-if="item.variant_title">({{ item.variant_title }})</span>
              </label>
              <template v-if="line(item.id).selected">
                <label>
                  Quantity
                  <input v-model.number="line(item.id).quantity" type="number" min="1" :max="item.quantity">
                </label>
                <label>
                  Reason
                  <select v-model="line(item.id).reason">
                    <option value="wrong_size">Wrong size</option>
                    <option value="damaged">Damaged</option>
                    <option value="not_as_described">Not as described</option>
                    <option value="ordered_by_mistake">Ordered by mistake</option>
                    <option value="defective">Defective</option>
                    <option value="other">Other</option>
                  </select>
                </label>
              </template>
            </div>

            <label>
              Notes
              <textarea v-model="returnNotes" rows="3"/>
            </label>

            <button type="submit" :disabled="requestingReturn || !hasReturnSelection">
              {{ requestingReturn ? 'Submitting…' : 'Submit return request' }}
            </button>
          </form>
          <p v-if="returnRequested" class="muted">Return request submitted.</p>
          <p v-if="returnError" class="error">{{ returnError }}</p>
        </section>
      </template>

      <p v-else-if="error" class="error">{{ error }}</p>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { CustomerOrder, ReturnReason } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const orderId = route.params.id as string

const order = ref<CustomerOrder | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const reordering = ref(false)
const reorderError = ref<string | null>(null)
const reorderSkipped = ref<Array<{ order_item_id: string; product_title: string; reason: string }>>([])

interface ReturnLineSelection {
  selected: boolean
  quantity: number
  reason: ReturnReason
}

const returnSelection = ref<Record<string, ReturnLineSelection>>({})
const returnNotes = ref('')
const requestingReturn = ref(false)
const returnRequested = ref(false)
const returnError = ref<string | null>(null)

const hasReturnSelection = computed(() => Object.values(returnSelection.value).some(l => l.selected))

// returnSelection is always populated (in load(), one entry per order
// item) before the template that reads it can render — this narrows the
// Record's possibly-undefined index type without an inline `!` on every
// template access.
function line(orderItemId: string): ReturnLineSelection {
  return returnSelection.value[orderItemId]!
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await useStorefrontApi().account.orders.get(orderId)
    order.value = response.data
    returnSelection.value = Object.fromEntries(
      response.data.items.map(item => [item.id, { selected: false, quantity: item.quantity, reason: 'other' as ReturnReason }]),
    )
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

async function handleReorder() {
  reordering.value = true
  reorderError.value = null
  reorderSkipped.value = []
  try {
    const response = await useStorefrontApi().account.orders.reorder(orderId)
    reorderSkipped.value = response.data.skipped
    await navigateTo('/cart')
  } catch (e) {
    reorderError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    reordering.value = false
  }
}

async function handleRequestReturn() {
  requestingReturn.value = true
  returnRequested.value = false
  returnError.value = null
  try {
    const items = Object.entries(returnSelection.value)
      .filter(([, selection]) => selection.selected)
      .map(([orderItemId, selection]) => ({ order_item_id: orderItemId, quantity: selection.quantity, reason: selection.reason }))

    await useStorefrontApi().account.orders.requestReturn(orderId, { notes: returnNotes.value || undefined, items })
    returnRequested.value = true
    await load()
  } catch (e) {
    returnError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    requestingReturn.value = false
  }
}

onMounted(load)

useSeoMeta({
  title: () => order.value ? `Order #${order.value.number}` : 'Order',
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

.statuses {
  display: flex;
  gap: 0.5rem;
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #f0f0f0;
  color: #555;
  font-size: 0.75rem;
  text-transform: capitalize;
}

section {
  margin-top: 1.75rem;
}

section ul {
  padding-left: 1.25rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
  max-width: 30rem;
}

.return-line {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e0e0e0;
}

label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.9rem;
  color: #555;
}

label.checkbox {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
  color: #1a1a1a;
}

input,
select,
textarea {
  padding: 0.5rem;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  font: inherit;
  color: #1a1a1a;
}

input[type='checkbox'] {
  width: auto;
}

button {
  align-self: flex-start;
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

.muted {
  color: #777;
}

.links a {
  color: #1a1a1a;
}
</style>

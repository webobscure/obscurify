<template>
  <div>
    <PageHeader
      :title="title"
      :breadcrumbs="[{ label: 'Customers', to: '/customers' }, { label: title }]"
    />
    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="customer">
      <!--
        Read-only: profile edits belong to the customer through the
        storefront portal (Milestone 16), not to the merchant here.
      -->
      <section>
        <h2>Profile</h2>
        <table class="kv">
          <tbody>
            <tr><th>Email</th><td>{{ customer.email ?? '—' }}</td></tr>
            <tr><th>First name</th><td>{{ customer.first_name ?? '—' }}</td></tr>
            <tr><th>Last name</th><td>{{ customer.last_name ?? '—' }}</td></tr>
            <tr><th>Phone</th><td>{{ customer.phone ?? '—' }}</td></tr>
            <tr><th>Status</th><td>{{ customer.status }}</td></tr>
            <tr><th>Verified</th><td>{{ customer.verified_at ?? 'Not verified' }}</td></tr>
            <tr><th>Created</th><td>{{ customer.created_at }}</td></tr>
          </tbody>
        </table>
      </section>

      <section>
        <h2>Metrics</h2>
        <table v-if="metric" class="kv">
          <tbody>
            <tr><th>Total spent</th><td>{{ formatMoney({ amount: metric.total_spent_amount, currency: metric.currency ?? 'USD' }) }}</td></tr>
            <tr><th>Average order value</th><td>{{ formatMoney({ amount: metric.average_order_value_amount, currency: metric.currency ?? 'USD' }) }}</td></tr>
            <tr><th>Lifetime value</th><td>{{ formatMoney({ amount: metric.lifetime_value_amount, currency: metric.currency ?? 'USD' }) }}</td></tr>
            <tr><th>Order count</th><td>{{ metric.order_count }}</td></tr>
            <tr><th>Refund count</th><td>{{ metric.refund_count }}</td></tr>
            <tr><th>Return count / rate</th><td>{{ metric.return_count }} ({{ metric.return_rate.toFixed(2) }}%)</td></tr>
            <tr><th>First order</th><td>{{ metric.first_order_at ?? '—' }}</td></tr>
            <tr><th>Last order</th><td>{{ metric.last_order_at ?? '—' }}</td></tr>
            <tr><th>Days since last order</th><td>{{ metric.days_since_last_order ?? '—' }}</td></tr>
            <tr><th>Computed at</th><td>{{ metric.computed_at }}</td></tr>
          </tbody>
        </table>
        <p v-else>No metrics computed yet — this customer has no orders, and hasn't registered or updated their profile since Milestone 18 shipped.</p>

        <template v-if="snapshots.length">
          <h3>Timeline (snapshots)</h3>
          <table>
            <thead>
              <tr><th>Captured</th><th>Total spent</th><th>Order count</th><th>LTV</th></tr>
            </thead>
            <tbody>
              <tr v-for="snapshot in snapshots" :key="snapshot.id">
                <td>{{ snapshot.captured_at }}</td>
                <td>{{ formatMoney({ amount: snapshot.metrics.total_spent_amount, currency: snapshot.metrics.currency ?? 'USD' }) }}</td>
                <td>{{ snapshot.metrics.order_count }}</td>
                <td>{{ formatMoney({ amount: snapshot.metrics.lifetime_value_amount, currency: snapshot.metrics.currency ?? 'USD' }) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </section>

      <section>
        <h2>Groups &amp; Segments</h2>
        <p v-if="groups.length || segments.length">
          <span v-for="group in groups" :key="group.id" class="badge">
            <NuxtLink :to="`/customer-groups/${group.id}`">{{ group.name }}</NuxtLink>
          </span>
          <span v-for="segment in segments" :key="segment.id" class="badge segment">
            <NuxtLink :to="`/customer-segments/${segment.id}`">{{ segment.name }}</NuxtLink>
          </span>
        </p>
        <p v-else class="muted">Not a member of any group or segment.</p>
      </section>

      <section>
        <h2>Tags</h2>
        <p>
          <span v-for="tag in tags" :key="tag.id" class="badge" :class="{ system: tag.is_system }">
            {{ tag.name }}
            <button v-if="!tag.is_system" type="button" class="unlink" @click="handleRemoveTag(tag.id)">×</button>
          </span>
          <span v-if="!tags.length" class="muted">No tags.</span>
        </p>
        <form class="add-tag" @submit.prevent="handleAssignTag">
          <select v-model="tagToAssign">
            <option value="" disabled>Assign a tag…</option>
            <option v-for="tag in assignableTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
          </select>
          <button type="submit" :disabled="!tagToAssign">Assign</button>
        </form>
      </section>

      <section>
        <h2>Addresses</h2>
        <table v-if="addresses.length">
          <thead>
            <tr>
              <th>Name</th>
              <th>Address</th>
              <th>Phone</th>
              <th>Default</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="address in addresses" :key="address.id">
              <td>{{ [address.first_name, address.last_name].filter(Boolean).join(' ') || '—' }}</td>
              <td>
                {{ address.address_line1 ?? '—' }}<br>
                <template v-if="address.address_line2">{{ address.address_line2 }}<br></template>
                {{ address.city }}<span v-if="address.region">, {{ address.region }}</span> {{ address.postal_code }}<br>
                {{ address.country_code ?? '—' }}
              </td>
              <td>{{ address.phone ?? '—' }}</td>
              <td>
                <span v-if="address.is_default_billing" class="muted">Billing</span>
                <span v-if="address.is_default_shipping" class="muted">Shipping</span>
                <span v-if="!address.is_default_billing && !address.is_default_shipping">—</span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else>No addresses.</p>
      </section>

      <section>
        <h2>Order history</h2>
        <table v-if="orders.length">
          <thead>
            <tr>
              <th>Number</th>
              <th>Order</th>
              <th>Financial</th>
              <th>Fulfillment</th>
              <th>Total</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td>#{{ order.number }}</td>
              <td>{{ order.order_status }}</td>
              <td>{{ order.financial_status }}</td>
              <td>{{ order.fulfillment_status }}</td>
              <td>{{ formatMoney({ amount: order.total_amount, currency: order.currency }) }}</td>
              <td><NuxtLink :to="`/orders/${order.id}`">View</NuxtLink></td>
            </tr>
          </tbody>
        </table>
        <p v-else>No orders yet.</p>
      </section>

      <section>
        <h2>Returns history</h2>
        <table v-if="returns.length">
          <thead>
            <tr>
              <th>Number</th>
              <th>Status</th>
              <th>Requested</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="returnRequest in returns" :key="returnRequest.id">
              <td>#{{ returnRequest.number }}</td>
              <td>{{ returnRequest.status }}</td>
              <td>{{ returnRequest.requested_at }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else>No returns yet.</p>
      </section>

      <section>
        <h2>Activity</h2>
        <ul v-if="activity.length" class="timeline">
          <li v-for="event in activity" :key="event.id">
            <strong>{{ event.event_type }}</strong>
            <span class="muted">{{ event.occurred_at ?? '—' }}</span>
            <pre>{{ JSON.stringify(event.payload) }}</pre>
          </li>
        </ul>
        <p v-else>No activity recorded.</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { Customer, CustomerActivityEvent, CustomerAddress, CustomerGroup, CustomerMetric, CustomerSegment, CustomerSnapshot, CustomerTag, Order, ReturnRequest } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const customerId = route.params.id as string
const activeStore = useActiveStore()

const customer = ref<Customer | null>(null)
const addresses = ref<CustomerAddress[]>([])
const orders = ref<Order[]>([])
const returns = ref<ReturnRequest[]>([])
const activity = ref<CustomerActivityEvent[]>([])
const metric = ref<CustomerMetric | null>(null)
const snapshots = ref<CustomerSnapshot[]>([])
const groups = ref<CustomerGroup[]>([])
const segments = ref<CustomerSegment[]>([])
const tags = ref<CustomerTag[]>([])
const allTags = ref<CustomerTag[]>([])
const tagToAssign = ref('')
const pending = ref(true)
const error = ref<string | null>(null)

const assignableTags = computed(() => {
  const assignedIds = new Set(tags.value.map(t => t.id))

  return allTags.value.filter(t => !t.is_system && !assignedIds.has(t.id))
})

const title = computed(() => {
  if (!customer.value) return 'Customer'

  const name = [customer.value.first_name, customer.value.last_name].filter(Boolean).join(' ')

  return customer.value.email ?? (name || 'Customer')
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const api = useApi()
    const [
      customerResponse, addressesResponse, ordersResponse, returnsResponse, activityResponse,
      metricResponse, snapshotsResponse, groupsResponse, segmentsResponse, tagsResponse, allTagsResponse,
    ] = await Promise.all([
      api.customers.get(customerId),
      api.customers.addresses(customerId),
      api.customers.orders(customerId),
      api.customers.returns(customerId),
      api.customers.activity(customerId),
      api.customers.metrics(customerId),
      api.customers.metricsHistory(customerId),
      api.customers.groups(customerId),
      api.customers.segments(customerId),
      api.customers.tags(customerId),
      api.customerTags.list(),
    ])
    customer.value = customerResponse.data
    addresses.value = addressesResponse.data
    orders.value = ordersResponse.data
    returns.value = returnsResponse.data
    activity.value = activityResponse.data
    metric.value = metricResponse.data
    snapshots.value = snapshotsResponse.data
    groups.value = groupsResponse.data
    segments.value = segmentsResponse.data
    tags.value = tagsResponse.data
    allTags.value = allTagsResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleAssignTag() {
  if (!tagToAssign.value) return
  error.value = null
  try {
    const response = await useApi().customers.assignTag(customerId, tagToAssign.value)
    tags.value.push(response.data)
    tagToAssign.value = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleRemoveTag(tagId: string) {
  error.value = null
  try {
    await useApi().customers.removeTag(customerId, tagId)
    tags.value = tags.value.filter(t => t.id !== tagId)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin-right: var(--space-2);
}

.timeline {
  margin: 0;
  padding-left: var(--space-4);
}

.timeline li {
  margin-bottom: var(--space-3);
}

.timeline pre {
  margin: var(--space-1) 0 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  white-space: pre-wrap;
  word-break: break-all;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: var(--space-3);
}

th,
td {
  text-align: left;
  padding: var(--space-1) var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.1rem 0.5rem;
  margin: 0 var(--space-1) var(--space-1) 0;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  font-size: var(--text-sm);
}

.badge.segment {
  background: var(--color-info-muted, #e6f0ff);
}

.badge.system {
  background: var(--color-warning-muted, #fff4e5);
}

.badge a {
  color: inherit;
}

.unlink {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-muted);
  padding: 0;
  font-size: var(--text-sm);
  line-height: 1;
}

.add-tag {
  display: flex;
  gap: var(--space-2);
  align-items: center;
}

.add-tag select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}
</style>

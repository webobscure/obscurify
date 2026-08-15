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
import type { Customer, CustomerActivityEvent, CustomerAddress, Order, ReturnRequest } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const customerId = route.params.id as string
const activeStore = useActiveStore()

const customer = ref<Customer | null>(null)
const addresses = ref<CustomerAddress[]>([])
const orders = ref<Order[]>([])
const returns = ref<ReturnRequest[]>([])
const activity = ref<CustomerActivityEvent[]>([])
const pending = ref(true)
const error = ref<string | null>(null)

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
    const [customerResponse, addressesResponse, ordersResponse, returnsResponse, activityResponse] = await Promise.all([
      api.customers.get(customerId),
      api.customers.addresses(customerId),
      api.customers.orders(customerId),
      api.customers.returns(customerId),
      api.customers.activity(customerId),
    ])
    customer.value = customerResponse.data
    addresses.value = addressesResponse.data
    orders.value = ordersResponse.data
    returns.value = returnsResponse.data
    activity.value = activityResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
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
</style>

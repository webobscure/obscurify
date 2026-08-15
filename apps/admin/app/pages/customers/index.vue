<template>
  <div>
    <PageHeader title="Customers" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <!-- Admin customer search (spec section 10): tags, groups,
           segments, metric thresholds — every filter is optional and
           ANDed together server-side (see AdminCustomerController::index). -->
      <form class="search" @submit.prevent="load(1)">
        <input v-model="filters.search" type="text" placeholder="Search email or name…">
        <select v-model="filters.tag">
          <option value="">Any tag</option>
          <option v-for="tag in tags" :key="tag.id" :value="tag.slug">{{ tag.name }}</option>
        </select>
        <select v-model="filters.group_id">
          <option value="">Any group</option>
          <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
        </select>
        <select v-model="filters.segment_id">
          <option value="">Any segment</option>
          <option v-for="segment in segments" :key="segment.id" :value="segment.id">{{ segment.name }}</option>
        </select>
        <input v-model.number="filters.min_total_spent" type="number" placeholder="Min total spent (cents)">
        <button type="submit">Search</button>
        <button type="button" @click="handleClear">Clear</button>
      </form>

      <table v-if="customers.length">
        <thead>
          <tr>
            <th>Email</th>
            <th>Name</th>
            <th>Status</th>
            <th>Verified</th>
            <th>Created</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="customer in customers" :key="customer.id">
            <td>{{ customer.email ?? '—' }}</td>
            <td>{{ [customer.first_name, customer.last_name].filter(Boolean).join(' ') || '—' }}</td>
            <td>{{ customer.status }}</td>
            <td>{{ customer.verified_at ?? '—' }}</td>
            <td>{{ customer.created_at }}</td>
            <td><NuxtLink :to="`/customers/${customer.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No customers match.</p>

      <div v-if="meta && meta.last_page > 1" class="pagination">
        <button type="button" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Previous</button>
        <span class="muted">Page {{ meta.current_page }} of {{ meta.last_page }} — {{ meta.total }} total</span>
        <button type="button" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Next</button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ApiCollection, Customer, CustomerGroup, CustomerSegment, CustomerTag } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

type CollectionMeta = NonNullable<ApiCollection<Customer>['meta']>

const customers = ref<Customer[]>([])
const meta = ref<CollectionMeta | null>(null)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

const tags = ref<CustomerTag[]>([])
const groups = ref<CustomerGroup[]>([])
const segments = ref<CustomerSegment[]>([])

const filters = reactive<{ search: string, tag: string, group_id: string, segment_id: string, min_total_spent: number | null }>({
  search: '',
  tag: '',
  group_id: '',
  segment_id: '',
  min_total_spent: null,
})

async function load(page = 1) {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().customers.list({
      page,
      search: filters.search || undefined,
      tag: filters.tag || undefined,
      group_id: filters.group_id || undefined,
      segment_id: filters.segment_id || undefined,
      min_total_spent: filters.min_total_spent ?? undefined,
    })
    customers.value = response.data
    meta.value = response.meta ?? null
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

function handleClear() {
  filters.search = ''
  filters.tag = ''
  filters.group_id = ''
  filters.segment_id = ''
  filters.min_total_spent = null
  load(1)
}

async function loadFilterOptions() {
  if (!activeStore.storeId.value) return
  try {
    const api = useApi()
    const [tagsResponse, groupsResponse, segmentsResponse] = await Promise.all([
      api.customerTags.list(),
      api.customerGroups.list(),
      api.customerSegments.list(),
    ])
    tags.value = tagsResponse.data
    groups.value = groupsResponse.data
    segments.value = segmentsResponse.data
  } catch {
    // Filter dropdowns are a convenience — a failure here shouldn't
    // block the customer list itself from loading.
  }
}

onMounted(() => {
  load()
  loadFilterOptions()
})
watch(() => activeStore.storeId.value, () => {
  load()
  loadFilterOptions()
})
</script>

<style scoped>
.search {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.search input,
.search select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.search button {
  padding: var(--space-1) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.pagination {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-top: var(--space-4);
}

.pagination button {
  padding: var(--space-1) var(--space-3);
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: default;
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>

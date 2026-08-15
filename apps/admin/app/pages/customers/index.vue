<template>
  <div>
    <PageHeader title="Customers" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
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
      <p v-else>No customers yet.</p>

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
import type { ApiCollection, Customer } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

type CollectionMeta = NonNullable<ApiCollection<Customer>['meta']>

const customers = ref<Customer[]>([])
const meta = ref<CollectionMeta | null>(null)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load(page = 1) {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const response = await useApi().customers.list(page)
    customers.value = response.data
    meta.value = response.meta ?? null
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(() => load())
watch(() => activeStore.storeId.value, () => load())
</script>

<style scoped>
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

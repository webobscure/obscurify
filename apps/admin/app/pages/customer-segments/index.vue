<template>
  <div>
    <PageHeader title="Customer Segments" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <section class="create">
        <h2>Create a segment</h2>
        <form @submit.prevent="handleCreate">
          <input v-model="name" type="text" placeholder="Name" required>
          <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create segment' }}</button>
        </form>
        <p class="hint">Add rules from the segment's own page after creating it.</p>
      </section>

      <table v-if="segments.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Members</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="segment in segments" :key="segment.id">
            <td>{{ segment.name }}</td>
            <td>{{ segment.status }}</td>
            <td>{{ segment.member_count ?? '—' }}</td>
            <td><NuxtLink :to="`/customer-segments/${segment.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No customer segments yet.</p>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CustomerSegment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()
const segments = ref<CustomerSegment[]>([])
const error = ref<string | null>(null)
const creating = ref(false)
const name = ref('')

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    segments.value = (await useApi().customerSegments.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().customerSegments.create({ name: name.value })
    name.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.create {
  margin-bottom: var(--space-4);
}

.create form {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.create input {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin-top: var(--space-1);
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
</style>
